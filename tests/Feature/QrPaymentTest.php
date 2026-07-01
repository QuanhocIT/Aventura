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

    private const TEST_SECRET = 'test-billing-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['billing.webhook_secret' => self::TEST_SECRET]);
    }

    /**
     * Sign and POST a payload to the real, signature-verified billing webhook
     * (the old unauthenticated /api/webhooks/payments/vietqr duplicate was retired —
     * see App\Http\Controllers\Billing\PaymentWebhookController).
     */
    private function postSignedWebhook(array $payload)
    {
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, self::TEST_SECRET);

        return $this->call('POST', route('billing.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-SePay-Signature' => $signature,
        ], $body);
    }

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

        // 4. Send a real, signed bank-transfer webhook payload
        $payload = [
            'content' => "Chuyen khoan don hang AVTORD{$order->id} thanh cong",
            'amount' => 200000,
        ];

        $response = $this->postSignedWebhook($payload);

        // 5. Assertions
        $response->assertOk();
        $response->assertJsonPath('success', true);

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

    public function test_webhook_rejects_invalid_signature()
    {
        $order = Order::factory()->create([
            'payment_status' => 'unpaid',
            'total_amount' => 100000,
        ]);

        $body = json_encode(['content' => "AVTORD{$order->id}"]);

        $response = $this->call('POST', route('billing.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-SePay-Signature' => 'not-the-real-signature',
        ], $body);

        $response->assertStatus(401);

        $order->refresh();
        $this->assertEquals('unpaid', $order->payment_status);
    }

    public function test_webhook_falls_through_to_billing_handler_for_non_order_content()
    {
        // Content without an AVTORD reference isn't an order payment — the webhook
        // should fall through to BillingService's subscription-webhook handling instead
        // of erroring, per PaymentWebhookController::tryHandleOrderPayment() returning
        // null when no AVTORD pattern is found.
        $response = $this->postSignedWebhook([
            'content' => 'Chuyen khoan khong co ma don hang',
            'amount' => 100000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Transaction code not found');
    }
}
