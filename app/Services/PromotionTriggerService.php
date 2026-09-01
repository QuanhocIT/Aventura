<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\Order;
use App\Models\PromotionTrigger;
use Illuminate\Support\Str;

class PromotionTriggerService
{
    public function checkFirstOrder(Customer $customer, Order $order): void
    {
        $orderCount = Order::withoutGlobalScopes()
            ->where('restaurant_id', $order->restaurant_id)
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->count();

        if ($orderCount !== 1) {
            return;
        }

        $triggers = PromotionTrigger::withoutGlobalScopes()
            ->where('restaurant_id', $order->restaurant_id)
            ->where('event_type', 'first_order')
            ->where('is_active', true)
            ->get();

        foreach ($triggers as $trigger) {
            $this->fireTrigger($trigger, $customer);
        }
    }

    public function checkOrderMilestone(Customer $customer, int $restaurantId): void
    {
        $orderCount = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->count();

        $triggers = PromotionTrigger::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('event_type', 'order_milestone')
            ->where('milestone_count', $orderCount)
            ->where('is_active', true)
            ->get();

        foreach ($triggers as $trigger) {
            $this->fireTrigger($trigger, $customer);
        }
    }

    public function checkBirthday(Customer $customer, int $restaurantId): void
    {
        $triggers = PromotionTrigger::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('event_type', 'birthday')
            ->where('is_active', true)
            ->get();

        foreach ($triggers as $trigger) {
            $alreadySent = CustomerCoupon::withoutGlobalScopes()
                ->where('trigger_id', $trigger->id)
                ->where('customer_id', $customer->id)
                ->where('created_at', '>=', now()->startOfYear())
                ->exists();

            if (! $alreadySent) {
                $this->fireTrigger($trigger, $customer);
            }
        }
    }

    public function checkInactivity(Customer $customer, int $restaurantId): void
    {
        $triggers = PromotionTrigger::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('event_type', 'inactive_30_days')
            ->where('is_active', true)
            ->get();

        foreach ($triggers as $trigger) {
            $alreadySent = CustomerCoupon::withoutGlobalScopes()
                ->where('trigger_id', $trigger->id)
                ->where('customer_id', $customer->id)
                ->where('created_at', '>=', now()->subDays(60))
                ->exists();

            if (! $alreadySent) {
                $this->fireTrigger($trigger, $customer);
            }
        }
    }

    public function fireTrigger(PromotionTrigger $trigger, Customer $customer): ?CustomerCoupon
    {
        $code = strtoupper(Str::random(3)).'-'.strtoupper(Str::random(6));

        return CustomerCoupon::create([
            'restaurant_id' => $trigger->restaurant_id,
            'customer_id' => $customer->id,
            'branch_id' => $customer->branch_id,
            'promotion_id' => $trigger->promotion_id,
            'trigger_id' => $trigger->id,
            'code' => $code,
            'discount_type' => $trigger->discount_type,
            'discount_value' => $trigger->discount_value,
            'max_discount_amount' => $trigger->max_discount_amount,
            'min_order_amount' => 0,
            'expires_at' => now()->addDays($trigger->validity_days),
            'status' => 'available',
            'source' => 'trigger',
        ]);
    }
}
