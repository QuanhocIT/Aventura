<?php

namespace App\Support\Tenant;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class TenantContext
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_BRANCH = 'branch';

    public const SCOPE_NONE = 'none';

    protected ?int $restaurantId = null;
    protected ?int $activeBranchId = null;
    protected string $scope = self::SCOPE_ALL;

    /**
     * Start a fresh request context. This is intentionally explicit so a
     * long-running worker cannot reuse the previous tenant or branch.
     */
    public function beginRequest(?int $restaurantId): void
    {
        $this->restaurantId = $restaurantId;
        $this->activeBranchId = null;
        $this->scope = self::SCOPE_ALL;
    }

    public function setRestaurantId(?int $restaurantId): void
    {
        $this->restaurantId = $restaurantId;
    }

    public function restaurantId(): ?int
    {
        return $this->restaurantId;
    }

    public function setActiveBranchId(?int $activeBranchId): void
    {
        if ($activeBranchId === null) {
            $this->setAllBranches();

            return;
        }

        $this->activeBranchId = $activeBranchId;
        $this->scope = self::SCOPE_BRANCH;
    }

    public function activeBranchId(): ?int
    {
        return $this->activeBranchId;
    }

    public function setAllBranches(): void
    {
        $this->activeBranchId = null;
        $this->scope = self::SCOPE_ALL;
    }

    public function setUnassigned(): void
    {
        $this->activeBranchId = null;
        $this->scope = self::SCOPE_NONE;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function isAllBranches(): bool
    {
        return $this->scope === self::SCOPE_ALL;
    }

    public function isBranchScoped(): bool
    {
        return $this->scope === self::SCOPE_BRANCH && $this->activeBranchId !== null;
    }

    public function isUnassigned(): bool
    {
        return $this->scope === self::SCOPE_NONE;
    }

    public function scopeKey(): string
    {
        return self::branchScopeKey($this->activeBranchId, $this->scope);
    }

    public static function branchScopeKey(?int $branchId, ?string $scope = null): string
    {
        if ($scope === self::SCOPE_NONE) {
            return self::SCOPE_NONE;
        }

        return $branchId === null ? self::SCOPE_ALL : self::SCOPE_BRANCH.':'.$branchId;
    }

    /**
     * Apply the request's branch scope to a query. Owner requests in the
     * "all" scope intentionally remain tenant-wide; unassigned staff get no
     * rows even if a caller forgot to add a manual branch condition.
     */
    public function applyBranchScope(
        EloquentBuilder|QueryBuilder $query,
        string $column = 'branch_id',
    ): EloquentBuilder|QueryBuilder {
        if ($this->isBranchScoped()) {
            $query->where($column, $this->activeBranchId);
        } elseif ($this->isUnassigned()) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function cacheKey(string $prefix, ...$parts): string
    {
        $parts[] = 'scope';
        $parts[] = $this->scopeKey();

        return implode(':', array_merge([$prefix], array_map(
            static fn ($part): string => (string) $part,
            $parts,
        )));
    }
}
