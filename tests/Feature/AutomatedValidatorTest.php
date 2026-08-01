<?php

namespace Tests\Feature;

use App\Mail\SuperAdminValidatorReportMail;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantDailyCheck;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AutomatedValidatorTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected Restaurant $restaurant;

    protected SubscriptionPlan $plan;

    protected RestaurantSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['superadmin.2fa_verified_until' => now()->addMinutes(15)->timestamp]);

        // 1. Setup SuperAdmin with 2FA confirmed to bypassRequireSuperAdminTwoFactor middleware
        $this->superAdmin = User::factory()->create([
            'two_factor_confirmed_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');
        $this->withSession(['superadmin.2fa_verified_user_id' => $this->superAdmin->id]);

        // 2. Setup Plan
        $this->plan = SubscriptionPlan::create([
            'name' => 'Pro Plan',
            'code' => 'PRO',
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'max_branches' => 5,
            'max_tables' => 50,
            'max_users' => 20,
            'status' => 'active',
        ]);

        // 3. Setup Restaurant
        $this->restaurant = Restaurant::create([
            'name' => 'Pizza House',
            'code' => 'PIZZA1',
            'slug' => 'pizza-house',
            'status' => 'active',
            'plan_id' => $this->plan->id,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
        ]);

        // 4. Setup Subscription
        $this->subscription = RestaurantSubscription::create([
            'restaurant_id' => $this->restaurant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'started_at' => now()->subDays(10),
            'ended_at' => now()->addDays(20),
            'price' => 500000,
        ]);
    }

    public function test_validator_command_detects_activity_and_updates_last_active_at(): void
    {
        Mail::fake();

        // Ghi nhận mốc cũ
        $this->restaurant->update(['last_active_at' => now()->subDays(5)]);

        // 1. Tạo đơn hàng hôm nay
        Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => 'ORD-1001',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        // 2. Chạy lệnh
        $this->artisan('restaurants:validate-activity')
            ->assertSuccessful();

        // 3. Kiểm định
        $this->restaurant->refresh();
        $this->assertFalse($this->restaurant->is_inactive_flagged);
        $this->assertNull($this->restaurant->inactive_flagged_at);
        $this->assertNotNull($this->restaurant->last_active_at);
        $this->assertTrue($this->restaurant->last_active_at->isToday());

        // Kiểm định có ghi log daily checks (truy vấn trực tiếp để tránh khác biệt định dạng ngày của SQLite)
        $check = RestaurantDailyCheck::where('restaurant_id', $this->restaurant->id)->first();
        $this->assertNotNull($check);
        $this->assertEquals(today()->toDateString(), $check->checked_date->toDateString());
        $this->assertEquals(1, $check->orders_count);
        $this->assertTrue($check->had_activity);
        $this->assertFalse($check->is_flagged);

        // Kiểm định đã gửi email báo cáo cho superadmin
        Mail::assertSent(SuperAdminValidatorReportMail::class, function ($mail) {
            return $mail->hasTo($this->superAdmin->email);
        });
    }

    public function test_validator_command_flags_inactive_restaurant(): void
    {
        Mail::fake();

        // 1. Thiết lập mốc hoạt động cũ (8 ngày trước, tức là vượt hạn mặc định 7 ngày)
        $this->restaurant->update([
            'last_active_at' => now()->subDays(8),
            'is_inactive_flagged' => false,
        ]);

        // 2. Chạy lệnh (không tạo hoạt động nào)
        $this->artisan('restaurants:validate-activity')
            ->assertSuccessful();

        // 3. Kiểm định
        $this->restaurant->refresh();
        $this->assertTrue($this->restaurant->is_inactive_flagged);
        $this->assertNotNull($this->restaurant->inactive_flagged_at);
        $this->assertTrue($this->restaurant->inactive_flagged_at->isToday());

        // Ghi log daily checks bị gắn cờ
        $check = RestaurantDailyCheck::where('restaurant_id', $this->restaurant->id)->first();
        $this->assertNotNull($check);
        $this->assertEquals(today()->toDateString(), $check->checked_date->toDateString());
        $this->assertEquals(0, $check->orders_count);
        $this->assertFalse($check->had_activity);
        $this->assertTrue($check->is_flagged);

        // Kiểm định đã gửi email báo cáo cho superadmin
        Mail::assertSent(SuperAdminValidatorReportMail::class);
    }

    public function test_superadmin_can_unflag_restaurant(): void
    {
        // 1. Gắn cờ thủ công cho cửa hàng
        $this->restaurant->update([
            'is_inactive_flagged' => true,
            'inactive_flagged_at' => now()->subDays(3),
            'last_active_at' => now()->subDays(10),
        ]);

        // 2. Gọi API gỡ cờ
        $response = $this->actingAs($this->superAdmin)
            ->patch(route('superadmin.restaurants.unflag', $this->restaurant));

        $response->assertRedirect();

        // 3. Kiểm định database
        $this->restaurant->refresh();
        $this->assertFalse($this->restaurant->is_inactive_flagged);
        $this->assertNull($this->restaurant->inactive_flagged_at);
        $this->assertNotNull($this->restaurant->last_active_at);
        $this->assertTrue($this->restaurant->last_active_at->isToday());
    }
}
