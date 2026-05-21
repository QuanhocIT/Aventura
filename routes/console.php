<?php

use App\Services\BillingService;
use Illuminate\Support\Facades\Artisan;
use App\Models\BillingInvoice;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Notifications\SubscriptionExpiryReminder;

Artisan::command('billing:sync-statuses', function (BillingService $billing) {
    $billing->markExpiredAndSuspended();
    $this->info('Billing statuses synced.');
})->purpose('Sync expired and suspended tenant statuses');

Artisan::command('billing:send-reminders', function (BillingService $billing) {
    $upcomingDays = (int) config('billing.upcoming_due_days', 7);
    $threshold = now()->addDays($upcomingDays)->endOfDay();

    RestaurantSubscription::query()
        ->with(['restaurant.owner'])
        ->whereIn('status', ['trial', 'active'])
        ->whereNotNull('ended_at')
        ->whereBetween('ended_at', [now(), $threshold])
        ->chunkById(100, function ($subscriptions) use ($billing) {
            foreach ($subscriptions as $subscription) {
                $restaurant = $subscription->restaurant;
                if (! $restaurant || ! $restaurant->owner) {
                    continue;
                }

                $invoice = BillingInvoice::query()->firstOrCreate([
                    'restaurant_id' => $restaurant->id,
                    'restaurant_subscription_id' => $subscription->id,
                    'type' => 'upcoming_renewal',
                ], [
                    'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.strtoupper(substr(md5((string) $subscription->id), 0, 4)),
                    'status' => 'pending',
                    'currency' => $restaurant->currency ?? 'VND',
                    'subtotal' => (float) $subscription->price,
                    'discount_amount' => 0,
                    'total' => (float) $subscription->price,
                    'issued_on' => now()->toDateString(),
                    'due_on' => $subscription->ended_at?->toDateString(),
                    'recipient_email' => $restaurant->owner->email,
                ]);

                dispatch(new \App\Jobs\GenerateInvoiceDocuments($invoice->id))->onQueue(config('billing.queue'));
                dispatch(new \App\Jobs\SendBillingInvoiceEmail($invoice->id))->onQueue(config('billing.queue'));

                $restaurant->owner->notify(new SubscriptionExpiryReminder(
                    $restaurant->name,
                    optional($subscription->ended_at)->format('d/m/Y'),
                    'upcoming'
                ));

                $subscription->update(['last_notified_at' => now()]);
            }
        });

    $this->info('Billing reminders queued.');
})->purpose('Queue reminders for subscriptions nearing expiration');
