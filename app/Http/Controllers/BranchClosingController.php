<?php

namespace App\Http\Controllers;

use App\Models\InventoryCountSession;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Notifications\InventoryCountAssignmentNotification;
use App\Services\InventoryCountScopeService;
use App\Services\InventoryCountService;
use App\Services\MaterialClosingService;
use App\Services\QuotaService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchClosingController extends Controller
{
    public function __construct(
        protected MaterialClosingService $closingService,
        protected InventoryCountService $countService,
        protected InventoryCountScopeService $countScope,
    ) {}

    public function page(Request $request): Response
    {
        $user = $request->user();
        $restaurant = $user->restaurant;
        $restaurant?->loadMissing('plan');

        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'inventory_basic')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'inventory_basic',
                'feature_label' => 'Chốt kho chi nhánh',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn phí',
                'required_plan' => 'Cơ bản',
            ]);
        }

        abort_unless(
            ! $this->countScope->isCentralWarehouseAccount($user),
            403,
            'Tài khoản Kho Tổng chỉ được chốt nguyên liệu tại Kho Tổng.',
        );

        $branches = $this->branchOptions($user);
        $requestedBranchId = $request->integer('branch_id');
        $activeBranchId = $requestedBranchId ?: ($user->canViewAllBranches()
            ? $branches->first()?->id
            : ($user->assignedBranchId() ?: $branches->first()?->id));

        abort_unless(
            $activeBranchId && $this->countScope->canAccessBranch($user, (int) $activeBranchId),
            403,
            'Bạn chưa được gán quyền chốt kho cho chi nhánh này.',
        );

        $branch = $branches->first(fn (RestaurantBranch $candidate): bool => (int) $candidate->id === (int) $activeBranchId);
        abort_unless($branch, 403, 'Chi nhánh chốt kho không tồn tại hoặc nằm ngoài phạm vi tài khoản.');

        $sessions = InventoryCountSession::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $branch->id)
            ->where('type', 'branch_closing')
            ->with([
                'items.ingredient.unit',
                'items.reconciledBy',
                'branch',
                'countedBy',
                'secondCountedBy',
                'approver',
                'rejectedBy',
                'cancelledBy',
            ])
            ->orderByDesc('id')
            ->get();

        $counterCandidates = User::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->where(function ($query) use ($branch): void {
                $query->where('branch_id', $branch->id)
                    ->orWhere('warehouse_branch_id', $branch->id);
            })
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['manager', 'inventory_staff']))
            ->select('id', 'name', 'email', 'branch_id', 'warehouse_branch_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('inventory/MaterialClosing', [
            'mode' => 'branch',
            'branch' => [
                'id' => (int) $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ],
            'branches' => $branches->map(fn (RestaurantBranch $candidate): array => [
                'id' => (int) $candidate->id,
                'name' => $candidate->name,
                'code' => $candidate->code,
            ])->values(),
            'selectedBranchId' => (int) $branch->id,
            'sessions' => $sessions,
            'tasks' => [],
            'counterCandidates' => $counterCandidates,
            'authUserId' => (int) $user->id,
            'canManage' => $this->canManage($user),
            'canApprove' => $user->isOwner() || $user->isSuperAdmin() || $user->can('inventory.adjust.approve'),
            'isWarehouseStaff' => false,
            'scopeMessage' => 'Chỉ lấy giao dịch, tồn kho và nguyên liệu thuộc chi nhánh đang chọn; không đọc hoặc thay đổi số liệu Kho Tổng.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403, 'Chỉ quản lý chi nhánh hoặc chủ nhà hàng mới được mở kỳ chốt kho.');

        $data = $request->validate([
            'branch_id' => ['required', 'integer', TenantRule::exists('restaurant_branches')],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $branchId = (int) $data['branch_id'];
        abort_unless($this->canAccessBranchClosing($user, $branchId), 403, 'Bạn không có quyền chốt kho cho chi nhánh này.');

        try {
            $session = $this->closingService->start(
                (int) $user->restaurant_id,
                $branchId,
                $user,
                $data['from_date'],
                $data['to_date'],
                null,
                'branch_closing',
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã tạo kỳ chốt kho chi nhánh và tính tồn phải còn.',
                'data' => $session,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403, 'Chỉ quản lý chi nhánh hoặc chủ nhà hàng mới được giao đối chiếu.');

        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)
            ->where('type', 'branch_closing')
            ->findOrFail($id);
        abort_unless($this->canAccessBranchClosing($user, (int) $session->branch_id), 403, 'Kỳ chốt không thuộc phạm vi chi nhánh của tài khoản.');

        $data = $request->validate([
            'assigned_to' => ['required', 'integer', TenantRule::exists('users')],
        ]);

        try {
            $counter = User::where('restaurant_id', $user->restaurant_id)
                ->where('status', 'active')
                ->where(function ($query) use ($session): void {
                    $query->where('branch_id', $session->branch_id)
                        ->orWhere('warehouse_branch_id', $session->branch_id);
                })
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['manager', 'inventory_staff']))
                ->whereKey((int) $data['assigned_to'])
                ->firstOrFail();

            $updated = $this->countService->assignSecondCounter($session, $user, $counter);
            $counter->notify(new InventoryCountAssignmentNotification($updated));

            return response()->json([
                'success' => true,
                'message' => 'Đã giao việc đối chiếu kỳ chốt kho chi nhánh.',
                'data' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** @return \Illuminate\Support\Collection<int, RestaurantBranch> */
    private function branchOptions(User $user)
    {
        return $this->countScope->branchesFor($user)
            ->filter(fn (RestaurantBranch $branch): bool => ! ($branch->is_central_warehouse || $branch->warehouse_type === 'central'))
            ->values();
    }

    private function canAccessBranchClosing(User $user, int $branchId): bool
    {
        $branch = RestaurantBranch::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->whereKey($branchId)
            ->first();

        return $branch !== null
            && ! ($branch->is_central_warehouse || $branch->warehouse_type === 'central')
            && ! $this->countScope->isCentralWarehouseAccount($user)
            && $this->countScope->canAccessBranch($user, $branchId);
    }

    private function canManage(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->isBranchManager()
            || $user->can('branch.manage');
    }
}
