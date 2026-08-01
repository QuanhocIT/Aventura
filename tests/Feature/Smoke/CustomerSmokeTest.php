<?php

namespace Tests\Feature\Smoke;

use App\Http\Middleware\SecurityFirewallMiddleware;
use App\Http\Middleware\TenantRateLimit;
use App\Models\Customer;
use App\Models\NewsPost;
use App\Models\OnlineStoreConfig;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Smoke-tests the CUSTOMER-FACING surface (public storefront, QR ordering,
 * portal, wallet, tracking, reservations, feedback, news, billing pay page)
 * against the aventura_test MySQL clone. Complements RouteSmokeTest, which
 * skips these routes because their params (slug/token/phone/code) need real
 * rows. All writes are rolled back via DatabaseTransactions.
 *
 *   php artisan test tests/Feature/Smoke/CustomerSmokeTest.php -c phpunit.mysql.xml
 */
class CustomerSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    private int $rid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            SecurityFirewallMiddleware::class,
            TenantRateLimit::class,
        ]);

        $this->owner = User::whereHas('roles', fn ($q) => $q->where('name', 'owner'))
            ->whereNotNull('restaurant_id')->orderBy('id')->firstOrFail();
        $this->rid = (int) $this->owner->restaurant_id;
    }

    // ── Public pages ────────────────────────────────────────────────────────

    public function test_public_pages_do_not_500(): void
    {
        foreach (['/', '/tin-tuc', '/feedback/new', '/status', '/api/status-data', '/api/health'] as $uri) {
            $res = $this->get($uri);
            $this->assertLessThan(500, $res->getStatusCode(), "$uri 5xx: ".optional($res->exception)->getMessage());
        }
    }

    public function test_news_detail_page_renders(): void
    {
        $post = NewsPost::create([
            'author_id' => $this->owner->id,
            'title' => 'Bài viết smoke test',
            'slug' => 'bai-viet-smoke-'.uniqid(),
            'excerpt' => 'Tóm tắt',
            'content' => 'Nội dung kiểm thử.',
            'category' => 'news',
            'is_published' => true,
            'published_at' => now()->subHour(),
        ]);

        $this->get("/tin-tuc/{$post->slug}")->assertOk();
    }

    // ── Online storefront: browse → checkout → track ───────────────────────

    public function test_storefront_browse_checkout_and_track(): void
    {
        $slug = 'smoke-store-'.uniqid();
        // updateOrCreate theo restaurant_id: tenant có thể đã có Online Store (backfill/
        // onboarding) → tránh vi phạm unique(restaurant_id).
        OnlineStoreConfig::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => $this->rid],
            [
                'is_active' => true,
                'slug' => $slug,
                'min_order_amount' => 0,
                'delivery_base_fee' => 0,
                'delivery_fee_per_km' => 0,
                'max_delivery_km' => 10,
                'enable_takeaway' => true,
                'enable_delivery' => true,
                'enable_preorder' => false,
            ]
        );

        $this->get("/order/{$slug}")->assertOk();
        $this->get("/api/online/{$slug}/menu")->assertOk();

        // Prefer a product without inventory tracking for a deterministic happy path.
        $product = Product::where('restaurant_id', $this->rid)
            ->where('is_active', true)->where('is_available', true)
            ->orderBy('track_inventory')->orderBy('id')
            ->firstOrFail();

        $checkout = $this->postJson("/api/online/{$slug}/checkout", [
            'customer_name' => 'Khách Smoke',
            'phone' => '0900000001',
            'channel' => 'takeaway',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'bank_transfer',
        ]);

        $this->assertLessThan(500, $checkout->getStatusCode(),
            'Checkout 5xx: '.optional($checkout->exception)->getMessage());
        $checkout->assertOk();

        $orderNumber = $checkout->json('order_number') ?? $checkout->json('order.order_number');
        $this->assertNotEmpty($orderNumber, 'Checkout response missing order_number: '.$checkout->getContent());

        $this->get("/order/track/{$orderNumber}")->assertOk();
        $this->getJson("/api/online/order/{$orderNumber}/status")->assertOk();
    }

    // ── QR at-table ordering ────────────────────────────────────────────────

    public function test_qr_ordering_flow(): void
    {
        $table = RestaurantTable::where('restaurant_id', $this->rid)
            ->whereNotNull('qr_token')->orderBy('id')->firstOrFail();

        $this->get("/customer/order/{$this->rid}/{$table->qr_token}")->assertOk();

        $product = Product::where('restaurant_id', $this->rid)
            ->where('is_active', true)->where('is_available', true)
            ->orderBy('track_inventory')->orderBy('id')
            ->firstOrFail();

        $submit = $this->postJson("/customer/order/{$this->rid}/{$table->qr_token}", [
            'customer_name' => 'Khách QR',
            'customer_phone' => '0900000002',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $this->assertLessThan(500, $submit->getStatusCode(),
            'QR submit 5xx: '.optional($submit->exception)->getMessage());
        $this->assertContains($submit->getStatusCode(), [200, 201],
            'QR submit unexpected: '.$submit->getContent());

        $this->postJson("/customer/order/call-staff/{$this->rid}", [
            'table_id' => $table->id,
            'message' => 'Smoke test gọi nhân viên',
        ])->assertOk();

        $payReq = $this->postJson("/customer/order/payment-request/{$this->rid}", [
            'table_id' => $table->id,
        ]);
        $this->assertLessThan(500, $payReq->getStatusCode(),
            'Payment request 5xx: '.optional($payReq->exception)->getMessage());

        $feedback = $this->postJson("/customer/order/feedback/{$this->rid}", [
            'table_id' => $table->id,
            'rating' => 5,
            'content' => 'Rất ngon (smoke test)',
            'is_anonymous' => false,
            'submitted_by_name' => 'Khách QR',
        ]);
        $this->assertLessThan(500, $feedback->getStatusCode(),
            'QR feedback 5xx: '.optional($feedback->exception)->getMessage());
    }

    // ── Customer portal + coupon wallet (signed-token protected) ───────────

    public function test_portal_dashboard_requires_valid_token(): void
    {
        $phone = Customer::withoutGlobalScopes()
            ->where('restaurant_id', $this->rid)
            ->where('phone', '<>', '')->whereNotNull('phone')
            ->orderBy('id')->value('phone');
        $this->assertNotNull($phone, 'No customer with phone for portal test');

        $valid = hash('sha256', $this->rid.$phone.config('app.key'));

        $this->get("/customer/portal/dashboard/{$this->rid}/{$phone}?token={$valid}")->assertOk();
        $this->get("/customer/portal/dashboard/{$this->rid}/{$phone}?token=sai-token")->assertForbidden();
        $this->get("/customer/portal/dashboard/{$this->rid}/{$phone}")->assertForbidden();

        $wallet = $this->get("/customer/coupons/{$this->rid}/{$phone}?token={$valid}");
        $this->assertLessThan(500, $wallet->getStatusCode(),
            'Wallet 5xx: '.optional($wallet->exception)->getMessage());
        $this->get("/customer/coupons/{$this->rid}/{$phone}?token=sai-token")->assertForbidden();
    }

    public function test_public_token_endpoint_removed_and_magic_link_works(): void
    {
        // The old vulnerable endpoint that handed out portal tokens must be gone.
        $this->get("/api/customer/portal-token/{$this->rid}/0900000009")->assertNotFound();

        // Magic-link request always returns a generic 200 (no phone enumeration).
        $this->postJson("/customer/portal/request-link/{$this->rid}", ['phone' => '0900000009'])
            ->assertOk();
    }

    // ── Public reservation ──────────────────────────────────────────────────

    public function test_public_reservation_booking(): void
    {
        $res = $this->post("/r/{$this->rid}/reservations", [
            'guest_name' => 'Khách Đặt Bàn',
            'guest_phone' => '0900000003',
            'reservation_date' => now()->addDay()->toDateString(),
            'reservation_time' => '18:30',
            'party_size' => 4,
            'source' => 'website',
        ]);

        $this->assertLessThan(500, $res->getStatusCode(),
            'Reservation 5xx: '.optional($res->exception)->getMessage());
        $this->assertTrue(
            DB::table('table_reservations')
                ->where('restaurant_id', $this->rid)
                ->where('guest_phone', '0900000003')
                ->exists(),
            'Reservation row not created. Response: '.$res->getStatusCode()
        );
    }

    // ── Tenant billing pay page (SePay VietQR) ──────────────────────────────

    public function test_billing_pay_page_for_pending_subscription(): void
    {
        $sub = DB::table('restaurant_subscriptions')
            ->whereNotNull('transaction_code')->where('transaction_code', '<>', '')
            ->orderByDesc('id')->first();

        if (! $sub) {
            $this->markTestSkipped('No subscription with transaction_code in clone');
        }

        $payer = User::whereHas('roles', fn ($q) => $q->where('name', 'owner'))
            ->where('restaurant_id', $sub->restaurant_id)->first();
        if (! $payer) {
            $this->markTestSkipped("No owner user for restaurant {$sub->restaurant_id}");
        }

        $page = $this->actingAs($payer)->get("/billing/pay/{$sub->transaction_code}");
        $this->assertLessThan(500, $page->getStatusCode(),
            'Pay page 5xx: '.optional($page->exception)->getMessage());

        $check = $this->actingAs($payer)->get("/api/billing/check/{$sub->transaction_code}");
        $this->assertLessThan(500, $check->getStatusCode(),
            'Billing check 5xx: '.optional($check->exception)->getMessage());
    }
}
