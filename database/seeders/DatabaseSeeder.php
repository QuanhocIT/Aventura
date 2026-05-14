<?php

namespace Database\Seeders;

use Database\Seeders\Restaurant\RestaurantDemoSeeder;
use Database\Seeders\System\SystemDemoSeeder;
use Database\Seeders\Tenant\TenantDemoSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SystemDemoSeeder::class,
            TenantDemoSeeder::class,
            RestaurantDemoSeeder::class,
        ]);
    }
}
