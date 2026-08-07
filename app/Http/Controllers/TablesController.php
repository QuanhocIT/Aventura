<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TablesController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $context = app(TenantContext::class);
        $scopeKey = $context->scopeKey();

        $areas = Cache::remember("restaurant_{$restaurantId}_areas:scope:{$scopeKey}", 3600, function () use ($restaurantId, $context) {
            return Area::where('restaurant_id', $restaurantId)
                ->when($context->isBranchScoped(), fn ($q) => $q->where('branch_id', $context->activeBranchId()))
                ->where('status', 'active')
                ->orderBy('display_order')
                ->withCount('tables')
                ->with('branch:id,name')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'code' => $a->code,
                    'tables_count' => $a->tables_count,
                    'branch_id' => $a->branch_id,
                    'branch_name' => $a->branch?->name,
                ])->toArray();
        });

        $tables = Cache::remember("restaurant_{$restaurantId}_tables:scope:{$scopeKey}", 3600, function () use ($restaurantId, $context) {
            return RestaurantTable::where('restaurant_id', $restaurantId)
                ->when($context->isBranchScoped(), fn ($q) => $q->where('branch_id', $context->activeBranchId()))
                ->with(['area', 'branch:id,name', 'activeOrder.creator', 'activeOrder.items.product'])
                ->whereNull('deleted_at')
                ->orderBy('area_id')
                ->orderBy('name')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'restaurant_id' => $t->restaurant_id,
                    'branch_id' => $t->branch_id,
                    'branch_name' => $t->branch?->name,
                    'name' => $t->name,
                    'capacity' => $t->capacity,
                    'status' => $t->status,
                    'x_pos' => (int) $t->x_pos,
                    'y_pos' => (int) $t->y_pos,
                    'area' => $t->area ? ['id' => $t->area->id, 'name' => $t->area->name] : null,
                    'qr_code' => $t->qr_code,
                    'qr_token' => $t->qr_token,
                    'active_order' => $t->activeOrder ? [
                        'id' => $t->activeOrder->id,
                        'order_number' => $t->activeOrder->order_number,
                        'waiter_name' => $t->activeOrder->creator?->name ?? 'Khách đặt (QR)',
                        'total_amount' => (float) $t->activeOrder->total_amount,
                        'elapsed_minutes' => $t->activeOrder->created_at ? now()->diffInMinutes($t->activeOrder->created_at) : 0,
                        'pending_items' => $t->activeOrder->items->filter(fn ($item) => is_null($item->served_at) && $item->status !== 'cancelled')->map(fn ($item) => [
                            'name' => $item->product?->name ?? 'Món ăn',
                            'quantity' => (float) $item->quantity,
                            'sent_at' => $item->sent_to_kitchen_at ? $item->sent_to_kitchen_at->diffForHumans() : null,
                            'is_late' => $item->sent_to_kitchen_at ? now()->diffInMinutes($item->sent_to_kitchen_at) >= 15 : false,
                        ])->values()->toArray(),
                    ] : null,
                ])->toArray();
        });

        return Inertia::render('tables/Index', [
            'areas' => $areas,
            'tables' => $tables,
            'branches' => $user->isOwner()
                ? $user->restaurant->branches()->where('status', 'active')->get(['id', 'name'])
                : $user->restaurant->branches()->whereKey($context->activeBranchId())->get(['id', 'name']),
            'activeBranchId' => $context->activeBranchId(),
            'branchScope' => $context->scope(),
        ]);
    }

    public function storeArea(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();
        $context = app(TenantContext::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'branch_id' => [
                'required', 'integer',
                Rule::exists('restaurant_branches', 'id')->where('restaurant_id', $user->restaurant_id),
            ],
        ]);
        $branchId = $this->resolveWriteBranch($user, $context, (int) $data['branch_id']);

        Area::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $branchId,
            'name' => $data['name'],
            'code' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'display_order' => Area::where('restaurant_id', $user->restaurant_id)->where('branch_id', $branchId)->count() + 1,
            'status' => 'active',
        ]);

        return back()->with('success', 'Đã thêm khu vực mới.');
    }

    public function destroyArea(Request $request, Area $area): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($area->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch((int) $area->branch_id), 403);

        $tablesCount = $area->tables()->whereNull('deleted_at')->count();
        if ($tablesCount > 0) {
            return back()->with('error', "Không thể xóa khu vực \"{$area->name}\" vì vẫn còn {$tablesCount} bàn. Vui lòng di chuyển hoặc xóa hết bàn trong khu vực này trước.");
        }

        $area->delete();

        return back()->with('success', "Đã xóa khu vực \"{$area->name}\" thành công.");
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();
        $context = app(TenantContext::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'area_id' => ['required', TenantRule::exists('areas')],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'branch_id' => [
                'required', 'integer',
                Rule::exists('restaurant_branches', 'id')->where('restaurant_id', $user->restaurant_id),
            ],
        ]);
        $branchId = $this->resolveWriteBranch($user, $context, (int) $data['branch_id']);
        $area = Area::where('restaurant_id', $user->restaurant_id)->findOrFail($data['area_id']);
        abort_if((int) $area->branch_id !== $branchId, 422, 'Khu vực phải thuộc đúng chi nhánh đã chọn.');

        RestaurantTable::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $branchId,
            'area_id' => $data['area_id'],
            'name' => $data['name'],
            'capacity' => $data['capacity'],
            'status' => 'available',
            'qr_token' => Str::random(32),
        ]);

        return back()->with('success', 'Đã thêm bàn mới.');
    }

    public function update(Request $request, RestaurantTable $table): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        abort_if($table->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch((int) $table->branch_id), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'in:available,occupied,reserved,inactive,cleaning'],
            'x_pos' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'y_pos' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        $table->update($data);

        return back()->with('success', 'Đã cập nhật bàn.');
    }

    public function destroy(Request $request, RestaurantTable $table): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        abort_if($table->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch((int) $table->branch_id), 403);

        $hasActiveOrder = $table->status === 'occupied'
            || $table->activeOrder()->exists()
            || $table->orders()->whereNotIn('status', ['completed', 'cancelled'])->exists();

        if ($hasActiveOrder) {
            throw ValidationException::withMessages([
                'table' => "Không thể xóa bàn {$table->name} vì bàn đang có khách hoặc đơn hàng chưa hoàn tất. Vui lòng thanh toán hoặc hủy đơn trước khi xóa bàn.",
            ]);
        }

        $table->delete();

        return back()->with('success', 'Đã xóa bàn thành công.');
    }

    public function regenerateQr(Request $request, RestaurantTable $table): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($table->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch((int) $table->branch_id), 403);

        $table->update([
            'qr_token' => Str::random(32),
        ]);

        return back()->with('success', 'Đã tạo lại mã QR mới cho bàn.');
    }

    private function resolveWriteBranch(User $user, TenantContext $context, int $requestedBranchId): int
    {
        if (! $user->canAccessBranch($requestedBranchId)) {
            throw ValidationException::withMessages(['branch_id' => 'Bạn không có quyền thao tác tại chi nhánh này.']);
        }

        if ($context->isBranchScoped() && $requestedBranchId !== $context->activeBranchId()) {
            throw ValidationException::withMessages(['branch_id' => 'Chi nhánh thao tác phải trùng chi nhánh hiện tại.']);
        }

        if (! $context->isBranchScoped() && ! $user->isOwner()) {
            throw ValidationException::withMessages(['branch_id' => 'Tài khoản quản lý phải thao tác trong chi nhánh được gán.']);
        }

        return $requestedBranchId;
    }
}
