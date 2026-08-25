<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
    public function getProgram(int $restaurantId): ?LoyaltyProgram
    {
        $program = LoyaltyProgram::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (! $program) {
            $program = new LoyaltyProgram([
                'restaurant_id' => $restaurantId,
                'is_active' => true,
                'points_per_vnd' => 0.0001,
                'point_value_vnd' => 100.00,
                'min_redeem_points' => 10,
                'birthday_bonus_points' => 50,
                'enable_qr_card' => true,
            ]);
        }

        return $program;
    }

    public function earnPoints(Customer $customer, Order $order, float $orderTotal): int
    {
        $program = $this->getProgram($customer->restaurant_id);

        if (! $program || ! $program->is_active || $orderTotal <= 0) {
            return 0;
        }

        // Fix #2: Idempotent check — tránh tích điểm 2 lần khi updateOrderStatus
        // và payOrder chạy đồng thời trên cùng 1 đơn hàng.
        $alreadyEarned = LoyaltyTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', 'earn')
            ->exists();

        if ($alreadyEarned) {
            return 0;
        }

        $tier = $customer->loyaltyTier;
        $multiplier = $tier ? (float) $tier->points_multiplier : 1.0;

        // Calculate points earned per product rule if configured
        $order->loadMissing('items.product');
        $itemPoints = 0;
        foreach ($order->items as $item) {
            $prodEarnPoints = (int) ($item->product?->earn_points ?? 0);
            if ($prodEarnPoints > 0) {
                $itemPoints += $prodEarnPoints * (int) $item->quantity;
            }
        }

        if ($itemPoints > 0) {
            $pointsEarned = (int) round($itemPoints * $multiplier);
        } else {
            $pointsEarned = (int) floor($orderTotal * (float) $program->points_per_vnd * $multiplier);
        }

        if ($pointsEarned <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($customer, $order, $program, $pointsEarned) {
            $customer = Customer::lockForUpdate()->find($customer->id);
            $newBalance = $customer->loyalty_points + $pointsEarned;

            $expiresAt = $program->points_expiry_days
                ? now()->addDays($program->points_expiry_days)
                : null;

            LoyaltyTransaction::create([
                'restaurant_id' => $customer->restaurant_id,
                'customer_id' => $customer->id,
                'type' => 'earn',
                'points' => $pointsEarned,
                'balance_after' => $newBalance,
                'description' => "Tích điểm đơn hàng #{$order->order_number}",
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'expires_at' => $expiresAt,
                'points_remaining' => $pointsEarned,
            ]);

            $customer->update(['loyalty_points' => $newBalance]);

            return $pointsEarned;
        });
    }

    public function redeemPoints(Customer $customer, int $pointsToRedeem, ?Order $order = null): float
    {
        $program = $this->getProgram($customer->restaurant_id);

        if (! $program || ! $program->is_active || $pointsToRedeem <= 0) {
            return 0;
        }

        if ($pointsToRedeem < $program->min_redeem_points) {
            return 0;
        }

        return DB::transaction(function () use ($customer, $pointsToRedeem, $order, $program) {
            $customer = Customer::lockForUpdate()->find($customer->id);

            $actual = min($pointsToRedeem, $customer->loyalty_points);
            if ($actual <= 0) {
                return 0;
            }

            // FIFO deduction
            $this->deductFifo($customer->id, $actual);

            $newBalance = $customer->loyalty_points - $actual;

            LoyaltyTransaction::create([
                'restaurant_id' => $customer->restaurant_id,
                'customer_id' => $customer->id,
                'type' => 'redeem',
                'points' => -$actual,
                'balance_after' => $newBalance,
                'description' => $order
                    ? "Quy đổi điểm cho đơn #{$order->order_number}"
                    : 'Quy đổi điểm thưởng',
                'reference_type' => $order ? Order::class : null,
                'reference_id' => $order?->id,
            ]);

            $customer->update(['loyalty_points' => $newBalance]);

            return $actual * (float) $program->point_value_vnd;
        });
    }

    /**
     * Đổi điểm lấy món ăn (Product Point Redemption) và khấu trừ số điểm tương ứng của khách hàng.
     */
    public function redeemProductWithPoints(Customer $customer, Product $product, int $quantity = 1, ?Order $order = null): float
    {
        $redeemPointsRequired = (int) ($product->redeem_points ?? 0);
        if ($redeemPointsRequired <= 0) {
            throw ValidationException::withMessages(['product' => "Món {$product->name} chưa được thiết lập số điểm đổi món."]);
        }

        $totalPointsNeeded = $redeemPointsRequired * $quantity;

        return DB::transaction(function () use ($customer, $product, $quantity, $totalPointsNeeded, $order) {
            $customer = Customer::lockForUpdate()->find($customer->id);

            if ($customer->loyalty_points < $totalPointsNeeded) {
                throw ValidationException::withMessages([
                    'points' => "Khách hàng hiện có {$customer->loyalty_points} điểm, không đủ {$totalPointsNeeded} điểm để đổi {$quantity} suất {$product->name}.",
                ]);
            }

            // FIFO deduction of points
            $this->deductFifo($customer->id, $totalPointsNeeded);

            $newBalance = $customer->loyalty_points - $totalPointsNeeded;
            $discountAmount = (float) $product->price * $quantity;

            LoyaltyTransaction::create([
                'restaurant_id' => $customer->restaurant_id,
                'customer_id' => $customer->id,
                'type' => 'redeem',
                'points' => -$totalPointsNeeded,
                'balance_after' => $newBalance,
                'description' => "Đổi {$quantity}x món [{$product->name}] (-{$totalPointsNeeded} điểm, Giảm ".number_format($discountAmount).' đ)',
                'reference_type' => $order ? Order::class : Product::class,
                'reference_id' => $order ? $order->id : $product->id,
            ]);

            $customer->update(['loyalty_points' => $newBalance]);

            return $discountAmount;
        });
    }

    public function redeemReward(Customer $customer, LoyaltyReward $reward): array
    {
        return DB::transaction(function () use ($customer, $reward) {
            $customer = Customer::lockForUpdate()->find($customer->id);
            $reward = LoyaltyReward::lockForUpdate()->find($reward->id);

            if (! $reward->is_active) {
                throw ValidationException::withMessages(['reward' => 'Phần thưởng không khả dụng.']);
            }

            if ($reward->max_redemptions && $reward->current_redemptions >= $reward->max_redemptions) {
                throw ValidationException::withMessages(['reward' => 'Phần thưởng đã hết số lượng.']);
            }

            if ($customer->loyalty_points < $reward->points_cost) {
                throw ValidationException::withMessages(['points' => 'Không đủ điểm để đổi phần thưởng này.']);
            }

            $this->deductFifo($customer->id, $reward->points_cost);

            $newBalance = $customer->loyalty_points - $reward->points_cost;

            LoyaltyTransaction::create([
                'restaurant_id' => $customer->restaurant_id,
                'customer_id' => $customer->id,
                'type' => 'redeem',
                'points' => -$reward->points_cost,
                'balance_after' => $newBalance,
                'description' => "Đổi phần thưởng: {$reward->name}",
                'reference_type' => LoyaltyReward::class,
                'reference_id' => $reward->id,
            ]);

            $customer->update(['loyalty_points' => $newBalance]);
            $reward->increment('current_redemptions');

            return ['success' => true, 'reward' => $reward->name, 'points_spent' => $reward->points_cost];
        });
    }

    public function expirePoints(): int
    {
        $totalExpired = 0;

        // Dùng chunk để tránh timeout/OOM khi có nhiều khách cần expire — xử lý 100 customer/lần
        LoyaltyTransaction::where('type', 'earn')
            ->where('points_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->distinct()
            ->orderBy('customer_id')
            ->pluck('customer_id')
            ->chunk(100)
            ->each(function ($customerIds) use (&$totalExpired) {
                foreach ($customerIds as $customerId) {
                    DB::transaction(function () use ($customerId, &$totalExpired) {
                        $customer = Customer::lockForUpdate()->find($customerId);
                        if (! $customer) {
                            return;
                        }

                        $expiredTxs = LoyaltyTransaction::where('customer_id', $customerId)
                            ->where('type', 'earn')
                            ->where('points_remaining', '>', 0)
                            ->where('expires_at', '<=', now())
                            ->lockForUpdate()
                            ->get();

                        $pointsToExpire = $expiredTxs->sum('points_remaining');
                        if ($pointsToExpire <= 0) {
                            return;
                        }

                        foreach ($expiredTxs as $tx) {
                            $tx->update(['points_remaining' => 0]);
                        }

                        $newBalance = max(0, $customer->loyalty_points - $pointsToExpire);

                        LoyaltyTransaction::create([
                            'restaurant_id' => $customer->restaurant_id,
                            'customer_id' => $customer->id,
                            'type' => 'expire',
                            'points' => -$pointsToExpire,
                            'balance_after' => $newBalance,
                            'description' => "Hết hạn {$pointsToExpire} điểm",
                        ]);

                        $customer->update(['loyalty_points' => $newBalance]);
                        $totalExpired += $pointsToExpire;
                    });
                }
            });

        return $totalExpired;
    }

    public function grantBirthdayBonus(Customer $customer, LoyaltyProgram $program): void
    {
        if ($program->birthday_bonus_points <= 0) {
            return;
        }

        $alreadyGranted = LoyaltyTransaction::where('customer_id', $customer->id)
            ->where('type', 'birthday')
            ->whereYear('created_at', now()->year)
            ->exists();

        if ($alreadyGranted) {
            return;
        }

        DB::transaction(function () use ($customer, $program) {
            $customer = Customer::lockForUpdate()->find($customer->id);
            $bonus = $program->birthday_bonus_points;
            $newBalance = $customer->loyalty_points + $bonus;

            LoyaltyTransaction::create([
                'restaurant_id' => $customer->restaurant_id,
                'customer_id' => $customer->id,
                'type' => 'birthday',
                'points' => $bonus,
                'balance_after' => $newBalance,
                'description' => 'Quà sinh nhật — chúc mừng sinh nhật!',
                'points_remaining' => $bonus,
                'expires_at' => $program->points_expiry_days
                    ? now()->addDays($program->points_expiry_days)
                    : null,
            ]);

            $customer->update(['loyalty_points' => $newBalance]);
        });

        try {
            app(EmailMicroserviceClient::class)->sendBirthdayVoucher([
                'recipient_email' => $customer->email,
                'recipient_name' => $customer->full_name,
                'restaurant_name' => $customer->restaurant?->name ?? 'Nhà hàng',
                'bonus_points' => $program->birthday_bonus_points,
            ]);
        } catch (\Throwable $e) {
            Log::warning('LoyaltyService: gửi birthday email thất bại', ['error' => $e->getMessage()]);
        }
    }

    public function adjustPoints(Customer $customer, int $points, string $reason, ?User $performer = null): void
    {
        DB::transaction(function () use ($customer, $points, $reason, $performer) {
            $customer = Customer::lockForUpdate()->find($customer->id);
            $newBalance = max(0, $customer->loyalty_points + $points);

            LoyaltyTransaction::create([
                'restaurant_id' => $customer->restaurant_id,
                'customer_id' => $customer->id,
                'type' => 'adjust',
                'points' => $points,
                'balance_after' => $newBalance,
                'description' => $reason,
                'performed_by' => $performer?->id,
            ]);

            $customer->update(['loyalty_points' => $newBalance]);
        });
    }

    public function refundPoints(Customer $customer, Order $order): void
    {
        $earnTx = LoyaltyTransaction::where('customer_id', $customer->id)
            ->where('type', 'earn')
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->first();

        if (! $earnTx) {
            return;
        }

        DB::transaction(function () use ($customer, $order, $earnTx) {
            $customer = Customer::lockForUpdate()->find($customer->id);
            $pointsToDeduct = $earnTx->points;
            $newBalance = max(0, $customer->loyalty_points - $pointsToDeduct);

            LoyaltyTransaction::create([
                'restaurant_id' => $customer->restaurant_id,
                'customer_id' => $customer->id,
                'type' => 'refund',
                'points' => -$pointsToDeduct,
                'balance_after' => $newBalance,
                'description' => "Hoàn trả điểm — đơn #{$order->order_number} bị hoàn tiền",
                'reference_type' => Order::class,
                'reference_id' => $order->id,
            ]);

            $earnTx->update(['points_remaining' => 0]);
            $customer->update(['loyalty_points' => $newBalance]);
        });
    }

    public function recalculateTier(Customer $customer): void
    {
        $tiers = LoyaltyTier::withoutGlobalScopes()
            ->where('restaurant_id', $customer->restaurant_id)
            ->orderBy('min_spent', 'desc')
            ->get();

        if ($tiers->isEmpty()) {
            return;
        }

        $totalSpent = (float) ($customer->total_spent ?? 0);
        $matchedTier = null;

        foreach ($tiers as $tier) {
            if ($totalSpent >= (float) $tier->min_spent) {
                $matchedTier = $tier;
                break;
            }
        }

        $tierId = $matchedTier?->id;
        $levelName = $matchedTier?->slug ?? 'silver';

        if ($customer->loyalty_tier_id !== $tierId) {
            $customer->update([
                'loyalty_tier_id' => $tierId,
                'membership_level' => $levelName,
            ]);
        }
    }

    public function generateLoyaltyQr(Customer $customer): string
    {
        if (! $customer->loyalty_qr_token) {
            $customer->update(['loyalty_qr_token' => Str::random(32)]);
            $customer->refresh();
        }

        $qrService = app(QrCodeService::class);
        $token = app(CustomerPortalAccessService::class)->issue(
            (int) $customer->restaurant_id,
            (string) $customer->phone,
        );
        $url = url("/customer/portal/dashboard/{$customer->restaurant_id}/{$customer->phone}?token={$token}");

        return $qrService->renderSvg($url);
    }

    public function getDashboardMetrics(int $restaurantId): array
    {
        return Cache::remember("loyalty_dashboard:{$restaurantId}", 300, function () use ($restaurantId) {
            $totalMembers = Customer::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('loyalty_points', '>', 0)
                ->count();

            $totalPointsIssued = (int) LoyaltyTransaction::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('type', 'earn')
                ->sum('points');

            $totalPointsRedeemed = (int) abs(LoyaltyTransaction::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('type', 'redeem')
                ->sum('points'));

            $redemptionRate = $totalPointsIssued > 0
                ? round(($totalPointsRedeemed / $totalPointsIssued) * 100, 1)
                : 0;

            $tierDistribution = LoyaltyTier::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->withCount('customers')
                ->ordered()
                ->get()
                ->map(fn ($t) => ['name' => $t->name, 'color' => $t->color, 'count' => $t->customers_count]);

            $recentTransactions = LoyaltyTransaction::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->with('customer:id,full_name,phone')
                ->latest()
                ->take(20)
                ->get();

            return compact('totalMembers', 'totalPointsIssued', 'totalPointsRedeemed', 'redemptionRate', 'tierDistribution', 'recentTransactions');
        });
    }

    private function deductFifo(int $customerId, int $points): void
    {
        $earnTxs = LoyaltyTransaction::where('customer_id', $customerId)
            ->where('type', 'earn')
            ->where('points_remaining', '>', 0)
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->get();

        $remaining = $points;
        foreach ($earnTxs as $tx) {
            if ($remaining <= 0) {
                break;
            }
            $deduct = min($remaining, $tx->points_remaining);
            $tx->decrement('points_remaining', $deduct);
            $remaining -= $deduct;
        }
    }
}
