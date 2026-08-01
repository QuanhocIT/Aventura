<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Bảng giá công khai (trang đăng nhập + trang chủ) chỉ được rao các gói bán đại trà.
 *
 * Bug đã vá 2026-07-30: FortifyServiceProvider::activePlans() lọc status='active'
 * nhưng THIẾU điều kiện is_custom=false (HomeController thì có) — nên gói riêng
 * SuperAdmin dựng cho một khách hàng cụ thể sẽ lộ nguyên giá đàm phán ra trang
 * đăng nhập cho mọi người xem.
 */
class PublicPlanListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // activePlans() cache 1 giờ — phải dọn giữa các test
        Cache::forget('active_plans');
    }

    private function planNamesOnLoginPage(): array
    {
        $response = $this->get('/login');
        $response->assertOk();

        $plans = $response->viewData('page')['props']['plans'] ?? [];

        return array_column($plans, 'name');
    }

    public function test_login_page_does_not_expose_custom_negotiated_plans(): void
    {
        SubscriptionPlan::factory()->create([
            'code' => 'public-plan',
            'name' => 'Gói bán đại trà',
            'status' => 'active',
            'is_custom' => false,
        ]);

        SubscriptionPlan::factory()->create([
            'code' => 'bespoke-abc',
            'name' => 'Gói riêng cho khách A',
            'price' => 12345000,
            'status' => 'active',
            'is_custom' => true,
        ]);

        $names = $this->planNamesOnLoginPage();

        $this->assertContains('Gói bán đại trà', $names);
        $this->assertNotContains(
            'Gói riêng cho khách A',
            $names,
            'Gói riêng đàm phán cho 1 khách hàng không được rao giá công khai'
        );
    }

    public function test_login_page_does_not_expose_inactive_plans(): void
    {
        SubscriptionPlan::factory()->create([
            'code' => 'retired-plan',
            'name' => 'Gói đã ngừng bán',
            'status' => 'inactive',
            'is_custom' => false,
        ]);

        $this->assertNotContains('Gói đã ngừng bán', $this->planNamesOnLoginPage());
    }

    public function test_home_page_applies_the_same_filters(): void
    {
        SubscriptionPlan::factory()->create([
            'code' => 'bespoke-home',
            'name' => 'Gói riêng khách B',
            'status' => 'active',
            'is_custom' => true,
        ]);
        SubscriptionPlan::factory()->create([
            'code' => 'retired-home',
            'name' => 'Gói ngừng bán B',
            'status' => 'inactive',
            'is_custom' => false,
        ]);

        $response = $this->get('/');
        $response->assertOk();

        $names = array_column($response->viewData('page')['props']['plans'] ?? [], 'name');

        $this->assertNotContains('Gói riêng khách B', $names);
        $this->assertNotContains('Gói ngừng bán B', $names);
    }
}
