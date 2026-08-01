<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\EInvoiceService;
use App\Services\OrderSplitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3FeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private User $user;

    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create([
            'name' => 'Nhà hàng Test',
            'tax_code' => '0101234567',
        ]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'PHASE3-A',
        ]);

        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function makeOrderWithItems(array $prices, string $paymentStatus = 'unpaid'): Order
    {
        $subtotal = array_sum($prices);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'P3-'.strtoupper(uniqid()),
            'channel' => 'dine_in',
            'status' => $paymentStatus === 'paid' ? 'completed' : 'pending',
            'payment_status' => $paymentStatus,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'total_amount' => $subtotal,
            'completed_at' => $paymentStatus === 'paid' ? now() : null,
        ]);

        foreach ($prices as $price) {
            $product = Product::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'price' => $price,
            ]);

            OrderItem::create([
                'restaurant_id' => $this->restaurant->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $price,
                'line_total' => $price,
                'status' => 'pending',
            ]);
        }

        return $order;
    }

    // ─────────── Hóa đơn điện tử TT78 ───────────

    public function test_einvoice_xml_contains_required_tt78_fields(): void
    {
        $order = $this->makeOrderWithItems([108000], 'paid');

        $xml = app(EInvoiceService::class)->buildXml($order);

        $this->assertStringContainsString('<HDon>', $xml);
        $this->assertStringContainsString('<MST>0101234567</MST>', $xml);
        $this->assertStringContainsString('Nhà hàng Test', $xml);
        $this->assertStringContainsString('<TSuat>8%</TSuat>', $xml);
        // 108.000đ gồm VAT 8% → 100.000 tiền hàng + 8.000 thuế
        $this->assertStringContainsString('<TgTCThue>100000</TgTCThue>', $xml);
        $this->assertStringContainsString('<TgTThue>8000</TgTThue>', $xml);
        $this->assertStringContainsString('<TgTTTBSo>108000</TgTTTBSo>', $xml);
    }

    public function test_einvoice_amount_in_words(): void
    {
        $service = app(EInvoiceService::class);

        $this->assertSame('Một trăm lẻ tám nghìn đồng', $service->amountInWords(108000));
        $this->assertSame('Hai triệu năm trăm nghìn đồng', $service->amountInWords(2500000));
        $this->assertSame('Mười lăm nghìn đồng', $service->amountInWords(15000));
    }

    public function test_einvoice_download_requires_paid_order(): void
    {
        $unpaid = $this->makeOrderWithItems([50000]);

        $this->actingAs($this->user)
            ->get("/orders/{$unpaid->id}/e-invoice.xml")
            ->assertStatus(422);

        $paid = $this->makeOrderWithItems([50000], 'paid');

        $this->actingAs($this->user)
            ->get("/orders/{$paid->id}/e-invoice.xml")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    // ─────────── API công khai v1 ───────────

    public function test_public_api_rejects_missing_or_invalid_key(): void
    {
        $this->getJson('/api/v1/products')->assertStatus(401);
        $this->getJson('/api/v1/products', ['X-Api-Key' => 'avk_sai-key'])->assertStatus(401);
    }

    public function test_public_api_returns_products_with_valid_key(): void
    {
        Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Phở API',
            'price' => 65000,
            'is_active' => true,
        ]);

        [$plainKey] = ApiKey::generate($this->restaurant->id, 'Test key');

        $response = $this->getJson('/api/v1/products', ['X-Api-Key' => $plainKey]);

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Phở API')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_revoked_api_key_is_rejected(): void
    {
        [$plainKey, $key] = ApiKey::generate($this->restaurant->id, 'Sắp thu hồi');
        $key->update(['is_active' => false]);

        $this->getJson('/api/v1/orders', ['X-Api-Key' => $plainKey])->assertStatus(401);
    }

    // ─────────── Tách bill / gộp đơn / chuyển bàn ───────────

    public function test_split_order_moves_items_and_recalculates_totals(): void
    {
        $order = $this->makeOrderWithItems([100000, 60000, 40000]);
        $itemToSplit = $order->items()->first();

        $newOrder = app(OrderSplitService::class)->splitOrder($order, [$itemToSplit->id], $this->user);

        $order->refresh();
        $this->assertEquals(100000, (float) $order->subtotal);
        $this->assertEquals(100000, (float) $newOrder->subtotal);
        $this->assertSame(2, $order->items()->count());
        $this->assertSame(1, $newOrder->items()->count());
        $this->assertStringContainsString($order->order_number, $newOrder->order_number);
    }

    public function test_cannot_split_all_items_or_paid_order(): void
    {
        $order = $this->makeOrderWithItems([50000]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(OrderSplitService::class)->splitOrder($order, [$order->items()->first()->id], $this->user);
    }

    public function test_merge_orders_combines_items_and_cancels_source(): void
    {
        $source = $this->makeOrderWithItems([70000]);
        $target = $this->makeOrderWithItems([30000]);

        app(OrderSplitService::class)->mergeOrders($source, $target, $this->user);

        $target->refresh();
        $source->refresh();

        $this->assertEquals(100000, (float) $target->subtotal);
        $this->assertSame(2, $target->items()->count());
        $this->assertSame('cancelled', $source->status);
        $this->assertSame(0, $source->items()->count());
    }

    public function test_move_table_updates_statuses(): void
    {
        $oldTable = RestaurantTable::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'occupied']);
        $newTable = RestaurantTable::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'available']);

        $order = $this->makeOrderWithItems([50000, 20000]);
        $order->update(['table_id' => $oldTable->id]);

        app(OrderSplitService::class)->moveTable($order, $newTable->id, $this->user);

        $this->assertEquals($newTable->id, $order->fresh()->table_id);
        $this->assertSame('occupied', $newTable->fresh()->status);
        $this->assertSame('available', $oldTable->fresh()->status);
    }

    public function test_cannot_move_to_occupied_table(): void
    {
        $busy = RestaurantTable::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'occupied']);
        $order = $this->makeOrderWithItems([50000, 20000]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(OrderSplitService::class)->moveTable($order, $busy->id, $this->user);
    }
}
