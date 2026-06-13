<?php

namespace Tests\Feature\Billing;

use App\Jobs\GenerateInvoiceDocuments;
use App\Jobs\SendBillingInvoiceEmail;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\SubscriptionExpiryReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BillingConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_statuses_updates_restaurants_and_subscriptions_consistently(): void
    {
        config()->set('billing.grace_period_days', 30);

        $plan = SubscriptionPlan::factory()->create();
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $expiredRestaurant = Restaurant::factory()->create([
            'plan_id' => $plan->id,
            'owner_user_id' => $ownerA->id,
            'status' => 'active',
            'subscription_ends_at' => now()->subDay(),
        ]);

        $suspendedRestaurant = Restaurant::factory()->create([
            'plan_id' => $plan->id,
            'owner_user_id' => $ownerB->id,
            'status' => 'expired',
            'subscription_ends_at' => now()->subDays(45),
        ]);

        $expiredSubscription = RestaurantSubscription::query()->create([
            'restaurant_id' => $expiredRestaurant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now()->subMonths(2),
            'ended_at' => now()->subDay(),
            'renewal_at' => now()->subDay(),
            'grace_ends_at' => now()->addDays(29),
            'price' => 499000,
        ]);

        $pastDueSubscription = RestaurantSubscription::query()->create([
            'restaurant_id' => $suspendedRestaurant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now()->subMonths(3),
            'ended_at' => now()->subDays(45),
            'renewal_at' => now()->subDays(45),
            'grace_ends_at' => now()->subDay(),
            'price' => 499000,
        ]);

        Artisan::call('billing:sync-statuses');

        $this->assertSame('expired', $expiredRestaurant->fresh()->status);
        $this->assertSame('expired', $expiredSubscription->fresh()->status);
        $this->assertSame('suspended', $suspendedRestaurant->fresh()->status);
        $this->assertSame('expired', $pastDueSubscription->fresh()->status);
    }

    public function test_send_reminders_queues_invoice_jobs_and_blocks_duplicate_notifications_same_day(): void
    {
        Queue::fake();
        Notification::fake();
        config()->set('billing.upcoming_due_days', 7);

        $plan = SubscriptionPlan::factory()->create(['price' => 499000]);
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'plan_id' => $plan->id,
            'owner_user_id' => $owner->id,
            'status' => 'active',
            'subscription_ends_at' => now()->addDays(3),
        ]);
        $owner->update(['restaurant_id' => $restaurant->id]);
        $owner = $owner->fresh();

        $subscription = RestaurantSubscription::query()->create([
            'restaurant_id' => $restaurant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now()->subMonth(),
            'ended_at' => now()->addDays(3),
            'renewal_at' => now()->addDays(3),
            'grace_ends_at' => now()->addDays(33),
            'price' => 499000,
        ]);

        Artisan::call('billing:send-reminders');

        $subscription->refresh();

        Notification::assertSentTo($owner, \App\Notifications\DunningNotification::class, 1);
        Queue::assertPushed(GenerateInvoiceDocuments::class, 1);
        Queue::assertPushed(SendBillingInvoiceEmail::class, 1);
        $this->assertNotNull($subscription->last_notified_at);
        $this->assertDatabaseCount('billing_invoices', 1);
        $this->assertDatabaseHas('billing_invoices', [
            'restaurant_id' => $restaurant->id,
            'restaurant_subscription_id' => $subscription->id,
            'type' => 'upcoming_renewal',
        ]);

        Artisan::call('billing:send-reminders');

        Notification::assertSentToTimes($owner, \App\Notifications\DunningNotification::class, 1);
        Queue::assertPushed(GenerateInvoiceDocuments::class, 1);
        Queue::assertPushed(SendBillingInvoiceEmail::class, 1);
        $this->assertDatabaseCount('billing_invoices', 1);
    }
}
