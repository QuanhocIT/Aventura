<?php

namespace Database\Seeders\Tenant;

use App\Services\Onboarding\RestaurantOnboardingService;
use Illuminate\Database\Seeder;

/**
 * Tạo tenant demo bằng cách gọi ĐÚNG pipeline onboarding production.
 * Điều này đảm bảo môi trường seed luôn đồng bộ với luồng đăng ký thật:
 * - Tạo restaurant + user + gán role owner
 * - Tạo subscription (trial)
 * - Seed dữ liệu mẫu: units, supplier, area, bàn + QR code thật, danh mục, món, nguyên liệu, công thức
 */
class TenantDemoSeeder extends Seeder
{
    public function __construct(
        private readonly RestaurantOnboardingService $onboarding,
    ) {}

    public function run(): void
    {
        $this->onboarding->onboard([
            'name' => 'Owner Demo',
            'restaurant_name' => 'Aventura Demo',
            'email' => 'owner@bepso.test',
            'password' => 'password',
            'phone' => '0900000001',
            'plan_code' => 'pro',
        ]);
    }
}
