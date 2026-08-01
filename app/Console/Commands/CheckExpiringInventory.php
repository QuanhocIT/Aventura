<?php

namespace App\Console\Commands;

use App\Models\InventoryBatch;
use App\Models\Restaurant;
use App\Services\EmailMicroserviceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiringInventory extends Command
{
    protected $signature = 'inventory:check-expiry';

    protected $description = 'Check for expiring inventory batches and send alerts';

    public function handle(EmailMicroserviceClient $email): int
    {
        // Mark expired batches
        $expired = InventoryBatch::withoutGlobalScopes()
            ->expired()
            ->update(['status' => 'expired']);

        $this->info("Marked {$expired} batches as expired.");

        // Find expiring soon (next 3 days) grouped by restaurant
        $expiring = InventoryBatch::withoutGlobalScopes()
            ->expiringSoon(3)
            ->with(['ingredient:id,name', 'restaurant.owner'])
            ->get()
            ->groupBy('restaurant_id');

        $alertCount = 0;

        foreach ($expiring as $restaurantId => $batches) {
            $restaurant = Restaurant::find($restaurantId);
            $owner = $restaurant?->owner;

            if (! $owner?->email) {
                continue;
            }

            $items = $batches->map(fn ($b) => [
                'name' => $b->ingredient?->name ?? 'N/A',
                'batch' => $b->batch_number ?? '-',
                'quantity' => (float) $b->quantity_remaining,
                'expiry_date' => $b->expiry_date->format('d/m/Y'),
                'days_left' => (int) now()->diffInDays($b->expiry_date, false),
            ])->all();

            try {
                $email->sendLowStockAlert([
                    'recipient_email' => $owner->email,
                    'restaurant_name' => $restaurant->name,
                    'items' => $items,
                    'alert_type' => 'expiry',
                ]);
                $alertCount++;
            } catch (\Throwable $e) {
                Log::warning('CheckExpiringInventory: failed to send alert', [
                    'restaurant_id' => $restaurantId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent expiry alerts to {$alertCount} restaurants.");

        return self::SUCCESS;
    }
}
