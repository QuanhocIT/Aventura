<?php

namespace App\Support\Tenant;

class TenantContext
{
    protected ?int $restaurantId = null;
    protected ?int $activeBranchId = null;

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
        $this->activeBranchId = $activeBranchId;
    }

    public function activeBranchId(): ?int
    {
        return $this->activeBranchId;
    }
}
