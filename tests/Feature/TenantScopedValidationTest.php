<?php

namespace Tests\Feature;

use App\Http\Controllers\Customer\CustomerLoyaltyClaimController;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Support\TenantRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 'exists:table,id' toàn cục cho phép ID của nhà hàng KHÁC vượt qua validation
 * — TenantRule::exists() phải chặn đúng những ID đó.
 */
class TenantScopedValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_rule_rejects_ids_belonging_to_another_restaurant(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();

        $productA = Product::factory()->create(['restaurant_id' => $restaurantA->id]);
        $productB = Product::factory()->create(['restaurant_id' => $restaurantB->id]);

        $rule = TenantRule::exists('products', restaurantId: $restaurantA->id);

        $this->assertFalse(
            Validator::make(['product_id' => $productA->id], ['product_id' => $rule])->fails(),
            'ID thuộc đúng nhà hàng phải hợp lệ'
        );

        $this->assertTrue(
            Validator::make(['product_id' => $productB->id], ['product_id' => $rule])->fails(),
            'ID của nhà hàng khác phải bị từ chối dù tồn tại trong bảng'
        );
    }

    public function test_tenant_rule_defaults_to_authenticated_user_restaurant(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();
        $staff = User::factory()->create(['restaurant_id' => $restaurantA->id, 'status' => 'active']);
        $productB = Product::factory()->create(['restaurant_id' => $restaurantB->id]);

        Auth::login($staff);

        $this->assertTrue(
            Validator::make(['product_id' => $productB->id], ['product_id' => TenantRule::exists('products')])->fails(),
            'Không truyền restaurantId thì phải tự lấy từ user đang đăng nhập'
        );
    }

    public function test_qr_guest_cannot_reference_table_of_another_restaurant(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();

        $branchB = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurantB->id,
            'manager_user_id' => null,
        ]);
        $areaB = Area::factory()->create([
            'restaurant_id' => $restaurantB->id,
            'branch_id' => $branchB->id,
        ]);

        $foreignTable = RestaurantTable::create([
            'restaurant_id' => $restaurantB->id,
            'branch_id' => $branchB->id,
            'area_id' => $areaB->id,
            'name' => 'Bàn nhà hàng B',
            'capacity' => 4,
            'status' => 'occupied',
            'qr_code' => 'QR-B1',
            'qr_token' => 'token-b1',
        ]);

        // Gọi nhân viên của nhà hàng A nhưng trỏ table_id sang nhà hàng B
        $response = $this->postJson(
            route('customer.qr-order.call-staff', $restaurantA->id),
            ['table_id' => $foreignTable->id]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_id']);
    }

    // ─────────── Luồng claim điểm loyalty ───────────

    private function makePaidOrder(Restaurant $restaurant): Order
    {
        return Order::create([
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-CLAIM-'.uniqid(),
            'channel' => 'qr',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 500000.0,
            'discount_amount' => 0.0,
            'total_amount' => 500000.0,
        ]);
    }

    public function test_claim_points_requires_valid_token(): void
    {
        $restaurant = Restaurant::factory()->create();
        $order = $this->makePaidOrder($restaurant);

        // Không có token → validation 422
        $this->postJson(route('customer.loyalty.claim-points'), [
            'order_id' => $order->id,
            'phone' => '0912345678',
        ])->assertStatus(422);

        // Token sai (kẻ tấn công quét order_id tuần tự) → 403
        $this->postJson(route('customer.loyalty.claim-points'), [
            'order_id' => $order->id,
            'claim_token' => str_repeat('a', 64),
            'phone' => '0912345678',
        ])->assertStatus(403);

        $this->assertSame(0, Customer::withoutGlobalScopes()->count());
    }

    public function test_claim_points_succeeds_once_and_blocks_double_claim(): void
    {
        $restaurant = Restaurant::factory()->create();
        $order = $this->makePaidOrder($restaurant);

        // Màn hình order-summary phát token hợp lệ
        $summary = $this->getJson(route('customer.loyalty.order-summary', $order->id));
        $summary->assertOk();
        $token = $summary->json('claim_token');
        $this->assertNotEmpty($token);

        $first = $this->postJson(route('customer.loyalty.claim-points'), [
            'order_id' => $order->id,
            'claim_token' => $token,
            'phone' => '0912345678',
            'full_name' => 'Nguyễn Văn Claim',
        ]);

        $first->assertOk();
        $first->assertJsonPath('success', true);

        $order->refresh();
        $this->assertNotNull($order->customer_id);

        $pointsAfterFirst = (int) Customer::withoutGlobalScopes()->find($order->customer_id)->loyalty_points;
        $this->assertGreaterThan(0, $pointsAfterFirst);

        // Claim lần 2 (kể cả số điện thoại khác) phải bị chặn — không cộng điểm lặp
        $second = $this->postJson(route('customer.loyalty.claim-points'), [
            'order_id' => $order->id,
            'claim_token' => $token,
            'phone' => '0987654321',
        ]);

        $second->assertStatus(422);
        $this->assertSame(
            $pointsAfterFirst,
            (int) Customer::withoutGlobalScopes()->find($order->customer_id)->loyalty_points
        );
    }

    public function test_order_summary_hidden_for_unpaid_orders(): void
    {
        $restaurant = Restaurant::factory()->create();
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-UNPAID-SUM',
            'channel' => 'qr',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 100000.0,
            'total_amount' => 100000.0,
        ]);

        $this->getJson(route('customer.loyalty.order-summary', $order->id))->assertStatus(404);

        // Đơn chưa thanh toán cũng không được claim kể cả có token đúng
        $this->postJson(route('customer.loyalty.claim-points'), [
            'order_id' => $order->id,
            'claim_token' => CustomerLoyaltyClaimController::claimToken($order),
            'phone' => '0912345678',
        ])->assertStatus(422);
    }
}
