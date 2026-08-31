<?php

namespace Database\Seeders\System;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free',
                'name' => 'Miễn Phí',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'max_branches' => 1,
                'max_tables' => 15,
                'max_users' => 5,
                'max_dishes' => 50,
                'features' => [
                    'kitchen_display' => false,
                    'qr_ordering' => false,
                    'inventory_basic' => false,
                    'hr_timekeeping' => false,
                    'hr_full' => false,
                    'advanced_analytics' => false,
                    'realtime' => false,
                    'fraud_detection' => false,
                    'email_reports' => false,
                    'ai_advisor' => false,
                    'supplier_portal' => false,
                    'ai_forecasting' => false,
                    'api_access' => false,
                    'max_areas' => 2,
                    'max_storage_mb' => 500,
                    'api_rate_limit' => 30,
                ],
                'status' => 'active',
            ],
            [
                'code' => 'starter',
                'name' => 'Cơ Bản',
                'price' => 299000,
                'billing_cycle' => 'monthly',
                'max_branches' => 3,
                'max_tables' => 60,
                'max_users' => 20,
                'max_dishes' => 300,
                'features' => [
                    'kitchen_display' => true,
                    'qr_ordering' => true,
                    'inventory_basic' => true,
                    'hr_timekeeping' => true,
                    'hr_full' => false,
                    'advanced_analytics' => false,
                    'realtime' => true,
                    'fraud_detection' => false,
                    'email_reports' => false,
                    'ai_advisor' => false,
                    'supplier_portal' => false,
                    'ai_forecasting' => false,
                    'api_access' => false,
                    'max_areas' => 8,
                    'max_storage_mb' => 5120,
                    'api_rate_limit' => 120,
                ],
                'status' => 'active',
            ],
            [
                'code' => 'pro',
                'name' => 'Chuyên Nghiệp',
                'price' => 699000,
                'billing_cycle' => 'monthly',
                'max_branches' => 10,
                'max_tables' => 200,
                'max_users' => 60,
                'max_dishes' => 1000,
                'features' => [
                    'kitchen_display' => true,
                    'qr_ordering' => true,
                    'inventory_basic' => true,
                    'hr_timekeeping' => true,
                    'hr_full' => true,
                    'advanced_analytics' => true,
                    'realtime' => true,
                    'fraud_detection' => true,
                    'email_reports' => true,
                    'ai_advisor' => true,
                    'supplier_portal' => false,
                    'ai_forecasting' => false,
                    'api_access' => false,
                    'max_areas' => 25,
                    'max_storage_mb' => 51200,
                    'api_rate_limit' => 600,
                ],
                'status' => 'active',
            ],
            [
                'code' => 'enterprise',
                'name' => 'Doanh Nghiệp',
                'price' => 1499000,
                'billing_cycle' => 'monthly',
                'max_branches' => null,
                'max_tables' => null,
                'max_users' => null,
                'max_dishes' => null,
                'features' => [
                    'kitchen_display' => true,
                    'qr_ordering' => true,
                    'inventory_basic' => true,
                    'hr_timekeeping' => true,
                    'hr_full' => true,
                    'advanced_analytics' => true,
                    'realtime' => true,
                    'fraud_detection' => true,
                    'email_reports' => true,
                    'ai_advisor' => true,
                    'supplier_portal' => true,
                    'ai_forecasting' => true,
                    'api_access' => true,
                    'max_areas' => null,
                    'max_storage_mb' => 204800,
                    'api_rate_limit' => 3000,
                ],
                'status' => 'active',
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
