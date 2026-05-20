<?php

namespace Database\Seeders;

use Database\Seeders\Restaurant\RestaurantDemoSeeder;
use Database\Seeders\System\SubscriptionPlanSeeder;
use Database\Seeders\System\SystemDemoSeeder;
use Database\Seeders\Tenant\TenantDemoSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SubscriptionPlanSeeder::class,
            SystemDemoSeeder::class,
            TenantDemoSeeder::class,
            RestaurantDemoSeeder::class,
        ]);
    }
}
