<?php

namespace App\Services;

use App\Models\MenuPriceTest;
use App\Support\Tenant\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MenuInsightService
{
    /**
     * Phân tích hiệu suất sản phẩm và trả về danh sách insights có thể hành động.
     */
    public function getInsights(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        $products = $this->queryProductPerformance($restaurantId, $days, 0, $branchId);

        if ($products->isEmpty()) {
            return [];
        }

        $maxRevenue = (float) $products->max('total_revenue');
        $maxQty = (float) $products->max('total_qty');
        $insights = [];

        foreach ($products as $p) {
            $revenue = (float) $p->total_revenue;
            $qty = (int) $p->total_qty;
            $price = (float) $p->price;
            $cost = (float) ($p->cost_price ?? 0);
            $margin = $price > 0 && $cost > 0
                ? round((($price - $cost) / $price) * 100, 1)
                : null;

            // ── Cảnh báo biên lợi nhuận thấp ─────────────────────────────────
            if ($margin !== null && $margin < 25 && $qty > 0) {
                $insights[] = [
                    'type' => 'low_margin',
                    'severity' => $margin < 10 ? 'critical' : 'warning',
                    'product' => $p->name,
                    'product_id' => $p->product_id,
                    'message' => "Biên lợi nhuận <strong>{$p->name}</strong> chỉ <strong>{$margin}%</strong> — dưới ngưỡng an toàn 25%",
                    'suggestion' => 'Tăng giá bán hoặc tối ưu chi phí nguyên liệu',
                    'value' => $margin,
                    'unit' => '%',
                ];
            }

            // ── Sản phẩm bán chậm ────────────────────────────────────────────
            $weeklyAvg = $days > 0 ? round($qty / max(1, $days / 7), 1) : 0;
            if ($weeklyAvg < 3 && $qty > 0) {
                $insights[] = [
                    'type' => 'slow_moving',
                    'severity' => 'info',
                    'product' => $p->name,
                    'product_id' => $p->product_id,
                    'message' => "<strong>{$p->name}</strong> chỉ bán <strong>{$weeklyAvg} lần/tuần</strong> trong {$days} ngày qua",
                    'suggestion' => 'Cân nhắc combo kích cầu hoặc ẩn khỏi menu nếu không sinh lời',
                    'value' => $weeklyAvg,
                    'unit' => 'lần/tuần',
                ];
            }

            // ── Sản phẩm bán chạy nhưng biên lợi thấp (star với vấn đề) ──────
            if ($margin !== null && $revenue > $maxRevenue * 0.3 && $margin < 30) {
                $insights[] = [
                    'type' => 'high_volume_low_margin',
                    'severity' => 'warning',
                    'product' => $p->name,
                    'product_id' => $p->product_id,
                    'message' => "<strong>{$p->name}</strong> bán chạy nhưng margin thấp ({$margin}%)",
                    'suggestion' => 'Đây là cơ hội lớn: tối ưu chi phí để tăng lợi nhuận đáng kể',
                    'value' => $margin,
                    'unit' => '%',
                ];
            }
        }

        // Sắp xếp: critical trước, warning sau, info cuối
        usort($insights, fn ($a, $b) => [
            'critical' => 0, 'warning' => 1, 'info' => 2,
        ][$a['severity']] <=> [
            'critical' => 0, 'warning' => 1, 'info' => 2,
        ][$b['severity']]);

        return array_slice($insights, 0, 8); // Tối đa 8 insights
    }

    /**
     * Dữ liệu BCG matrix: xếp sản phẩm vào 4 ô.
     */
    public function getBcgData(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        $products = $this->queryProductPerformance($restaurantId, $days, 0, $branchId);

        if ($products->isEmpty()) {
            return [];
        }

        $mapped = $products->map(function ($p) {
            $price = (float) $p->price;
            $cost = (float) ($p->cost_price ?? 0);
            $margin = $price > 0 ? (($price - $cost) / $price) * 100 : 0.0;

            return [
                'name' => $p->name,
                'product_id' => $p->product_id,
                'total_revenue' => (float) $p->total_revenue,
                'total_qty' => (int) $p->total_qty,
                'price' => $price,
                'cost_price' => $cost,
                'margin' => round($margin, 1),
            ];
        });

        $medianQty = $this->median($mapped->pluck('total_qty')->all());
        $medianMargin = $this->median($mapped->pluck('margin')->all());

        return $mapped->map(function ($item) use ($medianQty, $medianMargin) {
            $highQty = $item['total_qty'] >= $medianQty;
            $highMargin = $item['margin'] >= $medianMargin;

            $quadrant = match (true) {
                $highQty && $highMargin => 'star',       // ⭐ Stars
                $highQty && ! $highMargin => 'plowhorse',  // 🐎 Plowhorses
                ! $highQty && $highMargin => 'puzzle',     // 🧩 Puzzles
                default => 'dog',        // 🐶 Dogs
            };

            // AI suggestions for action
            $recommendation = match ($quadrant) {
                'star' => 'Món ăn Ngôi sao: Sản lượng cao & Lợi nhuận cao. Hãy duy trì vị trí nổi bật trên thực đơn, giữ nguyên giá bán và chất lượng để bảo vệ dòng tiền.',
                'plowhorse' => 'Món ăn Bò sữa: Bán rất tốt nhưng lợi nhuận thấp. Hãy thử đàm phán giảm giá nguyên vật liệu, hoặc tăng giá bán nhẹ (khoảng 3-5%), hoặc giảm kích thước khẩu phần một chút.',
                'puzzle' => 'Món ăn Câu đố: Biên lợi nhuận cực tốt nhưng kén khách. Đề xuất ghép món này vào các COMBO bán kèm cùng món bán chạy, tăng cường PR, hoặc đưa lên vị trí bắt mắt nhất trên thực đơn.',
                'dog' => 'Món ăn Thú cưng: Cả sản lượng và biên lợi nhuận đều thấp. Cân nhắc loại bỏ hoàn toàn khỏi thực đơn, hoặc thay thế bằng một công thức mới thu hút khách hơn.',
            };

            return array_merge($item, [
                'quadrant' => $quadrant,
                'ai_recommendation' => $recommendation,
                'median_qty' => $medianQty,
                'median_margin' => $medianMargin,
            ]);
        })->values()->all();
    }

    private function median(array $values): float
    {
        if (empty($values)) {
            return 0;
        }
        sort($values);
        $count = count($values);
        $mid = (int) floor($count / 2);

        return $count % 2 === 0
            ? ($values[$mid - 1] + $values[$mid]) / 2
            : $values[$mid];
    }

    /**
     * Hiệu suất sản phẩm + biên lợi nhuận cho chart.
     */
    public function getProductMargins(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        $products = $this->queryProductPerformance($restaurantId, $days, 0, $branchId);

        return $products
            ->filter(fn ($p) => (int) $p->total_qty > 0)
            ->map(fn ($p) => [
                'name' => $p->name,
                'margin' => (float) $p->price > 0 && (float) $p->cost_price > 0
                    ? round(((float) $p->price - (float) $p->cost_price) / (float) $p->price * 100, 1)
                    : null,
                'revenue' => (float) $p->total_revenue,
                'qty' => (int) $p->total_qty,
            ])
            ->filter(fn ($p) => $p['margin'] !== null)
            ->sortByDesc('revenue')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * Menu Scoring: điểm tổng hợp cho mỗi món (popularity × profitability × trend).
     * Score 0-100, kèm AI suggestion cho từng món.
     */
    public function getMenuScoring(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        $scopeKey = TenantContext::branchScopeKey($branchId);

        return Cache::remember("menu_scoring:{$restaurantId}:{$days}:{$scopeKey}", 300, function () use ($restaurantId, $days, $branchId) {
            $current = $this->queryProductPerformance($restaurantId, $days, 0, $branchId);
            $previous = $this->queryProductPerformance($restaurantId, $days, $days, $branchId);

            if ($current->isEmpty()) {
                return [];
            }

            $maxQty = max(1, (float) $current->max('total_qty'));
            $maxRevenue = max(1, (float) $current->max('total_revenue'));

            $previousMap = $previous->keyBy('product_id');

            return $current->map(function ($p) use ($maxQty, $previousMap, $days) {
                $qty = (int) $p->total_qty;
                $revenue = (float) $p->total_revenue;
                $price = (float) $p->price;
                $cost = (float) ($p->cost_price ?? 0);
                $margin = $price > 0 && $cost > 0 ? (($price - $cost) / $price) * 100 : 50;

                // Popularity score (0-100): normalized by max qty
                $popularityScore = round(($qty / $maxQty) * 100);

                // Profitability score (0-100): based on margin %
                $profitabilityScore = (int) min(100, max(0, $margin));

                // Trend score (0-100): compare with previous period
                $prev = $previousMap->get($p->product_id);
                $prevQty = $prev ? (int) $prev->total_qty : 0;
                $trendScore = 50; // neutral
                if ($prevQty > 0) {
                    $growth = (($qty - $prevQty) / $prevQty) * 100;
                    $trendScore = (int) min(100, max(0, 50 + $growth));
                } elseif ($qty > 0) {
                    $trendScore = 80; // new popular item
                }

                // Composite score (weighted)
                $compositeScore = (int) round($popularityScore * 0.4 + $profitabilityScore * 0.35 + $trendScore * 0.25);

                // AI suggestion
                $suggestion = $this->generateSuggestion($compositeScore, $popularityScore, $profitabilityScore, $trendScore, $margin, $p->name);

                return [
                    'product_id' => $p->product_id,
                    'name' => $p->name,
                    'price' => $price,
                    'cost_price' => $cost,
                    'qty' => $qty,
                    'revenue' => $revenue,
                    'margin' => round($margin, 1),
                    'popularity_score' => $popularityScore,
                    'profitability_score' => $profitabilityScore,
                    'trend_score' => $trendScore,
                    'composite_score' => $compositeScore,
                    'suggestion' => $suggestion,
                    'weekly_avg' => round($qty / max(1, $days / 7), 1),
                ];
            })->sortByDesc('composite_score')->values()->all();
        });
    }

    /**
     * Tổng hợp kết quả A/B test đang chạy.
     */
    public function syncPriceTestResults(MenuPriceTest $test): void
    {
        if ($test->status !== 'running') {
            return;
        }

        $startAt = $test->start_at;
        $endAt = $test->end_at ?? now();
        $midpoint = $startAt->copy()->addSeconds($startAt->diffInSeconds($endAt) / 2);

        // Orders trước midpoint = original price, sau midpoint = test price (truy cập bảng hot trực tiếp để dùng Index)
        $ordersOriginal = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.restaurant_id', $test->restaurant_id)
            ->where('orders.status', 'completed')
            ->whereNull('orders.deleted_at')
            ->where('order_items.product_id', $test->product_id)
            ->whereBetween('orders.completed_at', [$startAt, $midpoint])
            ->selectRaw('COUNT(*) as count, SUM(order_items.line_total) as revenue')
            ->first();

        $ordersTest = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.restaurant_id', $test->restaurant_id)
            ->where('orders.status', 'completed')
            ->whereNull('orders.deleted_at')
            ->where('order_items.product_id', $test->product_id)
            ->whereBetween('orders.completed_at', [$midpoint, $endAt])
            ->selectRaw('COUNT(*) as count, SUM(order_items.line_total) as revenue')
            ->first();

        $origCount = (int) ($ordersOriginal?->count ?? 0);
        $testCount = (int) ($ordersTest?->count ?? 0);
        $origRevenue = (float) ($ordersOriginal?->revenue ?? 0);
        $testRevenue = (float) ($ordersTest?->revenue ?? 0);

        $test->update([
            'orders_original' => $origCount,
            'orders_test' => $testCount,
            'revenue_original' => $origRevenue,
            'revenue_test' => $testRevenue,
            'results_json' => [
                'avg_revenue_original' => $origCount > 0 ? round($origRevenue / $origCount) : 0,
                'avg_revenue_test' => $testCount > 0 ? round($testRevenue / $testCount) : 0,
                'impact_percent' => $test->getImpactPercent(),
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function generateSuggestion(int $composite, int $popularity, int $profitability, int $trend, float $margin, string $name): string
    {
        if ($composite >= 80) {
            return "⭐ {$name} là món bán chạy nhất. Giữ nguyên vị trí nổi bật, có thể tăng giá nhẹ 3-5% để tối ưu lợi nhuận.";
        }

        if ($popularity >= 70 && $profitability < 40) {
            return "🐎 {$name} bán tốt nhưng lời ít (margin {$margin}%). Đề xuất tối ưu nguyên liệu hoặc tăng giá 5-8%.";
        }

        if ($popularity < 30 && $profitability >= 60) {
            return "🧩 {$name} lời cao nhưng ít người mua. Đề xuất ghép combo, đưa lên đầu menu, hoặc chạy khuyến mãi kích cầu.";
        }

        if ($trend < 30 && $popularity < 40) {
            return "📉 {$name} đang giảm sút. Cân nhắc thay đổi công thức, giảm giá, hoặc loại bỏ khỏi menu.";
        }

        if ($trend >= 70) {
            return "📈 {$name} đang tăng trưởng mạnh. Đây là cơ hội — tăng display, chuẩn bị nguyên liệu nhiều hơn.";
        }

        if ($composite < 30) {
            return "🐶 {$name} hiệu suất thấp. Đề xuất loại bỏ khỏi menu hoặc thay thế bằng món mới.";
        }

        return "📊 {$name} ở mức trung bình. Theo dõi thêm 2-4 tuần để đánh giá xu hướng.";
    }

    private function queryProductPerformance(int $restaurantId, int $days, int $offsetDays = 0, ?int $branchId = null)
    {
        $from = now()->subDays($days + $offsetDays);
        $to = now()->subDays($offsetDays);

        // 1. Lấy dữ liệu từ bảng hot (orders & order_items) - Dùng Index tối đa
        $active = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'completed')
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.completed_at', [$from, $to])
            ->when($branchId !== null, fn ($query) => $query->where('orders.branch_id', $branchId))
            ->select(
                'products.id as product_id',
                'products.name',
                'products.price',
                'products.cost_price',
                DB::raw('SUM(order_items.quantity) as total_qty'),
                DB::raw('SUM(order_items.line_total) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.price', 'products.cost_price')
            ->get();

        // 2. Lấy dữ liệu từ bảng archive (orders_archive & order_items_archive)
        $archived = DB::table('order_items_archive')
            ->join('orders_archive', 'order_items_archive.order_id', '=', 'orders_archive.id')
            ->join('products', 'order_items_archive.product_id', '=', 'products.id')
            ->where('orders_archive.restaurant_id', $restaurantId)
            ->where('orders_archive.status', 'completed')
            ->whereBetween('orders_archive.completed_at', [$from, $to])
            ->when($branchId !== null, fn ($query) => $query->where('orders_archive.branch_id', $branchId))
            ->select(
                'products.id as product_id',
                'products.name',
                'products.price',
                'products.cost_price',
                DB::raw('SUM(order_items_archive.quantity) as total_qty'),
                DB::raw('SUM(order_items_archive.line_total) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.price', 'products.cost_price')
            ->get();

        // 3. Gộp kết quả của cả hai bảng trong PHP
        $merged = collect();

        foreach ($active as $p) {
            $p->total_qty = (float) $p->total_qty;
            $p->total_revenue = (float) $p->total_revenue;
            $merged->put($p->product_id, $p);
        }

        foreach ($archived as $p) {
            $p->total_qty = (float) $p->total_qty;
            $p->total_revenue = (float) $p->total_revenue;
            if ($merged->has($p->product_id)) {
                $existing = $merged->get($p->product_id);
                $existing->total_qty += $p->total_qty;
                $existing->total_revenue += $p->total_revenue;
            } else {
                $merged->put($p->product_id, $p);
            }
        }

        return $merged->sortByDesc('total_revenue')->values();
    }
}
