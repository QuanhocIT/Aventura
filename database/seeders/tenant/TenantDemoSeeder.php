<?php

namespace Database\Seeders\Tenant;

use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::updateOrCreate(
            ['email' => 'owner@bepso.test'],
            [
                'name' => 'Owner Demo',
                'password' => Hash::make('password'),
                'phone' => '0900000001',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $restaurant = Restaurant::updateOrCreate(
            ['code' => 'FNBVIET-DEMO'],
            [
                'plan_id' => SubscriptionPlan::where('code', 'pro')->value('id'),
                'owner_user_id' => $owner->id,
                'name' => 'Bepso Viet Demo',
                'slug' => 'bepso-viet-demo',
                'phone' => '02873000001',
                'email' => 'hello@bepso.test',
                'address' => '1 Nguyen Hue, Quan 1, TP.HCM',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'status' => 'active',
                'subscription_started_at' => now()->toDateString(),
                'subscription_ends_at' => now()->addMonth()->toDateString(),
            ],
        );

        $owner->forceFill([
            'restaurant_id' => $restaurant->id,
        ])->save();

        $owner->syncRoles(['owner']);
    }
}
