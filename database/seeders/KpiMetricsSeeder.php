<?php

namespace Database\Seeders;

use App\Models\KpiMetric;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class KpiMetricsSeeder extends Seeder
{
    public function run(?int $restaurantId = null): void
    {
        $restaurants = $restaurantId ? Restaurant::where('id', $restaurantId)->get() : Restaurant::all();

        foreach ($restaurants as $restaurant) {
            $this->seedDefaultsForRestaurant($restaurant->id);
        }
    }

    public function seedDefaultsForRestaurant(int $restaurantId): void
    {
        $defaults = [
            // Waiter Role
            [
                'restaurant_id' => $restaurantId,
                'role' => 'waiter',
                'metric_code' => 'waiter_orders_served',
                'metric_name' => 'Số đơn phục vụ trung bình (đơn/ca)',
                'weight' => 40.00,
                'target' => 15.00,
                'target_type' => 'more_is_better',
                'bonus_amount' => 200000.00, // 200k VND
                'commission_rate' => 0.00,
            ],
            [
                'restaurant_id' => $restaurantId,
                'role' => 'waiter',
                'metric_code' => 'waiter_customer_rating',
                'metric_name' => 'Điểm đánh giá của khách hàng (sao)',
                'weight' => 35.00,
                'target' => 4.50,
                'target_type' => 'more_is_better',
                'bonus_amount' => 150000.00,
                'commission_rate' => 0.00,
            ],
            [
                'restaurant_id' => $restaurantId,
                'role' => 'waiter',
                'metric_code' => 'waiter_service_speed',
                'metric_name' => 'Tốc độ phục vụ trung bình (phút/món)',
                'weight' => 25.00,
                'target' => 5.00, // lower is better
                'target_type' => 'less_is_better',
                'bonus_amount' => 100000.00,
                'commission_rate' => 0.00,
            ],

            // Kitchen / Chef Role
            [
                'restaurant_id' => $restaurantId,
                'role' => 'kitchen',
                'metric_code' => 'chef_prep_time',
                'metric_name' => 'Thời gian chế biến trung bình (phút)',
                'weight' => 40.00,
                'target' => 15.00,
                'target_type' => 'less_is_better',
                'bonus_amount' => 250000.00,
                'commission_rate' => 0.00,
            ],
            [
                'restaurant_id' => $restaurantId,
                'role' => 'kitchen',
                'metric_code' => 'chef_rejection_rate',
                'metric_name' => 'Tỷ lệ món bị hủy/trả lại (%)',
                'weight' => 30.00,
                'target' => 2.00,
                'target_type' => 'less_is_better',
                'bonus_amount' => 150000.00,
                'commission_rate' => 0.00,
            ],
            [
                'restaurant_id' => $restaurantId,
                'role' => 'kitchen',
                'metric_code' => 'chef_food_rating',
                'metric_name' => 'Chất lượng món ăn (đánh giá sao)',
                'weight' => 30.00,
                'target' => 4.50,
                'target_type' => 'more_is_better',
                'bonus_amount' => 200000.00,
                'commission_rate' => 0.00,
            ],

            // Cashier Role
            [
                'restaurant_id' => $restaurantId,
                'role' => 'cashier',
                'metric_code' => 'cashier_processed_revenue',
                'metric_name' => 'Doanh số xử lý thanh toán (VND)',
                'weight' => 40.00,
                'target' => 50000000.00, // 50M VND
                'target_type' => 'more_is_better',
                'bonus_amount' => 200000.00,
                'commission_rate' => 0.20, // 0.2% commission
            ],
            [
                'restaurant_id' => $restaurantId,
                'role' => 'cashier',
                'metric_code' => 'cashier_error_rate',
                'metric_name' => 'Tỷ lệ sai lệch két tiền mặt (%)',
                'weight' => 30.00,
                'target' => 0.00,
                'target_type' => 'less_is_better',
                'bonus_amount' => 150000.00,
                'commission_rate' => 0.00,
            ],
            [
                'restaurant_id' => $restaurantId,
                'role' => 'cashier',
                'metric_code' => 'cashier_checkout_speed',
                'metric_name' => 'Tốc độ thanh toán trung bình (phút)',
                'weight' => 30.00,
                'target' => 2.00,
                'target_type' => 'less_is_better',
                'bonus_amount' => 100000.00,
                'commission_rate' => 0.00,
            ],
        ];

        foreach ($defaults as $data) {
            KpiMetric::updateOrCreate(
                [
                    'restaurant_id' => $data['restaurant_id'],
                    'role' => $data['role'],
                    'metric_code' => $data['metric_code'],
                ],
                $data
            );
        }
    }
}
