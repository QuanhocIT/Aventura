<?php

namespace App\Services;

use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Defines the branch boundary for inventory-count workflows.
 *
 * A warehouse manager/staff account is a central-warehouse account. It must
 * never be able to widen its inventory-count scope by sending another
 * branch_id from the browser or by calling the service directly.
 */
class InventoryCountScopeService
{
    public function __construct(
        protected CentralWarehouseService $centralWarehouseService,
    ) {}

    public function isCentralWarehouseAccount(User $user): bool
    {
        return ! $user->isOwner()
            && ! $user->isSuperAdmin()
            && $user->hasAnyRole(['warehouse_manager', 'warehouse_staff']);
    }

    public function centralWarehouseFor(User $user): ?RestaurantBranch
    {
        return $this->centralWarehouseService->getCentralWarehouse((int) $user->restaurant_id);
    }

    public function canAccessBranch(User $user, int $branchId): bool
    {
        if ((int) $user->restaurant_id <= 0 || $branchId <= 0) {
            return false;
        }

        if ($this->isCentralWarehouseAccount($user)) {
            $centralWarehouse = $this->centralWarehouseFor($user);
            $assignedBranchId = $user->warehouse_branch_id ?: $user->assignedBranchId();

            return $centralWarehouse !== null
                && $assignedBranchId !== null
                && (int) $assignedBranchId === (int) $centralWarehouse->id
                && (int) $centralWarehouse->id === $branchId;
        }

        return $user->canAccessBranch($branchId);
    }

    public function assertCanAccessBranch(User $user, int $branchId): void
    {
        if (! $this->canAccessBranch($user, $branchId)) {
            throw new InvalidArgumentException(
                $this->isCentralWarehouseAccount($user)
                    ? 'Tài khoản Trưởng kho Tổng chỉ được kiểm kê nguyên liệu và tồn kho của Kho Tổng.'
                    : 'Tài khoản không được phép kiểm kê chi nhánh này.'
            );
        }
    }

    /**
     * Return only branches that the count screen is allowed to expose.
     * The central account intentionally receives a one-branch collection.
     */
    public function branchesFor(User $user): Collection
    {
        if ($this->isCentralWarehouseAccount($user)) {
            $centralWarehouse = $this->centralWarehouseFor($user);

            return $centralWarehouse ? collect([$centralWarehouse]) : collect();
        }

        return RestaurantBranch::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->when(! $user->canViewAllBranches(), fn ($query) => $query->whereKey($user->assignedBranchId()))
            ->get();
    }

    public function centralScopeMessage(): string
    {
        return 'Phạm vi cố định: chỉ kiểm kê nguyên liệu và tồn kho của Kho Tổng; không thao tác dữ liệu chi nhánh.';
    }
}
