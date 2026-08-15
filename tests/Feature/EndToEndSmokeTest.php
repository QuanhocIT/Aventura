<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EndToEndSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->owner->assignRole('owner');
    }

    public function test_liveness_and_readiness_endpoints_respond(): void
    {
        $liveness = $this->getJson(route('health'));
        $liveness->assertStatus(200);
        $liveness->assertJsonPath('status', 'ok');

        $readiness = $this->getJson(route('ready'));
        // Status might be 200 or 503 depending on local Redis/Meili, but JSON structure must exist
        $readiness->assertJsonStructure(['ready', 'checks']);
    }

    public function test_vietqr_payment_webhook_rejects_insufficient_amount(): void
    {
        Config::set('billing.webhook_secret', 'test_secret_123');

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'total_amount' => 500000,
            'payment_status' => 'pending',
        ]);

        $payload = [
            'description' => "Thanh toan don hang AVTORD{$order->id}",
            'amount' => 10000, // Transfers only 10,000 VND for 500,000 VND order
        ];

        $content = json_encode($payload);
        $signature = hash_hmac('sha256', $content, 'test_secret_123');

        $response = $this->call(
            'POST',
            route('api.webhooks.payments.vietqr'),
            [],
            [],
            [],
            [
                'HTTP_X-SePay-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $content
        );

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_vietqr_payment_webhook_processes_valid_payment(): void
    {
        Config::set('billing.webhook_secret', 'test_secret_123');

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'total_amount' => 150000,
            'payment_status' => 'pending',
            'created_by' => $this->owner->id,
        ]);

        $payload = [
            'description' => "Thanh toan don hang AVTORD{$order->id}",
            'amount' => 150000,
        ];

        $content = json_encode($payload);
        $signature = hash_hmac('sha256', $content, 'test_secret_123');

        $response = $this->call(
            'POST',
            route('api.webhooks.payments.vietqr'),
            [],
            [],
            [],
            [
                'HTTP_X-SePay-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $content
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }
}
