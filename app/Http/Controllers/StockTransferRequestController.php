<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\StockTransferRequest;
use App\Models\User;
use App\Notifications\StockTransferStageNotification;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Điều chuyển hàng liên chi nhánh theo chu trình:
 * yêu cầu → định tuyến → xuất kho → nhận hàng → đối soát.
 *
 * Việc trừ kho chỉ xảy ra khi xuất, việc cộng kho chỉ xảy ra khi nhận.
 * Mọi bước thay đổi tồn kho đều khóa bản ghi điều chuyển và tồn kho trong
 * cùng một transaction để tránh xuất/nhận trùng khi người dùng bấm lại.
 */
class StockTransferRequestController extends Controller
{
    private function assertManager(User $user): void
    {
        abort_unless(
            $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'manager', 'warehouse_manager']),
            403,
            'Bạn không có quyền thao tác điều chuyển.',
        );
    }

    private function assertCanRoute(User $user): void
    {
        abort_unless(
            $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']),
            403,
            'Chỉ Chủ hoặc Trưởng kho Tổng được định tuyến điều chuyển.',
        );
    }

    private function assertTenantTransfer(User $user, StockTransferRequest $transfer): void
    {
        abort_if((int) $transfer->restaurant_id !== (int) $user->restaurant_id, 403);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->assertManager($user);
        $canViewAllBranches = $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']);

        $transfers = StockTransferRequest::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->when(! $canViewAllBranches, function ($query) use ($user): void {
                $query->where(function ($branchQuery) use ($user): void {
                    $branchQuery->where('to_branch_id', $user->assignedBranchId())
                        ->orWhere('from_branch_id', $user->assignedBranchId());
                });
            })
            ->with([
                'toBranch:id,name',
                'fromBranch:id,name',
                'ingredient:id,name,unit_id',
                'ingredient.unit:id,symbol',
                'requestedBy:id,name',
                'routedBy:id,name',
                'dispatchedBy:id,name',
                'receivedBy:id,name',
                'discrepancyResolvedBy:id,name',
            ])
            ->latest('id')
            ->limit(300)
            ->get();

        $stocks = Inventory::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->get(['branch_id', 'ingredient_id', 'quantity_on_hand', 'last_cost'])
            ->keyBy(fn (Inventory $inventory): string => $inventory->branch_id.':'.$inventory->ingredient_id);

        $canRoute = $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']);
        $transfers = $transfers->map(function (StockTransferRequest $transfer) use ($user, $stocks, $canRoute): array {
            $stock = $transfer->from_branch_id
                ? $stocks->get($transfer->from_branch_id.':'.$transfer->ingredient_id)
                : null;
            $isRequester = (int) $transfer->requested_by === (int) $user->id;

            return [
                'id' => $transfer->id,
                'status' => $transfer->status,
                'ingredient_id' => $transfer->ingredient_id,
                'ingredient' => $transfer->ingredient?->name,
                'unit' => $transfer->ingredient?->unit?->symbol ?? 'đơn vị',
                'to_branch_id' => $transfer->to_branch_id,
                'to_branch' => $transfer->toBranch?->name,
                'from_branch_id' => $transfer->from_branch_id,
                'from_branch' => $transfer->fromBranch?->name,
                'quantity_requested' => (float) $transfer->quantity_requested,
                'quantity_dispatched' => $transfer->quantity_dispatched !== null ? (float) $transfer->quantity_dispatched : null,
                'quantity_received' => $transfer->quantity_received !== null ? (float) $transfer->quantity_received : null,
                'quantity_remaining' => max(0, (float) $transfer->quantity_requested - (float) ($transfer->quantity_received ?? 0)),
                'discrepancy_quantity' => (float) ($transfer->discrepancy_quantity ?? 0),
                'source_available_quantity' => $stock ? (float) $stock->quantity_on_hand : 0,
                'source_unit_cost' => $stock ? (float) $stock->last_cost : 0,
                'reason' => $transfer->reason,
                'owner_note' => $transfer->owner_note,
                'dispatch_note' => $transfer->dispatch_note,
                'received_condition' => $transfer->received_condition,
                'received_note' => $transfer->received_note,
                'receiving_evidence_path' => $transfer->receiving_evidence_path,
                'discrepancy_reason' => $transfer->discrepancy_reason,
                'discrepancy_resolution' => $transfer->discrepancy_resolution,
                'handover_code' => $transfer->handover_code,
                'requested_by' => $transfer->requestedBy?->name,
                'routed_by' => $transfer->routedBy?->name,
                'dispatched_by' => $transfer->dispatchedBy?->name,
                'received_by' => $transfer->receivedBy?->name,
                'discrepancy_resolved_by' => $transfer->discrepancyResolvedBy?->name,
                'reject_reason' => $transfer->reject_reason,
                'cancel_reason' => $transfer->cancel_reason,
                'created_at' => $transfer->created_at?->format('d/m/Y H:i'),
                'routed_at' => $transfer->routed_at?->format('d/m/Y H:i'),
                'dispatched_at' => $transfer->dispatched_at?->format('d/m/Y H:i'),
                'received_at' => $transfer->received_at?->format('d/m/Y H:i'),
                'can_route' => $canRoute && $transfer->status === 'requested',
                'can_dispatch' => $transfer->status === 'routed'
                    && $transfer->from_branch_id
                    && $user->canAccessBranch((int) $transfer->from_branch_id),
                'can_receive' => $transfer->status === 'dispatched'
                    && $transfer->to_branch_id
                    && $user->canAccessBranch((int) $transfer->to_branch_id)
                    && (int) $transfer->dispatched_by !== (int) $user->id,
                'can_resolve' => $canRoute && $transfer->status === 'discrepancy',
                'can_cancel' => in_array($transfer->status, ['requested', 'routed'], true)
                    && ($isRequester || $canRoute),
            ];
        });

        $branches = RestaurantBranch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->when(! $canViewAllBranches, fn ($query) => $query->where('id', $user->assignedBranchId()))
            ->orderBy('name')
            ->get(['id', 'name']);

        $ingredients = Ingredient::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->with('unit:id,symbol')
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id', 'unit_id'])
            ->map(fn (Ingredient $ingredient): array => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'branch_id' => $ingredient->branch_id,
                'unit' => $ingredient->unit?->symbol ?? 'đơn vị',
            ]);

        return Inertia::render('inventory/Transfers', [
            'transfers' => $transfers->values(),
            'branches' => $branches,
            'ingredients' => $ingredients->values(),
            'permissions' => [
                'can_route' => $canRoute,
                'can_create' => true,
            ],
            'summary' => [
                'requested' => $transfers->where('status', 'requested')->count(),
                'routed' => $transfers->where('status', 'routed')->count(),
                'dispatched' => $transfers->where('status', 'dispatched')->count(),
                'discrepancy' => $transfers->where('status', 'discrepancy')->count(),
                'completed' => $transfers->where('status', 'received')->count(),
            ],
        ]);
    }

    /** Chi nhánh thiếu tạo yêu cầu điều chuyển. */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->assertManager($user);

        $data = $request->validate([
            'to_branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'ingredient_id' => ['required', TenantRule::exists('ingredients')],
            'quantity_requested' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $toBranch = RestaurantBranch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->findOrFail((int) $data['to_branch_id']);
        abort_unless($user->canAccessBranch($toBranch->id), 403, 'Bạn chỉ được yêu cầu cho chi nhánh thuộc phạm vi của mình.');

        $ingredient = Ingredient::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->findOrFail((int) $data['ingredient_id']);
        if ($ingredient->branch_id !== null && (int) $ingredient->branch_id !== $toBranch->id) {
            return back()->withErrors(['ingredient_id' => 'Nguyên liệu này chỉ được dùng tại chi nhánh khác.']);
        }

        $hasOpenRequest = StockTransferRequest::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('to_branch_id', $toBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->whereIn('status', ['requested', 'routed', 'dispatched', 'discrepancy'])
            ->exists();
        if ($hasOpenRequest) {
            return back()->withErrors(['ingredient_id' => 'Chi nhánh này đã có yêu cầu cùng nguyên liệu đang xử lý. Hãy theo dõi yêu cầu hiện tại hoặc ghi rõ nhu cầu bổ sung.']);
        }

        $transfer = StockTransferRequest::create([
            'restaurant_id' => $user->restaurant_id,
            'to_branch_id' => $toBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_requested' => $data['quantity_requested'],
            'reason' => trim($data['reason']),
            'status' => 'requested',
            'requested_by' => $user->id,
        ]);

        $this->notifyTransferParties($user, $transfer, 'requested');
        AuditLog::log('stock_transfer_requested', 'created', $transfer, null, ['by' => $user->name]);

        return back()->with('success', 'Đã tạo yêu cầu điều chuyển và gửi vào hàng chờ định tuyến.');
    }

    /** Chủ hoặc Trưởng kho Tổng chọn nguồn cấp và duyệt định tuyến. */
    public function route(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanRoute($user);
        $this->assertTenantTransfer($user, $transfer);

        $data = $request->validate([
            'from_branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'owner_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $lockedTransfer = DB::transaction(function () use ($data, $transfer, $user): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if ($lockedTransfer->status !== 'requested') {
                    throw new \RuntimeException('Yêu cầu này đã được định tuyến hoặc không còn chờ xử lý.');
                }

                $fromBranch = RestaurantBranch::query()
                    ->where('restaurant_id', $user->restaurant_id)
                    ->where('status', 'active')
                    ->findOrFail((int) $data['from_branch_id']);
                if ($fromBranch->id === (int) $lockedTransfer->to_branch_id) {
                    throw new \InvalidArgumentException('Chi nhánh cấp phải khác chi nhánh nhận.');
                }

                $lockedTransfer->update([
                    'from_branch_id' => $fromBranch->id,
                    'owner_note' => isset($data['owner_note']) ? trim((string) $data['owner_note']) : null,
                    'status' => 'routed',
                    'routed_by' => $user->id,
                    'routed_at' => now(),
                    'handover_code' => strtoupper(Str::random(6)),
                ]);

                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['from_branch_id' => $e->getMessage()]);
        }

        $this->notifyTransferParties($user, $lockedTransfer, 'routed');
        AuditLog::log('stock_transfer_routed', 'updated', $lockedTransfer, null, ['from_branch_id' => $lockedTransfer->from_branch_id, 'by' => $user->name]);

        return back()->with('success', 'Đã định tuyến nguồn cấp và sinh mã giao nhận.');
    }

    /** Chi nhánh cấp xuất đủ số lượng yêu cầu, ghi giảm tồn nguồn. */
    public function dispatch(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertManager($user);
        $this->assertTenantTransfer($user, $transfer);

        $data = $request->validate([
            'quantity_dispatched' => ['required', 'numeric', 'min:0.001'],
            'dispatch_note' => ['nullable', 'string', 'max:500'],
        ]);
        $qty = (float) $data['quantity_dispatched'];

        if (! $transfer->from_branch_id || ! $user->canAccessBranch((int) $transfer->from_branch_id)) {
            abort(403, 'Bạn không thuộc chi nhánh cấp hàng.');
        }

        try {
            $lockedTransfer = DB::transaction(function () use ($transfer, $user, $qty, $data): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if ($lockedTransfer->status !== 'routed') {
                    throw new \RuntimeException('Yêu cầu chưa được định tuyến hoặc đã xuất hàng.');
                }
                if (abs($qty - (float) $lockedTransfer->quantity_requested) > 0.0005) {
                    throw new \InvalidArgumentException('Phiên bản hiện tại yêu cầu xuất đủ số lượng đã yêu cầu. Nếu cần chia nhiều chuyến, hãy tạo yêu cầu bổ sung riêng.');
                }

                $inventory = Inventory::query()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->where('branch_id', $lockedTransfer->from_branch_id)
                    ->where('ingredient_id', $lockedTransfer->ingredient_id)
                    ->lockForUpdate()
                    ->first();
                if (! $inventory || (float) $inventory->quantity_on_hand + 0.0005 < $qty) {
                    throw new \RuntimeException('Chi nhánh cấp không đủ tồn thực tế để xuất hàng.');
                }

                $unitCost = (float) $inventory->last_cost;
                $before = (float) $inventory->quantity_on_hand;
                $after = round($before - $qty, 3);
                InventoryTransaction::create([
                    'restaurant_id' => $lockedTransfer->restaurant_id,
                    'branch_id' => $lockedTransfer->from_branch_id,
                    'ingredient_id' => $lockedTransfer->ingredient_id,
                    'inventory_id' => $inventory->id,
                    'performed_by' => $user->id,
                    'type' => 'adjustment',
                    'direction' => 'out',
                    'quantity' => $qty,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'unit_cost' => $unitCost,
                    'total_cost' => $qty * $unitCost,
                    'reference_code' => 'TR-'.$lockedTransfer->id.'-OUT',
                    'notes' => 'Điều chuyển #'.$lockedTransfer->id.' sang '.$lockedTransfer->to_branch_id.' (mã '.$lockedTransfer->handover_code.')',
                    'occurred_at' => now(),
                ]);

                $inventory->update([
                    'quantity_on_hand' => $after,
                    'theoretical_quantity' => max(0, (float) $inventory->theoretical_quantity - $qty),
                    'updated_by' => $user->id,
                ]);
                $lockedTransfer->update([
                    'status' => 'dispatched',
                    'quantity_dispatched' => $qty,
                    'dispatch_unit_cost' => $unitCost,
                    'dispatched_by' => $user->id,
                    'dispatched_at' => now(),
                    'dispatch_note' => isset($data['dispatch_note']) ? trim((string) $data['dispatch_note']) : null,
                ]);

                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể xuất hàng: '.$e->getMessage());
        }

        $this->notifyTransferParties($user, $lockedTransfer, 'dispatched');
        AuditLog::log('stock_transfer_dispatched', 'updated', $lockedTransfer, null, ['quantity' => $qty, 'by' => $user->name]);

        return back()->with('success', 'Đã xuất kho. Chi nhánh nhận cần kiểm đếm và nhập mã giao nhận.');
    }

    /** Chi nhánh nhận xác nhận số lượng thực nhận, có thể phát sinh chênh lệch. */
    public function receive(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertManager($user);
        $this->assertTenantTransfer($user, $transfer);

        $data = $request->validate([
            'handover_code' => ['required', 'string', 'size:6'],
            // Tương thích với thao tác nhanh: chỉ nhập mã là xác nhận nhận đủ hàng tốt.
            'quantity_received' => ['nullable', 'numeric', 'min:0'],
            'received_condition' => ['nullable', 'string', 'in:good,damaged,shortage,mixed'],
            'received_note' => ['nullable', 'string', 'max:1000'],
            'receiving_evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        if (! $transfer->to_branch_id || ! $user->canAccessBranch((int) $transfer->to_branch_id)) {
            abort(403, 'Bạn không thuộc chi nhánh nhận hàng.');
        }

        $evidencePath = null;
        if ($request->hasFile('receiving_evidence')) {
            $evidencePath = $request->file('receiving_evidence')->store('stock-transfers/'.$user->restaurant_id, 'public');
        }
        try {
            $lockedTransfer = DB::transaction(function () use ($transfer, $user, $data, $evidencePath): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if ($lockedTransfer->status !== 'dispatched') {
                    throw new \RuntimeException('Hàng chưa được xuất hoặc đã được nhận trước đó.');
                }
                if (strtoupper(trim((string) $data['handover_code'])) !== strtoupper((string) $lockedTransfer->handover_code)) {
                    throw new \InvalidArgumentException('Mã giao nhận không đúng.');
                }
                if ((int) $lockedTransfer->dispatched_by === (int) $user->id) {
                    throw new \InvalidArgumentException('Người nhận phải khác người xuất hàng.');
                }

                $dispatchedQty = (float) $lockedTransfer->quantity_dispatched;
                $receivedQty = array_key_exists('quantity_received', $data) && $data['quantity_received'] !== null
                    ? (float) $data['quantity_received']
                    : $dispatchedQty;
                $receivedCondition = $data['received_condition'] ?? 'good';
                if ($receivedQty > $dispatchedQty + 0.0005) {
                    throw new \InvalidArgumentException('Số lượng thực nhận không được lớn hơn số lượng đã xuất.');
                }
                $difference = round($dispatchedQty - $receivedQty, 3);
                if ($difference > 0 && mb_strlen(trim((string) ($data['received_note'] ?? ''))) < 5) {
                    throw new \InvalidArgumentException('Khi nhận thiếu hoặc hỏng, bắt buộc ghi rõ biên bản chênh lệch.');
                }
                if (($difference > 0 || $receivedCondition !== 'good') && ! $evidencePath) {
                    throw new \InvalidArgumentException('Khi nhận thiếu hoặc hàng không đạt, bắt buộc đính kèm ảnh/PDF bằng chứng.');
                }

                $inventory = Inventory::query()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->where('branch_id', $lockedTransfer->to_branch_id)
                    ->where('ingredient_id', $lockedTransfer->ingredient_id)
                    ->lockForUpdate()
                    ->first();
                if (! $inventory) {
                    $inventory = Inventory::create([
                        'restaurant_id' => $lockedTransfer->restaurant_id,
                        'branch_id' => $lockedTransfer->to_branch_id,
                        'ingredient_id' => $lockedTransfer->ingredient_id,
                        'quantity_on_hand' => 0,
                        'theoretical_quantity' => 0,
                        'last_cost' => (float) ($lockedTransfer->dispatch_unit_cost ?? 0),
                    ]);
                }

                $unitCost = (float) ($lockedTransfer->dispatch_unit_cost ?? $inventory->last_cost);
                $before = (float) $inventory->quantity_on_hand;
                $after = round($before + $receivedQty, 3);
                if ($receivedQty > 0) {
                    InventoryTransaction::create([
                        'restaurant_id' => $lockedTransfer->restaurant_id,
                        'branch_id' => $lockedTransfer->to_branch_id,
                        'ingredient_id' => $lockedTransfer->ingredient_id,
                        'inventory_id' => $inventory->id,
                        'performed_by' => $user->id,
                        'type' => 'adjustment',
                        'direction' => 'in',
                        'quantity' => $receivedQty,
                        'quantity_before' => $before,
                        'quantity_after' => $after,
                        'unit_cost' => $unitCost,
                        'total_cost' => $receivedQty * $unitCost,
                        'reference_code' => 'TR-'.$lockedTransfer->id.'-IN',
                        'notes' => 'Điều chuyển #'.$lockedTransfer->id.' nhận từ '.$lockedTransfer->from_branch_id.' (mã '.$lockedTransfer->handover_code.')',
                        'occurred_at' => now(),
                    ]);
                }

                $inventory->update([
                    'quantity_on_hand' => $after,
                    'theoretical_quantity' => (float) $inventory->theoretical_quantity + $receivedQty,
                    'last_cost' => $unitCost > 0 ? $unitCost : $inventory->last_cost,
                    'updated_by' => $user->id,
                ]);
                $lockedTransfer->update([
                    'status' => $difference > 0 ? 'discrepancy' : 'received',
                    'quantity_received' => $receivedQty,
                    'discrepancy_quantity' => $difference,
                    'received_by' => $user->id,
                    'received_at' => now(),
                    'received_condition' => $receivedCondition,
                    'received_note' => isset($data['received_note']) ? trim((string) $data['received_note']) : null,
                    'receiving_evidence_path' => $evidencePath,
                    'discrepancy_reason' => $difference > 0 ? trim((string) $data['received_note']) : null,
                ]);

                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            if ($evidencePath) {
                Storage::disk('public')->delete($evidencePath);
            }

            if ($e instanceof \InvalidArgumentException) {
                $message = $e->getMessage();
                $field = str_contains($message, 'Mã giao nhận') || str_contains($message, 'Người nhận')
                    ? 'handover_code'
                    : (str_contains($message, 'Số lượng') ? 'quantity_received' : 'received_note');

                return back()->withErrors([$field => $message]);
            }

            return back()->with('error', 'Không thể nhận hàng: '.$e->getMessage());
        }

        $stage = $lockedTransfer->status === 'discrepancy' ? 'discrepancy' : 'received';
        $this->notifyTransferParties($user, $lockedTransfer, $stage);
        AuditLog::log('stock_transfer_received', 'updated', $lockedTransfer, null, [
            'quantity_received' => (float) $lockedTransfer->quantity_received,
            'discrepancy_quantity' => (float) $lockedTransfer->discrepancy_quantity,
            'by' => $user->name,
        ]);

        return back()->with('success', $stage === 'discrepancy'
            ? 'Đã nhập phần hàng thực nhận. Yêu cầu đang chờ xử lý chênh lệch.'
            : 'Đã nhận đủ hàng và cộng tồn kho chi nhánh.');
    }

    /** Chủ hoặc Trưởng kho Tổng xác nhận hướng xử lý phần thiếu/hỏng. */
    public function resolveDiscrepancy(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanRoute($user);
        $this->assertTenantTransfer($user, $transfer);
        $data = $request->validate(['discrepancy_resolution' => ['required', 'string', 'min:10', 'max:1000']]);

        try {
            $lockedTransfer = DB::transaction(function () use ($transfer, $user, $data): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if ($lockedTransfer->status !== 'discrepancy') {
                    throw new \RuntimeException('Yêu cầu không còn ở trạng thái chờ xử lý chênh lệch.');
                }
                $lockedTransfer->update([
                    'status' => 'received',
                    'discrepancy_resolution' => trim($data['discrepancy_resolution']),
                    'discrepancy_resolved_by' => $user->id,
                    'discrepancy_resolved_at' => now(),
                ]);

                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể chốt chênh lệch: '.$e->getMessage());
        }

        AuditLog::log('stock_transfer_discrepancy_resolved', 'updated', $lockedTransfer, null, ['by' => $user->name]);

        return back()->with('success', 'Đã ghi nhận hướng xử lý và đóng chênh lệch điều chuyển.');
    }

    /** Hủy yêu cầu trước khi xuất hàng. */
    public function cancel(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertManager($user);
        $this->assertTenantTransfer($user, $transfer);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'min:5', 'max:500']]);

        $canCancel = $user->isSuperAdmin()
            || $user->hasAnyRole(['owner', 'warehouse_manager'])
            || (int) $transfer->requested_by === (int) $user->id;
        abort_unless($canCancel, 403, 'Bạn không có quyền hủy yêu cầu này.');
        if (! in_array($transfer->status, ['requested', 'routed'], true)) {
            return back()->with('error', 'Chỉ được hủy yêu cầu trước khi xuất hàng.');
        }

        $transfer->update([
            'status' => 'cancelled',
            'cancel_reason' => trim($data['cancel_reason']),
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
        ]);
        $this->notifyTransferParties($user, $transfer->fresh(), 'cancelled');
        AuditLog::log('stock_transfer_cancelled', 'updated', $transfer, null, ['by' => $user->name]);

        return back()->with('success', 'Đã hủy yêu cầu điều chuyển.');
    }

    /** Chủ hoặc Trưởng kho Tổng từ chối yêu cầu trước khi xuất. */
    public function reject(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanRoute($user);
        $this->assertTenantTransfer($user, $transfer);
        $data = $request->validate(['reject_reason' => ['required', 'string', 'min:5', 'max:500']]);

        if (! in_array($transfer->status, ['requested', 'routed'], true)) {
            return back()->with('error', 'Chỉ từ chối được yêu cầu chưa xuất hàng.');
        }
        $transfer->update(['status' => 'rejected', 'reject_reason' => trim($data['reject_reason'])]);
        $this->notifyTransferParties($user, $transfer->fresh(), 'rejected');
        AuditLog::log('stock_transfer_rejected', 'updated', $transfer, null, ['by' => $user->name]);

        return back()->with('success', 'Đã từ chối yêu cầu điều chuyển.');
    }

    private function notifyOwners(User $actor, StockTransferRequest $transfer, string $stage): void
    {
        User::query()
            ->where('restaurant_id', $actor->restaurant_id)
            ->where('id', '!=', $actor->id)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['owner', 'warehouse_manager']))
            ->get()
            ->each(fn (User $user) => $user->notify(new StockTransferStageNotification($transfer, $stage, $actor->name)));
    }

    private function notifyTransferParties(User $actor, StockTransferRequest $transfer, string $stage): void
    {
        $branchIds = array_values(array_filter([(int) $transfer->from_branch_id, (int) $transfer->to_branch_id]));
        $recipientIds = User::where('restaurant_id', $actor->restaurant_id)
            ->where(function ($query) use ($transfer, $branchIds) {
                $query->whereKey($transfer->requested_by)
                    ->orWhereHas('roles', fn ($roles) => $roles->whereIn('name', ['owner', 'warehouse_manager']))
                    ->orWhere(function ($manager) use ($branchIds) {
                        $manager->whereIn('branch_id', $branchIds)
                            ->whereHas('roles', fn ($roles) => $roles->where('name', 'manager'));
                    });
            })
            ->where('id', '!=', $actor->id)
            ->pluck('id');

        User::whereIn('id', $recipientIds->unique())
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(new StockTransferStageNotification($transfer, $stage, $actor->name)));
    }

    private function notifyBranchManagers(?int $branchId, User $actor, StockTransferRequest $transfer, string $stage): void
    {
        if (! $branchId) {
            return;
        }

        User::query()
            ->where('restaurant_id', $actor->restaurant_id)
            ->where('id', '!=', $actor->id)
            ->where(function ($query) use ($branchId): void {
                $query->where(function ($branchQuery) use ($branchId): void {
                    $branchQuery->where('branch_id', $branchId)
                        ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'manager'));
                })->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'warehouse_manager'));
            })
            ->get()
            ->each(fn (User $user) => $user->notify(new StockTransferStageNotification($transfer, $stage, $actor->name)));
    }
}
