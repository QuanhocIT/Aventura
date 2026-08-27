<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryCountSession;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\RestaurantBranch;
use App\Models\StockTransferRequest;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\BranchDataAssignmentService;
use App\Services\CentralWarehouseService;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($restaurant, 403, 'Không tìm thấy nhà hàng.');
        abort_unless($request->user()->isOwner(), 403, 'Chỉ chủ nhà hàng mới có quyền quản lý chi nhánh.');

        $quota = app(QuotaService::class);
        $branches = $restaurant->branches()
            ->withCount(['employees', 'tables'])
            ->with('manager:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (RestaurantBranch $branch) => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'phone' => $branch->phone,
                'email' => $branch->email,
                'address' => $branch->address,
                'status' => $branch->status,
                'manager_user_id' => $branch->manager_user_id,
                'manager_name' => $branch->manager?->name,
                'employees_count' => (int) $branch->employees_count,
                'tables_count' => (int) $branch->tables_count,
            ])->values();

        $managerCandidates = $restaurant->users()
            ->where('status', 'active')
            ->with('roles:id,name')
            ->get()
            ->filter(fn (User $user) => $user->isBranchManager())
            ->map(function (User $user) use ($restaurant): array {
                $assignedBranch = $restaurant->branches()
                    ->where('manager_user_id', $user->id)
                    ->value('name');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'assigned_branch_name' => $assignedBranch,
                ];
            })
            ->values();

        return Inertia::render('settings/Branches', [
            'branches' => $branches,
            'managerCandidates' => $managerCandidates,
            'limit' => $quota->getLimit($restaurant, 'branches'),
            'canAddMore' => $quota->canAdd($restaurant, 'branches'),
        ]);
    }

    public function store(Request $request, BranchDataAssignmentService $branchDataAssignment): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($restaurant, 403, 'Không tìm thấy nhà hàng.');
        abort_unless($request->user()->isOwner(), 403, 'Chỉ chủ nhà hàng mới có quyền tạo chi nhánh.');

        if ($request->input('manager_user_id') === '0') {
            $request->merge(['manager_user_id' => null]);
        }

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('restaurant_branches')->where('restaurant_id', $restaurant->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'manager_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('restaurant_id', $restaurant->id),
            ],
        ]);

        $manager = $this->resolveManager($restaurant->id, $data['manager_user_id'] ?? null);
        unset($data['manager_user_id']);

        DB::transaction(function () use ($restaurant, $data, $manager, $branchDataAssignment): void {
            $branch = $restaurant->branches()->create($data + ['status' => 'active']);
            $this->syncManagerAssignment($branch, $manager, null);
            $branchDataAssignment->assignLegacyRowsToSoleBranch($branch);
        });

        return back()->with('success', "Đã tạo chi nhánh \"{$data['name']}\" thành công.");
    }

    public function update(Request $request, RestaurantBranch $branch): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($restaurant && $branch->restaurant_id === $restaurant->id, 403, 'Chi nhánh không thuộc nhà hàng của bạn.');
        abort_unless($request->user()->isOwner(), 403, 'Chỉ chủ nhà hàng mới có quyền sửa chi nhánh.');

        if ($request->input('manager_user_id') === '0') {
            $request->merge(['manager_user_id' => null]);
        }

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('restaurant_branches')->where('restaurant_id', $restaurant->id)->ignore($branch->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'manager_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('restaurant_id', $restaurant->id),
            ],
        ]);

        if (($data['status'] ?? null) === 'inactive') {
            try {
                app(CentralWarehouseService::class)->assertBranchCanBeDeactivated($branch);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['status' => $e->getMessage()]);
            }
        }

        $managerAssignmentChanged = array_key_exists('manager_user_id', $data);
        $manager = $managerAssignmentChanged
            ? $this->resolveManager($restaurant->id, $data['manager_user_id'], $branch->id)
            : null;
        $previousManagerId = $branch->getRawOriginal('manager_user_id');
        unset($data['manager_user_id']);

        DB::transaction(function () use ($branch, $data, $managerAssignmentChanged, $manager, $previousManagerId): void {
            $branch->update($data);

            if ($managerAssignmentChanged) {
                $this->syncManagerAssignment($branch, $manager, $previousManagerId);
            }
        });

        return back()->with('success', "Đã cập nhật chi nhánh \"{$branch->name}\".");
    }

    public function destroy(Request $request, RestaurantBranch $branch): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($restaurant && $branch->restaurant_id === $restaurant->id, 403, 'Chi nhánh không thuộc nhà hàng của bạn.');
        abort_unless($request->user()->isOwner(), 403, 'Chỉ chủ nhà hàng mới có quyền xoá chi nhánh.');

        if ($restaurant->branches()->count() <= 1) {
            return back()->withErrors(['branch' => 'Không thể xoá chi nhánh cuối cùng — nhà hàng phải có ít nhất 1 chi nhánh.']);
        }

        // Chặn xóa nếu là Kho Tổng active
        if ($branch->is_central_warehouse || $branch->warehouse_type === 'central') {
            return back()->withErrors(['branch' => 'Không thể xoá Kho Tổng đang kích hoạt. Vui lòng thiết lập Kho Tổng sang chi nhánh khác trước khi xoá.']);
        }

        if ($branch->employees()->exists()) {
            return back()->withErrors(['branch' => 'Chi nhánh vẫn còn nhân viên đang được gán — vui lòng chuyển nhân viên sang chi nhánh khác trước khi xoá.']);
        }

        // Chặn xóa nếu còn tồn kho nguyên liệu > 0
        if (Inventory::where('branch_id', $branch->id)->where('quantity_on_hand', '>', 0)->exists()) {
            return back()->withErrors(['branch' => 'Chi nhánh vẫn còn tồn kho nguyên vật liệu (> 0). Vui lòng luân chuyển hoặc xuất huỷ tồn kho trước khi xoá.']);
        }

        // Chặn xóa nếu còn lô hàng chưa xuất hết
        if (InventoryBatch::where('branch_id', $branch->id)->where('quantity_remaining', '>', 0)->exists()) {
            return back()->withErrors(['branch' => 'Chi nhánh vẫn còn các lô hàng chưa xuất hết. Vui lòng xử lý lô hàng trước khi xoá.']);
        }

        // Chặn xóa nếu có đơn cấp phát kho tổng chưa hoàn tất
        if (SupplyRequest::where(fn ($q) => $q->where('from_branch_id', $branch->id)->orWhere('to_branch_id', $branch->id))
            ->whereNotIn('status', SupplyRequest::terminalStatuses())
            ->exists()) {
            return back()->withErrors(['branch' => 'Chi nhánh đang có đơn yêu cầu cấp phát chưa hoàn tất hoặc đang xử lý.']);
        }

        // Chặn xóa nếu có đơn luân chuyển kho chưa đóng
        if (StockTransferRequest::where(fn ($q) => $q->where('from_branch_id', $branch->id)->orWhere('to_branch_id', $branch->id))
            ->whereNotIn('status', ['received', 'returned', 'destroyed', 'cancelled', 'rejected'])
            ->exists()) {
            return back()->withErrors(['branch' => 'Chi nhánh đang có đơn luân chuyển kho chưa hoàn tất.']);
        }

        // Chặn xóa nếu có phiên kiểm kê chưa duyệt / chưa hủy
        if (InventoryCountSession::where('branch_id', $branch->id)
            ->whereNotIn('status', ['approved', 'cancelled', 'rejected'])
            ->exists()) {
            return back()->withErrors(['branch' => 'Chi nhánh đang có phiên kiểm kê kho chưa kết thúc.']);
        }

        // Chặn xóa nếu có tranh chấp kho đang mở
        if (InventoryDiscrepancyDispute::whereHas('supplyRequest', fn ($q) => $q->where('from_branch_id', $branch->id)->orWhere('to_branch_id', $branch->id))
            ->where('status', 'open')
            ->exists()) {
            return back()->withErrors(['branch' => 'Chi nhánh đang có hồ sơ tranh chấp khiếu nại kho chưa giải quyết.']);
        }

        $previousManagerId = $branch->getRawOriginal('manager_user_id');
        DB::transaction(function () use ($branch, $previousManagerId): void {
            $this->syncManagerAssignment($branch, null, $previousManagerId);
            $branch->delete();
        });

        return back()->with('success', "Đã xoá chi nhánh \"{$branch->name}\".");
    }

    private function resolveManager(int $restaurantId, mixed $managerUserId, ?int $exceptBranchId = null): ?User
    {
        if ($managerUserId === null || $managerUserId === '' || (int) $managerUserId === 0) {
            return null;
        }

        $manager = User::query()
            ->where('restaurant_id', $restaurantId)
            ->whereKey((int) $managerUserId)
            ->first();

        if (! $manager || ! $manager->isBranchManager()) {
            throw ValidationException::withMessages([
                'manager_user_id' => 'Người được gán phải có role quản lý chi nhánh.',
            ]);
        }

        $alreadyAssigned = RestaurantBranch::query()
            ->where('restaurant_id', $restaurantId)
            ->where('manager_user_id', $manager->id)
            ->where('status', 'active')
            ->when($exceptBranchId !== null, fn ($query) => $query->where('id', '!=', $exceptBranchId))
            ->exists();

        if ($alreadyAssigned) {
            throw ValidationException::withMessages([
                'manager_user_id' => 'Quản lý này đã được gán cho một chi nhánh khác.',
            ]);
        }

        return $manager;
    }

    private function syncManagerAssignment(RestaurantBranch $branch, ?User $manager, mixed $previousManagerId): void
    {
        $newManagerId = $manager?->id;

        if ($previousManagerId && (int) $previousManagerId !== $newManagerId) {
            $previousManager = User::query()
                ->where('restaurant_id', $branch->restaurant_id)
                ->whereKey((int) $previousManagerId)
                ->first();

            if ($previousManager) {
                if ((int) $previousManager->getRawOriginal('branch_id') === (int) $branch->id) {
                    $previousManager->forceFill(['branch_id' => null])->saveQuietly();
                }

                $previousEmployee = $previousManager->employee;
                if ($previousEmployee && (int) $previousEmployee->getRawOriginal('branch_id') === (int) $branch->id) {
                    $previousEmployee->forceFill(['branch_id' => null])->saveQuietly();
                }
            }
        }

        if ($manager) {
            $manager->forceFill(['branch_id' => $branch->id])->saveQuietly();
            $employee = $manager->employee;
            if ($employee) {
                $employee->forceFill(['branch_id' => $branch->id])->saveQuietly();
            }
        }

        if ((int) $branch->getRawOriginal('manager_user_id') !== (int) $newManagerId) {
            $branch->forceFill(['manager_user_id' => $newManagerId])->saveQuietly();
        }
    }
}
