<?php

namespace App\Services;

use App\Models\MenuPriceTest;
use App\Support\Tenant\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * Phân tích xu hướng bán món và thói quen gọi món.
     *
     * Kỳ hiện tại được so sánh với một kỳ liền trước có cùng số ngày. Dữ liệu
     * được đọc từ cả bảng đơn hiện tại và bảng archive để báo cáo không bị hụt
     * khi đơn cũ đã được chuyển sang lưu trữ.
     */
    public function getBehaviorAnalytics(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        $days = max(1, min(365, $days));
        $currentTo = now();
        $currentFrom = $currentTo->copy()->subDays($days);
        $previousTo = $currentFrom->copy();
        $previousFrom = $previousTo->copy()->subDays($days);

        $currentProducts = $this->fetchProductStats($restaurantId, $currentFrom, $currentTo, $branchId);
        $previousProducts = $this->fetchProductStats($restaurantId, $previousFrom, $previousTo, $branchId);
        $currentMap = $currentProducts->keyBy('product_id');
        $previousMap = $previousProducts->keyBy('product_id');

        $totalCurrentQty = (float) $currentProducts->sum('qty');
        $totalCurrentRevenue = (float) $currentProducts->sum('revenue');
        $currentOrders = $this->fetchOrderFacts($restaurantId, $currentFrom, $currentTo, $branchId);
        $previousOrders = $this->fetchOrderFacts($restaurantId, $previousFrom, $previousTo, $branchId);

        // Giữ lại cả món chỉ xuất hiện ở kỳ trước để nhận diện món đã giảm
        // về 0 lượt gọi, thay vì vô tình loại món đó khỏi danh sách cảnh báo.
        $allProductIds = $currentProducts->pluck('product_id')
            ->merge($previousProducts->pluck('product_id'))
            ->unique()
            ->values();
        $menu = $allProductIds
            ->map(function (int $productId) use ($currentMap, $previousMap, $totalCurrentQty): array {
                $item = $currentMap->get($productId) ?? array_merge(
                    $previousMap->get($productId),
                    ['qty' => 0, 'revenue' => 0, 'order_count' => 0],
                );
                $previous = $previousMap->get($item['product_id']);
                $previousQty = (float) ($previous['qty'] ?? 0);
                $changeQty = $item['qty'] - $previousQty;
                $changePercent = $previousQty > 0
                    ? ($changeQty / $previousQty) * 100
                    : ($item['qty'] > 0 ? 100 : 0);

                return array_merge($item, [
                    'previous_qty' => $this->roundMetric($previousQty),
                    'change_qty' => $this->roundMetric($changeQty),
                    'change_percent' => round($changePercent, 1),
                    'trend' => $changeQty > 0 ? 'up' : ($changeQty < 0 ? 'down' : 'stable'),
                    'quantity_share' => $totalCurrentQty > 0
                        ? round(($item['qty'] / $totalCurrentQty) * 100, 1)
                        : 0,
                ]);
            })
            ->sortByDesc('qty')
            ->values();

        $rising = $menu
            ->filter(fn (array $item): bool => $item['change_qty'] > 0)
            ->sortByDesc(fn (array $item): array => [$item['change_percent'], $item['change_qty']])
            ->take(8)
            ->values()
            ->all();
        $falling = $menu
            ->filter(fn (array $item): bool => $item['change_qty'] < 0)
            ->sortBy(fn (array $item): array => [$item['change_percent'], $item['change_qty']])
            ->take(8)
            ->values()
            ->all();

        $branchBreakdown = $this->fetchBranchBreakdown($restaurantId, $currentFrom, $currentTo, $branchId);
        $periodProducts = $menu->take(12)->values();
        $topProductIds = $periodProducts->pluck('product_id')->all();
        $pairs = $this->buildCoOrderPairs($currentOrders, $topProductIds);
        $habits = $this->buildCustomerHabits($currentOrders, $previousOrders, $menu);
        $dayparts = $this->buildDaypartBreakdown($currentOrders);
        $channels = $this->buildChannelBreakdown($currentOrders);
        $categories = $this->buildCategoryBreakdown($menu);

        return [
            'period' => [
                'days' => $days,
                'from' => $currentFrom->toDateString(),
                'to' => $currentTo->toDateString(),
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $previousTo->toDateString(),
            ],
            'summary' => [
                'orders' => count($currentOrders),
                'previous_orders' => count($previousOrders),
                'items' => $this->roundMetric($totalCurrentQty),
                'revenue' => round($totalCurrentRevenue, 2),
                'avg_order_value' => count($currentOrders) > 0
                    ? round($totalCurrentRevenue / count($currentOrders), 2)
                    : 0,
                'unique_products' => $menu->count(),
                'rising_products' => count($rising),
                'falling_products' => count($falling),
                'orders_change_percent' => $this->percentageChange(count($previousOrders), count($currentOrders)),
            ],
            'top_dishes' => $periodProducts->all(),
            'rising' => $rising,
            'falling' => $falling,
            'branch_breakdown' => $branchBreakdown,
            'categories' => $categories,
            'dayparts' => $dayparts,
            'channels' => $channels,
            'pairs' => $pairs,
            'customer_habits' => $habits,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchProductStats(
        int $restaurantId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $branchId,
    ) {
        $rows = collect();
        $queries = [['items' => 'order_items', 'orders' => 'orders']];

        if (Schema::hasTable('order_items_archive') && Schema::hasTable('orders_archive')) {
            $queries[] = ['items' => 'order_items_archive', 'orders' => 'orders_archive'];
        }

        foreach ($queries as $tables) {
            $query = DB::table($tables['items'].' as oi')
                ->join($tables['orders'].' as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.id')
                ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                ->where('o.restaurant_id', $restaurantId)
                ->where('o.status', 'completed')
                ->whereBetween('o.completed_at', [$from, $to])
                ->where('oi.status', '!=', 'cancelled')
                ->when($branchId !== null, fn ($q) => $q->where('o.branch_id', $branchId))
                ->select([
                    'oi.product_id', 'p.name', 'p.price', 'p.cost_price', 'pc.name as category_name',
                    DB::raw('SUM(oi.quantity) as qty'),
                    DB::raw('SUM(oi.line_total) as revenue'),
                    DB::raw('COUNT(DISTINCT oi.order_id) as order_count'),
                ])
                ->groupBy('oi.product_id', 'p.name', 'p.price', 'p.cost_price', 'pc.name')
                ->get();

            foreach ($query as $row) {
                $key = (int) $row->product_id;
                $existing = $rows->get($key, [
                    'product_id' => $key,
                    'name' => (string) $row->name,
                    'price' => (float) $row->price,
                    'cost_price' => (float) ($row->cost_price ?? 0),
                    'category_name' => $row->category_name ?: 'Khác',
                    'qty' => 0.0, 'revenue' => 0.0, 'order_count' => 0,
                ]);
                $existing['qty'] += (float) $row->qty;
                $existing['revenue'] += (float) $row->revenue;
                $existing['order_count'] += (int) $row->order_count;
                $rows->put($key, $existing);
            }
        }

        return $rows->map(function (array $item): array {
            $item['qty'] = $this->roundMetric($item['qty']);
            $item['revenue'] = round($item['revenue'], 2);
            $item['order_count'] = (int) $item['order_count'];
            $price = (float) $item['price'];
            $cost = (float) $item['cost_price'];
            $item['margin'] = $price > 0 ? round((($price - $cost) / $price) * 100, 1) : 0;

            return $item;
        })->sortByDesc('qty')->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchOrderFacts(
        int $restaurantId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $branchId,
    ): array {
        $facts = collect();
        $queries = [['items' => 'order_items', 'orders' => 'orders']];

        if (Schema::hasTable('order_items_archive') && Schema::hasTable('orders_archive')) {
            $queries[] = ['items' => 'order_items_archive', 'orders' => 'orders_archive'];
        }

        foreach ($queries as $tables) {
            $rows = DB::table($tables['items'].' as oi')
                ->join($tables['orders'].' as o', 'oi.order_id', '=', 'o.id')
                ->join('products as p', 'oi.product_id', '=', 'p.id')
                ->where('o.restaurant_id', $restaurantId)
                ->where('o.status', 'completed')
                ->whereBetween('o.completed_at', [$from, $to])
                ->where('oi.status', '!=', 'cancelled')
                ->when($branchId !== null, fn ($q) => $q->where('o.branch_id', $branchId))
                ->select([
                    'o.id as order_id', 'o.branch_id', 'o.customer_id', 'o.channel',
                    'o.total_amount', 'o.completed_at', 'oi.product_id', 'p.name as product_name', 'oi.quantity',
                ])
                ->orderBy('o.completed_at')
                ->limit(50000)
                ->get();

            foreach ($rows as $row) {
                $orderId = (int) $row->order_id;
                $fact = $facts->get($orderId, [
                    'order_id' => $orderId,
                    'branch_id' => $row->branch_id ? (int) $row->branch_id : null,
                    'customer_id' => $row->customer_id ? (int) $row->customer_id : null,
                    'channel' => (string) ($row->channel ?: 'other'),
                    'total_amount' => (float) $row->total_amount,
                    'completed_at' => $row->completed_at,
                    'items' => [],
                ]);
                $fact['items'][] = [
                    'product_id' => (int) $row->product_id,
                    'name' => (string) $row->product_name,
                    'quantity' => (float) $row->quantity,
                ];
                $facts->put($orderId, $fact);
            }
        }

        return $facts->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchBranchBreakdown(
        int $restaurantId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $branchId,
    ): array {
        $rows = collect();
        $queries = [['items' => 'order_items', 'orders' => 'orders']];

        if (Schema::hasTable('order_items_archive') && Schema::hasTable('orders_archive')) {
            $queries[] = ['items' => 'order_items_archive', 'orders' => 'orders_archive'];
        }

        foreach ($queries as $tables) {
            $query = DB::table($tables['items'].' as oi')
                ->join($tables['orders'].' as o', 'oi.order_id', '=', 'o.id')
                ->leftJoin('restaurant_branches as b', 'o.branch_id', '=', 'b.id')
                ->where('o.restaurant_id', $restaurantId)
                ->where('o.status', 'completed')
                ->whereBetween('o.completed_at', [$from, $to])
                ->where('oi.status', '!=', 'cancelled')
                ->when($branchId !== null, fn ($q) => $q->where('o.branch_id', $branchId))
                ->select([
                    'o.branch_id',
                    DB::raw("COALESCE(b.name, 'Chưa gán chi nhánh') as branch_name"),
                    DB::raw('COUNT(DISTINCT o.id) as orders'),
                    DB::raw('SUM(oi.quantity) as items'),
                    DB::raw('SUM(oi.line_total) as revenue'),
                ])
                ->groupBy('o.branch_id', 'b.name')
                ->get();

            foreach ($query as $row) {
                $key = $row->branch_id ? (int) $row->branch_id : 0;
                $existing = $rows->get($key, [
                    'branch_id' => $key ?: null,
                    'branch_name' => (string) $row->branch_name,
                    'orders' => 0, 'items' => 0.0, 'revenue' => 0.0,
                ]);
                $existing['orders'] += (int) $row->orders;
                $existing['items'] += (float) $row->items;
                $existing['revenue'] += (float) $row->revenue;
                $rows->put($key, $existing);
            }
        }

        return $rows->map(function (array $item): array {
            $item['items'] = $this->roundMetric($item['items']);
            $item['revenue'] = round($item['revenue'], 2);
            $item['avg_order_value'] = $item['orders'] > 0
                ? round($item['revenue'] / $item['orders'], 2) : 0;

            return $item;
        })->sortByDesc('revenue')->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     * @param  array<int, int>  $topProductIds
     * @return array<int, array<string, mixed>>
     */
    private function buildCoOrderPairs(array $orders, array $topProductIds): array
    {
        if (empty($topProductIds)) {
            return [];
        }

        $topIds = array_fill_keys(array_map('intval', $topProductIds), true);
        $pairCounts = [];
        $itemCounts = [];
        $orderCount = count($orders);
        $nameById = [];

        foreach ($orders as $order) {
            $items = collect($order['items'])
                ->filter(fn (array $item): bool => isset($topIds[$item['product_id']]))
                ->unique('product_id')
                ->values();
            foreach ($items as $item) {
                $id = (int) $item['product_id'];
                $itemCounts[$id] = ($itemCounts[$id] ?? 0) + 1;
                $nameById[$id] = $item['name'];
            }
            for ($i = 0; $i < $items->count(); $i++) {
                for ($j = $i + 1; $j < $items->count(); $j++) {
                    $a = min((int) $items[$i]['product_id'], (int) $items[$j]['product_id']);
                    $b = max((int) $items[$i]['product_id'], (int) $items[$j]['product_id']);
                    $key = $a.':'.$b;
                    $pairCounts[$key] = ($pairCounts[$key] ?? 0) + 1;
                }
            }
        }

        return collect($pairCounts)
            ->map(function (int $count, string $key) use ($itemCounts, $nameById, $orderCount): array {
                [$a, $b] = array_map('intval', explode(':', $key));
                $support = $orderCount > 0 ? $count / $orderCount : 0;
                $confidence = ($itemCounts[$a] ?? 0) > 0 ? $count / $itemCounts[$a] : 0;
                $expected = $orderCount > 0 ? ($itemCounts[$b] ?? 0) / $orderCount : 0;

                return [
                    'item_a' => $nameById[$a] ?? 'Món #'.$a,
                    'item_b' => $nameById[$b] ?? 'Món #'.$b,
                    'product_a_id' => $a,
                    'product_b_id' => $b,
                    'co_occurrence' => $count,
                    'support' => round($support * 100, 1),
                    'confidence' => round($confidence * 100, 1),
                    'lift' => $expected > 0 ? round($confidence / $expected, 2) : 0,
                ];
            })
            ->sortByDesc(fn (array $pair): array => [$pair['lift'], $pair['co_occurrence']])
            ->take(8)->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     * @return array<string, mixed>
     */
    private function buildCustomerHabits(array $orders, array $previousOrders, $menu): array
    {
        $customerCounts = collect($orders)
            ->filter(fn (array $order): bool => $order['customer_id'] !== null)
            ->countBy('customer_id');
        $identifiedCustomers = $customerCounts->count();
        $repeatCustomers = $customerCounts->filter(fn (int $count): bool => $count >= 2)->count();
        $totalItems = (float) $menu->sum('qty');

        return [
            'identified_customers' => $identifiedCustomers,
            'repeat_customers' => $repeatCustomers,
            'repeat_rate' => $identifiedCustomers > 0
                ? round(($repeatCustomers / $identifiedCustomers) * 100, 1) : 0,
            'avg_orders_per_customer' => $identifiedCustomers > 0
                ? round(count($orders) / $identifiedCustomers, 1) : 0,
            'avg_items_per_order' => count($orders) > 0 ? round($totalItems / count($orders), 1) : 0,
            'new_or_returning_signal' => $repeatCustomers > 0
                ? 'Có nhóm khách quay lại; nên tạo ưu đãi cá nhân hóa theo món ưa thích.'
                : 'Chưa đủ khách định danh để kết luận tỷ lệ quay lại.',
            'previous_identified_customers' => collect($previousOrders)
                ->filter(fn (array $order): bool => $order['customer_id'] !== null)
                ->pluck('customer_id')->unique()->count(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function buildDaypartBreakdown(array $orders): array
    {
        $labels = [
            'morning' => 'Sáng (06–11h)', 'lunch' => 'Trưa (11–14h)',
            'afternoon' => 'Chiều (14–17h)', 'dinner' => 'Tối (17–22h)',
            'late' => 'Đêm (22–06h)',
        ];
        $stats = collect(array_keys($labels))->mapWithKeys(fn (string $key): array => [$key => [
            'key' => $key, 'label' => $labels[$key], 'orders' => 0, 'revenue' => 0.0,
        ]]);

        foreach ($orders as $order) {
            $hour = (int) Carbon::parse($order['completed_at'])->hour;
            $key = match (true) {
                $hour >= 6 && $hour < 11 => 'morning',
                $hour >= 11 && $hour < 14 => 'lunch',
                $hour >= 14 && $hour < 17 => 'afternoon',
                $hour >= 17 && $hour < 22 => 'dinner',
                default => 'late',
            };
            $item = $stats->get($key);
            $item['orders']++;
            $item['revenue'] += (float) $order['total_amount'];
            $stats->put($key, $item);
        }

        $maxOrders = max(1, (int) $stats->max('orders'));

        return $stats->map(function (array $item) use ($maxOrders): array {
            $item['revenue'] = round($item['revenue'], 2);
            $item['share'] = round(($item['orders'] / $maxOrders) * 100, 1);

            return $item;
        })->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function buildChannelBreakdown(array $orders): array
    {
        return collect($orders)->groupBy('channel')->map(function ($items, string $channel): array {
            return [
                'channel' => $channel,
                'orders' => $items->count(),
                'revenue' => round((float) $items->sum('total_amount'), 2),
                'avg_order_value' => $items->count() > 0
                    ? round((float) $items->sum('total_amount') / $items->count(), 2) : 0,
            ];
        })->sortByDesc('orders')->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $menu
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryBreakdown($menu): array
    {
        return $menu->groupBy('category_name')->map(function ($items, string $category): array {
            return [
                'category' => $category,
                'items' => $this->roundMetric((float) $items->sum('qty')),
                'revenue' => round((float) $items->sum('revenue'), 2),
                'products' => $items->count(),
                'top_product' => $items->sortByDesc('qty')->first()['name'] ?? null,
            ];
        })->sortByDesc('items')->values()->take(8)->all();
    }

    private function percentageChange(float|int $previous, float|int $current): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function roundMetric(float $value): int|float
    {
        return fmod($value, 1.0) === 0.0 ? (int) $value : round($value, 2);
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
