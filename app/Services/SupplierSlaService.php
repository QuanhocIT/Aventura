<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPriceHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Tính chỉ số SLA đối soát & giao nhận của nhà cung cấp (tỉ lệ đúng giờ, độ
 * chính xác đơn hàng, biến động giá nguyên liệu, đánh giá gần nhất) — tách khỏi
 * SupplierController để controller chỉ còn lo HTTP, theo đúng khuôn đã áp dụng
 * cho PlatformMetricsService (SuperAdmin\DashboardController).
 */
class SupplierSlaService
{
    /**
     * @param  array{pos?: Collection, ingredients?: Collection, histories?: Collection}|null  $context
     *                                                                                                   Context preload theo supplier_id (dùng khi tính hàng loạt cho nhiều nhà cung cấp — xem calculateForDashboard()).
     */
    public function calculateForSupplier(Supplier $supplier, ?array $context = null): array
    {
        $pos = isset($context['pos'])
            ? ($context['pos']->get($supplier->id) ?? collect())
            : PurchaseOrder::where('supplier_id', $supplier->id)
                ->whereIn('status', ['delivered', 'frozen'])
                ->get();

        $totalPos = $pos->count();
        $onTimeCount = 0;
        $accurateCount = 0;

        foreach ($pos as $po) {
            // Check accuracy
            if (! $po->is_discrepant) {
                $accurateCount++;
            }

            // Check on-time
            if ($po->delivery_due_date && $po->delivered_at) {
                $dueDate = Carbon::parse($po->delivery_due_date);
                $deliveredDate = Carbon::parse($po->delivered_at);
                // 30 mins grace period
                if ($deliveredDate->lte($dueDate->addMinutes(30))) {
                    $onTimeCount++;
                }
            } else {
                // If no due date, count as on-time for safety or ignore
                $onTimeCount++;
            }
        }

        $onTimeRate = $totalPos > 0 ? ($onTimeCount / $totalPos) * 100 : 100;
        $accuracyRate = $totalPos > 0 ? ($accurateCount / $totalPos) * 100 : 100;

        // Price Volatility per ingredient
        $priceVolatility = [];
        $ingredients = isset($context['ingredients'])
            ? ($context['ingredients']->get($supplier->id) ?? collect())
            : Ingredient::where('supplier_id', $supplier->id)->get();

        $ingredientIds = $ingredients->pluck('id')->toArray();
        $histories = isset($context['histories'])
            ? ($context['histories']->get($supplier->id) ?? collect())->groupBy('ingredient_id')
            : SupplierPriceHistory::where('supplier_id', $supplier->id)
                ->whereIn('ingredient_id', $ingredientIds)
                ->orderBy('effective_date')
                ->get()
                ->groupBy('ingredient_id');

        $totalVolatility = 0;
        $volatilityCount = 0;

        foreach ($ingredients as $ing) {
            $prices = isset($histories[$ing->id])
                ? $histories[$ing->id]->pluck('price')->toArray()
                : [];

            $count = count($prices);
            if ($count > 1) {
                $mean = array_sum($prices) / $count;
                $variance = 0.0;
                foreach ($prices as $p) {
                    $variance += pow($p - $mean, 2);
                }
                $stdDev = sqrt($variance / ($count - 1));
                $volatility = ($mean > 0) ? ($stdDev / $mean) * 100 : 0;
            } else {
                $volatility = 0;
            }

            $priceVolatility[] = [
                'ingredient_name' => $ing->name,
                'sku' => $ing->sku,
                'current_price' => (float) $ing->average_cost,
                'price_history_count' => $count,
                'volatility_percent' => round($volatility, 2),
            ];

            if ($count > 1) {
                $totalVolatility += $volatility;
                $volatilityCount++;
            }
        }

        $avgVolatility = $volatilityCount > 0 ? ($totalVolatility / $volatilityCount) : 0;

        // Get recent ratings
        $recentRatings = $pos->whereNotNull('rating')->map(fn ($po) => [
            'po_number' => $po->po_number,
            'rating' => $po->rating,
            'rating_notes' => $po->rating_notes,
            'delivered_at' => $po->delivered_at->format('d/m/Y H:i'),
        ])->values()->all();

        $averageRating = $pos->whereNotNull('rating')->avg('rating') ?? 5.0;

        return [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'total_orders_analyzed' => $totalPos,
            'on_time_rate' => round($onTimeRate, 1),
            'accuracy_rate' => round($accuracyRate, 1),
            'average_rating' => round($averageRating, 1),
            'average_volatility' => round($avgVolatility, 1),
            'price_volatility' => $priceVolatility,
            'recent_ratings' => $recentRatings,
        ];
    }

    /**
     * Tính SLA cho toàn bộ nhà cung cấp active của 1 nhà hàng — preload theo lô
     * (pos/ingredients/histories) để tránh N+1 khi lặp qua từng nhà cung cấp.
     */
    public function calculateForRestaurant(int $restaurantId): array
    {
        $suppliers = Supplier::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get();

        $supplierIds = $suppliers->pluck('id')->toArray();

        $context = [];

        $context['pos'] = PurchaseOrder::whereIn('supplier_id', $supplierIds)
            ->whereIn('status', ['delivered', 'frozen'])
            ->get()
            ->groupBy('supplier_id');

        $context['ingredients'] = Ingredient::whereIn('supplier_id', $supplierIds)
            ->get()
            ->groupBy('supplier_id');

        $allIngredientIds = Ingredient::whereIn('supplier_id', $supplierIds)->pluck('id')->toArray();
        $context['histories'] = SupplierPriceHistory::whereIn('supplier_id', $supplierIds)
            ->whereIn('ingredient_id', $allIngredientIds)
            ->orderBy('effective_date')
            ->get()
            ->groupBy('supplier_id');

        return $suppliers->map(fn (Supplier $supplier) => $this->calculateForSupplier($supplier, $context))->values()->all();
    }
}
