<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    /**
     * Several controllers (PromotionController combos, Cashier/Kitchen availability
     * toggles, OrdersController) mutate products without going through
     * ProductManagementController's manual Cache::forget() calls, leaving the admin's
     * cached product list (up to 1h TTL) stale. Centralizing invalidation here catches
     * every current and future mutation path instead of requiring each controller to
     * remember to bust the cache itself.
     */
    public function saved(Product $product): void
    {
        Cache::forget("restaurant_{$product->restaurant_id}_products");
    }

    public function deleted(Product $product): void
    {
        Cache::forget("restaurant_{$product->restaurant_id}_products");
    }
}
