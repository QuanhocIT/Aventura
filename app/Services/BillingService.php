<?php

namespace App\Services;

use App\Jobs\GenerateInvoiceDocuments;
use App\Jobs\SendBillingInvoiceEmail;
use App\Models\BillingAdjustment;
use App\Models\BillingInvoice;
use App\Models\CommissionLog;
use App\Models\Coupon;
use App\Models\PaymentWebhook;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\DunningNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class BillingService
{
    public function handleWebhook(array $payload, array $headers = [], ?string $signature = null, string $provider = 'bank'): array
    {
        $transactionCode = $this->extractTransactionCode($payload);

        return DB::transaction(function () use ($payload, $headers, $signature, $provider, $transactionCode) {
            // 1. Tạo hoặc lấy PaymentWebhook trong transaction
            $webhook = PaymentWebhook::query()->firstOrCreate(
                ['provider' => $provider, 'transaction_code' => $transactionCode, 'signature' => $signature],
                [
                    'event_type' => Arr::get($payload, 'event_type') ?? Arr::get($payload, 'eventType'),
                    'status' => 'received',
                    'headers' => $headers,
                    'payload' => $payload,
                ]
            );

            // Khóa bản ghi webhook để tránh race condition khi hai request chạy song song
            $webhook = PaymentWebhook::where('id', $webhook->id)->lockForUpdate()->firstOrFail();

            if ($webhook->processed_at || $webhook->status === 'processed') {
                return ['ok' => true, 'message' => 'Webhook already processed', 'duplicate' => true];
            }

            // 2. Tìm Restaurant liên quan
            $restaurant = Restaurant::query()
                ->whereHas('subscriptions', fn ($q) => $q->where('transaction_code', $transactionCode))
                ->first();

            if (! $restaurant) {
                $webhook->update(['status' => 'orphaned', 'processed_at' => now(), 'error_message' => 'Transaction code not found']);

                return ['ok' => false, 'message' => 'Transaction code not found'];
            }

            // 3. Khóa subscription để cập nhật
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

            Cache::forget("quota_summary:{$restaurant->id}");
            Cache::forget("user_permissions:{$restaurant->owner_user_id}");

            $invoice = BillingInvoice::query()->create([
                'restaurant_id' => $restaurant->id,
                'restaurant_subscription_id' => $subscription->id,
                'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'type' => 'payment_success',
                'status' => 'pending',
                'payment_status' => 'paid',
                'paid_at' => $paidAt,
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

            try {
                $this->awardReferralCommission($restaurant, $invoice);
            } catch (\Throwable $e) {
                Log::error('Failed to award referral commission: '.$e->getMessage(), [
                    'restaurant_id' => $restaurant->id,
                    'invoice_id' => $invoice->id,
                    'exception' => $e,
                ]);
            }

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
        $manualOverride = ($subscription->billing_meta ?? [])['manual_override'] ?? null;
        $discountAmount = ($manualOverride['type'] ?? null) === 'discount'
            ? min((float) $subscription->price, max(0, (float) ($manualOverride['discount_amount'] ?? 0)))
            : 0;

        return BillingInvoice::query()->create([
            'restaurant_id' => $restaurant->id,
            'restaurant_subscription_id' => $subscription->id,
            'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'type' => $type,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => $restaurant->currency ?? 'VND',
            'subtotal' => (float) $subscription->price,
            'discount_amount' => $discountAmount,
            'total' => max(0, (float) $subscription->price - $discountAmount),
            'issued_on' => now()->toDateString(),
            'due_on' => $subscription->ended_at?->toDateString(),
            'recipient_email' => $restaurant->owner?->email ?? $restaurant->email,
            'meta' => $discountAmount > 0 ? [
                'manual_discount' => [
                    'amount' => $discountAmount,
                    'applied_at' => now()->toIso8601String(),
                ],
            ] : null,
        ]);
    }

    public function applyManualOverride(Restaurant $restaurant, array $data, ?User $actor = null): RestaurantSubscription
    {
        return DB::transaction(function () use ($restaurant, $data, $actor) {
            $subscription = RestaurantSubscription::query()
                ->where('restaurant_id', $restaurant->id)
                ->whereIn('status', ['trial', 'active'])
                ->latest('id')
                ->lockForUpdate()
                ->first()
                ?? RestaurantSubscription::query()
                    ->where('restaurant_id', $restaurant->id)
                    ->latest('id')
                    ->lockForUpdate()
                    ->firstOrFail();
            $days = (int) ($data['days'] ?? 0);
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $type = $data['type'] ?? 'extend';
            $reason = $data['reason'] ?? null;
            $coupon = null;

            if (! empty($data['coupon_code'])) {
                $coupon = Coupon::query()
                    ->where('code', strtoupper($data['coupon_code']))
                    ->lockForUpdate()
                    ->first();

                if (! $coupon || ! $coupon->isValid()) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Coupon không tồn tại hoặc không còn hiệu lực.',
                    ]);
                }

                if ($type !== 'discount') {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Coupon chỉ được áp dụng cho thao tác giảm giá.',
                    ]);
                }

                $discountAmount = $coupon->getDiscountAmount((float) $subscription->price);
            }

            if ($type === 'discount' && $discountAmount <= 0) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'Điều chỉnh giảm giá phải lớn hơn 0.',
                ]);
            }

            $now = now();
            $baseEndedAt = $subscription->ended_at && $subscription->ended_at->isFuture()
                ? $subscription->ended_at->copy()
                : $now->copy();
            $newEndedAt = $baseEndedAt->copy()->addDays(max(0, $days));
            $reactivates = in_array($type, ['trial', 'active', 'extend'], true) && $days > 0;

            $subscriptionMeta = [
                'type' => $type,
                'days' => $days,
                'discount_amount' => $discountAmount,
                'reason' => $reason,
                'applied_at' => $now->toIso8601String(),
            ];

            if ($reactivates) {
                RestaurantSubscription::query()
                    ->where('restaurant_id', $restaurant->id)
                    ->whereIn('status', ['trial', 'active'])
                    ->where('id', '!=', $subscription->id)
                    ->update(['status' => 'expired', 'ended_at' => $now]);

                $subscription->update([
                    'ended_at' => $newEndedAt,
                    'renewal_at' => $newEndedAt,
                    'status' => 'active',
                    'billing_meta' => array_merge($subscription->billing_meta ?? [], [
                        'manual_override' => $subscriptionMeta,
                    ]),
                ]);

                $restaurant->update([
                    'status' => 'active',
                    'lifecycle_status' => 'active',
                    'subscription_ends_at' => $newEndedAt->toDateString(),
                ]);
            } else {
                // A discount is an accounting adjustment only. It must not
                // silently reactivate an expired tenant or move its renewal.
                $subscription->update([
                    'billing_meta' => array_merge($subscription->billing_meta ?? [], [
                        'manual_override' => $subscriptionMeta,
                    ]),
                ]);

                if ($type === 'discount' && $discountAmount > 0) {
                    $invoice = BillingInvoice::query()
                        ->where('restaurant_id', $restaurant->id)
                        ->where('restaurant_subscription_id', $subscription->id)
                        ->where(function ($query) {
                            $query->whereNull('payment_status')->orWhere('payment_status', '!=', 'paid');
                        })
                        ->where('status', '!=', 'written_off')
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                    if ($invoice) {
                        $subtotal = (float) ($invoice->subtotal ?: $subscription->price);
                        $appliedDiscount = min($subtotal, max((float) $invoice->discount_amount, $discountAmount));
                        $invoice->update([
                            'discount_amount' => $appliedDiscount,
                            'total' => max(0, $subtotal - $appliedDiscount),
                            'meta' => array_merge($invoice->meta ?? [], [
                                'manual_discount' => [
                                    'amount' => $appliedDiscount,
                                    'applied_at' => $now->toIso8601String(),
                                    'reason' => $reason,
                                ],
                            ]),
                        ]);
                    }
                }
            }

            BillingAdjustment::query()->create([
                'restaurant_id' => $restaurant->id,
                'restaurant_subscription_id' => $subscription->id,
                'created_by' => $actor?->id,
                'type' => $type,
                'days' => $days,
                'discount_amount' => $discountAmount,
                'coupon_code' => $data['coupon_code'] ?? null,
                'reason' => $reason,
                'meta' => Arr::except($data, ['password']),
            ]);

            if ($coupon) {
                $coupon->increment('uses_count');
            }

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
            ->update(['status' => 'expired', 'lifecycle_status' => 'expired']);

        Restaurant::query()
            ->where('status', 'expired')
            ->whereDate('subscription_ends_at', '<', $graceThreshold->toDateString())
            ->update(['status' => 'suspended', 'lifecycle_status' => 'suspended']);

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

    /**
     * Gửi cảnh báo gia hạn đa giai đoạn (Dunning System).
     *
     * Stage D-7 : Nhắc nhở 7 ngày trước
     * Stage D-3 : Cảnh báo 3 ngày trước
     * Stage D0  : Hết hạn hôm nay (bước vào grace period)
     * Stage D+3 : Quá grace period, tài khoản bị khóa
     */
    public function sendExpiryReminders(): int
    {
        $now = now();
        $sent = 0;

        // Generate upcoming renewal invoices if within the upcoming due window
        $upcomingDays = (int) config('billing.upcoming_due_days', 7);
        $upcomingThreshold = $now->copy()->addDays($upcomingDays)->endOfDay();

        RestaurantSubscription::query()
            ->with(['restaurant'])
            ->whereIn('status', ['trial', 'active'])
            ->whereNotNull('ended_at')
            ->whereBetween('ended_at', [$now, $upcomingThreshold])
            ->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $restaurant = $subscription->restaurant;
                    if (! $restaurant || $this->isDunningPaused($subscription)) {
                        continue;
                    }

                    $invoiceExists = BillingInvoice::query()
                        ->where('restaurant_subscription_id', $subscription->id)
                        ->where('type', 'upcoming_renewal')
                        ->exists();

                    if (! $invoiceExists) {
                        $invoice = $this->createUpcomingInvoice($restaurant, $subscription);
                        $this->queueInvoiceRegeneration($invoice);
                        $this->queueInvoiceEmail($invoice);
                    }
                }
            });

        // D-7: Hết hạn trong 6–7 ngày
        $sent += $this->sendDunningStage(
            fromDays: 6, toDays: 7, stage: 'd7', statusIn: ['trial', 'active'], now: $now
        );

        // D-3: Hết hạn trong 2–3 ngày
        $sent += $this->sendDunningStage(
            fromDays: 2, toDays: 3, stage: 'd3', statusIn: ['trial', 'active'], now: $now
        );

        // D0: Hết hạn hôm nay
        $sent += $this->sendDunningStage(
            fromDays: 0, toDays: 0, stage: 'd0', statusIn: ['trial', 'active'], now: $now
        );

        // D+3: Đã hết hạn 3+ ngày (overdue)
        $sent += $this->sendOverdueDunning($now);

        return $sent;
    }

    private function sendDunningStage(int $fromDays, int $toDays, string $stage, array $statusIn, CarbonInterface $now): int
    {
        $windowStart = $now->copy()->addDays($fromDays)->startOfDay();
        $windowEnd = $now->copy()->addDays($toDays)->endOfDay();
        $sent = 0;

        RestaurantSubscription::query()
            ->with(['restaurant.owner'])
            ->whereIn('status', $statusIn)
            ->whereNotNull('ended_at')
            ->whereBetween('ended_at', [$windowStart, $windowEnd])
            ->where(function ($q) use ($stage) {
                // Không gửi lại nếu đã gửi stage này hôm nay
                $q->whereNull('last_notified_at')
                    ->orWhere('billing_meta->dunning_stage', '!=', $stage)
                    ->orWhereDate('last_notified_at', '<', now()->toDateString());
            })
            ->chunkById(100, function ($subscriptions) use (&$sent, $now, $stage) {
                foreach ($subscriptions as $subscription) {
                    $restaurant = $subscription->restaurant;
                    if (! $restaurant || ! $restaurant->owner || $this->isDunningPaused($subscription)) {
                        continue;
                    }

                    $daysLeft = (int) $now->diffInDays($subscription->ended_at, false);
                    $renewalUrl = route('billing.checkout').'?plan='.($subscription->plan?->code ?? '');

                    $restaurant->owner->notify(new DunningNotification(
                        restaurantName: $restaurant->name,
                        expiresAt: optional($subscription->ended_at)->format('d/m/Y') ?? '',
                        daysLeft: $daysLeft,
                        renewalUrl: $renewalUrl,
                        stage: $stage,
                    ));

                    $subscription->update([
                        'last_notified_at' => now(),
                        'billing_meta' => array_merge($subscription->billing_meta ?? [], [
                            'dunning_stage' => $stage,
                            'dunning_sent_at' => now()->toIso8601String(),
                        ]),
                    ]);

                    $sent++;
                }
            });

        return $sent;
    }

    private function sendOverdueDunning(CarbonInterface $now): int
    {
        $graceDays = (int) config('billing.grace_period_days', 30);
        // Đưa ra cảnh báo khi quá hạn từ 1–3 ngày (trong grace period)
        $graceCutoff = $now->copy()->subDays(3)->startOfDay();
        $sent = 0;

        RestaurantSubscription::query()
            ->with(['restaurant.owner'])
            ->whereIn('status', ['expired'])
            ->whereNotNull('ended_at')
            ->whereBetween('ended_at', [$graceCutoff, $now->copy()->subDay()->endOfDay()])
            ->where(function ($q) {
                $q->whereNull('last_notified_at')
                    ->orWhere('billing_meta->dunning_stage', '!=', 'overdue')
                    ->orWhereDate('last_notified_at', '<', now()->toDateString());
            })
            ->chunkById(100, function ($subscriptions) use (&$sent, $now) {
                foreach ($subscriptions as $subscription) {
                    $restaurant = $subscription->restaurant;
                    if (! $restaurant || ! $restaurant->owner || $this->isDunningPaused($subscription)) {
                        continue;
                    }

                    $renewalUrl = route('billing.checkout').'?plan='.($subscription->plan?->code ?? '');

                    $restaurant->owner->notify(new DunningNotification(
                        restaurantName: $restaurant->name,
                        expiresAt: optional($subscription->ended_at)->format('d/m/Y') ?? '',
                        daysLeft: (int) $now->diffInDays($subscription->ended_at, false),
                        renewalUrl: $renewalUrl,
                        stage: 'overdue',
                    ));

                    $subscription->update([
                        'last_notified_at' => now(),
                        'billing_meta' => array_merge($subscription->billing_meta ?? [], [
                            'dunning_stage' => 'overdue',
                            'dunning_sent_at' => now()->toIso8601String(),
                        ]),
                    ]);

                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * Tính proration credit cho việc nâng cấp gói.
     * Credit chỉ được ghi nhận thông qua BillingAdjustment (tracking only),
     * không trừ trực tiếp vì SePay là manual bank transfer.
     */
    public function calculateProration(RestaurantSubscription $currentSubscription): array
    {
        if (! $currentSubscription->ended_at || $currentSubscription->ended_at->isPast()) {
            return ['credit_amount' => 0, 'remaining_days' => 0, 'daily_rate' => 0];
        }

        $remainingDays = (int) now()->diffInDays($currentSubscription->ended_at, false);
        if ($remainingDays <= 0) {
            return ['credit_amount' => 0, 'remaining_days' => 0, 'daily_rate' => 0];
        }

        $cycleDays = $this->billingCycleDays($currentSubscription);
        $dailyRate = (float) $currentSubscription->price / max($cycleDays, 1);
        $creditAmount = round($dailyRate * $remainingDays);

        return [
            'credit_amount' => $creditAmount,
            'remaining_days' => $remainingDays,
            'daily_rate' => round($dailyRate),
            'cycle_days' => $cycleDays,
        ];
    }

    /**
     * Ghi nhận proration credit khi upgrade gói.
     * Tạo BillingAdjustment tracking cho mục đích số liệu.
     */
    public function recordProrationCredit(
        RestaurantSubscription $oldSub,
        RestaurantSubscription $newSub,
        float $creditAmount,
        ?User $actor = null
    ): BillingAdjustment {
        return BillingAdjustment::create([
            'restaurant_id' => $oldSub->restaurant_id,
            'restaurant_subscription_id' => $newSub->id,
            'created_by' => $actor?->id,
            'type' => 'proration_credit',
            'days' => 0,
            'discount_amount' => $creditAmount,
            'reason' => "Credit từ gói cũ (#{$oldSub->id}) — nâng cấp lên gói mới",
            'meta' => [
                'old_subscription_id' => $oldSub->id,
                'new_subscription_id' => $newSub->id,
                'old_plan_id' => $oldSub->plan_id,
            ],
        ]);
    }

    /**
     * Tự động tạo giao dịch gia hạn mới cho các subscription bật auto_renewal.
     * Được gọi khi subscription sắp hết hạn (D-7) để có mã sẵn cho khách chuyển khoản.
     */
    public function generateRenewalTransactions(): int
    {
        $now = now();
        $threshold = $now->copy()->addDays(7)->endOfDay();
        $created = 0;

        RestaurantSubscription::query()
            ->with(['restaurant', 'plan'])
            ->where('status', 'active')
            ->where('auto_renewal_enabled', true)
            ->whereNotNull('ended_at')
            ->whereBetween('ended_at', [$now, $threshold])
            // Không có pending renewal transaction rồi
            ->whereDoesntHave('restaurant.subscriptions', function ($q) use ($now) {
                $q->where('status', 'expired')
                    ->whereJsonContains('meta->pending_payment', true)
                    ->where('started_at', '>=', $now->copy()->subDays(7));
            })
            ->chunkById(100, function ($subscriptions) use (&$created) {
                foreach ($subscriptions as $subscription) {
                    $restaurant = $subscription->restaurant;
                    $plan = $subscription->plan;
                    if (! $restaurant || ! $plan) {
                        continue;
                    }

                    try {
                        $transactionCode = $this->generateTransactionCode($restaurant->id);
                        $endsAt = $subscription->ended_at->copy()->addDays(
                            $this->billingCycleDays($subscription)
                        );

                        RestaurantSubscription::create([
                            'restaurant_id' => $restaurant->id,
                            'plan_id' => $plan->id,
                            'status' => 'expired',
                            'started_at' => $subscription->ended_at,
                            'ended_at' => $endsAt,
                            'renewal_at' => $endsAt,
                            'original_price' => $subscription->price,
                            'price' => $subscription->price,
                            'billing_cycle' => $subscription->billing_cycle,
                            'auto_renewal_enabled' => true,
                            'transaction_code' => $transactionCode,
                            'meta' => [
                                'source' => 'auto_renewal',
                                'parent_sub_id' => $subscription->id,
                                'plan_code' => $plan->code,
                                'pending_payment' => true,
                            ],
                            'billing_meta' => [
                                'provider' => 'sepay',
                                'renewal_generated_at' => now()->toIso8601String(),
                            ],
                        ]);

                        $created++;
                    } catch (\Throwable $e) {
                        Log::error('Auto-renewal transaction generation failed', [
                            'subscription_id' => $subscription->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $created;
    }

    private function generateTransactionCode(int $restaurantId): string
    {
        do {
            $code = 'AVT'.str_pad((string) $restaurantId, 4, '0', STR_PAD_LEFT).Str::upper(Str::random(8));
        } while (RestaurantSubscription::query()->where('transaction_code', $code)->exists());

        return $code;
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
        $cycle = strtolower(trim($subscription->billing_cycle ?? $subscription->plan?->billing_cycle ?? 'monthly'));

        return match ($cycle) {
            'yearly' => 365,
            'quarterly' => 90,
            'half_yearly' => 180,
            'biennial' => 730,
            default => 30,
        };
    }

    /**
     * Award referral commission to the referrer of the restaurant owner.
     */
    private function awardReferralCommission(Restaurant $restaurant, BillingInvoice $invoice): void
    {
        $owner = $restaurant->owner;
        if (! $owner) {
            $owner = User::where('id', $restaurant->owner_user_id)->first();
        }

        if (! $owner || ! $owner->referred_by_id) {
            return;
        }

        $referrer = User::where('id', $owner->referred_by_id)->first();
        if (! $referrer) {
            return;
        }

        $amount = (float) $invoice->total;
        if ($amount <= 0) {
            return;
        }

        $percentage = (float) SystemSetting::get('referral_commission_percentage', config('referral.commission_percentage', 10));
        $commissionAmount = $amount * ($percentage / 100);

        CommissionLog::create([
            'user_id' => $referrer->id,
            'buyer_id' => $owner->id,
            'restaurant_id' => $restaurant->id,
            'billing_invoice_id' => $invoice->id,
            'amount' => $amount,
            'commission_percentage' => $percentage,
            'commission_amount' => $commissionAmount,
        ]);

        $referrer->increment('commission_balance', $commissionAmount);
    }

    // =========================================================================
    // DUNNING MANUAL CONTROLS
    // =========================================================================

    /**
     * Gửi email dunning thủ công cho một subscription (được gọi từ Dunning Dashboard).
     */
    public function manualSendDunning(RestaurantSubscription $subscription, string $stage = 'd7'): void
    {
        $restaurant = $subscription->restaurant ?? Restaurant::find($subscription->restaurant_id);
        if (! $restaurant) {
            return;
        }

        $owner = $restaurant->owner;
        if (! $owner) {
            return;
        }

        $now = now();
        $daysLeft = $subscription->ended_at
            ? (int) round($now->diffInDays($subscription->ended_at, false))
            : 0;
        $renewalUrl = route('billing.checkout').'?plan='.($subscription->plan?->code ?? '');

        $owner->notify(new DunningNotification(
            restaurantName: $restaurant->name,
            expiresAt: optional($subscription->ended_at)->format('d/m/Y') ?? '',
            daysLeft: $daysLeft,
            renewalUrl: $renewalUrl,
            stage: $stage,
        ));

        $subscription->update([
            'last_notified_at' => $now,
            'billing_meta' => array_merge($subscription->billing_meta ?? [], [
                'dunning_stage' => $stage,
                'dunning_sent_at' => $now->toIso8601String(),
                'manual_send' => true,
                'manual_send_at' => $now->toIso8601String(),
            ]),
        ]);

        Log::info('Manual dunning email sent', [
            'subscription_id' => $subscription->id,
            'restaurant_id' => $restaurant->id,
            'stage' => $stage,
        ]);
    }

    /**
     * Tạm dừng dunning cho subscription trong N ngày.
     * Được lưu vào billing_meta JSON — không cần migration.
     */
    public function pauseDunning(RestaurantSubscription $subscription, int $days): void
    {
        $pausedUntil = now()->addDays($days);

        $subscription->update([
            'billing_meta' => array_merge($subscription->billing_meta ?? [], [
                'dunning_paused_until' => $pausedUntil->toIso8601String(),
                'dunning_paused_at' => now()->toIso8601String(),
                'dunning_pause_days' => $days,
            ]),
        ]);

        Log::info('Dunning paused', [
            'subscription_id' => $subscription->id,
            'paused_until' => $pausedUntil->toDateString(),
        ]);
    }

    private function isDunningPaused(RestaurantSubscription $subscription): bool
    {
        $pausedUntil = $subscription->billing_meta['dunning_paused_until'] ?? null;
        if (! $pausedUntil) {
            return false;
        }

        try {
            return Carbon::parse($pausedUntil)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }
}
