<?php

namespace Tests\Feature\Billing;

use App\Jobs\GenerateInvoiceDocuments;
use App\Jobs\SendBillingInvoiceEmail;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_webhook_renews_subscription_and_queues_invoice_jobs(): void
    {
        Queue::fake();
        config()->set('billing.webhook_secret', 'secret-key');

        $plan = SubscriptionPlan::factory()->create(['billing_cycle' => 'monthly', 'price' => 499000]);
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'plan_id' => $plan->id,
            'owner_user_id' => $owner->id,
            'status' => 'expired',
            'subscription_ends_at' => now()->subDay()->toDateString(),
        ]);
        $owner->update(['restaurant_id' => $restaurant->id]);

        $subscription = RestaurantSubscription::query()->create([
            'restaurant_id' => $restaurant->id,
            'plan_id' => $plan->id,
            'status' => 'expired',
            'started_at' => now()->subMonth(),
            'ended_at' => now()->subDay(),
            'renewal_at' => now()->subDay(),
            'price' => 499000,
            'transaction_code' => 'TXN-123',
        ]);

        $payload = ['transaction_code' => 'TXN-123', 'amount' => 499000, 'event_type' => 'payment.succeeded'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'secret-key');

        $response = $this->withHeaders(['X-Signature' => $signature])
            ->postJson(route('billing.webhook'), $payload);

        $response->assertOk();

        $restaurant->refresh();
        $subscription->refresh();

        $this->assertSame('active', $restaurant->status);
        $this->assertSame('active', $subscription->status);
        $this->assertNotNull($subscription->last_paid_at);

        Queue::assertPushed(GenerateInvoiceDocuments::class);
        Queue::assertPushed(SendBillingInvoiceEmail::class);
    }
}