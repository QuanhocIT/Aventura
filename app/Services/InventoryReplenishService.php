<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InventoryReplenishService
{
    /**
     * Thu thập lịch sử tiêu thụ và dự báo tồn kho nguyên liệu.
     */
    public function getForecastAndReplenish(int $restaurantId, ?int $branchId = null): array
    {
        // 1. Get all active ingredients of the restaurant
        $ingredients = Ingredient::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                ->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->get();

        if ($ingredients->isEmpty()) {
            return [];
        }

        // 2. Fetch daily usage history from completed orders in the last 30 days using BOM (recipes)
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(30);

        // Fetch completed orders
        $orders = Order::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                ->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->with(['items.product.recipes'])
            ->get();

        // Calculate daily consumption per ingredient
        $dailyUsage = []; // [ingredient_id][date] = quantity
        foreach ($orders as $order) {
            $date = Carbon::parse($order->completed_at)->toDateString();
            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product) {
                    continue;
                }

                $recipes = $product->recipes;
                foreach ($recipes as $recipe) {
                    $ingId = $recipe->ingredient_id;
                    $qtyUsed = $item->quantity * $recipe->quantity * (1 + ($recipe->waste_rate ?? 0) / 100);

                    if (! isset($dailyUsage[$ingId])) {
                        $dailyUsage[$ingId] = [];
                    }
                    if (! isset($dailyUsage[$ingId][$date])) {
                        $dailyUsage[$ingId][$date] = 0;
                    }
                    $dailyUsage[$ingId][$date] += $qtyUsed;
                }
            }
        }

        // 3. Prepare payload
        $inventories = Inventory::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get()
            ->keyBy('ingredient_id');

        $payload = [];
        foreach ($ingredients as $ing) {
            // Get current stock
            $inventory = $inventories->get($ing->id);
            $currentStock = $inventory ? (float) $inventory->quantity_on_hand : 0.0;

            // Prepare history payload
            $historyData = [];
            $ingHistory = $dailyUsage[$ing->id] ?? [];
            foreach ($ingHistory as $date => $qty) {
                $historyData[] = [
                    'date' => $date,
                    'quantity' => (float) $qty,
                ];
            }

            // If no history, add empty array
            $payload[] = [
                'ingredient_id' => $ing->id,
                'ingredient_name' => $ing->name,
                'sku' => $ing->sku,
                'current_stock' => $currentStock,
                'min_stock_level' => (float) ($ing->min_stock_level ?? 5.0),
                'unit_symbol' => $ing->unit?->symbol ?? 'cái',
                'history' => $historyData,
            ];
        }

        // 4. Call FastAPI
        $baseUrl = config('services.analytics.url');
        $url = "{$baseUrl}/api/analytics/inventory-forecast-replenish";

        $forecastResults = app(CircuitBreaker::class)->for('analytics_service')->attempt(
            function () use ($url, $payload) {
                $response = Http::timeout(5)
                    ->withHeaders(app(AnalyticsServiceClient::class)->authHeaders())
                    ->post($url, [
                        'ingredients' => $payload,
                    ]);

                if (! $response->successful()) {
                    Log::warning('InventoryReplenishService: Python service returned code '.$response->status());
                    throw new \RuntimeException("Inventory replenish service trả lỗi HTTP {$response->status()}");
                }

                return $response->json()['forecasts'] ?? null;
            },
            fn () => null
        );

        // Fallback to PHP if Python fails
        if (! $forecastResults) {
            $forecastResults = $this->fallbackForecast($payload);
        }

        return $forecastResults;
    }

    /**
     * Tự động tạo PO nháp cho những nguyên liệu có cảnh báo từ AI.
     */
    public function generateReplenishmentOrders(int $restaurantId, array $forecasts, int $creatorId, ?int $branchId = null): array
    {
        $createdPos = [];

        // Group replenishment items by supplier
        $replenishBySupplier = []; // [supplier_id][] = ['ingredient_id' => x, 'qty' => y, 'price' => z]

        $ingredientIds = collect($forecasts)->pluck('ingredient_id')->toArray();
        $ingredients = Ingredient::whereIn('id', $ingredientIds)
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                ->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->get()
            ->keyBy('id');

        foreach ($forecasts as $f) {
            if (! $f['needs_replenishment'] || $f['suggested_replenish_quantity'] <= 0) {
                continue;
            }

            $ingredient = $ingredients->get($f['ingredient_id']);
            if (! $ingredient || ! $ingredient->supplier_id) {
                continue;
            }

            $supplierId = $ingredient->supplier_id;
            if (! isset($replenishBySupplier[$supplierId])) {
                $replenishBySupplier[$supplierId] = [];
            }

            $replenishBySupplier[$supplierId][] = [
                'ingredient_id' => $ingredient->id,
                'quantity' => $f['suggested_replenish_quantity'],
                'price_per_unit' => (float) $ingredient->average_cost,
                'total_cost' => $f['suggested_replenish_quantity'] * (float) $ingredient->average_cost,
            ];
        }

        if (empty($replenishBySupplier)) {
            return [];
        }

        $supplierIds = array_keys($replenishBySupplier);
        $suppliers = Supplier::whereIn('id', $supplierIds)->get()->keyBy('id');

        // Create POs
        DB::transaction(function () use ($restaurantId, $branchId, $replenishBySupplier, $creatorId, $suppliers, &$createdPos) {
            foreach ($replenishBySupplier as $supplierId => $items) {
                $supplier = $suppliers->get($supplierId);
                if (! $supplier) {
                    continue;
                }

                $totalAmount = array_sum(array_column($items, 'total_cost'));

                $po = PurchaseOrder::create([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'supplier_id' => $supplier->id,
                    'po_number' => 'PO-'.now()->format('Ymd').'-AI-'.strtoupper(Str::random(4)),
                    'status' => 'pending_approval', // Draft PO awaiting Owner approval
                    'total_amount' => $totalAmount,
                    'created_by' => $creatorId,
                    'notes' => 'Đơn hàng tự động đề xuất từ AI Dự báo tồn kho Aventura.',
                    'delivery_due_date' => Carbon::now()->addDays(2), // default lead time 2 days
                ]);

                foreach ($items as $item) {
                    $po->items()->create([
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity_ordered' => $item['quantity'],
                        'price_per_unit' => $item['price_per_unit'],
                        'total_cost' => $item['total_cost'],
                    ]);
                }

                $createdPos[] = $po;
            }
        });

        return $createdPos;
    }

    /**
     * Fallback PHP forecasting algorithm if Python is offline.
     */
    private function fallbackForecast(array $payload): array
    {
        $forecasts = [];
        foreach ($payload as $p) {
            $currentStock = $p['current_stock'];
            $minStock = $p['min_stock_level'];
            $history = $p['history'];

            if (empty($history)) {
                $avgDaily = 1.0;
                $daysRemaining = $currentStock / $avgDaily;
            } else {
                $qtys = array_column($history, 'quantity');
                $avgDaily = array_sum($qtys) / count($qtys);
                if ($avgDaily <= 0) {
                    $avgDaily = 0.5;
                }
                $stockToConsume = max(0.0, $currentStock - $minStock);
                $daysRemaining = $stockToConsume / $avgDaily;
            }

            $needsReplenishment = $currentStock <= $minStock || $daysRemaining <= 7.0;

            $suggestedQty = 0.0;
            if ($needsReplenishment) {
                $suggestedQty = max(1.0, ($minStock * 1.5) - $currentStock + ($avgDaily * 7));
                $suggestedQty = round($suggestedQty, 3);
            }

            $forecasts[] = [
                'ingredient_id' => $p['ingredient_id'],
                'ingredient_name' => $p['ingredient_name'],
                'sku' => $p['sku'],
                'current_stock' => $currentStock,
                'min_stock_level' => $minStock,
                'unit_symbol' => $p['unit_symbol'],
                'average_daily_usage' => round($avgDaily, 3),
                'days_remaining' => round($daysRemaining, 1),
                'needs_replenishment' => $needsReplenishment,
                'suggested_replenish_quantity' => $suggestedQty,
            ];
        }

        return $forecasts;
    }
}
