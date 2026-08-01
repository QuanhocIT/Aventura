<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bật sẵn Cửa hàng Online cho các nhà hàng đã tồn tại nhưng chưa có cấu hình
 * (trước đây online_store_configs trống → link storefront order/{slug} luôn 404).
 * Nhà hàng mới đã được tạo config tự động trong RestaurantOnboardingService::seedDefaults().
 */
return new class extends Migration
{
    public function up(): void
    {
        $restaurants = DB::table('restaurants')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('online_store_configs')
                    ->whereColumn('online_store_configs.restaurant_id', 'restaurants.id');
            })
            ->get(['id', 'name', 'slug']);

        $now = now();

        foreach ($restaurants as $r) {
            $slug = $r->slug ?: Str::slug($r->name).'-'.Str::lower(Str::random(4));

            DB::table('online_store_configs')->insert([
                'restaurant_id' => $r->id,
                'is_active' => true,
                'slug' => $slug,
                'description' => 'Đặt món trực tuyến tại '.$r->name,
                'min_order_amount' => 0,
                'delivery_base_fee' => 15000,
                'delivery_fee_per_km' => 5000,
                'max_delivery_km' => 10,
                'enable_takeaway' => true,
                'enable_delivery' => true,
                'enable_preorder' => false,
                'accepted_payments' => json_encode(['bank_transfer']),
                'operating_hours' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Data backfill — không xoá ngược để tránh mất cấu hình chủ quán đã chỉnh sau đó.
    }
};
