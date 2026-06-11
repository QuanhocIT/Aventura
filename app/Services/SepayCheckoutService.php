<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SepayCheckoutService
{
    /**
     * @return array{subscription:RestaurantSubscription,payment_url:string,transaction_code:string}
     */
    public function createCheckout(Restaurant $restaurant, SubscriptionPlan $plan, string $source = 'upgrade'): array
    {
        if ((float) $plan->price <= 0) {
            throw new RuntimeException('Goi mien phi khong can thanh toan.');
        }

        $subscription = DB::transaction(function () use ($restaurant, $plan, $source): RestaurantSubscription {
            $activeSubscription = $restaurant->activeSubscription()->lockForUpdate()->first();
            $transactionCode = $this->generateTransactionCode($restaurant->id);
            $startsAt = now();
            $endsAt = $this->calculateEndsAt($activeSubscription);

            return RestaurantSubscription::query()->create([
                'restaurant_id' => $restaurant->id,
                'plan_id' => $plan->id,
                'status' => 'expired',
                'started_at' => $startsAt,
                'ended_at' => $endsAt,
                'renewal_at' => $endsAt,
                'price' => $plan->price,
                'transaction_code' => $transactionCode,
                'meta' => [
                    'source' => $source,
                    'plan_code' => $plan->code,
                    'pending_payment' => true,
                ],
                'billing_meta' => [
                    'provider' => 'sepay',
                    'checkout_created_at' => now()->toIso8601String(),
                ],
            ]);
        });

        return [
            'subscription' => $subscription,
            'payment_url' => $this->paymentUrl($restaurant, $subscription),
            'transaction_code' => (string) $subscription->transaction_code,
        ];
    }

    public function paymentUrl(Restaurant $restaurant, RestaurantSubscription $subscription): string
    {
        $bank = (string) config('services.sepay.bank');
        $accountNumber = (string) config('services.sepay.account_number');
        $template = (string) config('services.sepay.qr_template', 'compact');
        $baseUrl = rtrim((string) config('services.sepay.checkout_url', 'https://qr.sepay.vn/img'), '/');

        if ($bank === '' || $accountNumber === '') {
            throw new RuntimeException('Chua cau hinh SEPAY_BANK hoac SEPAY_ACCOUNT_NUMBER.');
        }

        $description = $subscription->transaction_code;
        $accountName = (string) config('services.sepay.account_name');

        return $baseUrl.'?'.http_build_query([
            'acc' => $accountNumber,
            'bank' => $bank,
            'amount' => (int) $subscription->price,
            'des' => $description,
            'template' => $template,
            'accountName' => $accountName !== '' ? $accountName : $restaurant->name,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function calculateEndsAt(?RestaurantSubscription $activeSubscription): CarbonInterface
    {
        $base = $activeSubscription?->ended_at && $activeSubscription->ended_at->isFuture()
            ? $activeSubscription->ended_at->copy()
            : now();

        return $base->addMonth();
    }

    private function generateTransactionCode(int $restaurantId): string
    {
        do {
            $code = 'AVT'.str_pad((string) $restaurantId, 4, '0', STR_PAD_LEFT).Str::upper(Str::random(8));
        } while (RestaurantSubscription::query()->where('transaction_code', $code)->exists());

        return $code;
    }
}
