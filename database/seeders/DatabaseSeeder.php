<?php

namespace Database\Seeders;

use Database\Seeders\Restaurant\RestaurantDemoSeeder;
use Database\Seeders\System\ChatbotKnowledgeSeeder;
use Database\Seeders\System\SubscriptionPlanSeeder;
use Database\Seeders\System\SystemDemoSeeder;
use Database\Seeders\Tenant\TenantDemoSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionsSeeder::class,
            SubscriptionPlanSeeder::class,
            SystemDemoSeeder::class,
            TenantDemoSeeder::class,
            RestaurantDemoSeeder::class,
            SuperAdminSeeder::class,
            ChatbotKnowledgeSeeder::class,
        ]);
    }
}
