<?php

namespace App\Services;

use App\Jobs\GenerateInvoiceDocuments;
use App\Jobs\SendBillingInvoiceEmail;
use App\Models\BillingAdjustment;
use App\Models\BillingInvoice;
use App\Models\PaymentWebhook;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\SubscriptionExpiryReminder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    public function handleWebhook(array $payload, array $headers = [], ?string $signature = null, string $provider = 'bank'): array
    {
        $transactionCode = $this->extractTransactionCode($payload);

        $webhook = PaymentWebhook::query()->firstOrCreate(
            ['provider' => $provider, 'transaction_code' => $transactionCode, 'signature' => $signature],
            [
                'event_type' => Arr::get($payload, 'event_type') ?? Arr::get($payload, 'eventType'),
                'status' => 'received',
                'headers' => $headers,
                'payload' => $payload,
            ]
        );

        if ($webhook->processed_at) {
            return ['ok' => true, 'message' => 'Webhook already processed', 'duplicate' => true];
        }

        $restaurant = Restaurant::query()
            ->whereHas('subscriptions', fn ($q) => $q->where('transaction_code', $transactionCode))
            ->first();

        if (! $restaurant) {
            $webhook->update(['status' => 'orphaned', 'processed_at' => now(), 'error_message' => 'Transaction code not found']);

            return ['ok' => false, 'message' => 'Transaction code not found'];
        }

        return DB::transaction(function () use ($payload, $restaurant, $webhook, $transactionCode) {
            $subscription = $restaurant->subscriptions()
                ->where('transaction_code', $transactionCode)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                $webhook->update(['status' => 'orphaned', 'processed_at' => now(), 'error_message' => 'Subscription not found']);

                return ['ok' => false, 'message' => 'Subscription not found'];
            }

            if ($subscription->status === 'active' && $subscription->last_paid_at) {
                $webhook->update(['status' => 'processed', 'processed_at' => now()]);

                return ['ok' => true, 'message' => 'Already paid'];
            }

            $paidAt = now();
            $graceDays = (int) config('billing.grace_period_days', 30);
            $durationDays = $this->billingCycleDays($subscription);
            $endAt = Carbon::parse($subscription->ended_at ?? $subscription->renewal_at ?? $paidAt)->greaterThan($paidAt)
                ? Carbon::parse($subscription->ended_at ?? $subscription->renewal_at)
                : $paidAt;
            $newEndedAt = $endAt->copy()->addDays($durationDays);

            $subscription->update([
                'status' => 'active',
                'ended_at' => $newEndedAt,
                'renewal_at' => $newEndedAt,
                'last_paid_at' => $paidAt,
                'grace_ends_at' => $newEndedAt->copy()->addDays($graceDays),
                'billing_meta' => array_merge($subscription->billing_meta ?? [], [
                    'provider_payload' => $payload,
                    'paid_transaction_code' => $transactionCode,
                ]),
            ]);

            $restaurant->update([
                'plan_id' => $subscription->plan_id,
                'status' => 'active',
                'subscription_ends_at' => $newEndedAt->toDateString(),
                'subscription_started_at' => $subscription->started_at?->toDateString() ?? $paidAt->toDateString(),
            ]);

            $invoice = BillingInvoice::query()->create([
                'restaurant_id' => $restaurant->id,
                'restaurant_subscription_id' => $subscription->id,
                'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'type' => 'payment_success',
                'status' => 'pending',
                'currency' => $restaurant->currency ?? 'VND',
                'subtotal' => (float) $subscription->price,
                'discount_amount' => 0,
                'total' => (float) $subscription->price,
                'issued_on' => now()->toDateString(),
                'due_on' => now()->toDateString(),
                'recipient_email' => $restaurant->owner?->email ?? $restaurant->email,
                'meta' => [
                    'provider' => $webhook->provider,
                    'transaction_code' => $transactionCode,
                    'webhook_id' => $webhook->id,
                ],
            ]);

            $webhook->update(['status' => 'processed', 'processed_at' => now()]);

            return [
                'ok' => true,
                'restaurant' => $restaurant,
                'subscription' => $subscription,
                'invoice' => $invoice,
            ];
        });
    }

    public function createUpcomingInvoice(Restaurant $restaurant, RestaurantSubscription $subscription, string $type = 'upcoming_renewal'): BillingInvoice
    {
        return BillingInvoice::query()->create([
            'restaurant_id' => $restaurant->id,
            'restaurant_subscription_id' => $subscription->id,
            'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'type' => $type,
            'status' => 'pending',
            'currency' => $restaurant->currency ?? 'VND',
            'subtotal' => (float) $subscription->price,
            'discount_amount' => 0,
            'total' => (float) $subscription->price,
            'issued_on' => now()->toDateString(),
            'due_on' => $subscription->ended_at?->toDateString(),
            'recipient_email' => $restaurant->owner?->email ?? $restaurant->email,
        ]);
    }

    public function applyManualOverride(Restaurant $restaurant, array $data, ?User $actor = null): RestaurantSubscription
    {
        return DB::transaction(function () use ($restaurant, $data, $actor) {
            $subscription = $restaurant->activeSubscription ?? $restaurant->subscriptions()->latest('id')->firstOrFail();
            $days = (int) ($data['days'] ?? 0);
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $type = $data['type'] ?? 'extend';
            $reason = $data['reason'] ?? null;

            $newEndedAt = $subscription->ended_at?->copy() ?? now();
            if ($days > 0) {
                $newEndedAt = $newEndedAt->addDays($days);
            }

            $subscription->update([
                'ended_at' => $newEndedAt,
                'renewal_at' => $newEndedAt,
                'status' => in_array($type, ['trial', 'active'], true) ? 'active' : $subscription->status,
                'billing_meta' => array_merge($subscription->billing_meta ?? [], [
                    'manual_override' => [
                        'type' => $type,
                        'days' => $days,
                        'discount_amount' => $discountAmount,
                        'reason' => $reason,
                    ],
                ]),
            ]);

            $restaurant->update([
                'status' => 'active',
                'subscription_ends_at' => $newEndedAt->toDateString(),
            ]);

            BillingAdjustment::query()->create([
                'restaurant_id' => $restaurant->id,
                'restaurant_subscription_id' => $subscription->id,
                'created_by' => $actor?->id,
                'type' => $type,
                'days' => $days,
                'discount_amount' => $discountAmount,
                'reason' => $reason,
                'meta' => $data,
            ]);

            return $subscription;
        });
    }

    public function queueInvoiceRegeneration(BillingInvoice $invoice): void
    {
        $invoice->update(['status' => 'pending']);
        GenerateInvoiceDocuments::dispatch($invoice->id)->onQueue(config('billing.queue'));
    }

    public function queueInvoiceEmail(BillingInvoice $invoice): void
    {
        SendBillingInvoiceEmail::dispatch($invoice->id)->onQueue(config('billing.queue'));
    }

    public function retryWebhook(PaymentWebhook $webhook): array
    {
        $webhook->update([
            'status' => 'received',
            'processed_at' => null,
            'error_message' => null,
        ]);

        return $this->handleWebhook(
            $webhook->payload ?? [],
            $webhook->headers ?? [],
            $webhook->signature,
            $webhook->provider
        );
    }

    public function markExpiredAndSuspended(): void
    {
        $now = now();
        $graceThreshold = $now->copy()->subDays((int) config('billing.grace_period_days', 30));

        RestaurantSubscription::query()
            ->whereIn('status', ['trial', 'active'])
            ->whereNotNull('ended_at')
            ->where('ended_at', '<', $now)
            ->update(['status' => 'expired']);

        Restaurant::query()
            ->where('status', 'active')
            ->whereDate('subscription_ends_at', '<', $now->toDateString())
            ->update(['status' => 'expired']);

        Restaurant::query()
            ->where('status', 'expired')
            ->whereDate('subscription_ends_at', '<', $graceThreshold->toDateString())
            ->update(['status' => 'suspended']);

        RestaurantSubscription::query()
            ->whereHas('restaurant', fn ($query) => $query->where('status', 'active'))
            ->whereNotNull('ended_at')
            ->where('ended_at', '>=', $now)
            ->where('status', '!=', 'active')
            ->update(['status' => 'active']);

        RestaurantSubscription::query()
            ->whereHas('restaurant', fn ($query) => $query->where('status', 'expired'))
            ->whereNotNull('ended_at')
            ->where('ended_at', '<', $now)
            ->where('status', '!=', 'expired')
            ->update(['status' => 'expired']);
    }

    public function sendExpiryReminders(): int
    {
        $now = now();
        $threshold = $now->copy()->addDays((int) config('billing.upcoming_due_days', 7))->endOfDay();
        $sent = 0;

        RestaurantSubscription::query()
            ->with(['restaurant.owner'])
            ->whereIn('status', ['trial', 'active'])
            ->whereNotNull('ended_at')
            ->whereBetween('ended_at', [$now, $threshold])
            ->where(function ($query) use ($now) {
                $query->whereNull('last_notified_at')
                    ->orWhereDate('last_notified_at', '<', $now->toDateString());
            })
            ->chunkById(100, function ($subscriptions) use (&$sent) {
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

                    $this->queueInvoiceRegeneration($invoice);
                    $this->queueInvoiceEmail($invoice);

                    $restaurant->owner->notify(new SubscriptionExpiryReminder(
                        $restaurant->name,
                        optional($subscription->ended_at)->format('d/m/Y'),
                        'upcoming'
                    ));

                    $subscription->update(['last_notified_at' => now()]);
                    $sent++;
                }
            });

        return $sent;
    }

    private function extractTransactionCode(array $payload): ?string
    {
        $explicit = Arr::get($payload, 'transaction_code')
            ?? Arr::get($payload, 'transactionCode')
            ?? Arr::get($payload, 'referenceCode')
            ?? Arr::get($payload, 'data.transaction_code')
            ?? Arr::get($payload, 'data.transactionCode')
            ?? Arr::get($payload, 'data.referenceCode');

        if ($explicit) {
            return (string) $explicit;
        }

        $content = (string) (Arr::get($payload, 'content')
            ?? Arr::get($payload, 'description')
            ?? Arr::get($payload, 'transferContent')
            ?? Arr::get($payload, 'data.content')
            ?? Arr::get($payload, 'data.description')
            ?? Arr::get($payload, 'data.transferContent')
            ?? '');

        if (preg_match('/AVT[0-9A-Z]{12,}/i', $content, $matches)) {
            return strtoupper($matches[0]);
        }

        return null;
    }

    private function billingCycleDays(RestaurantSubscription $subscription): int
    {
        $cycle = $subscription->billing_cycle ?? $subscription->plan?->billing_cycle ?? 'monthly';

        return match ($cycle) {
            'yearly' => 365,
            'quarterly' => 90,
            default => 30,
        };
    }
}
