<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\StockTransferRequest;
use App\Models\User;
use App\Notifications\StockTransferStageNotification;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Điều chuyển hàng liên chi nhánh có ĐỊNH TUYẾN của Chủ + bàn giao HAI BƯỚC + mã giao
 * nhận + người xuất ≠ người nhận. Xem StockTransferRequest để hiểu luồng.
 */
class StockTransferRequestController extends Controller
{
    private function assertManager(User $user): void
    {
        abort_unless(
            $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'manager', 'warehouse_manager']),
            403,
            'Bạn không có quyền thao tác điều chuyển.'
        );
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->assertManager($user);

        $transfers = StockTransferRequest::where('restaurant_id', $user->restaurant_id)
            ->with(['toBranch:id,name', 'fromBranch:id,name', 'ingredient:id,name', 'requestedBy:id,name', 'dispatchedBy:id,name', 'receivedBy:id,name'])
            ->latest('id')->limit(200)->get()
            ->map(fn (StockTransferRequest $t) => [
                'id' => $t->id,
                'status' => $t->status,
                'ingredient' => $t->ingredient?->name,
                'to_branch' => $t->toBranch?->name,
                'from_branch' => $t->fromBranch?->name,
                'quantity_requested' => (float) $t->quantity_requested,
                'quantity_dispatched' => $t->quantity_dispatched !== null ? (float) $t->quantity_dispatched : null,
                'quantity_received' => $t->quantity_received !== null ? (float) $t->quantity_received : null,
                'reason' => $t->reason,
                'owner_note' => $t->owner_note,
                'handover_code' => $t->handover_code,
                'requested_by' => $t->requestedBy?->name,
                'dispatched_by' => $t->dispatchedBy?->name,
                'received_by' => $t->receivedBy?->name,
                'reject_reason' => $t->reject_reason,
                'created_at' => $t->created_at->format('d/m/Y H:i'),
            ]);

        $branches = $user->restaurant
            ? $user->restaurant->branches()->where('status', 'active')->get(['id', 'name'])
            : collect();
        $ingredients = \App\Models\Ingredient::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')->orderBy('name')->get(['id', 'name', 'branch_id']);

        return Inertia::render('inventory/Transfers', [
            'transfers' => $transfers,
            'branches' => $branches,
            'ingredients' => $ingredients,
            'isOwner' => $user->isOwner() || $user->isSuperAdmin(),
        ]);
    }

    /** Bước 1 — Quản lý chi nhánh THIẾU tạo yêu cầu. */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->assertManager($user);

        $data = $request->validate([
            'to_branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'ingredient_id' => ['required', TenantRule::exists('ingredients')],
            'quantity_requested' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        abort_unless($user->canAccessBranch((int) $data['to_branch_id']), 403, 'Bạn chỉ được yêu cầu cho chi nhánh của mình.');

        $transfer = StockTransferRequest::create([
            'restaurant_id' => $user->restaurant_id,
            'to_branch_id' => $data['to_branch_id'],
            'ingredient_id' => $data['ingredient_id'],
            'quantity_requested' => $data['quantity_requested'],
            'reason' => $data['reason'],
            'status' => 'requested',
            'requested_by' => $user->id,
        ]);

        $this->notifyOwners($user, $transfer, 'requested');
        AuditLog::log('stock_transfer_requested', 'created', $transfer, null, ['by' => $user->name]);

        return back()->with('success', 'Đã gửi yêu cầu điều chuyển tới Chủ để định tuyến chi nhánh cấp hàng.');
    }

    /** Bước 2 — Chủ định tuyến: chọn chi nhánh THỪA + sinh mã giao nhận. */
    public function route(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->isOwner(), 403, 'Chỉ Chủ được định tuyến điều chuyển.');
        abort_if($transfer->restaurant_id !== $user->restaurant_id, 403);

        if ($transfer->status !== 'requested') {
            return back()->with('error', 'Chỉ định tuyến được yêu cầu đang chờ.');
        }

        $data = $request->validate([
            'from_branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'owner_note' => ['nullable', 'string', 'max:255'],
        ]);

        if ((int) $data['from_branch_id'] === (int) $transfer->to_branch_id) {
            return back()->withErrors(['from_branch_id' => 'Chi nhánh cấp phải khác chi nhánh nhận.']);
        }

        $transfer->update([
            'from_branch_id' => $data['from_branch_id'],
            'owner_note' => $data['owner_note'] ?? null,
            'status' => 'routed',
            'routed_by' => $user->id,
            'routed_at' => now(),
            'handover_code' => strtoupper(Str::random(6)),
        ]);

        // Báo quản lý chi nhánh cấp hàng để XUẤT.
        $this->notifyBranchManagers($transfer->from_branch_id, $user, $transfer, 'routed');
        AuditLog::log('stock_transfer_routed', 'updated', $transfer, null, ['from_branch_id' => $transfer->from_branch_id, 'by' => $user->name]);

        return back()->with('success', 'Đã định tuyến và sinh mã giao nhận. Chi nhánh cấp hàng sẽ xuất kho.');
    }

    /** Bước 3 — Chi nhánh THỪA xuất hàng (trừ kho nguồn). */
    public function dispatch(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertManager($user);
        abort_if($transfer->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch($transfer->from_branch_id), 403, 'Bạn không thuộc chi nhánh cấp hàng.');

        if ($transfer->status !== 'routed') {
            return back()->with('error', 'Yêu cầu chưa được định tuyến hoặc đã xuất.');
        }

        $data = $request->validate([
            'quantity_dispatched' => ['required', 'numeric', 'min:0.001', 'max:'.((float) $transfer->quantity_requested)],
        ]);
        $qty = (float) $data['quantity_dispatched'];

        try {
            DB::transaction(function () use ($transfer, $user, $qty) {
                $invFrom = Inventory::where('restaurant_id', $transfer->restaurant_id)
                    ->where('branch_id', $transfer->from_branch_id)
                    ->where('ingredient_id', $transfer->ingredient_id)
                    ->lockForUpdate()->first();

                if (! $invFrom || (float) $invFrom->quantity_on_hand < $qty) {
                    throw new \RuntimeException('Chi nhánh cấp không đủ tồn để xuất.');
                }

                $invFrom->decrement('quantity_on_hand', $qty);
                $invFrom->decrement('theoretical_quantity', $qty);

                InventoryTransaction::create([
                    'restaurant_id' => $transfer->restaurant_id,
                    'branch_id' => $transfer->from_branch_id,
                    'ingredient_id' => $transfer->ingredient_id,
                    'inventory_id' => $invFrom->id,
                    'performed_by' => $user->id,
                    'type' => 'adjustment', 'direction' => 'out',
                    'quantity' => $qty, 'unit_cost' => $invFrom->last_cost,
                    'total_cost' => $qty * (float) $invFrom->last_cost,
                    'notes' => 'Điều chuyển #'.$transfer->id.': xuất sang chi nhánh nhận (mã '.$transfer->handover_code.')',
                    'occurred_at' => now(),
                ]);

                $transfer->update([
                    'status' => 'dispatched',
                    'quantity_dispatched' => $qty,
                    'dispatched_by' => $user->id,
                    'dispatched_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể xuất hàng: '.$e->getMessage());
        }

        $this->notifyBranchManagers($transfer->to_branch_id, $user, $transfer->refresh(), 'dispatched');
        AuditLog::log('stock_transfer_dispatched', 'updated', $transfer, null, ['quantity' => $qty, 'by' => $user->name]);

        return back()->with('success', 'Đã xuất hàng. Chi nhánh nhận nhập mã giao nhận để hoàn tất.');
    }

    /** Bước 4 — Chi nhánh THIẾU nhận hàng bằng mã (người nhận ≠ người xuất). */
    public function receive(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertManager($user);
        abort_if($transfer->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch($transfer->to_branch_id), 403, 'Bạn không thuộc chi nhánh nhận.');

        if ($transfer->status !== 'dispatched') {
            return back()->with('error', 'Hàng chưa được xuất hoặc đã nhận.');
        }

        // Người nhận phải KHÁC người xuất (bàn giao hai người).
        if ($transfer->dispatched_by === $user->id) {
            return back()->withErrors(['handover_code' => 'Người nhận phải khác người xuất hàng.']);
        }

        $data = $request->validate(['handover_code' => ['required', 'string']]);
        if (strtoupper(trim($data['handover_code'])) !== $transfer->handover_code) {
            return back()->withErrors(['handover_code' => 'Mã giao nhận không đúng.']);
        }

        $qty = (float) $transfer->quantity_dispatched;

        try {
            DB::transaction(function () use ($transfer, $user, $qty) {
                $invTo = Inventory::where('restaurant_id', $transfer->restaurant_id)
                    ->where('branch_id', $transfer->to_branch_id)
                    ->where('ingredient_id', $transfer->ingredient_id)
                    ->lockForUpdate()->first();

                if (! $invTo) {
                    $invTo = Inventory::create([
                        'restaurant_id' => $transfer->restaurant_id,
                        'branch_id' => $transfer->to_branch_id,
                        'ingredient_id' => $transfer->ingredient_id,
                        'quantity_on_hand' => 0, 'theoretical_quantity' => 0, 'last_cost' => 0,
                    ]);
                }

                $invTo->increment('quantity_on_hand', $qty);
                $invTo->increment('theoretical_quantity', $qty);

                InventoryTransaction::create([
                    'restaurant_id' => $transfer->restaurant_id,
                    'branch_id' => $transfer->to_branch_id,
                    'ingredient_id' => $transfer->ingredient_id,
                    'inventory_id' => $invTo->id,
                    'performed_by' => $user->id,
                    'type' => 'adjustment', 'direction' => 'in',
                    'quantity' => $qty, 'unit_cost' => $invTo->last_cost,
                    'total_cost' => $qty * (float) $invTo->last_cost,
                    'notes' => 'Điều chuyển #'.$transfer->id.': nhận từ chi nhánh cấp (mã '.$transfer->handover_code.')',
                    'occurred_at' => now(),
                ]);

                $transfer->update([
                    'status' => 'received',
                    'quantity_received' => $qty,
                    'received_by' => $user->id,
                    'received_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể nhận hàng: '.$e->getMessage());
        }

        AuditLog::log('stock_transfer_received', 'updated', $transfer, null, ['by' => $user->name]);

        return back()->with('success', 'Đã nhận hàng và cộng tồn kho chi nhánh. Hoàn tất điều chuyển.');
    }

    /** Chủ từ chối yêu cầu. */
    public function reject(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->isOwner(), 403);
        abort_if($transfer->restaurant_id !== $user->restaurant_id, 403);

        if (! in_array($transfer->status, ['requested', 'routed'], true)) {
            return back()->with('error', 'Chỉ từ chối được yêu cầu chưa xuất hàng.');
        }

        $data = $request->validate(['reject_reason' => ['required', 'string', 'max:255']]);
        $transfer->update(['status' => 'rejected', 'reject_reason' => $data['reject_reason']]);
        AuditLog::log('stock_transfer_rejected', 'updated', $transfer, null, ['by' => $user->name]);

        return back()->with('success', 'Đã từ chối yêu cầu điều chuyển.');
    }

    private function notifyOwners(User $actor, StockTransferRequest $transfer, string $stage): void
    {
        User::where('restaurant_id', $actor->restaurant_id)->role('owner')
            ->where('id', '!=', $actor->id)->get()
            ->each(fn (User $o) => $o->notify(new StockTransferStageNotification($transfer, $stage, $actor->name)));
    }

    private function notifyBranchManagers(?int $branchId, User $actor, StockTransferRequest $transfer, string $stage): void
    {
        if (! $branchId) {
            return;
        }
        User::where('restaurant_id', $actor->restaurant_id)
            ->where('id', '!=', $actor->id)
            ->where('branch_id', $branchId)
            ->role('manager')->get()
            ->each(fn (User $m) => $m->notify(new StockTransferStageNotification($transfer, $stage, $actor->name)));
    }
}
