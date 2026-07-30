<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PlatformOrder;
use App\Models\PosDevice;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantIntegration;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\DeliveryPlatforms\DeliveryPlatformService;
use App\Services\Integrations\MisaExportService;
use App\Services\Integrations\WebhookDispatchService;
use App\Services\Printing\EscPosBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationHubTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restaurant = Restaurant::factory()->create();
    }

    // ─────────── ESC/POS ───────────

    public function test_escpos_builder_generates_valid_commands(): void
    {
        $bytes = EscPosBuilder::for58mm()
            ->initialize()
            ->align('center')
            ->bold()
            ->text('HÓA ĐƠN')
            ->bold(false)
            ->row('Phở bò', '50,000d')
            ->cut()
            ->getBytes();

        $this->assertStringStartsWith("\x1b@", $bytes);          // init
        $this->assertStringContainsString("\x1ba\x01", $bytes);  // align center
        $this->assertStringContainsString('HOA DON', $bytes);    // dấu đã được strip
        $this->assertStringContainsString('Pho bo', $bytes);
        $this->assertStringContainsString("\x1dV", $bytes);      // cut
    }

    public function test_escpos_row_fits_paper_width(): void
    {
        $builder = EscPosBuilder::for58mm();
        $builder->row('Món có tên rất dài vượt quá khổ giấy in nhiệt 58mm', '1,000,000d');

        $lines = explode("\n", $builder->getBytes());
        $this->assertSame(32, strlen($lines[0]));
    }

    // ─────────── Webhook API developer ───────────

    public function test_webhook_signature_format(): void
    {
        $signature = WebhookDispatchService::sign('whsec_test', '{"a":1}', 1700000000);

        $expected = hash_hmac('sha256', '1700000000.{"a":1}', 'whsec_test');
        $this->assertSame("t=1700000000,v1={$expected}", $signature);
    }

    public function test_webhook_dispatch_sends_signed_request(): void
    {
        Http::fake(['example.test/*' => Http::response(['ok' => true], 200)]);

        $endpoint = WebhookEndpoint::create([
            'restaurant_id' => $this->restaurant->id,
            'url' => 'https://example.test/hooks',
            'secret' => 'whsec_abc',
            'events' => ['order.paid'],
            'is_active' => true,
        ]);

        app(WebhookDispatchService::class)->dispatch($this->restaurant->id, 'order.paid', [
            'order_number' => 'TEST123',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.test/hooks'
                && $request->hasHeader('X-Aventura-Event', 'order.paid')
                && str_starts_with($request->header('X-Aventura-Signature')[0] ?? '', 't=');
        });

        $delivery = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->first();
        $this->assertNotNull($delivery);
        $this->assertSame('success', $delivery->status);
        $this->assertSame(200, $delivery->response_code);
    }

    public function test_webhook_not_sent_for_unsubscribed_event(): void
    {
        Http::fake();

        WebhookEndpoint::create([
            'restaurant_id' => $this->restaurant->id,
            'url' => 'https://example.test/hooks',
            'secret' => 'whsec_abc',
            'events' => ['reservation.created'],
            'is_active' => true,
        ]);

        app(WebhookDispatchService::class)->dispatch($this->restaurant->id, 'order.paid', []);

        Http::assertNothingSent();
        $this->assertSame(0, WebhookDelivery::count());
    }

    // ─────────── Grab Food / ShopeeFood ───────────

    public function test_grabfood_order_ingestion_creates_order(): void
    {
        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Phở bò tái',
            'price' => 65000,
            'is_active' => true,
        ]);

        $service = app(DeliveryPlatformService::class);

        $platformOrder = $service->ingestOrder($this->restaurant->id, 'grabfood', [
            'orderID' => 'GF-001',
            'eater' => ['name' => 'Nguyễn Văn A', 'mobileNumber' => '0912345678'],
            'items' => [
                ['name' => 'Phở bò tái', 'quantity' => 2, 'price' => 65000],
            ],
            'orderTotal' => 130000,
        ]);

        $this->assertSame('GF-001', $platformOrder->platform_order_id);
        $this->assertNotNull($platformOrder->order_id);

        $order = Order::withoutGlobalScopes()->find($platformOrder->order_id);
        $this->assertSame('delivery', $order->channel);
        $this->assertEquals(130000, (float) $order->total_amount);
        $this->assertSame(1, $order->items()->count());
        $this->assertEquals($product->id, $order->items()->first()->product_id);
    }

    public function test_platform_order_ingestion_is_idempotent(): void
    {
        Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Cơm gà', 'price' => 45000, 'is_active' => true,
        ]);

        $payload = [
            'orderID' => 'GF-DUP',
            'eater' => ['name' => 'Khách', 'mobileNumber' => '0900000001'],
            'items' => [['name' => 'Cơm gà', 'quantity' => 1, 'price' => 45000]],
            'orderTotal' => 45000,
        ];

        $service = app(DeliveryPlatformService::class);
        $first = $service->ingestOrder($this->restaurant->id, 'grabfood', $payload);
        $second = $service->ingestOrder($this->restaurant->id, 'grabfood', $payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PlatformOrder::withoutGlobalScopes()->count());
        $this->assertSame(1, Order::withoutGlobalScopes()->count());
    }

    public function test_delivery_webhook_rejects_invalid_signature(): void
    {
        RestaurantIntegration::create([
            'restaurant_id' => $this->restaurant->id,
            'provider' => 'grabfood',
            'is_enabled' => true,
            'credentials' => ['webhook_secret' => 'secret123'],
        ]);

        $response = $this->postJson(
            "/api/webhooks/delivery/grabfood/{$this->restaurant->id}",
            ['orderID' => 'GF-X'],
            ['X-Grab-Signature' => 'sai-chu-ky']
        );

        $response->assertStatus(401);
    }

    public function test_delivery_webhook_accepts_valid_signature(): void
    {
        Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Bún chả', 'price' => 55000, 'is_active' => true,
        ]);

        RestaurantIntegration::create([
            'restaurant_id' => $this->restaurant->id,
            'provider' => 'grabfood',
            'is_enabled' => true,
            'credentials' => ['webhook_secret' => 'secret123'],
        ]);

        $payload = [
            'orderID' => 'GF-SIGNED',
            'eater' => ['name' => 'Khách', 'mobileNumber' => '0900000002'],
            'items' => [['name' => 'Bún chả', 'quantity' => 1, 'price' => 55000]],
            'orderTotal' => 55000,
        ];

        $body = json_encode($payload);
        $signature = base64_encode(hash_hmac('sha256', $body, 'secret123', true));

        $response = $this->call(
            'POST',
            "/api/webhooks/delivery/grabfood/{$this->restaurant->id}",
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Grab-Signature' => $signature,
                'HTTP_ACCEPT' => 'application/json',
            ],
            $body
        );

        $response->assertOk();
        $this->assertSame(1, PlatformOrder::withoutGlobalScopes()->where('platform_order_id', 'GF-SIGNED')->count());
    }

    public function test_delivery_webhook_requires_enabled_integration(): void
    {
        $response = $this->postJson("/api/webhooks/delivery/grabfood/{$this->restaurant->id}", []);

        $response->assertStatus(403);
    }

    // ─────────── POS pairing ───────────

    public function test_pos_device_pairing_flow(): void
    {
        $device = PosDevice::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'POS quầy 1',
            'type' => 'pos',
            'status' => 'pending',
            'pairing_code' => 'ABC123',
        ]);

        $response = $this->postJson('/api/pos/pair', ['pairing_code' => 'abc123']);

        $response->assertOk()->assertJsonStructure(['device_token']);

        $device->refresh();
        $this->assertSame('active', $device->status);
        $this->assertNull($device->pairing_code);

        // Heartbeat với token vừa nhận
        $token = $response->json('device_token');
        $this->postJson('/api/pos/heartbeat', ['app_version' => '1.0.0'], ['X-Device-Token' => $token])
            ->assertOk();

        $this->assertTrue($device->fresh()->isOnline());
    }

    public function test_pos_pairing_rejects_invalid_code(): void
    {
        $this->postJson('/api/pos/pair', ['pairing_code' => 'XXXXXX'])->assertStatus(422);
    }

    // ─────────── MISA export ───────────

    public function test_misa_csv_contains_paid_orders(): void
    {
        Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => 'MISA01',
            'channel' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 200000,
            'discount_amount' => 0,
            'total_amount' => 200000,
        ]);

        $csv = app(MisaExportService::class)->buildCsv(
            $this->restaurant->id,
            now()->format('Y-m-d'),
            now()->format('Y-m-d')
        );

        $this->assertStringContainsString('Ngày hạch toán', $csv);
        $this->assertStringContainsString('BH-MISA01', $csv);
        $this->assertStringContainsString('200000', $csv);
        $this->assertStringContainsString('Khách lẻ', $csv);
    }

    // ─────────── Trang settings ───────────

    public function test_integrations_page_renders(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/settings/integrations')
            ->assertOk();
    }

    public function test_integration_credentials_are_encrypted_and_hidden(): void
    {
        $integration = RestaurantIntegration::create([
            'restaurant_id' => $this->restaurant->id,
            'provider' => 'zalo_oa',
            'credentials' => ['access_token' => 'super-secret'],
        ]);

        // Không lộ trong serialize
        $this->assertArrayNotHasKey('credentials', $integration->toArray());

        // Lưu DB dạng mã hóa, không phải plaintext
        $raw = \Illuminate\Support\Facades\DB::table('restaurant_integrations')
            ->where('id', $integration->id)
            ->value('credentials');
        $this->assertStringNotContainsString('super-secret', (string) $raw);

        $this->assertSame('super-secret', $integration->fresh()->credential('access_token'));
    }
}
