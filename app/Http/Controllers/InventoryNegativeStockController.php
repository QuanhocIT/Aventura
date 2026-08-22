<?php

namespace App\Http\Controllers;

use App\Models\InventoryNegativeCase;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\CentralWarehouseService;
use App\Services\NegativeInventoryService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryNegativeStockController extends Controller
{
    public function __construct(
        protected NegativeInventoryService $negativeInventoryService,
        protected CentralWarehouseService $centralWarehouseService,
        protected TenantContext $tenantContext,
    ) {}

    public function page(Request $request): Response
    {
        $user = $request->user();
        $this->assertCanView($user);
        $branchId = $this->resolveBranch($request);
        $status = $request->string('status', 'active')->toString();
        $status = in_array($status, ['active', 'resolved', 'all'], true) ? $status : 'active';
        $severity = $request->string('severity')->toString() ?: null;
        $data = $this->negativeInventoryService->controlData(
            (int) $user->restaurant_id,
            $branchId,
            $status,
            $severity,
        );

        $centralBranch = $this->centralWarehouseService->getCentralWarehouse((int) $user->restaurant_id);
        $isOwner = $user->isOwner() || $user->isSuperAdmin();
        $branches = RestaurantBranch::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->when(! $isOwner, fn ($query) => $query->whereKey($branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'warehouse_type'])
            ->map(fn (RestaurantBranch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'is_central' => $branch->warehouse_type === 'central',
            ])
            ->values();

        $responsibleUsers = $this->responsibleUsers($user, $branchId);
        $scopeLabel = $branchId
            ? ($branches->firstWhere('id', $branchId)['name'] ?? 'Kho được phân công')
            : 'Toàn bộ chi nhánh và Kho Tổng';

        return Inertia::render('inventory/NegativeStockControl', [
            'cases' => $data['cases'],
            'summary' => $data['summary'],
            'branches' => $branches,
            'responsibleUsers' => $responsibleUsers,
            'filters' => [
                'branch_id' => $branchId,
                'status' => $status,
                'severity' => $severity,
            ],
            'scopeLabel' => $scopeLabel,
            'scopeType' => $this->scopeType($user, $branchId, $centralBranch?->id),
            'canManage' => $this->canManage($user),
            'canApprove' => $isOwner,
            'canViewAllBranches' => $isOwner,
            'centralBranchId' => $centralBranch?->id,
            'rootCauseOptions' => $this->negativeInventoryService->rootCauseOptions(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->assertCanView($user);
        $branchId = $this->resolveBranch($request);

        return response()->json([
            'success' => true,
            'cases' => $this->negativeInventoryService->activeFor((int) $user->restaurant_id, $branchId),
            'summary' => $this->negativeInventoryService->controlData((int) $user->restaurant_id, $branchId)['summary'],
        ]);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);
        $data = $request->validate([
            'handling_plan' => ['required', 'string', 'min:10', 'max:2000'],
            'root_cause' => ['nullable', 'string', 'max:2000'],
            'responsible_user_id' => ['nullable', 'integer'],
            'expected_restock_at' => ['nullable', 'date'],
            'root_cause_code' => ['nullable', Rule::in(array_keys(NegativeInventoryService::ROOT_CAUSES))],
            'containment_action' => ['nullable', 'string', 'max:2000'],
            'corrective_action' => ['nullable', 'string', 'max:2000'],
        ]);
        $case = $this->caseForActor($user, $request, $id);

        $updated = $this->negativeInventoryService->updatePlan(
            $case,
            $user,
            $data['handling_plan'],
            $data['responsible_user_id'] ?? null,
            $data['expected_restock_at'] ?? null,
            $data['root_cause'] ?? null,
            $data['root_cause_code'] ?? null,
            $data['containment_action'] ?? null,
            $data['corrective_action'] ?? null,
        );

        return response()->json(['success' => true, 'case' => $this->negativeInventoryService->present($updated)]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $case = $this->caseForActor($user, $request, $id);
        $updated = $this->negativeInventoryService->decideApproval(
            $case,
            $user,
            $data['decision'],
            $data['note'],
        );

        return response()->json(['success' => true, 'case' => $this->negativeInventoryService->present($updated)]);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);
        $data = $request->validate([
            'resolution_type' => ['required', 'string', Rule::in(['restocked', 'adjusted', 'verified'])],
            'resolution_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $case = $this->caseForActor($user, $request, $id);
        $resolved = $this->negativeInventoryService->resolve(
            $case,
            $user,
            $data['resolution_type'],
            $data['resolution_note'],
        );

        return response()->json(['success' => true, 'case' => $this->negativeInventoryService->present($resolved)]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->assertCanView($user);
        $case = $this->caseForActor($user, $request, $id);

        return response()->json([
            'success' => true,
            'case' => $this->negativeInventoryService->detail($case, $user),
        ]);
    }

    public function submitVerification(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);
        $data = $request->validate([
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $case = $this->caseForActor($user, $request, $id);
        $updated = $this->negativeInventoryService->submitVerification($case, $user, $data['note']);

        return response()->json(['success' => true, 'case' => $this->negativeInventoryService->present($updated)]);
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);
        $data = $request->validate([
            'resolution_type' => ['required', 'string', Rule::in(['restocked', 'adjusted', 'verified'])],
            'resolution_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $case = $this->caseForActor($user, $request, $id);
        $updated = $this->negativeInventoryService->verifyAndResolve(
            $case,
            $user,
            $data['resolution_type'],
            $data['resolution_note'],
        );

        return response()->json(['success' => true, 'case' => $this->negativeInventoryService->present($updated)]);
    }

    private function caseForActor(User $user, Request $request, int $id): InventoryNegativeCase
    {
        $case = InventoryNegativeCase::withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id)
            ->findOrFail($id);

        // The service repeats this boundary check so direct service calls and
        // future endpoints cannot bypass role scope.
        if (! $user->isOwner() && ! $user->isSuperAdmin()) {
            $this->resolveBranch($request, (int) $case->branch_id);
        }

        return $case;
    }

    private function assertCanView(User $user): void
    {
        abort_unless(
            $user->isOwner()
                || $user->isSuperAdmin()
                || $user->hasAnyRole(['manager', 'quản lý', 'quan_ly', 'quanly', 'inventory_staff', 'warehouse_manager', 'warehouse_staff']),
            403,
        );
    }

    private function canManage(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->hasAnyRole(['manager', 'quản lý', 'quan_ly', 'quanly', 'warehouse_manager']);
    }

    private function resolveBranch(Request $request, ?int $explicitBranchId = null): ?int
    {
        $user = $request->user();
        if ($user->isOwner() || $user->isSuperAdmin()) {
            $branchId = $explicitBranchId ?? ($request->integer('branch_id') ?: null);
            if ($branchId !== null) {
                abort_unless($user->canAccessBranch($branchId), 403);
            }

            return $branchId;
        }

        if ($user->hasAnyRole(['warehouse_manager', 'warehouse_staff'])) {
            return $this->centralWarehouseService->getCentralWarehouse($user->restaurant_id)?->id;
        }

        $assignedBranchId = $user->assignedBranchId();
        $branchId = $explicitBranchId
            ?? ($request->integer('branch_id') ?: $this->tenantContext->activeBranchId())
            ?? $assignedBranchId;
        abort_unless($branchId !== null && (int) $branchId === (int) $assignedBranchId, 403, 'Tài khoản chi nhánh chỉ được xem kho được phân công.');

        return (int) $branchId;
    }

    private function responsibleUsers(User $user, ?int $branchId): array
    {
        return User::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->where(function ($query) use ($branchId) {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', ['owner', 'manager', 'inventory_staff', 'warehouse_manager', 'warehouse_staff']))
                    ->when($branchId !== null, fn ($scopedQuery) => $scopedQuery->where(function ($branchQuery) use ($branchId) {
                        $branchQuery->where('branch_id', $branchId)->orWhere('warehouse_branch_id', $branchId);
                    }));
            })
            ->select('id', 'name', 'branch_id', 'warehouse_branch_id')
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (User $candidate): array => [
                'id' => $candidate->id,
                'name' => $candidate->name,
            ])
            ->values()
            ->all();
    }

    private function scopeType(User $user, ?int $branchId, ?int $centralBranchId): string
    {
        if ($user->isOwner() || $user->isSuperAdmin()) {
            return $branchId ? ($branchId === $centralBranchId ? 'central' : 'branch') : 'all';
        }

        return $user->hasAnyRole(['warehouse_manager', 'warehouse_staff']) ? 'central' : 'branch';
    }
}
