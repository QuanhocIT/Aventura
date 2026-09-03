<?php

namespace App\Services;

use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Centralizes the operational boundary for warehouse_staff accounts.
 *
 * Managers and owners are intentionally not restricted by this service. A
 * warehouse_staff user, however, must be active, explicitly assigned to the
 * configured central warehouse, and must not be paused/inactive.
 */
class WarehouseStaffAccessService
{
    public function __construct(
        protected CentralWarehouseService $centralWarehouseService,
    ) {}

    public function isWarehouseStaff(User $user): bool
    {
        return $user->hasRole('warehouse_staff');
    }

    public function centralWarehouseFor(User $user): ?RestaurantBranch
    {
        return $this->centralWarehouseService->getCentralWarehouse((int) $user->restaurant_id);
    }

    public function assignedWarehouseBranchId(User $user): ?int
    {
        $branchId = $user->warehouse_branch_id ?: $user->assignedBranchId();

        return $branchId ? (int) $branchId : null;
    }

    public function isActive(User $user): bool
    {
        if (! $this->isWarehouseStaff($user)) {
            return true;
        }

        return $user->status === 'active'
            && ($user->warehouse_staff_status ?? 'active') === 'active';
    }

    public function isAssignedToCentral(User $user): bool
    {
        if (! $this->isWarehouseStaff($user)) {
            return true;
        }

        $central = $this->centralWarehouseFor($user);

        return $central !== null
            && $this->assignedWarehouseBranchId($user) !== null
            && $this->assignedWarehouseBranchId($user) === (int) $central->id;
    }

    public function canOperate(User $user): bool
    {
        return $this->isActive($user) && $this->isAssignedToCentral($user);
    }

    public function assertCanOperate(User $user): void
    {
        if (! $this->isWarehouseStaff($user)) {
            return;
        }

        if (! $this->isActive($user)) {
            throw new AuthorizationException('Tài khoản Nhân viên kho Tổng đang tạm dừng hoặc không còn hoạt động.');
        }

        $central = $this->centralWarehouseFor($user);
        if (! $central) {
            throw new AuthorizationException('Nhà hàng chưa cấu hình Kho Tổng đang hoạt động.');
        }

        if ($this->assignedWarehouseBranchId($user) !== (int) $central->id) {
            throw new AuthorizationException('Tài khoản chưa được gán đúng chi nhánh Kho Tổng.');
        }
    }

    public function assertCanAccessCentral(User $user): void
    {
        if (! $this->isWarehouseStaff($user)) {
            return;
        }

        $central = $this->centralWarehouseFor($user);
        if (! $central || $this->assignedWarehouseBranchId($user) !== (int) $central->id) {
            throw new AuthorizationException('Tài khoản chỉ được truy cập dữ liệu Kho Tổng đã được phân công.');
        }

        // Nhân viên kho phải trong ca làm việc mới được vào portal
        $employee = $user->employee;
        if (! $employee || ! $employee->isWithinScheduledShift()) {
            throw new AuthorizationException('Bạn chưa đến ca làm việc. Vui lòng kiểm tra lịch ca và đăng nhập đúng giờ.');
        }
    }

    public function scopeActiveCentralStaff($query, User $actor, ?int $centralBranchId = null)
    {
        $centralBranchId ??= $this->centralWarehouseFor($actor)?->id;

        return $query
            ->where('status', 'active')
            ->where('warehouse_staff_status', 'active')
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'warehouse_staff'))
            ->when($centralBranchId, fn ($q) => $q->where(function ($scope) use ($centralBranchId) {
                $scope->where('warehouse_branch_id', $centralBranchId)
                    ->orWhere('branch_id', $centralBranchId);
            }));
    }
}
