<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\Payment;
use App\Events\OrderPaid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QrPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_vietqr_webhook_successfully_pays_unpaid_order()
    {
        Event::fake();

        // 1. Setup restaurant, branch, area, and owner user
        $restaurant = Restaurant::factory()->create();
        $branch = \App\Models\RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => null,
        ]);
        $area = \App\Models\Area::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // 2. Setup table
        $table = RestaurantTable::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'area_id' => $area->id,
            'name' => 'Bàn 5',
            'capacity' => 4,
            'status' => 'occupied',
            'qr_code' => 'QR-5',
            'qr_token' => 'token-5',
        ]);

        // 3. Setup unpaid order on that table
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'table_id' => $table->id,
            'created_by' => $user->id,
            'order_number' => 'ORD-TEST-QR',
            'channel' => 'qr',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 200000.0,
            'discount_amount' => 0.0,
            'total_amount' => 200000.0,
        ]);

        // 4. Send POST request to simulated webhook
        $payload = [
            'description' => "Chuyen khoan don hang AVTORD{$order->id} thanh cong",
            'amount' => 200000,
        ];

        $response = $this->postJson(route('api.webhooks.payments.vietqr'), $payload);

        // 5. Assertions
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('payment_status', 'paid');

        // Check database
        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertEquals('paid', $order->payment_status);

        // Check payment record
        $this->assertDatabaseHas('payments', [
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'payment_method' => 'bank_transfer',
            'amount' => 200000.0,
            'status' => 'paid',
        ]);

        // Check table released
        $table->refresh();
        $this->assertEquals('available', $table->status);

        // Check event dispatched
        Event::assertDispatched(OrderPaid::class, function ($event) use ($order, $restaurant, $table) {
            return $event->order->id === $order->id &&
                   $event->restaurantId === $restaurant->id &&
                   $event->tableId === $table->id;
        });
    }

    public function test_vietqr_webhook_fails_with_invalid_description()
    {
        $payload = [
            'description' => "Chuyen khoan khong co ma don hang",
            'amount' => 100000,
        ];

        $response = $this->postJson(route('api.webhooks.payments.vietqr'), $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }
}
