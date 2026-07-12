<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationDepositTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create([
            'reservation_deposit_amount' => 200000,
        ]);

        RestaurantTable::factory()->create(['restaurant_id' => $this->restaurant->id]);

        // Cấu hình VNPay giả để cổng thanh toán khả dụng trong test
        config([
            'services.vnpay.tmncode' => 'TESTCODE',
            'services.vnpay.hashsecret' => 'test-secret',
        ]);
    }

    private function reservationPayload(): array
    {
        return [
            'guest_name' => 'Nguyễn Văn Cọc',
            'guest_phone' => '0911222333',
            'reservation_date' => now()->addDay()->toDateString(),
            'reservation_time' => '19:00',
            'party_size' => 4,
        ];
    }

    public function test_public_reservation_creates_deposit_order_and_payment_url(): void
    {
        $response = $this->postJson(
            "/r/{$this->restaurant->id}/reservations",
            $this->reservationPayload()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('deposit.amount', 200000);

        // Cổng cụ thể tùy cấu hình (bank_transfer/vnpay...) — chỉ cần có link thanh toán
        $this->assertNotEmpty($response->json('deposit.gateway'));
        $this->assertNotEmpty($response->json('deposit.payment_url'));

        $reservation = TableReservation::withoutGlobalScopes()->first();
        $this->assertSame('pending', $reservation->deposit_status);
        $this->assertEquals(200000, (float) $reservation->deposit_amount);
        $this->assertNotNull($reservation->deposit_order_id);

        $order = Order::withoutGlobalScopes()->find($reservation->deposit_order_id);
        $this->assertSame('reservation_deposit', $order->channel);
        $this->assertEquals(200000, (float) $order->total_amount);
    }

    public function test_paying_deposit_confirms_reservation_automatically(): void
    {
        $this->postJson("/r/{$this->restaurant->id}/reservations", $this->reservationPayload())->assertOk();

        $reservation = TableReservation::withoutGlobalScopes()->first();
        $order = Order::withoutGlobalScopes()->find($reservation->deposit_order_id);

        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);

        // Mô phỏng webhook cổng thanh toán xác nhận cọc đã trả
        app(OrderService::class)->payOrder(
            $order,
            ['payment_method' => 'vnpay', 'cash_received' => $order->total_amount, 'change_amount' => 0],
            $user,
            true
        );

        $reservation->refresh();
        $this->assertSame('paid', $reservation->deposit_status);
        $this->assertSame('confirmed', $reservation->status);
        $this->assertNotNull($reservation->confirmed_at);
    }

    public function test_reservation_without_deposit_config_skips_deposit(): void
    {
        $this->restaurant->update(['reservation_deposit_amount' => 0]);

        $response = $this->postJson(
            "/r/{$this->restaurant->id}/reservations",
            $this->reservationPayload()
        );

        $response->assertOk()->assertJsonPath('deposit', null);

        $reservation = TableReservation::withoutGlobalScopes()->first();
        $this->assertSame('none', $reservation->deposit_status);
        $this->assertNull($reservation->deposit_order_id);
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }
}
