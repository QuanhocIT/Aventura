<?php

namespace App\Console\Commands;

use App\Events\Kitchen\KitchenUpdated;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AlertOverdueKitchenOrders extends Command
{
    protected $signature = 'kitchen:alert-overdue-orders';

    protected $description = 'Alert kitchen staff when an order has been preparing for more than 30 minutes';

    public function handle()
    {
        $this->info('Checking for overdue kitchen orders...');
        $cutoff = Carbon::now()->subMinutes(30);

        // Find orders in 'preparing' status that have not been updated for 30 minutes
        $overdueOrders = Order::withoutGlobalScopes()
            ->where('status', 'preparing')
            ->where('updated_at', '<', $cutoff)
            ->get();

        $alertedRestaurants = [];

        foreach ($overdueOrders as $order) {
            try {
                $this->info("Order ID {$order->id} (Number: {$order->order_number}) is overdue in preparing.");

                if (! in_array($order->restaurant_id, $alertedRestaurants)) {
                    event(new KitchenUpdated($order->restaurant_id));
                    $alertedRestaurants[] = $order->restaurant_id;
                    $this->info("Broadcasted KitchenUpdated event for restaurant ID {$order->restaurant_id}");
                }
            } catch (\Throwable $e) {
                Log::error("Error alerting overdue order ID {$order->id}: ".$e->getMessage());
                $this->error("Error alerting overdue order ID {$order->id}: ".$e->getMessage());
            }
        }

        $this->info('Completed checking overdue orders. Alerted '.count($alertedRestaurants).' restaurants.');

        return Command::SUCCESS;
    }
}
