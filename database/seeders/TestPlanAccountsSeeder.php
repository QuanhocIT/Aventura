<?php

namespace Database\Seeders;

use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Onboarding\RestaurantOnboardingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class TestPlanAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $onboarding = app(RestaurantOnboardingService::class);

        $accounts = [
            [
                'name' => 'Test Free',
                'restaurant_name' => 'Nhà hàng Free',
                'email' => 'free@test.com',
                'password' => 'password',
                'phone' => '0900000010',
                'target_plan' => 'free',
            ],
            [
                'name' => 'Test Starter',
                'restaurant_name' => 'Nhà hàng Cơ Bản',
                'email' => 'starter@test.com',
                'password' => 'password',
                'phone' => '0900000020',
                'target_plan' => 'starter',
            ],
            [
                'name' => 'Test Pro',
                'restaurant_name' => 'Nhà hàng Chuyên Nghiệp',
                'email' => 'pro@test.com',
                'password' => 'password',
                'phone' => '0900000030',
                'target_plan' => 'pro',
            ],
            [
                'name' => 'Test Enterprise',
                'restaurant_name' => 'Nhà hàng Doanh Nghiệp',
                'email' => 'enterprise@test.com',
                'password' => 'password',
                'phone' => '0900000040',
                'target_plan' => 'enterprise',
            ],
        ];

        foreach ($accounts as $acc) {
            if (User::where('email', $acc['email'])->exists()) {
                $this->command->info("Đã tồn tại: {$acc['email']} — bỏ qua");

                continue;
            }

            $user = $onboarding->onboard([
                'name' => $acc['name'],
                'restaurant_name' => $acc['restaurant_name'],
                'email' => $acc['email'],
                'password' => $acc['password'],
                'phone' => $acc['phone'],
            ]);

            $targetPlan = SubscriptionPlan::where('code', $acc['target_plan'])->first();
            if ($targetPlan && $acc['target_plan'] !== 'free') {
                $restaurant = $user->restaurant;
                $restaurant->update(['plan_id' => $targetPlan->id]);

                RestaurantSubscription::where('restaurant_id', $restaurant->id)
                    ->latest('id')
                    ->first()
                    ?->update([
                        'plan_id' => $targetPlan->id,
                        'status' => 'active',
                        'price' => $targetPlan->price,
                    ]);

                Cache::forget("quota_summary:{$restaurant->id}");
            }

            $this->command->info("Tạo xong: {$acc['email']} — Gói: {$acc['target_plan']}");
        }

        $this->command->newLine();
        $this->command->table(
            ['Email', 'Mật khẩu', 'Gói'],
            collect($accounts)->map(fn ($a) => [$a['email'], 'password', $a['target_plan']])->toArray()
        );
    }
}
