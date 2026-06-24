<?php

namespace App\Support\Tenant;

class TenantContext
{
    protected ?int $restaurantId = null;

    public function setRestaurantId(?int $restaurantId): void
    {
        $this->restaurantId = $restaurantId;
    }

    public function restaurantId(): ?int
    {
        return $this->restaurantId;
    }
}
