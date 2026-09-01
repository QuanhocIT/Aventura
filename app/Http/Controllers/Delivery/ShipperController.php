<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Shipper;
use App\Models\Employee;
use App\Models\User;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShipperController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManager($request);

        $restaurantId = $request->user()->restaurant_id;
        $branchId = $this->branchIdFor($request->user());

        $shippers = Shipper::with(['employee'])
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('branch_id', $branchId)))
            ->withTrashed()
            ->orderByDesc('is_active')
            ->get()
            ->map(fn (Shipper $s) => [
                'id' => $s->id,
                'employee_id' => $s->employee_id,
                'name' => $s->employee?->full_name ?? "Shipper #{$s->id}",
                'vehicle_type' => $s->vehicle_type,
                'vehicle_plate' => $s->vehicle_plate,
                'is_active' => $s->is_active,
                'max_orders_per_batch' => $s->max_orders_per_batch,
                'max_capacity_kg' => (float) $s->max_capacity_kg,
                'deleted_at' => $s->deleted_at?->toISOString(),
            ]);

        $employees = Employee::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDoesntHave('shipper')
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        return Inertia::render('delivery/Shippers', [
            'shippers' => $shippers,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $restaurantId = $request->user()->restaurant_id;
        $branchId = $this->branchIdFor($request->user());

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', "exists:employees,id,restaurant_id,{$restaurantId}"],
            'vehicle_type' => ['required', 'in:bike,motorbike,car'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
            'max_orders_per_batch' => ['nullable', 'integer', 'min:1', 'max:20'],
            'max_capacity_kg' => ['nullable', 'numeric', 'min:1', 'max:500'],
        ]);

        $employee = Employee::where('restaurant_id', $restaurantId)
            ->whereKey($validated['employee_id'])
            ->where('status', 'active')
            ->first();

        abort_unless($employee, 422, 'Chỉ có thể đăng ký nhân viên đang hoạt động làm shipper.');

        abort_unless($branchId === null || (int) $employee->branch_id === $branchId, 403, 'NhÃ¢n viÃªn khÃ´ng thuá»™c chi nhÃ¡nh Ä‘ang thao tÃ¡c.');

        $shipper = Shipper::create([
            'employee_id' => $validated['employee_id'],
            'vehicle_type' => $validated['vehicle_type'],
            'vehicle_plate' => $validated['vehicle_plate'] ?? null,
            'max_orders_per_batch' => $validated['max_orders_per_batch'] ?? 5,
            'max_capacity_kg' => $validated['max_capacity_kg'] ?? 20,
            'is_active' => true,
        ]);

        return response()->json($shipper->load('employee'), 201);
    }

    public function update(Request $request, Shipper $shipper): JsonResponse
    {
        $this->authorizeManager($request);

        $this->assertShipperScope($request->user(), $shipper);

        $validated = $request->validate([
            'vehicle_type' => ['sometimes', 'in:bike,motorbike,car'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
            'max_orders_per_batch' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'max_capacity_kg' => ['sometimes', 'numeric', 'min:1', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_active', $validated) && ! $validated['is_active']) {
            $hasOpenBatch = $shipper->batches()
                ->whereIn('status', ['pending', 'dispatched', 'in_progress'])
                ->exists();

            abort_if($hasOpenBatch, 422, 'Không thể tạm ngưng shipper đang có chuyến chưa hoàn tất.');
        }

        $shipper->update($validated);

        return response()->json($shipper->fresh());
    }

    public function destroy(Request $request, Shipper $shipper): JsonResponse
    {
        $this->authorizeManager($request);

        $this->assertShipperScope($request->user(), $shipper);

        $hasOpenBatch = $shipper->batches()
            ->whereIn('status', ['pending', 'dispatched', 'in_progress'])
            ->exists();

        abort_if($hasOpenBatch, 422, 'Không thể vô hiệu hóa shipper đang có chuyến chưa hoàn tất.');

        $shipper->update(['is_active' => false]);
        $shipper->delete();

        return response()->json(['message' => 'Shipper đã được vô hiệu hóa']);
    }

    private function authorizeManager(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user->isSuperAdmin()
                || $user->hasAnyRole(['owner', 'manager'])
                || $user->can('manage_orders'),
            403,
            'Bạn không có quyền quản lý đội ngũ shipper.'
        );
    }

    private function branchIdFor(User $user): ?int
    {
        $context = app(TenantContext::class);

        if ($context->isUnassigned()) {
            abort(403, 'TÃ i khoáº£n chÆ°a Ä‘Æ°á»£c gÃ¡n chi nhÃ¡nh.');
        }

        if ($context->isBranchScoped()) {
            abort_unless($user->canAccessBranch($context->activeBranchId()), 403);

            return (int) $context->activeBranchId();
        }

        if (! $user->canViewAllBranches()) {
            $branchId = $user->assignedBranchId();
            abort_unless($branchId !== null, 403, 'TÃ i khoáº£n chÆ°a Ä‘Æ°á»£c gÃ¡n chi nhÃ¡nh.');

            return (int) $branchId;
        }

        return null;
    }

    private function assertShipperScope(User $user, Shipper $shipper): void
    {
        abort_if((int) $shipper->restaurant_id !== (int) $user->restaurant_id, 403);
        $shipper->loadMissing('employee');

        $branchId = $this->branchIdFor($user);
        abort_unless($branchId === null || (int) $shipper->employee?->branch_id === $branchId, 403);
    }
}
