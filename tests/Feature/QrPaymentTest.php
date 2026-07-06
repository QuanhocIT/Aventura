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

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.webhook_secret', self::WEBHOOK_SECRET);
    }

    private function postVietQrWebhook(array $payload)
    {
        $signature = hash_hmac('sha256', json_encode($payload), self::WEBHOOK_SECRET);

        return $this->postJson(route('api.webhooks.payments.vietqr'), $payload, [
            'X-SePay-Signature' => $signature,
        ]);
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

        // 4. Send POST request to simulated webhook
        $payload = [
            'description' => "Chuyen khoan don hang AVTORD{$order->id} thanh cong",
            'amount' => 200000,
        ];

        $response = $this->postVietQrWebhook($payload);

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

    public function test_duplicate_webhook_delivery_does_not_double_process_payment()
    {
        Event::fake();

        $restaurant = Restaurant::factory()->create();
        $branch = \App\Models\RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => null,
        ]);
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'created_by' => $user->id,
            'order_number' => 'ORD-DUPLICATE-WEBHOOK',
            'channel' => 'qr',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 150000.0,
            'discount_amount' => 0.0,
            'total_amount' => 150000.0,
        ]);

        $payload = [
            'description' => "Chuyen khoan don hang AVTORD{$order->id} thanh cong",
            'amount' => 150000,
        ];

        // Cổng thanh toán thực tế thường retry webhook — gửi 2 lần liên tiếp cho cùng 1 giao dịch.
        $first = $this->postVietQrWebhook($payload);
        $second = $this->postVietQrWebhook($payload);

        $first->assertOk();
        $second->assertOk();

        $this->assertSame(1, Payment::where('order_id', $order->id)->count());

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_order_service_pay_order_is_idempotent_under_stale_reads()
    {
        // Mô phỏng đúng kịch bản race condition: 2 request đọc Order TRƯỚC khi request nào
        // kịp ghi 'paid' — cả 2 đều cầm bản Order "stale" với payment_status = unpaid, giống
        // hệt việc 2 webhook đến gần như cùng lúc rồi mới lần lượt gọi payOrder().
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'status' => 'active']);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'created_by' => $user->id,
            'order_number' => 'ORD-RACE-SERVICE',
            'channel' => 'qr',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 80000.0,
            'discount_amount' => 0.0,
            'total_amount' => 80000.0,
        ]);

        $staleCopyA = Order::find($order->id);
        $staleCopyB = Order::find($order->id);

        $paymentData = ['payment_method' => 'bank_transfer', 'cash_received' => 80000.0, 'change_amount' => 0];

        app(\App\Services\OrderService::class)->payOrder($staleCopyA, $paymentData, $user);
        app(\App\Services\OrderService::class)->payOrder($staleCopyB, $paymentData, $user);

        $this->assertSame(1, Payment::where('order_id', $order->id)->count());

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_vietqr_webhook_fails_with_invalid_description()
    {
        $payload = [
            'description' => "Chuyen khoan khong co ma don hang",
            'amount' => 100000,
        ];

        $response = $this->postVietQrWebhook($payload);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_vietqr_webhook_rejects_request_without_valid_signature(): void
    {
        $restaurant = Restaurant::factory()->create();
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-NOSIG',
            'channel' => 'qr',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'total_amount' => 50000.0,
        ]);

        // Không kèm header X-SePay-Signature — phải bị từ chối, không được phép giả mạo thanh toán.
        $response = $this->postJson(route('api.webhooks.payments.vietqr'), [
            'description' => "AVTORD{$order->id}",
        ]);

        $response->assertStatus(401);

        $order->refresh();
        $this->assertSame('unpaid', $order->payment_status);
    }
}
