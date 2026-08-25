<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phân tích món bán chạy (Best-Seller Analytics).
 *
 * Khác với MenuInsightService (BCG + chấm điểm + A/B test giá) vốn trả lời câu
 * hỏi "món nào nên giữ / bỏ", service này trả lời câu hỏi vận hành hằng ngày:
 * "món nào đang bán chạy, chạy tới mức nào, chạy nhờ đâu và đang lên hay xuống".
 *
 * Điểm khác biệt chính:
 *  - Xếp hạng ĐẦY ĐỦ danh mục (không cắt top 5/10 cứng như dashboard/report).
 *  - Phân loại ABC theo nguyên lý Pareto trên chỉ số người dùng chọn.
 *  - Đo mức độ tập trung doanh thu (HHI) — cảnh báo phụ thuộc quá ít món.
 *  - Chuỗi thời gian theo ngày cho từng món để nhìn xu hướng thật, không chỉ
 *    so sánh hai con số đầu – cuối kỳ.
 *  - Drill-down từng món: khung giờ, thứ trong tuần, kênh bán, chi nhánh và
 *    các món hay được gọi kèm.
 */
class BestSellerAnalyticsService
{
    /** Các chỉ số có thể dùng để xếp hạng. */
    public const METRICS = ['quantity', 'revenue', 'profit'];

    /** Ngưỡng luỹ kế cho nhóm A và nhóm B trong phân loại ABC. */
    private const ABC_A_THRESHOLD = 80.0;

    private const ABC_B_THRESHOLD = 95.0;

    /** Trần số đơn khi quét phân tích gọi kèm. */
    private const CO_ORDER_SCAN_LIMIT = 20000;

    private const CACHE_TTL = 300;

    /**
     * Toàn bộ dữ liệu cho màn hình "Phân tích món bán chạy".
     *
     * @param  array{metric?: string|null, category_id?: int|null, limit?: int|null}  $options
     * @return array<string, mixed>
     */
    public function analyze(
        int $restaurantId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId = null,
        array $options = [],
    ): array {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $metric = $this->normalizeMetric($options['metric'] ?? null);
        $categoryId = isset($options['category_id']) && $options['category_id'] !== null
            ? (int) $options['category_id']
            : null;
        $limit = max(3, min(30, (int) ($options['limit'] ?? 10)));

        $cacheKey = implode(':', [
            'best_sellers', $restaurantId, $branchId ?? 'all', $categoryId ?? 'all',
            $metric, $limit, $from->toDateString(), $to->toDateString(),
        ]);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $restaurantId, $from, $to, $branchId, $metric, $categoryId, $limit
        ): array {
            return $this->build($restaurantId, $from, $to, $branchId, $metric, $categoryId, $limit);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function build(
        int $restaurantId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
        string $metric,
        ?int $categoryId,
        int $limit,
    ): array {
        // Kỳ so sánh có độ dài đúng bằng kỳ hiện tại và kết thúc ngay trước đó.
        $days = $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $previousTo = $from->subSecond();
        $previousFrom = $from->subDays($days)->startOfDay();

        $current = $this->aggregateProducts($restaurantId, $from, $to, $branchId, $categoryId);
        $previous = $this->aggregateProducts($restaurantId, $previousFrom, $previousTo, $branchId, $categoryId);

        $currentOrders = $this->countOrders($restaurantId, $from, $to, $branchId);
        $previousOrders = $this->countOrders($restaurantId, $previousFrom, $previousTo, $branchId);

        $ranking = $this->buildRanking($current, $previous, $metric, $currentOrders);
        $topIds = collect($ranking)
            ->filter(fn (array $row): bool => $row['metric_value'] > 0)
            ->take($limit)
            ->pluck('product_id')
            ->all();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $previousTo->toDateString(),
            ],
            'filters' => [
                'metric' => $metric,
                'metric_label' => $this->metricLabel($metric),
                'category_id' => $categoryId,
                'limit' => $limit,
                'branch_id' => $branchId,
            ],
            'summary' => $this->buildSummary(
                $ranking,
                $current,
                $previous,
                $currentOrders,
                $previousOrders,
                $metric,
                $restaurantId,
                $categoryId,
            ),
            'ranking' => $ranking,
            'pareto' => $this->buildPareto($ranking, $metric),
            'movers' => $this->buildMovers($ranking),
            'daily_series' => $this->buildDailySeries($restaurantId, $from, $to, $branchId, $categoryId, $topIds),
            'categories' => $this->buildCategories($ranking),
            'dayparts' => $this->buildDayparts($restaurantId, $from, $to, $branchId, $categoryId),
            'weekdays' => $this->buildWeekdays($restaurantId, $from, $to, $branchId, $categoryId),
            'channels' => $this->buildChannels($restaurantId, $from, $to, $branchId, $categoryId),
            'branches' => $this->buildBranches($restaurantId, $from, $to, $branchId, $categoryId),
        ];
    }

    /**
     * Chi tiết một món: xu hướng, khung giờ, kênh bán, chi nhánh, món gọi kèm.
     *
     * @return array<string, mixed>|null
     */
    public function dishDetail(
        int $restaurantId,
        int $productId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId = null,
    ): ?array {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $days = $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $previousTo = $from->subSecond();
        $previousFrom = $from->subDays($days)->startOfDay();

        $product = DB::table('products')
            ->where('id', $productId)
            ->where('restaurant_id', $restaurantId)
            ->first(['id', 'name', 'price', 'cost_price', 'image_url', 'is_active', 'deleted_at']);

        if (! $product) {
            return null;
        }

        $current = $this->aggregateProducts($restaurantId, $from, $to, $branchId, null, $productId)->first();
        $previous = $this->aggregateProducts($restaurantId, $previousFrom, $previousTo, $branchId, null, $productId)->first();
        $orders = $this->countOrders($restaurantId, $from, $to, $branchId);

        $qty = (float) ($current['qty'] ?? 0);
        $revenue = (float) ($current['revenue'] ?? 0);
        $profit = (float) ($current['gross_profit'] ?? 0);
        $orderCount = (int) ($current['order_count'] ?? 0);
        $series = $this->buildDailySeries($restaurantId, $from, $to, $branchId, null, [$productId]);

        return [
            'product' => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'price' => (float) $product->price,
                'cost_price' => (float) ($product->cost_price ?? 0),
                'image_url' => $product->image_url,
                'is_active' => (bool) $product->is_active,
                'is_retired' => $product->deleted_at !== null,
                'category_name' => $current['category_name'] ?? 'Chưa phân loại',
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $previousTo->toDateString(),
            ],
            'summary' => [
                'qty' => $this->roundMetric($qty),
                'revenue' => round($revenue, 2),
                'gross_profit' => round($profit, 2),
                'margin_percent' => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0.0,
                'order_count' => $orderCount,
                'attach_rate' => $orders > 0 ? round($orderCount / $orders * 100, 1) : 0.0,
                'avg_qty_per_order' => $orderCount > 0 ? round($qty / $orderCount, 2) : 0.0,
                'daily_avg_qty' => $days > 0 ? round($qty / $days, 2) : 0.0,
                'previous_qty' => $this->roundMetric((float) ($previous['qty'] ?? 0)),
                'previous_revenue' => round((float) ($previous['revenue'] ?? 0), 2),
                'qty_change_percent' => $this->percentageChange((float) ($previous['qty'] ?? 0), $qty),
                'revenue_change_percent' => $this->percentageChange((float) ($previous['revenue'] ?? 0), $revenue),
            ],
            'daily_series' => $series['products'][0]['points'] ?? [],
            'dayparts' => $this->buildDayparts($restaurantId, $from, $to, $branchId, null, $productId),
            'weekdays' => $this->buildWeekdays($restaurantId, $from, $to, $branchId, null, $productId),
            'channels' => $this->buildChannels($restaurantId, $from, $to, $branchId, null, $productId),
            'branches' => $this->buildBranches($restaurantId, $from, $to, $branchId, null, $productId),
            'paired_with' => $this->buildPairedDishes($restaurantId, $from, $to, $branchId, $productId),
        ];
    }

    /**
     * Dòng dữ liệu phẳng để xuất CSV.
     *
     * @param  array<int, array<string, mixed>>  $ranking
     * @return array<int, array<int, string|int|float>>
     */
    public function exportRows(array $ranking): array
    {
        $rows = [[
            'Hạng', 'Món', 'Danh mục', 'Số lượng bán', 'Doanh thu', 'Giá vốn',
            'Lợi nhuận gộp', 'Biên LN (%)', 'Số đơn có món', 'Tỷ lệ đơn có món (%)',
            'SL TB/đơn', 'Tỷ trọng (%)', 'Luỹ kế (%)', 'Nhóm ABC',
            'Kỳ trước', 'Thay đổi (%)', 'Xu hướng',
        ]];

        foreach ($ranking as $row) {
            $rows[] = [
                $row['rank'],
                $row['name'],
                $row['category_name'],
                $row['qty'],
                $row['revenue'],
                $row['cogs'],
                $row['gross_profit'],
                $row['margin_percent'],
                $row['order_count'],
                $row['attach_rate'],
                $row['avg_qty_per_order'],
                $row['share_percent'],
                $row['cumulative_percent'],
                $row['abc_class'],
                $row['previous_metric_value'],
                $row['change_percent'],
                $this->trendLabel($row['trend']),
            ];
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Truy vấn nền
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Cặp bảng cần quét: bảng nóng + bảng archive (nếu có).
     *
     * @return array<int, array{items: string, orders: string}>
     */
    private function tablePairs(): array
    {
        $pairs = [['items' => 'order_items', 'orders' => 'orders']];

        if (Schema::hasTable('order_items_archive') && Schema::hasTable('orders_archive')) {
            $pairs[] = ['items' => 'order_items_archive', 'orders' => 'orders_archive'];
        }

        return $pairs;
    }

    /**
     * @param  array{items: string, orders: string}  $pair
     */
    private function itemQuery(
        array $pair,
        int $restaurantId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
        ?int $categoryId = null,
        ?int $productId = null,
    ) {
        return DB::table($pair['items'].' as oi')
            ->join($pair['orders'].' as o', 'oi.order_id', '=', 'o.id')
            ->join('products as p', 'oi.product_id', '=', 'p.id')
            ->where('o.restaurant_id', $restaurantId)
            ->where('o.status', 'completed')
            ->whereBetween('o.completed_at', [$from, $to])
            ->where('oi.status', '!=', 'cancelled')
            ->when($pair['orders'] === 'orders', fn ($query) => $query->whereNull('o.deleted_at'))
            ->when($branchId !== null, fn ($query) => $query->where('o.branch_id', $branchId))
            ->when($categoryId !== null, fn ($query) => $query->where('p.category_id', $categoryId))
            ->when($productId !== null, fn ($query) => $query->where('oi.product_id', $productId));
    }

    /**
     * Gộp doanh số theo món, cộng dồn cả bảng nóng lẫn archive.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregateProducts(
        int $restaurantId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
        ?int $categoryId,
        ?int $productId = null,
    ): Collection {
        $rows = collect();

        foreach ($this->tablePairs() as $pair) {
            $records = $this->itemQuery($pair, $restaurantId, $from, $to, $branchId, $categoryId, $productId)
                ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                ->select([
                    'oi.product_id',
                    'p.name',
                    'p.price',
                    'p.cost_price',
                    'p.category_id',
                    'p.deleted_at as product_deleted_at',
                    'pc.name as category_name',
                    DB::raw('SUM(oi.quantity) as qty'),
                    DB::raw('SUM(oi.line_total) as revenue'),
                    DB::raw('COUNT(DISTINCT oi.order_id) as order_count'),
                ])
                ->groupBy('oi.product_id', 'p.name', 'p.price', 'p.cost_price', 'p.category_id', 'p.deleted_at', 'pc.name')
                ->get();

            foreach ($records as $record) {
                $key = (int) $record->product_id;
                $existing = $rows->get($key, [
                    'product_id' => $key,
                    'name' => (string) $record->name,
                    'price' => (float) $record->price,
                    'cost_price' => (float) ($record->cost_price ?? 0),
                    'category_id' => $record->category_id ? (int) $record->category_id : null,
                    'category_name' => $record->category_name ?: 'Chưa phân loại',
                    'is_retired' => $record->product_deleted_at !== null,
                    'qty' => 0.0,
                    'revenue' => 0.0,
                    'order_count' => 0,
                ]);
                $existing['qty'] += (float) $record->qty;
                $existing['revenue'] += (float) $record->revenue;
                $existing['order_count'] += (int) $record->order_count;
                $rows->put($key, $existing);
            }
        }

        return $rows->map(function (array $item): array {
            $item['cogs'] = round($item['cost_price'] * $item['qty'], 2);
            $item['gross_profit'] = round($item['revenue'] - $item['cogs'], 2);
            $item['revenue'] = round($item['revenue'], 2);

            return $item;
        });
    }

    private function countOrders(
        int $restaurantId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
    ): int {
        $total = 0;

        foreach ($this->tablePairs() as $pair) {
            $total += (int) DB::table($pair['orders'].' as o')
                ->where('o.restaurant_id', $restaurantId)
                ->where('o.status', 'completed')
                ->whereBetween('o.completed_at', [$from, $to])
                ->when($pair['orders'] === 'orders', fn ($query) => $query->whereNull('o.deleted_at'))
                ->when($branchId !== null, fn ($query) => $query->where('o.branch_id', $branchId))
                ->count('o.id');
        }

        return $total;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dựng kết quả
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  Collection<int, array<string, mixed>>  $current
     * @param  Collection<int, array<string, mixed>>  $previous
     * @return array<int, array<string, mixed>>
     */
    private function buildRanking(
        Collection $current,
        Collection $previous,
        string $metric,
        int $totalOrders,
    ): array {
        // Giữ cả món chỉ xuất hiện ở kỳ trước để nhận diện món đã rơi khỏi menu.
        $ids = $current->keys()->merge($previous->keys())->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $previousRanks = $this->rankMap($previous, $metric);

        $rows = $ids->map(function (int $productId) use ($current, $previous, $metric, $totalOrders): array {
            $now = $current->get($productId);
            $before = $previous->get($productId);
            $base = $now ?? array_merge($before, [
                'qty' => 0.0, 'revenue' => 0.0, 'cogs' => 0.0,
                'gross_profit' => 0.0, 'order_count' => 0,
            ]);

            $qty = (float) $base['qty'];
            $revenue = (float) $base['revenue'];
            $profit = (float) $base['gross_profit'];
            $orderCount = (int) $base['order_count'];

            $metricValue = $this->metricValue($base, $metric);
            $previousMetricValue = $before ? $this->metricValue($before, $metric) : 0.0;

            return [
                'product_id' => $productId,
                'name' => $base['name'],
                'category_id' => $base['category_id'],
                'category_name' => $base['category_name'],
                'price' => round((float) $base['price'], 2),
                'cost_price' => round((float) $base['cost_price'], 2),
                'is_retired' => (bool) $base['is_retired'],
                'qty' => $this->roundMetric($qty),
                'revenue' => round($revenue, 2),
                'cogs' => round((float) $base['cogs'], 2),
                'gross_profit' => round($profit, 2),
                'margin_percent' => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0.0,
                'order_count' => $orderCount,
                'attach_rate' => $totalOrders > 0 ? round($orderCount / $totalOrders * 100, 1) : 0.0,
                'avg_qty_per_order' => $orderCount > 0 ? round($qty / $orderCount, 2) : 0.0,
                'metric_value' => round($metricValue, 2),
                'previous_qty' => $this->roundMetric((float) ($before['qty'] ?? 0)),
                'previous_revenue' => round((float) ($before['revenue'] ?? 0), 2),
                'previous_metric_value' => round($previousMetricValue, 2),
                'change_value' => round($metricValue - $previousMetricValue, 2),
                'change_percent' => $this->percentageChange($previousMetricValue, $metricValue),
                'trend' => $this->trendOf($previousMetricValue, $metricValue),
                'is_new' => $previousMetricValue <= 0 && $metricValue > 0,
                'is_dropped' => $previousMetricValue > 0 && $metricValue <= 0,
            ];
        })
            ->sortByDesc('metric_value')
            ->values();

        $totalMetric = (float) $rows->sum('metric_value');
        $cumulative = 0.0;

        return $rows->map(function (array $row, int $index) use (&$cumulative, $totalMetric, $previousRanks): array {
            $share = $totalMetric > 0 ? $row['metric_value'] / $totalMetric * 100 : 0.0;
            $cumulativeBefore = $cumulative;
            $cumulative += $share;

            $row['rank'] = $index + 1;
            $row['share_percent'] = round($share, 2);
            $row['cumulative_percent'] = round(min($cumulative, 100), 2);
            $row['abc_class'] = $this->abcClass($cumulativeBefore, (float) $row['metric_value']);
            $row['previous_rank'] = $previousRanks[$row['product_id']] ?? null;
            $row['rank_delta'] = $row['previous_rank'] !== null
                ? $row['previous_rank'] - $row['rank']
                : null;

            return $row;
        })->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $products
     * @return array<int, int>
     */
    private function rankMap(Collection $products, string $metric): array
    {
        return $products
            ->filter(fn (array $item): bool => $this->metricValue($item, $metric) > 0)
            ->sortByDesc(fn (array $item): float => $this->metricValue($item, $metric))
            ->values()
            ->mapWithKeys(fn (array $item, int $index): array => [$item['product_id'] => $index + 1])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $ranking
     * @param  Collection<int, array<string, mixed>>  $current
     * @param  Collection<int, array<string, mixed>>  $previous
     * @return array<string, mixed>
     */
    private function buildSummary(
        array $ranking,
        Collection $current,
        Collection $previous,
        int $currentOrders,
        int $previousOrders,
        string $metric,
        int $restaurantId,
        ?int $categoryId,
    ): array {
        $sold = collect($ranking)->filter(fn (array $row): bool => $row['metric_value'] > 0)->values();
        $totalQty = (float) $current->sum('qty');
        $totalRevenue = (float) $current->sum('revenue');
        $totalCogs = (float) $current->sum('cogs');
        $totalProfit = (float) $current->sum('gross_profit');

        $hhi = $sold->reduce(
            static fn (float $carry, array $row): float => $carry + ((float) $row['share_percent'] ** 2),
            0.0,
        );

        $catalogSize = (int) DB::table('products')
            ->where('restaurant_id', $restaurantId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
            ->count();

        $top = $sold->first();

        return [
            'metric' => $metric,
            'metric_label' => $this->metricLabel($metric),
            'total_qty' => $this->roundMetric($totalQty),
            'total_revenue' => round($totalRevenue, 2),
            'total_cogs' => round($totalCogs, 2),
            'total_gross_profit' => round($totalProfit, 2),
            'gross_margin_percent' => $totalRevenue > 0 ? round($totalProfit / $totalRevenue * 100, 1) : 0.0,
            'orders' => $currentOrders,
            'avg_items_per_order' => $currentOrders > 0 ? round($totalQty / $currentOrders, 2) : 0.0,
            'dishes_sold' => $sold->count(),
            'catalog_size' => $catalogSize,
            'catalog_coverage' => $catalogSize > 0
                ? round(min($sold->count() / $catalogSize, 1) * 100, 1)
                : 0.0,
            'never_sold' => max(0, $catalogSize - $sold->count()),
            'top_dish' => $top ? [
                'product_id' => $top['product_id'],
                'name' => $top['name'],
                'qty' => $top['qty'],
                'revenue' => $top['revenue'],
                'gross_profit' => $top['gross_profit'],
                'share_percent' => $top['share_percent'],
            ] : null,
            'top1_share' => round((float) $sold->take(1)->sum('share_percent'), 1),
            'top3_share' => round((float) $sold->take(3)->sum('share_percent'), 1),
            'top5_share' => round((float) $sold->take(5)->sum('share_percent'), 1),
            'top10_share' => round((float) $sold->take(10)->sum('share_percent'), 1),
            'hhi' => (int) round($hhi),
            'concentration' => $this->concentrationLevel($hhi),
            'previous' => [
                'total_qty' => $this->roundMetric((float) $previous->sum('qty')),
                'total_revenue' => round((float) $previous->sum('revenue'), 2),
                'total_gross_profit' => round((float) $previous->sum('gross_profit'), 2),
                'orders' => $previousOrders,
                'dishes_sold' => $previous->filter(fn (array $item): bool => $item['qty'] > 0)->count(),
            ],
            'change' => [
                'qty_percent' => $this->percentageChange((float) $previous->sum('qty'), $totalQty),
                'revenue_percent' => $this->percentageChange((float) $previous->sum('revenue'), $totalRevenue),
                'profit_percent' => $this->percentageChange((float) $previous->sum('gross_profit'), $totalProfit),
                'orders_percent' => $this->percentageChange($previousOrders, $currentOrders),
            ],
        ];
    }

    /**
     * Phân loại ABC theo nguyên lý Pareto trên chỉ số đang chọn.
     *
     * @param  array<int, array<string, mixed>>  $ranking
     * @return array<string, mixed>
     */
    private function buildPareto(array $ranking, string $metric): array
    {
        $sold = collect($ranking)->filter(fn (array $row): bool => $row['metric_value'] > 0)->values();

        $definitions = [
            'A' => [
                'label' => 'Nhóm A — Trụ cột',
                'hint' => 'Tạo ~80% '.$this->metricLabel($metric).'. Tuyệt đối không để hết nguyên liệu, không đổi công thức tuỳ tiện.',
            ],
            'B' => [
                'label' => 'Nhóm B — Hỗ trợ',
                'hint' => 'Đóng góp vừa phải. Ứng viên tốt để đẩy lên nhóm A bằng combo hoặc vị trí đẹp trên menu.',
            ],
            'C' => [
                'label' => 'Nhóm C — Đuôi dài',
                'hint' => 'Đóng góp không đáng kể nhưng vẫn tốn nguyên liệu, kho bãi và công đào tạo bếp.',
            ],
        ];

        $classes = [];
        foreach ($definitions as $class => $definition) {
            $items = $sold->where('abc_class', $class);
            $classes[] = [
                'class' => $class,
                'label' => $definition['label'],
                'hint' => $definition['hint'],
                'dishes' => $items->count(),
                'qty' => $this->roundMetric((float) $items->sum('qty')),
                'revenue' => round((float) $items->sum('revenue'), 2),
                'gross_profit' => round((float) $items->sum('gross_profit'), 2),
                'share_percent' => round((float) $items->sum('share_percent'), 1),
                'dish_share_percent' => $sold->count() > 0
                    ? round($items->count() / $sold->count() * 100, 1)
                    : 0.0,
            ];
        }

        $dishesFor80 = $sold->where('abc_class', 'A')->count();

        return [
            'classes' => $classes,
            'dishes_for_80' => $dishesFor80,
            'dishes_for_80_share' => $sold->count() > 0
                ? round($dishesFor80 / $sold->count() * 100, 1)
                : 0.0,
            'dishes_sold' => $sold->count(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $ranking
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildMovers(array $ranking): array
    {
        $rows = collect($ranking);

        return [
            'rising' => $rows
                ->filter(fn (array $row): bool => $row['change_value'] > 0 && ! $row['is_new'])
                ->sortByDesc(fn (array $row): array => [$row['change_percent'], $row['change_value']])
                ->take(8)->values()->all(),
            'falling' => $rows
                ->filter(fn (array $row): bool => $row['change_value'] < 0 && ! $row['is_dropped'])
                ->sortBy(fn (array $row): array => [$row['change_percent'], $row['change_value']])
                ->take(8)->values()->all(),
            'newcomers' => $rows
                ->filter(fn (array $row): bool => $row['is_new'])
                ->sortByDesc('metric_value')->take(8)->values()->all(),
            'dropouts' => $rows
                ->filter(fn (array $row): bool => $row['is_dropped'])
                ->sortByDesc('previous_metric_value')->take(8)->values()->all(),
        ];
    }

    /**
     * Chuỗi theo ngày: tổng toàn kỳ + đường riêng cho từng món top.
     *
     * @param  array<int, int>  $productIds
     * @return array<string, mixed>
     */
    private function buildDailySeries(
        int $restaurantId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
        ?int $categoryId,
        array $productIds,
    ): array {
        $dates = [];
        for ($cursor = $from->startOfDay(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            $dates[] = $cursor->toDateString();
        }

        $totals = array_fill_keys($dates, ['qty' => 0.0, 'revenue' => 0.0]);
        $perProduct = [];

        foreach ($this->tablePairs() as $pair) {
            $records = $this->itemQuery($pair, $restaurantId, $from, $to, $branchId, $categoryId)
                ->select([
                    'oi.product_id',
                    DB::raw('DATE(o.completed_at) as day'),
                    DB::raw('SUM(oi.quantity) as qty'),
                    DB::raw('SUM(oi.line_total) as revenue'),
                ])
                ->groupBy('oi.product_id', DB::raw('DATE(o.completed_at)'))
                ->get();

            foreach ($records as $record) {
                $day = substr((string) $record->day, 0, 10);
                if (! array_key_exists($day, $totals)) {
                    continue;
                }
                $totals[$day]['qty'] += (float) $record->qty;
                $totals[$day]['revenue'] += (float) $record->revenue;

                $productId = (int) $record->product_id;
                if (! in_array($productId, $productIds, true)) {
                    continue;
                }
                $perProduct[$productId][$day]['qty'] = ($perProduct[$productId][$day]['qty'] ?? 0.0) + (float) $record->qty;
                $perProduct[$productId][$day]['revenue'] = ($perProduct[$productId][$day]['revenue'] ?? 0.0) + (float) $record->revenue;
            }
        }

        $names = DB::table('products')
            ->where('restaurant_id', $restaurantId)
            ->whereIn('id', $productIds ?: [0])
            ->pluck('name', 'id');

        $products = [];
        foreach ($productIds as $productId) {
            $products[] = [
                'product_id' => $productId,
                'name' => (string) ($names[$productId] ?? 'Món #'.$productId),
                'points' => array_map(fn (string $day): array => [
                    'date' => $day,
                    'qty' => $this->roundMetric((float) ($perProduct[$productId][$day]['qty'] ?? 0)),
                    'revenue' => round((float) ($perProduct[$productId][$day]['revenue'] ?? 0), 2),
                ], $dates),
            ];
        }

        return [
            'dates' => $dates,
            'total' => array_map(fn (string $day): array => [
                'date' => $day,
                'qty' => $this->roundMetric($totals[$day]['qty']),
                'revenue' => round($totals[$day]['revenue'], 2),
            ], $dates),
            'products' => $products,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $ranking
     * @return array<int, array<string, mixed>>
     */
    private function buildCategories(array $ranking): array
    {
        $sold = collect($ranking)->filter(fn (array $row): bool => $row['qty'] > 0);
        $totalRevenue = (float) $sold->sum('revenue');

        return $sold
            ->groupBy('category_name')
            ->map(function (Collection $items, string $category) use ($totalRevenue): array {
                $revenue = (float) $items->sum('revenue');
                $best = $items->sortByDesc('qty')->first();

                return [
                    'category' => $category,
                    'dishes' => $items->count(),
                    'qty' => $this->roundMetric((float) $items->sum('qty')),
                    'revenue' => round($revenue, 2),
                    'gross_profit' => round((float) $items->sum('gross_profit'), 2),
                    'revenue_share' => $totalRevenue > 0 ? round($revenue / $totalRevenue * 100, 1) : 0.0,
                    'top_dish' => $best['name'] ?? null,
                    'top_dish_qty' => $best['qty'] ?? 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDayparts(
        int $restaurantId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
        ?int $categoryId,
        ?int $productId = null,
    ): array {
        $labels = [
            'morning' => 'Sáng (06–11h)',
            'lunch' => 'Trưa (11–14h)',
            'afternoon' => 'Chiều (14–17h)',
            'dinner' => 'Tối (17–22h)',
            'late' => 'Đêm (22–06h)',
        ];
        $buckets = [];
        foreach ($labels as $key => $label) {
            $buckets[$key] = ['key' => $key, 'label' => $label, 'qty' => 0.0, 'revenue' => 0.0];
        }

        $hourExpression = $this->hourExpression();

        foreach ($this->tablePairs() as $pair) {
            $records = $this->itemQuery($pair, $restaurantId, $from, $to, $branchId, $categoryId, $productId)
                ->selectRaw($hourExpression.' as hour, SUM(oi.quantity) as qty, SUM(oi.line_total) as revenue')
                ->groupBy(DB::raw($hourExpression))
                ->get();

            foreach ($records as $record) {
                $hour = (int) $record->hour;
                $key = match (true) {
                    $hour >= 6 && $hour < 11 => 'morning',
                    $hour >= 11 && $hour < 14 => 'lunch',
                    $hour >= 14 && $hour < 17 => 'afternoon',
                    $hour >= 17 && $hour < 22 => 'dinner',
                    default => 'late',
                };
                $buckets[$key]['qty'] += (float) $record->qty;
                $buckets[$key]['revenue'] += (float) $record->revenue;
            }
        }

        return $this->withShare(array_values($buckets));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildWeekdays(
        int $restaurantId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
        ?int $categoryId,
        ?int $productId = null,
    ): array {
        // 0 = Chủ nhật … 6 = Thứ Bảy — khớp strftime('%w') và DAYOFWEEK() - 1.
        $labels = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
        $buckets = [];
        foreach ($labels as $index => $label) {
            $buckets[$index] = ['key' => (string) $index, 'label' => $label, 'qty' => 0.0, 'revenue' => 0.0];
        }

        $weekdayExpression = $this->weekdayExpression();

        foreach ($this->tablePairs() as $pair) {
            $records = $this->itemQuery($pair, $restaurantId, $from, $to, $branchId, $categoryId, $productId)
                ->selectRaw($weekdayExpression.' as weekday, SUM(oi.quantity) as qty, SUM(oi.line_total) as revenue')
                ->groupBy(DB::raw($weekdayExpression))
                ->get();

            foreach ($records as $record) {
                $index = (int) $record->weekday;
                if (! isset($buckets[$index])) {
                    continue;
                }
                $buckets[$index]['qty'] += (float) $record->qty;
                $buckets[$index]['revenue'] += (float) $record->revenue;
            }
        }

        return $this->withShare(array_values($buckets));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildChannels(
        int $restaurantId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
        ?int $categoryId,
        ?int $productId = null,
    ): array {
        $rows = collect();

        foreach ($this->tablePairs() as $pair) {
            $records = $this->itemQuery($pair, $restaurantId, $from, $to, $branchId, $categoryId, $productId)
                ->select([
                    'o.channel',
                    DB::raw('SUM(oi.quantity) as qty'),
                    DB::raw('SUM(oi.line_total) as revenue'),
                    DB::raw('COUNT(DISTINCT o.id) as orders'),
                ])
                ->groupBy('o.channel')
                ->get();

            foreach ($records as $record) {
                $key = (string) ($record->channel ?: 'other');
                $existing = $rows->get($key, [
                    'key' => $key,
                    'label' => $this->channelLabel($key),
                    'qty' => 0.0,
                    'revenue' => 0.0,
                    'orders' => 0,
                ]);
                $existing['qty'] += (float) $record->qty;
                $existing['revenue'] += (float) $record->revenue;
                $existing['orders'] += (int) $record->orders;
                $rows->put($key, $existing);
            }
        }

        return $this->withShare($rows->sortByDesc('qty')->values()->all());
    }

    /**
     * Doanh số theo chi nhánh, kèm món bán chạy nhất của từng chi nhánh.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildBranches(
        int $restaurantId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
        ?int $categoryId,
        ?int $productId = null,
    ): array {
        $branches = collect();

        foreach ($this->tablePairs() as $pair) {
            $records = $this->itemQuery($pair, $restaurantId, $from, $to, $branchId, $categoryId, $productId)
                ->leftJoin('restaurant_branches as b', 'o.branch_id', '=', 'b.id')
                ->select([
                    'o.branch_id',
                    DB::raw("COALESCE(b.name, 'Chưa gán chi nhánh') as branch_name"),
                    'oi.product_id',
                    'p.name as product_name',
                    DB::raw('SUM(oi.quantity) as qty'),
                    DB::raw('SUM(oi.line_total) as revenue'),
                ])
                ->groupBy('o.branch_id', 'b.name', 'oi.product_id', 'p.name')
                ->get();

            foreach ($records as $record) {
                $key = $record->branch_id ? (int) $record->branch_id : 0;
                $branch = $branches->get($key, [
                    'branch_id' => $key ?: null,
                    'branch_name' => (string) $record->branch_name,
                    'qty' => 0.0,
                    'revenue' => 0.0,
                    'dishes' => [],
                ]);
                $branch['qty'] += (float) $record->qty;
                $branch['revenue'] += (float) $record->revenue;

                $productKey = (int) $record->product_id;
                $branch['dishes'][$productKey] = [
                    'name' => (string) $record->product_name,
                    'qty' => ($branch['dishes'][$productKey]['qty'] ?? 0.0) + (float) $record->qty,
                ];
                $branches->put($key, $branch);
            }
        }

        return $this->withShare(
            $branches->map(function (array $branch): array {
                $top = collect($branch['dishes'])->sortByDesc('qty')->first();
                unset($branch['dishes']);
                $branch['top_dish'] = $top['name'] ?? null;
                $branch['top_dish_qty'] = $this->roundMetric((float) ($top['qty'] ?? 0));

                return $branch;
            })->sortByDesc('qty')->values()->all()
        );
    }

    /**
     * Món hay được gọi chung đơn với món đang xem.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPairedDishes(
        int $restaurantId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $branchId,
        int $productId,
    ): array {
        $pairs = collect();
        $baseOrders = 0;

        foreach ($this->tablePairs() as $pair) {
            $orderIds = $this->itemQuery($pair, $restaurantId, $from, $to, $branchId, null, $productId)
                ->distinct()
                ->limit(self::CO_ORDER_SCAN_LIMIT)
                ->pluck('oi.order_id')
                ->all();

            if ($orderIds === []) {
                continue;
            }
            $baseOrders += count($orderIds);

            $records = DB::table($pair['items'].' as oi')
                ->join('products as p', 'oi.product_id', '=', 'p.id')
                ->whereIn('oi.order_id', $orderIds)
                ->where('oi.product_id', '!=', $productId)
                ->where('oi.status', '!=', 'cancelled')
                ->select([
                    'oi.product_id',
                    'p.name',
                    DB::raw('SUM(oi.quantity) as qty'),
                    DB::raw('COUNT(DISTINCT oi.order_id) as orders'),
                ])
                ->groupBy('oi.product_id', 'p.name')
                ->get();

            foreach ($records as $record) {
                $key = (int) $record->product_id;
                $existing = $pairs->get($key, [
                    'product_id' => $key,
                    'name' => (string) $record->name,
                    'qty' => 0.0,
                    'orders' => 0,
                ]);
                $existing['qty'] += (float) $record->qty;
                $existing['orders'] += (int) $record->orders;
                $pairs->put($key, $existing);
            }
        }

        return $pairs
            ->map(function (array $item) use ($baseOrders): array {
                $item['qty'] = $this->roundMetric($item['qty']);
                // Confidence: trong các đơn có món gốc, bao nhiêu % cũng có món này.
                $item['confidence'] = $baseOrders > 0
                    ? round($item['orders'] / $baseOrders * 100, 1)
                    : 0.0;

                return $item;
            })
            ->sortByDesc('orders')
            ->take(8)
            ->values()
            ->all();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tiện ích
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Thêm cột tỷ trọng (%) theo số lượng cho các breakdown dạng nhóm.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function withShare(array $rows): array
    {
        $total = array_sum(array_map(static fn (array $row): float => (float) $row['qty'], $rows));

        return array_map(function (array $row) use ($total): array {
            $row['share_percent'] = $total > 0 ? round((float) $row['qty'] / $total * 100, 1) : 0.0;
            $row['qty'] = $this->roundMetric((float) $row['qty']);
            $row['revenue'] = round((float) $row['revenue'], 2);

            return $row;
        }, $rows);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function metricValue(array $item, string $metric): float
    {
        return match ($metric) {
            'revenue' => (float) $item['revenue'],
            'profit' => (float) $item['gross_profit'],
            default => (float) $item['qty'],
        };
    }

    public function normalizeMetric(?string $metric): string
    {
        return in_array($metric, self::METRICS, true) ? $metric : 'quantity';
    }

    private function metricLabel(string $metric): string
    {
        return match ($metric) {
            'revenue' => 'doanh thu',
            'profit' => 'lợi nhuận gộp',
            default => 'số lượng bán',
        };
    }

    /**
     * Phân nhóm theo luỹ kế TRƯỚC món này, để món vắt qua mốc 80% vẫn thuộc
     * nhóm A — đúng nghĩa "tập nhỏ nhất đủ tạo ra 80%".
     */
    private function abcClass(float $cumulativeBefore, float $metricValue): string
    {
        if ($metricValue <= 0) {
            return 'C';
        }

        return match (true) {
            $cumulativeBefore < self::ABC_A_THRESHOLD => 'A',
            $cumulativeBefore < self::ABC_B_THRESHOLD => 'B',
            default => 'C',
        };
    }

    /**
     * HHI trên thang 0–10 000 (tổng bình phương thị phần tính bằng %).
     *
     * @return array{level: string, label: string, hint: string}
     */
    private function concentrationLevel(float $hhi): array
    {
        return match (true) {
            $hhi >= 2500 => [
                'level' => 'high',
                'label' => 'Tập trung cao',
                'hint' => 'Doanh số phụ thuộc vào rất ít món. Hết nguyên liệu một món là thủng doanh thu — cần dự phòng nguồn cung và nuôi thêm món thay thế.',
            ],
            $hhi >= 1500 => [
                'level' => 'medium',
                'label' => 'Tập trung vừa',
                'hint' => 'Có vài món chủ lực rõ ràng. Mức lành mạnh, nhưng nên theo dõi sát tồn kho nguyên liệu của nhóm A.',
            ],
            default => [
                'level' => 'low',
                'label' => 'Phân tán',
                'hint' => 'Doanh số trải đều nhiều món. Ổn định nhưng bếp phải xoay nhiều — cân nhắc rút gọn đuôi dài nhóm C.',
            ],
        };
    }

    private function trendOf(float $previous, float $current): string
    {
        if ($current > $previous) {
            return 'up';
        }

        return $current < $previous ? 'down' : 'stable';
    }

    private function trendLabel(string $trend): string
    {
        return match ($trend) {
            'up' => 'Tăng',
            'down' => 'Giảm',
            default => 'Đi ngang',
        };
    }

    private function channelLabel(string $channel): string
    {
        return match ($channel) {
            'dine_in' => 'Tại quán',
            'takeaway' => 'Mang đi',
            'delivery' => 'Giao hàng',
            'online' => 'Đặt online',
            'qr' => 'QR tại bàn',
            'other' => 'Khác',
            default => $channel,
        };
    }

    private function hourExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', o.completed_at) AS INTEGER)"
            : 'HOUR(o.completed_at)';
    }

    private function weekdayExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%w', o.completed_at) AS INTEGER)"
            : 'DAYOFWEEK(o.completed_at) - 1';
    }

    private function percentageChange(float|int $previous, float|int $current): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function roundMetric(float $value): int|float
    {
        return fmod($value, 1.0) === 0.0 ? (int) $value : round($value, 2);
    }
}
