<?php

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\InventoryTransaction;
use App\Support\Tenant\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WasteAnalyticsService
{
    public function getDashboard(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        return Cache::remember($this->cacheKey('waste_dashboard', $restaurantId, $days, $branchId), 300, function () use ($restaurantId, $days, $branchId) {
            $totalWasteCost = (float) InventoryTransaction::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('type', 'waste')
                ->where('occurred_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('total_cost');

            $totalRevenue = (float) DB::table('orders')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->where('completed_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('total_amount') +
                (float) DB::table('orders_archive')
                    ->where('restaurant_id', $restaurantId)
                    ->where('status', 'completed')
                    ->where('completed_at', '>=', now()->subDays($days))
                    ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                    ->sum('total_amount');

            $wasteRatio = $totalRevenue > 0 ? round(($totalWasteCost / $totalRevenue) * 100, 2) : 0;
            $benchmarkStatus = $wasteRatio <= 5 ? 'excellent' : ($wasteRatio <= 10 ? 'normal' : 'high');

            $wasteCount = InventoryTransaction::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('type', 'waste')
                ->where('occurred_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->count();

            return [
                'total_waste_cost' => $totalWasteCost,
                'total_revenue' => $totalRevenue,
                'waste_ratio' => $wasteRatio,
                'waste_count' => $wasteCount,
                'benchmark_status' => $benchmarkStatus,
                'benchmark_label' => match ($benchmarkStatus) {
                    'excellent' => 'Xuất sắc (≤5%)',
                    'normal' => 'Bình thường (5-10%)',
                    'high' => 'Cao — cần cải thiện (>10%)',
                },
                'by_category' => $this->getWasteByCategory($restaurantId, $days, $branchId),
                'top_ingredients' => $this->getTopWasteIngredients($restaurantId, $days, $branchId),
            ];
        });
    }

    public function getWasteByCategory(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        return InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('type', 'waste')
            ->where('occurred_at', '>=', now()->subDays($days))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->select(
                DB::raw("COALESCE(waste_category, 'other') as category"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_cost) as total_cost')
            )
            ->groupBy('waste_category')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category,
                'label' => $this->categoryLabel($r->category),
                'count' => (int) $r->count,
                'total_cost' => (float) $r->total_cost,
            ])
            ->sortByDesc('total_cost')
            ->values()
            ->all();
    }

    public function getTopWasteIngredients(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        return DB::table('inventory_transactions')
            ->join('ingredients', 'inventory_transactions.ingredient_id', '=', 'ingredients.id')
            ->where('inventory_transactions.restaurant_id', $restaurantId)
            ->where('inventory_transactions.type', 'waste')
            ->where('inventory_transactions.occurred_at', '>=', now()->subDays($days))
            ->when($branchId !== null, fn ($query) => $query->where('inventory_transactions.branch_id', $branchId))
            ->select(
                'ingredients.id',
                'ingredients.name',
                DB::raw('SUM(inventory_transactions.quantity) as total_qty'),
                DB::raw('SUM(inventory_transactions.total_cost) as total_cost'),
                DB::raw('COUNT(*) as waste_count')
            )
            ->groupBy('ingredients.id', 'ingredients.name')
            ->orderByDesc('total_cost')
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'ingredient_id' => $r->id,
                'name' => $r->name,
                'total_qty' => (float) $r->total_qty,
                'total_cost' => (float) $r->total_cost,
                'waste_count' => (int) $r->waste_count,
            ])
            ->all();
    }

    public function getTrendData(int $restaurantId, int $months = 6, ?int $branchId = null): array
    {
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', occurred_at)"
            : "DATE_FORMAT(occurred_at, '%Y-%m')";

        return DB::table('inventory_transactions')
            ->where('restaurant_id', $restaurantId)
            ->where('type', 'waste')
            ->where('occurred_at', '>=', now()->subMonths($months))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->select(
                DB::raw("{$monthExpression} as month"),
                DB::raw('SUM(total_cost) as total_cost'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw($monthExpression))
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'month' => $r->month,
                'total_cost' => (float) $r->total_cost,
                'count' => (int) $r->count,
            ])
            ->all();
    }

    public function getAiSuggestions(int $restaurantId, ?int $branchId = null): array
    {
        $topWaste = $this->getTopWasteIngredients($restaurantId, 30, $branchId);
        $byCategory = $this->getWasteByCategory($restaurantId, 30, $branchId);
        $suggestions = [];

        foreach (array_slice($topWaste, 0, 3) as $item) {
            $suggestions[] = [
                'type' => 'reduce_order',
                'icon' => '📦',
                'title' => "Giảm lượng đặt hàng {$item['name']}",
                'description' => "Nguyên liệu này hao hụt {$item['waste_count']} lần ({$item['total_cost']}đ) trong 30 ngày. Đề xuất giảm 15-20% lượng nhập mỗi đợt.",
                'ingredient_id' => $item['ingredient_id'],
            ];
        }

        $expired = collect($byCategory)->firstWhere('category', 'expired');
        if ($expired && $expired['count'] > 2) {
            $suggestions[] = [
                'type' => 'expiry_management',
                'icon' => '⏰',
                'title' => 'Cải thiện quản lý hạn sử dụng',
                'description' => "{$expired['count']} lần hao hụt do hết hạn ({$expired['total_cost']}đ). Đề xuất: áp dụng FIFO nghiêm ngặt, nhập hàng thường xuyên hơn với số lượng nhỏ hơn.",
            ];
        }

        $cookingLoss = collect($byCategory)->firstWhere('category', 'cooking_loss');
        if ($cookingLoss && $cookingLoss['count'] > 5) {
            $suggestions[] = [
                'type' => 'recipe_adjust',
                'icon' => '📝',
                'title' => 'Điều chỉnh công thức/quy trình chế biến',
                'description' => "{$cookingLoss['count']} lần hao hụt trong chế biến. Đề xuất: training lại nhân viên bếp, chuẩn hóa SOP, tăng waste_rate trong recipe.",
            ];
        }

        if (empty($suggestions)) {
            $suggestions[] = [
                'type' => 'good',
                'icon' => '✅',
                'title' => 'Hao hụt đang ở mức tốt',
                'description' => 'Không phát hiện vấn đề nghiêm trọng. Tiếp tục duy trì quy trình hiện tại.',
            ];
        }

        return $suggestions;
    }

    public function getExpiringItems(int $restaurantId, int $days = 3, ?int $branchId = null): array
    {
        return InventoryBatch::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->expiringSoon($days)
            ->with('ingredient:id,name')
            ->orderBy('expiry_date')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'ingredient_name' => $b->ingredient?->name,
                'batch_number' => $b->batch_number,
                'quantity_remaining' => (float) $b->quantity_remaining,
                'unit_cost' => (float) $b->unit_cost,
                'expiry_date' => $b->expiry_date?->format('d/m/Y') ?? '—',
                'days_left' => $b->expiry_date ? (int) now()->diffInDays($b->expiry_date, false) : 0,
            ])
            ->all();
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'spoilage' => 'Hỏng/Ôi thiu',
            'expired' => 'Hết hạn sử dụng',
            'damaged' => 'Hư hại/Đổ vỡ',
            'cooking_loss' => 'Hao hụt chế biến',
            'theft' => 'Thất thoát/Mất cắp',
            default => 'Khác',
        };
    }

    private function cacheKey(string $prefix, int $restaurantId, int $period, ?int $branchId): string
    {
        return implode(':', [
            $prefix,
            $restaurantId,
            $period,
            TenantContext::branchScopeKey($branchId),
        ]);
    }
}
