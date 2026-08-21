<?php

namespace App\Http\Controllers;

use App\Models\InventoryCountSession;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Notifications\InventoryCountApprovalNotification;
use App\Notifications\InventoryCountAssignmentNotification;
use App\Services\InventoryCountService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCountController extends Controller
{
    public function __construct(
        protected InventoryCountService $countService
    ) {}

    /**
     * Trang Quản lý Phiên kiểm kê tồn kho (Inertia View)
     */
    public function page(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->when(! $user->canViewAllBranches(), fn ($q) => $q->whereKey($user->assignedBranchId()))
            ->get();

        $activeBranchId = $request->integer('branch_id') ?: ($user->canViewAllBranches() ? null : ($user->assignedBranchId() ?: $branches->first()?->id));
        abort_unless(
            ! $activeBranchId || $branches->contains('id', (int) $activeBranchId),
            403,
            'Bạn chỉ có thể xem phiên kiểm kê trong phạm vi chi nhánh được phân công.'
        );

        $sessions = InventoryCountSession::where('restaurant_id', $restaurantId)
            ->when($activeBranchId, fn ($q) => $q->where('branch_id', $activeBranchId))
            ->with(['items.ingredient.unit', 'items.reconciledBy', 'branch', 'countedBy', 'secondCountedBy', 'approver', 'rejectedBy', 'cancelledBy'])
            ->orderByDesc('id')
            ->get();

        $counterCandidates = User::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->where(function ($query) use ($activeBranchId) {
                if ($activeBranchId) {
                    $query->where('branch_id', $activeBranchId)
                        ->orWhere('warehouse_branch_id', $activeBranchId);
                }
            })
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['warehouse_staff', 'warehouse_manager', 'manager']))
            ->select('id', 'name', 'email', 'branch_id', 'warehouse_branch_id')
            ->orderBy('name')
            ->get();

        $isOwnerOrAdmin = $user->isOwner() || $user->isSuperAdmin();
        $sessions->each(function ($session) use ($isOwnerOrAdmin) {
            if ($session->blind_count && $session->status === 'in_progress' && ! $isOwnerOrAdmin) {
                $session->items->each(function ($item) {
                    $item->expected_quantity = null;
                    $item->variance_quantity = null;
                    $item->variance_percent = null;
                    $item->variance_value = null;
                });
            }
        });

        return Inertia::render('inventory/InventoryCount', [
            'branches'       => $branches,
            'activeBranchId' => $activeBranchId,
            'countSessions'  => $sessions,
            'counterCandidates' => $counterCandidates,
            'authUserId'     => $user->id,
            'canStartCount'  => $user->can('inventory.count') || $user->hasRole('warehouse_manager') || $user->isOwner() || $user->isSuperAdmin(),
            'canApprove'     => $user->can('inventory.adjust.approve') || $user->hasRole('warehouse_manager') || $user->isOwner() || $user->isSuperAdmin(),
        ]);
    }

    /**
     * Bắt đầu phiên kiểm kê mới
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id'      => ['required', TenantRule::exists('restaurant_branches')],
            'type'           => 'required|string|in:periodic,spot_check,abc_cycle',
            'blind_count'    => 'nullable|boolean',
            'ingredient_ids' => 'nullable|array',
        ]);

        $user = $request->user();

        if (! $user->canAccessBranch((int) $data['branch_id'])) {
            abort(403, 'Bạn chỉ có thể khởi tạo kiểm kê cho chi nhánh thuộc phạm vi quản lý.');
        }

        try {
            $session = $this->countService->startCountSession(
                $user->restaurant_id,
                (int) $data['branch_id'],
                $user,
                $data['type'],
                (bool) ($data['blind_count'] ?? false),
                $data['ingredient_ids'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã khởi tạo phiên kiểm kê thành công.',
                'data'    => $session,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ghi nhận kết quả đếm thực tế
     */
    public function submitCounts(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSessionBranch($user, $session);

        $data = $request->validate([
            'items'                    => 'required|array|min:1',
            'items.*.id'               => 'required|integer',
            'items.*.counted_quantity' => 'required|numeric|min:0',
            'items.*.notes'            => 'nullable|string',
            'is_second_counter'        => 'nullable|boolean',
        ]);

        try {
            $updated = $this->countService->submitCounts(
                $session,
                $user,
                $data['items'],
                (bool) ($data['is_second_counter'] ?? false)
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật số lượng kiểm đếm thành công.',
                'data'    => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Hoàn tất đếm & gửi duyệt
     */
    public function assignSecondCounter(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSessionBranch($user, $session);

        $data = $request->validate([
            'user_id' => ['required', 'integer', TenantRule::exists('users')],
        ]);

        try {
            $counter = User::where('restaurant_id', $user->restaurant_id)
                ->where('status', 'active')
                ->whereKey((int) $data['user_id'])
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['warehouse_staff', 'warehouse_manager', 'manager']))
                ->firstOrFail();

            $updated = $this->countService->assignSecondCounter($session, $user, $counter);
            $counter->notify(new InventoryCountAssignmentNotification($updated));

            return response()->json(['success' => true, 'message' => 'Đã phân công người đếm 2 cho phiên kiểm kê.', 'data' => $updated]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function reconcileItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $user = $request->user();
        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSessionBranch($user, $session);

        $data = $request->validate([
            'final_quantity' => 'required|numeric|min:0',
            'notes' => 'required|string|max:1000',
        ]);

        try {
            $updated = $this->countService->reconcileItem(
                $session,
                $user,
                $itemId,
                (float) $data['final_quantity'],
                $data['notes'],
            );

            return response()->json([
                'success' => true,
                'message' => 'Da chot ket qua doi soat dong dem.',
                'data' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function submitForApproval(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSessionBranch($user, $session);

        $data = $request->validate([
            'variance_photo_path' => 'nullable|string',
            'notes'               => 'nullable|string',
        ]);

        try {
            $updated = $this->countService->finalizeAndSubmitForApproval(
                $session,
                $data['variance_photo_path'] ?? null,
                $data['notes'] ?? null
            );

            User::where('restaurant_id', $user->restaurant_id)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['owner', 'super_admin', 'warehouse_manager']))
                        ->orWhereHas('permissions', fn ($permissions) => $permissions->where('name', 'inventory.adjust.approve'));
                })
                ->get()
                ->each(fn (User $reviewer) => $reviewer->notify(new InventoryCountApprovalNotification($updated, 'submitted')));

            return response()->json([
                'success' => true,
                'message' => 'Phiên kiểm kê đã được gửi duyệt thành công.',
                'data'    => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Phê duyệt phiên kiểm kê & tự động điều chỉnh tồn kho
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSessionBranch($user, $session);

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        try {
            $updated = $this->countService->rejectCountSession($session, $user, $data['reason']);
            User::whereKey($updated->counted_by)->first()?->notify(new InventoryCountApprovalNotification($updated, 'rejected'));
            User::whereKey($updated->second_counted_by)->first()?->notify(new InventoryCountApprovalNotification($updated, 'rejected'));

            return response()->json(['success' => true, 'message' => 'Da tu choi phien kiem ke.', 'data' => $updated]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSessionBranch($user, $session);

        try {
            $updated = $this->countService->reopenRejectedSession($session, $user);

            return response()->json(['success' => true, 'message' => 'Da mo lai phien bi tu choi de dieu chinh.', 'data' => $updated]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSessionBranch($user, $session);

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        try {
            $updated = $this->countService->cancelCountSession($session, $user, $data['reason']);

            return response()->json(['success' => true, 'message' => 'Da huy phien kiem ke.', 'data' => $updated]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSessionBranch($user, $session);

        try {
            $updated = $this->countService->approveCountSession($session, $user);
            User::whereKey($updated->counted_by)->first()?->notify(new InventoryCountApprovalNotification($updated, 'approved'));
            User::whereKey($updated->second_counted_by)->first()?->notify(new InventoryCountApprovalNotification($updated, 'approved'));

            return response()->json([
                'success' => true,
                'message' => 'Đã phê duyệt kiểm kê và cập nhật tồn kho thành công.',
                'data'    => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function quickCountPreset(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('inventory.count'),
            403,
            'Bạn không có quyền tạo kiểm kê nhanh.'
        );
        $restaurantId = $user->restaurant_id;

        $data = $request->validate([
            'branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'preset' => ['required', 'string', 'in:low_stock,high_value,expiring_soon,used_today'],
            'blind_count' => ['nullable', 'boolean'],
        ]);

        $branchId = (int) $data['branch_id'];
        abort_unless($user->canAccessBranch($branchId), 403, 'Bạn không có quyền kiểm kê chi nhánh này.');

        $query = \App\Models\Ingredient::where('restaurant_id', $restaurantId)
            ->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $branchId));

        match ($data['preset']) {
            'low_stock' => $query->whereHas('inventories', fn ($inv) => $inv->where('branch_id', $branchId)->whereRaw('inventories.quantity_on_hand <= ingredients.min_stock_level')),
            'high_value' => $query->where('average_cost', '>=', 50000)->orderByDesc('average_cost')->limit(20),
            'expiring_soon' => $query->whereHas('batches', fn ($b) => $b
                ->where('branch_id', $branchId)
                ->where('quantity_remaining', '>', 0)
                ->where('status', 'active')
                ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(3)->toDateString()])),
            'used_today' => $query->whereHas('transactions', fn ($m) => $m->where('branch_id', $branchId)->whereDate('created_at', today())),
            default => null,
        };

        $ingredientIds = $query->pluck('id')->all();

        if (empty($ingredientIds)) {
            return response()->json([
                'success' => false,
                'message' => "Không tìm thấy nguyên liệu phù hợp với bộ lọc kiểm kê nhanh '{$data['preset']}'.",
            ], 422);
        }

        $session = $this->countService->startCountSession(
            $restaurantId,
            (int) $data['branch_id'],
            $user,
            'spot_check',
            (bool) ($data['blind_count'] ?? false),
            $ingredientIds,
        );

        return response()->json([
            'success' => true,
            'message' => "Đã tạo phiên kiểm kê nhanh '{$data['preset']}' thành công với ".count($ingredientIds)." nguyên liệu.",
            'data' => $session,
        ]);
    }

    /**
     * Upload ảnh chứng từ sai lệch kiểm kê (lưu trữ riêng tư bảo mật kèm mã hash)
     */
    public function uploadVarianceProof(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSessionBranch($user, $session);

        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->store("restaurants/{$user->restaurant_id}/inventory_counts", 'local');

        $session->update([
            'variance_proof_path' => $path,
            'variance_proof_hash' => $hash,
        ]);

        return response()->json([
            'success' => true,
            'url'     => route('inventory.count-sessions.proof', ['id' => $session->id]),
            'path'    => $path,
            'hash'    => $hash,
        ]);
    }

    /**
     * Tải / Xem ảnh chứng từ kiểm kê (kiểm tra quyền bảo mật)
     */
    public function viewVarianceProof(Request $request, int $id)
    {
        $user = $request->user();
        $session = InventoryCountSession::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSessionBranch($user, $session);

        $path = $session->variance_proof_path;
        abort_unless($path, 404, 'Không tìm thấy chứng từ kiểm kê.');

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($path));
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return response()->file(\Illuminate\Support\Facades\Storage::disk('public')->path($path));
        }

        abort(404, 'File chứng từ không tồn tại.');
    }

    private function authorizeSessionBranch($user, InventoryCountSession $session): void
    {
        abort_unless(
            $user->canAccessBranch((int) $session->branch_id) || $user->isWarehouseManager(),
            403,
            'Bạn không có quyền thao tác phiên kiểm kê của chi nhánh này.'
        );
    }
}
