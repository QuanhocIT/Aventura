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
                'code'           => 'free',
                'name'           => 'Free',
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
                'code'           => 'pro',
                'name'           => 'Pro',
                'price'          => 499000,
                'billing_cycle'  => 'monthly',
                'max_branches'   => null,
                'max_tables'     => null,
                'max_users'      => null,
                'features'       => [
                    'max_areas'           => null,
                    'max_storage_mb'      => 10240,
                    'ai_features'         => true,
                    'realtime'            => true,
                    'advanced_analytics'  => true,
                    'api_rate_limit'      => 600,
                ],
                'status' => 'active',
            ],
            [
                'code'           => 'max',
                'name'           => 'Max',
                'price'          => 999000,
                'billing_cycle'  => 'monthly',
                'max_branches'   => 10,
                'max_tables'     => 300,
                'max_users'      => 80,
                'features'       => [
                    'max_areas'           => 30,
                    'max_storage_mb'      => 51200,
                    'ai_features'         => true,
                    'realtime'            => true,
                    'advanced_analytics'  => true,
                    'api_rate_limit'      => 1200,
                ],
                'status' => 'active',
            ],
            [
                'code'           => 'ultra',
                'name'           => 'Ultra',
                'price'          => 1999000,
                'billing_cycle'  => 'monthly',
                'max_branches'   => null,
                'max_tables'     => null,
                'max_users'      => null,
                'features'       => [
                    'max_areas'           => null,
                    'max_storage_mb'      => 204800,
                    'ai_features'         => true,
                    'realtime'            => true,
                    'advanced_analytics'  => true,
                    'api_rate_limit'      => 3000,
                ],
                'status' => 'active',
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
