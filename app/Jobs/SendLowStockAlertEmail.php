<?php

namespace App\Jobs;

use App\Models\Restaurant;
use App\Models\Inventory;
use App\Services\EmailMicroserviceClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendLowStockAlertEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(
        public readonly int $restaurantId
    ) {}

    public function handle(EmailMicroserviceClient $client): void
    {
        // 1. Clear pending flag
        Cache::forget("low_stock_pending:{$this->restaurantId}");

        // 2. Set cooldown lock to prevent another mail for 30 minutes
        Cache::put("low_stock_cooldown:{$this->restaurantId}", true, 1800);

        // 3. Find all inventories of this restaurant where stock is low
        $restaurant = Restaurant::with(['owner'])->find($this->restaurantId);
        if (!$restaurant) {
            return;
        }

        // Fetch low stock items: quantity_on_hand < min_stock_level
        $lowStockInventories = Inventory::where('inventories.restaurant_id', $this->restaurantId)
            ->join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
            ->whereColumn('inventories.quantity_on_hand', '<', 'ingredients.min_stock_level')
            ->select('inventories.*', 'ingredients.name as ingredient_name', 'ingredients.min_stock_level', 'ingredients.unit_id')
            ->with(['ingredient.unit'])
            ->get();

        if ($lowStockInventories->isEmpty()) {
            Log::info("SendLowStockAlertEmail: No low stock ingredients found for restaurant {$this->restaurantId}");
            return;
        }

        $items = [];
        foreach ($lowStockInventories as $inv) {
            $items[] = [
                'name'    => $inv->ingredient_name ?? ($inv->ingredient ? $inv->ingredient->name : 'Unknown'),
                'current' => (float) $inv->quantity_on_hand,
                'unit'    => $inv->ingredient && $inv->ingredient->unit ? $inv->ingredient->unit->symbol : 'units',
                'min'     => (float) $inv->min_stock_level,
            ];
        }

        // Send to owner or fallback
        $recipientEmail = $restaurant->owner ? $restaurant->owner->email : $restaurant->email;
        if (empty($recipientEmail)) {
            Log::warning("SendLowStockAlertEmail: No recipient email found for restaurant {$this->restaurantId}");
            return;
        }

        $sent = $client->sendLowStockAlert([
            'recipient_email' => $recipientEmail,
            'recipient_name'  => $restaurant->owner ? $restaurant->owner->name : 'Owner',
            'restaurant_name' => $restaurant->name,
            'items'           => $items,
            'inventory_url'   => url('/inventory'),
        ]);

        Log::info('SendLowStockAlertEmail: ' . ($sent ? 'success' : 'failed'), [
            'restaurant_id' => $this->restaurantId,
            'recipient'     => $recipientEmail,
            'items_count'   => count($items),
        ]);
    }

    public function tags(): array
    {
        return ["restaurant:{$this->restaurantId}", "low-stock-alert"];
    }
}
