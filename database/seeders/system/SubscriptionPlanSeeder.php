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
                'code'           => 'FREE',
                'name'           => 'Miễn phí',
                'price'          => 0,
                'billing_cycle'  => 'monthly',
                'max_branches'   => 1,
                'max_tables'     => 10,
                'max_users'      => 5,
                'features'       => [
                    'max_areas'           => 2,
                    'max_storage_mb'      => 500,
                    'ai_features'         => false,
                    'realtime'            => false,
                    'advanced_analytics'  => false,
                    'api_rate_limit'      => 60,
                ],
                'status' => 'active',
            ],
            [
                'code'           => 'PRO',
                'name'           => 'Cao cấp',
                'price'          => 299000,
                'billing_cycle'  => 'monthly',
                'max_branches'   => null,  // null = không giới hạn
                'max_tables'     => null,
                'max_users'      => null,
                'features'       => [
                    'max_areas'           => null,  // null = không giới hạn
                    'max_storage_mb'      => 10240,
                    'ai_features'         => true,
                    'realtime'            => true,
                    'advanced_analytics'  => true,
                    'api_rate_limit'      => 600,
                ],
                'status' => 'active',
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
