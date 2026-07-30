<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\Promotion;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(int $restaurantId, string $phone): string
    {
        return hash('sha256', $restaurantId . $phone . config('app.key'));
    }

    public function test_coupon_wallet_rejects_request_without_valid_token(): void
    {
        $restaurant = Restaurant::factory()->create();
        $customer = Customer::create([
            'restaurant_id' => $restaurant->id,
            'phone' => '0909000001',
            'full_name' => 'Khách Hàng A',
        ]);

        $response = $this->get(route('customer.coupons.wallet', [$restaurant->id, $customer->phone]));

        $response->assertForbidden();
    }

    public function test_coupon_wallet_allows_request_with_valid_token(): void
    {
        $restaurant = Restaurant::factory()->create();
        $customer = Customer::create([
            'restaurant_id' => $restaurant->id,
            'phone' => '0909000002',
            'full_name' => 'Khách Hàng B',
        ]);

        $token = $this->tokenFor($restaurant->id, $customer->phone);

        $response = $this->get(route('customer.coupons.wallet', [$restaurant->id, $customer->phone]) . '?token=' . $token);

        $response->assertOk();
    }

    public function test_claim_coupon_rejects_promotion_from_another_restaurant(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();

        $customer = Customer::create([
            'restaurant_id' => $restaurantA->id,
            'phone' => '0909000003',
            'full_name' => 'Khách Hàng C',
        ]);

        $foreignPromotion = Promotion::create([
            'restaurant_id' => $restaurantB->id,
            'name' => 'Khuyến mãi nhà hàng khác',
            'code' => 'FOREIGN10',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'is_approved' => true,
        ]);

        $token = $this->tokenFor($restaurantA->id, $customer->phone);

        $response = $this->postJson(
            route('customer.coupons.claim', [$restaurantA->id, $customer->phone]) . '?token=' . $token,
            ['promotion_id' => $foreignPromotion->id]
        );

        // 422 (validation) chứ không còn 404: TenantRule::exists() chặn ngay ở tầng
        // validate, sớm hơn findOrFail cũ — vẫn từ chối, chỉ là chặn sớm hơn 1 bước.
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['promotion_id']);

        $this->assertDatabaseMissing('customer_coupons', [
            'customer_id' => $customer->id,
            'promotion_id' => $foreignPromotion->id,
        ]);
    }

    public function test_redeem_reward_rejects_request_without_valid_token(): void
    {
        $restaurant = Restaurant::factory()->create();
        $customer = Customer::create([
            'restaurant_id' => $restaurant->id,
            'phone' => '0909000004',
            'full_name' => 'Khách Hàng D',
            'loyalty_points' => 1000,
        ]);

        $reward = LoyaltyReward::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Ly trà đá miễn phí',
            'type' => 'free_product',
            'value' => 5000,
            'points_cost' => 100,
            'is_active' => true,
        ]);

        $response = $this->postJson(
            route('customer.portal.redeem', [$restaurant->id, $customer->phone]),
            ['reward_id' => $reward->id]
        );

        $response->assertForbidden();

        $customer->refresh();
        $this->assertEquals(1000, $customer->loyalty_points);
    }

    public function test_redeem_reward_succeeds_with_valid_token(): void
    {
        $restaurant = Restaurant::factory()->create();
        $customer = Customer::create([
            'restaurant_id' => $restaurant->id,
            'phone' => '0909000005',
            'full_name' => 'Khách Hàng E',
            'loyalty_points' => 1000,
        ]);

        $reward = LoyaltyReward::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Ly trà đá miễn phí',
            'type' => 'free_product',
            'value' => 5000,
            'points_cost' => 100,
            'is_active' => true,
        ]);

        $token = $this->tokenFor($restaurant->id, $customer->phone);

        $response = $this->postJson(
            route('customer.portal.redeem', [$restaurant->id, $customer->phone]) . '?token=' . $token,
            ['reward_id' => $reward->id]
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $customer->refresh();
        $this->assertEquals(900, $customer->loyalty_points);
    }
}
