<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PromotionTrigger;
use App\Services\PromotionTriggerService;
use Illuminate\Console\Command;

class CheckInactiveCustomerTriggers extends Command
{
    protected $signature = 'promotions:check-inactive';
    protected $description = 'Fire triggers for customers inactive 30+ days';

    public function handle(PromotionTriggerService $service): int
    {
        $restaurants = PromotionTrigger::withoutGlobalScopes()
            ->where('event_type', 'inactive_30_days')
            ->where('is_active', true)
            ->pluck('restaurant_id')
            ->unique();

        $fired = 0;

        foreach ($restaurants as $restaurantId) {
            $customers = Customer::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where(function ($q) {
                    $q->where('last_order_at', '<=', now()->subDays(30))
                      ->orWhereNull('last_order_at');
                })
                ->get();

            foreach ($customers as $customer) {
                $service->checkInactivity($customer, $restaurantId);
                $fired++;
            }
        }

        $this->info("Checked {$fired} inactive customers.");

        return self::SUCCESS;
    }
}
