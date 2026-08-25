<?php

namespace Database\Seeders;

use App\Models\BillingAdjustment;
use App\Models\BillingInvoice;
use App\Models\PaymentWebhook;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminBillingDemoSeeder extends Seeder
{
    /**
     * Seed isolated demo data for Super Admin > Billing Center.
     *
     * All records use a DEMO-BILLING prefix so this seeder is safe to run
     * repeatedly without duplicating the sample rows.
     */
    public function run(): void
    {
        $restaurant = Restaurant::query()
            ->whereHas('owner', fn ($query) => $query->where('email', 'owner@bepso.test'))
            ->first()
            ?? Restaurant::query()->orderBy('id')->first();

        if (! $restaurant) {
            $this->command?->warn('Không tìm thấy nhà hàng để tạo dữ liệu Billing Center mẫu.');

            return;
        }

        $plans = SubscriptionPlan::query()
            ->whereIn('code', ['pro', 'enterprise', 'starter'])
            ->get()
            ->keyBy('code');
        $activePlan = $plans->get('pro') ?? $plans->get('starter');
        $expiredPlan = $plans->get('enterprise') ?? $activePlan;

        if (! $activePlan || ! $expiredPlan) {
            $this->command?->warn('Không tìm thấy gói dịch vụ để tạo dữ liệu Billing Center mẫu.');

            return;
        }

        $now = now();
        $superAdminId = User::query()
            ->where('email', 'superadmin@aventura.local')
            ->value('id');
        $recipientEmail = $restaurant->owner?->email ?? $restaurant->email;

        $activeSubscription = RestaurantSubscription::updateOrCreate(
            ['transaction_code' => 'DEMO-BILLING-ACTIVE-001'],
            [
                'restaurant_id' => $restaurant->id,
                'plan_id' => $activePlan->id,
                'status' => 'active',
                'started_at' => $now->copy()->subDays(75),
                'ended_at' => $now->copy()->addDays(15),
                'renewal_at' => $now->copy()->addDays(15),
                'price' => $activePlan->price,
                'original_price' => $activePlan->price,
                'billing_cycle' => 'monthly',
                'last_paid_at' => $now->copy()->subDays(15),
                'grace_ends_at' => $now->copy()->addDays(45),
                'auto_renewal_enabled' => true,
                'meta' => ['demo_seed' => 'super-admin-billing'],
                'billing_meta' => ['demo_seed' => 'super-admin-billing'],
            ],
        );

        $expiredSubscription = RestaurantSubscription::updateOrCreate(
            ['transaction_code' => 'DEMO-BILLING-EXPIRED-001'],
            [
                'restaurant_id' => $restaurant->id,
                'plan_id' => $expiredPlan->id,
                'status' => 'expired',
                'started_at' => $now->copy()->subMonths(7),
                'ended_at' => $now->copy()->subDays(42),
                'renewal_at' => $now->copy()->subDays(42),
                'price' => $expiredPlan->price,
                'original_price' => $expiredPlan->price,
                'billing_cycle' => 'monthly',
                'auto_renewal_enabled' => false,
                'meta' => ['demo_seed' => 'super-admin-billing'],
                'billing_meta' => ['demo_seed' => 'super-admin-billing'],
            ],
        );

        $invoiceDefinitions = [
            ['001', 'payment_success', 'processed', 699000, 0, 10, $activeSubscription],
            ['002', 'payment_success', 'processed', 699000, 0, 9, $activeSubscription],
            ['003', 'payment_success', 'processed', 1499000, 0, 8, $expiredSubscription],
            ['004', 'upcoming_renewal', 'sent', 699000, 0, 7, $activeSubscription],
            ['005', 'payment_success', 'processed', 699000, 0, 6, $activeSubscription],
            ['006', 'discount', 'generated', 599000, 100000, 5, $activeSubscription],
            ['007', 'payment_success', 'processed', 1499000, 0, 4, $expiredSubscription],
            ['008', 'upcoming_renewal', 'pending', 699000, 0, 3, $activeSubscription],
            ['009', 'extend', 'processed', 699000, 0, 2, $activeSubscription],
            ['010', 'trial', 'processed', 0, 0, 1, $activeSubscription],
            ['011', 'payment_success', 'expired', 1499000, 0, 0, $expiredSubscription],
            ['012', 'payment_success', 'processed', 699000, 0, 0, $activeSubscription],
        ];

        foreach ($invoiceDefinitions as [$sequence, $type, $status, $total, $discount, $monthsAgo, $subscription]) {
            $createdAt = $now->copy()
                ->subMonths($monthsAgo)
                ->startOfMonth()
                ->addDays(8)
                ->setTime(9 + ((int) $sequence % 7), 15);
            $invoice = BillingInvoice::withTrashed()->firstOrNew([
                'invoice_number' => "DEMO-BILLING-INV-{$sequence}",
            ]);

            if ($invoice->trashed()) {
                $invoice->restore();
            }

            $invoice->forceFill([
                'restaurant_id' => $restaurant->id,
                'restaurant_subscription_id' => $subscription->id,
                'type' => $type,
                'status' => $status,
                'currency' => $restaurant->currency ?? 'VND',
                'subtotal' => $total + $discount,
                'discount_amount' => $discount,
                'total' => $total,
                'issued_on' => $createdAt->toDateString(),
                'due_on' => $status === 'pending'
                    ? $createdAt->copy()->addDays(14)->toDateString()
                    : $createdAt->copy()->addDays(7)->toDateString(),
                'recipient_email' => $recipientEmail,
                'sent_at' => in_array($status, ['sent', 'processed', 'expired'], true)
                    ? $createdAt->copy()->addHours(2)
                    : null,
                'meta' => [
                    'demo_seed' => 'super-admin-billing',
                    'source' => 'Billing Center sample data',
                ],
            ]);
            $invoice->created_at = $createdAt;
            $invoice->updated_at = $createdAt;
            $invoice->save();
        }

        $webhookDefinitions = [
            ['001', 'processed', 'payment.success', 'DEMO-BILLING-ACTIVE-001', 1, 200, 'Payment processed successfully', 5],
            ['002', 'processed', 'subscription.renewed', 'DEMO-BILLING-RENEWAL-002', 1, 200, 'Subscription renewed', 4],
            ['003', 'received', 'payment.received', 'DEMO-BILLING-ACTIVE-003', 0, null, null, 3],
            ['004', 'failed', 'payment.failed', 'DEMO-BILLING-FAILED-004', 3, 500, 'Gateway timeout while validating payment', 2],
            ['005', 'orphaned', 'payment.success', 'DEMO-BILLING-MISSING-005', 1, 404, 'Transaction code not found', 2],
            ['006', 'processed', 'payment.success', 'DEMO-BILLING-ACTIVE-006', 1, 200, 'Payment processed successfully', 1],
            ['007', 'received', 'subscription.renewal', 'DEMO-BILLING-RENEWAL-007', 0, null, null, 0],
            ['008', 'failed', 'payment.failed', 'DEMO-BILLING-FAILED-008', 2, 502, 'Payment provider temporarily unavailable', 0],
        ];

        foreach ($webhookDefinitions as [$sequence, $status, $eventType, $transactionCode, $attempts, $responseCode, $errorMessage, $daysAgo]) {
            $createdAt = $now->copy()->subDays($daysAgo)->setTime(8 + ((int) $sequence % 8), 30);
            $webhook = PaymentWebhook::firstOrNew([
                'provider' => 'sepay',
                'transaction_code' => $transactionCode,
            ]);
            $webhook->forceFill([
                'event_type' => $eventType,
                'signature' => "demo-signature-{$sequence}",
                'status' => $status,
                'headers' => [
                    'content-type' => 'application/json',
                    'x-demo-seed' => 'super-admin-billing',
                ],
                'payload' => [
                    'event_id' => "demo-billing-event-{$sequence}",
                    'event_type' => $eventType,
                    'transaction_code' => $transactionCode,
                    'amount' => $sequence === '005' ? 699000 : 1499000,
                    'occurred_at' => $createdAt->toIso8601String(),
                ],
                'processed_at' => in_array($status, ['processed', 'orphaned'], true)
                    ? $createdAt->copy()->addMinutes(2)
                    : null,
                'error_message' => $errorMessage,
            ]);
            $webhook->created_at = $createdAt;
            $webhook->updated_at = $createdAt;
            $webhook->save();
        }

        $adjustmentDefinitions = [
            ['001', 'extend', 14, 0, null, 'Tặng thêm thời gian dùng thử cho nhà hàng demo.', 18],
            ['002', 'discount', 0, 100000, 'DEMO100K', 'Hỗ trợ giảm giá tháng đầu khi chuyển sang gói Pro.', 12],
            ['003', 'trial', 7, 0, null, 'Gia hạn dùng thử theo chương trình onboarding.', 7],
            ['004', 'extend', 30, 0, null, 'Bù thời gian gián đoạn dịch vụ trong đợt bảo trì.', 2],
            ['005', 'discount', 0, 150000, 'DEMOCARE150', 'Ưu đãi chăm sóc khách hàng doanh nghiệp.', 0],
        ];

        foreach ($adjustmentDefinitions as [$sequence, $type, $days, $discountAmount, $couponCode, $reason, $daysAgo]) {
            $createdAt = $now->copy()->subDays($daysAgo)->setTime(10 + ((int) $sequence % 6), 10);
            $adjustment = BillingAdjustment::withTrashed()->firstOrNew([
                'restaurant_id' => $restaurant->id,
                'reason' => "[DEMO-BILLING-{$sequence}] {$reason}",
            ]);

            if ($adjustment->trashed()) {
                $adjustment->restore();
            }

            $adjustment->forceFill([
                'restaurant_subscription_id' => $activeSubscription->id,
                'created_by' => $superAdminId,
                'type' => $type,
                'days' => $days,
                'discount_amount' => $discountAmount,
                'coupon_code' => $couponCode,
                'reason' => "[DEMO-BILLING-{$sequence}] {$reason}",
                'meta' => ['demo_seed' => 'super-admin-billing'],
            ]);
            $adjustment->created_at = $createdAt;
            $adjustment->updated_at = $createdAt;
            $adjustment->save();
        }

        $this->command?->info(
            'Đã tạo/cập nhật dữ liệu mẫu Billing Center: 12 hóa đơn, 8 webhook và 5 điều chỉnh.',
        );
    }
}
