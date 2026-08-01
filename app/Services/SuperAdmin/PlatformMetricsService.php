<?php

namespace App\Services\SuperAdmin;

use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SystemAlert;
use App\Models\User;
use App\Services\AnalyticsServiceClient;
use App\Services\CircuitBreaker;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Tính toán các chỉ số SaaS toàn nền tảng (MRR/ARR, tăng trưởng tenant, cohort
 * retention, NRR/GRR, hiệu suất theo gói...) cho SuperAdmin\DashboardController —
 * tách khỏi controller (trước đây 1032 dòng) để controller chỉ còn lo phần
 * HTTP/cache/Inertia, theo đúng khuôn đã áp dụng cho DashboardController khách
 * hàng (xem app/Services/Dashboard/*).
 */
class PlatformMetricsService
{
    public function monthlyRecurringRevenue(RestaurantSubscription $subscription): float
    {
        $price = (float) ($subscription->price > 0 ? $subscription->price : ($subscription->plan?->price ?? 0));
        $cycle = strtolower((string) ($subscription->plan?->billing_cycle ?? 'monthly'));

        return match ($cycle) {
            'yearly' => $price / 12,
            'quarterly' => $price / 3,
            default => $price,
        };
    }

    public function freeToProConversionsForMonth($subscriptions, CarbonInterface $monthStart, CarbonInterface $monthEnd): int
    {
        return $subscriptions
            ->groupBy('restaurant_id')
            ->filter(function ($restaurantSubscriptions) use ($monthStart, $monthEnd) {
                $hasFreeBefore = $restaurantSubscriptions->contains(function (RestaurantSubscription $subscription) {
                    return strtolower((string) $subscription->plan?->code) === 'free';
                });

                if (! $hasFreeBefore) {
                    return false;
                }

                return $restaurantSubscriptions->contains(function (RestaurantSubscription $subscription) use ($monthStart, $monthEnd) {
                    return strtolower((string) $subscription->plan?->code) === 'pro'
                        && $subscription->started_at
                        && $subscription->started_at->between($monthStart, $monthEnd);
                });
            })
            ->count();
    }

    public function tenantGrowthSeries(CarbonInterface $now, int $months, int $offsetMonths = 0): array
    {
        $subscriptions = RestaurantSubscription::with(['plan'])
            ->whereNotNull('started_at')
            ->get();

        return collect(range($months - 1, 0))
            ->map(function (int $offset) use ($now, $subscriptions, $offsetMonths) {
                $month = $now->copy()->subMonths($offset + $offsetMonths)->startOfMonth();
                $start = $month->copy()->startOfMonth();
                $end = $month->copy()->endOfMonth();

                $newTenants = Restaurant::whereBetween('created_at', [$start, $end])->count();
                $freeToPro = $this->freeToProConversionsForMonth(
                    $subscriptions,
                    $start,
                    $end,
                );

                return [
                    'label' => $month->format('m/Y'),
                    'month' => $month->format('Y-m'),
                    'new_tenants' => $newTenants,
                    'free_to_pro' => $freeToPro,
                    'conversion_rate' => $newTenants > 0 ? round(($freeToPro / $newTenants) * 100, 2) : 0,
                ];
            })
            ->values()
            ->all();
    }

    public function topOrderRestaurants(CarbonInterface $windowStart): array
    {
        return Order::query()
            ->select('restaurant_id', DB::raw('COUNT(*) as orders_count'))
            ->where('created_at', '>=', $windowStart)
            ->groupBy('restaurant_id')
            ->orderByDesc('orders_count')
            ->with('restaurant:id,name,code')
            ->take(5)
            ->get()
            ->map(fn (Order $order) => [
                'restaurant_id' => $order->restaurant_id,
                'name' => $order->restaurant?->name ?? 'Tenant #'.$order->restaurant_id,
                'code' => $order->restaurant?->code ?? null,
                'orders_count' => (int) $order->orders_count,
            ])
            ->all();
    }

    public function topStorageRestaurants(): array
    {
        return MediaAsset::query()
            ->select('restaurant_id', DB::raw('SUM(size_bytes) as storage_bytes'), DB::raw('COUNT(*) as files_count'))
            ->whereNotNull('restaurant_id')
            ->groupBy('restaurant_id')
            ->orderByDesc('storage_bytes')
            ->with('restaurant:id,name,code')
            ->take(5)
            ->get()
            ->map(fn (MediaAsset $asset) => [
                'restaurant_id' => $asset->restaurant_id,
                'name' => $asset->restaurant?->name ?? 'Tenant #'.$asset->restaurant_id,
                'code' => $asset->restaurant?->code ?? null,
                'storage_bytes' => (int) $asset->storage_bytes,
                'files_count' => (int) $asset->files_count,
            ])
            ->all();
    }

    /**
     * Phân tích cohort: nhóm nhà hàng theo tháng đăng ký (created_at) trong 6 tháng gần
     * nhất, tính tỉ lệ còn hoạt động (status = active và có đơn hàng trong tháng mốc) ở
     * các mốc M+1, M+3, M+6 — chuẩn dùng để vẽ bảng nhiệt (heatmap) giữ chân tenant.
     */
    /**
     * Phân tích cohort — ưu tiên gọi analytics_service (pandas) qua CircuitBreaker,
     * tự động rơi về tính toán PHP (cohortAnalysisFallback) khi service gián đoạn
     * hoặc mạch đang OPEN. PHP chỉ lo truy vấn dữ liệu thô (không tính retention),
     * phần nhóm cohort + tính % giữ chân được offload sang Python.
     */
    public function cohortAnalysis(CarbonInterface $now): array
    {
        $months = 6;
        $windowStart = $now->copy()->subMonths($months - 1)->startOfMonth();

        $restaurants = Restaurant::query()
            ->whereBetween('created_at', [$windowStart, $now->copy()->endOfMonth()])
            ->get(['id', 'created_at', 'status']);

        if ($restaurants->isEmpty()) {
            return $this->cohortAnalysisFallback($now);
        }

        $orderActivity = Order::query()
            ->whereIn('restaurant_id', $restaurants->pluck('id'))
            ->whereBetween('created_at', [$windowStart, $now])
            ->selectRaw('restaurant_id, DATE(created_at) as order_date')
            ->distinct()
            ->get();

        $url = config('services.analytics.url').'/api/analytics/cohort-retention';

        return app(CircuitBreaker::class)->for('analytics_service')->attempt(
            function () use ($url, $restaurants, $orderActivity, $months, $now) {
                $response = Http::timeout(5)
                    ->withHeaders(app(AnalyticsServiceClient::class)->authHeaders())
                    ->post($url, [
                        'restaurants' => $restaurants->map(fn (Restaurant $r) => [
                            'restaurant_id' => $r->id,
                            'created_at' => $r->created_at->toDateString(),
                            'status' => $r->status,
                        ])->values()->all(),
                        'order_activity' => $orderActivity->map(fn ($o) => [
                            'restaurant_id' => $o->restaurant_id,
                            'order_date' => $o->order_date,
                        ])->values()->all(),
                        'months' => $months,
                        'now' => $now->toDateString(),
                    ]);

                if (! $response->successful()) {
                    throw new \RuntimeException("cohortAnalysis: analytics service trả lỗi HTTP {$response->status()}");
                }

                $cohorts = $response->json('cohorts');
                if (! is_array($cohorts)) {
                    throw new \RuntimeException('cohortAnalysis: phản hồi không hợp lệ từ analytics service');
                }

                return $cohorts;
            },
            fn () => $this->cohortAnalysisFallback($now)
        );
    }

    /**
     * Tính cohort retention hoàn toàn bằng PHP — dùng khi analytics_service
     * gián đoạn (mạch OPEN) hoặc trả lỗi. Kết quả PHẢI khớp định dạng hệt
     * endpoint Python (cùng field 'cohort'/'month'/'total'/'m1'/'m3'/'m6') để
     * phía dùng dữ liệu (dashboard, MV) không cần biết nguồn nào đã tính ra nó.
     */
    private function cohortAnalysisFallback(CarbonInterface $now): array
    {
        $cohortStarts = collect(range(5, 0))->map(fn (int $offset) => $now->copy()->subMonths($offset)->startOfMonth());

        return $cohortStarts->map(function (CarbonInterface $cohortStart) use ($now) {
            $cohortEnd = $cohortStart->copy()->endOfMonth();

            $cohortRestaurants = Restaurant::query()
                ->whereBetween('created_at', [$cohortStart, $cohortEnd])
                ->get(['id', 'status']);

            $total = $cohortRestaurants->count();
            $activeRestaurantIds = $cohortRestaurants->where('status', 'active')->pluck('id');

            $milestones = [];

            foreach ([1, 3, 6] as $monthsAfter) {
                $checkpoint = $cohortStart->copy()->addMonths($monthsAfter);

                if ($total === 0 || $checkpoint->greaterThan($now) || $activeRestaurantIds->isEmpty()) {
                    $milestones["m{$monthsAfter}"] = $checkpoint->greaterThan($now) ? null : 0.0;

                    continue;
                }

                $windowStart = $checkpoint->copy()->startOfMonth();
                $windowEnd = $checkpoint->copy()->endOfMonth();

                $orderedRestaurantIds = Order::query()
                    ->whereIn('restaurant_id', $activeRestaurantIds)
                    ->whereBetween('created_at', [$windowStart, $windowEnd])
                    ->distinct()
                    ->pluck('restaurant_id');

                $retainedCount = $activeRestaurantIds->intersect($orderedRestaurantIds)->count();
                $milestones["m{$monthsAfter}"] = round(($retainedCount / $total) * 100, 1);
            }

            return [
                'cohort' => $cohortStart->format('m/Y'),
                'month' => $cohortStart->format('Y-m'),
                'total' => $total,
                ...$milestones,
            ];
        })->values()->all();
    }

    /**
     * Doanh thu định kỳ (MRR) theo từng gói (plan_code) trong N tháng gần nhất — dữ liệu
     * cho biểu đồ "Revenue breakdown theo gói". Một subscription được tính là "đang hoạt
     * động" trong tháng X nếu started_at <= cuối tháng X và chưa kết thúc/huỷ trước đầu tháng X.
     */
    public function revenueBreakdownByPlan(CarbonInterface $now, int $months = 6): array
    {
        $planCodes = SubscriptionPlan::query()->pluck('code')->map(fn ($code) => strtolower($code))->unique()->values();

        $subscriptions = RestaurantSubscription::with(['plan'])
            ->whereNotNull('started_at')
            ->get();

        return collect(range($months - 1, 0))
            ->map(function (int $offset) use ($now, $subscriptions, $planCodes) {
                $monthStart = $now->copy()->subMonths($offset)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();

                $activeDuringMonth = $subscriptions->filter(
                    fn (RestaurantSubscription $s) => $this->wasSubscriptionActiveDuring($s, $monthStart, $monthEnd)
                );

                $mrrByPlan = $activeDuringMonth
                    ->groupBy(fn (RestaurantSubscription $s) => strtolower((string) ($s->plan?->code ?? 'free')))
                    ->map(fn ($group) => round($group->sum(fn (RestaurantSubscription $s) => $this->monthlyRecurringRevenue($s))));

                return [
                    'label' => $monthStart->format('m/Y'),
                    'month' => $monthStart->format('Y-m'),
                    'by_plan' => $planCodes->mapWithKeys(fn ($code) => [$code => (float) ($mrrByPlan[$code] ?? 0)])->all(),
                    'total' => (float) round($mrrByPlan->sum(), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Kiểm tra một subscription có "đang hoạt động" (tính phí) trong khoảng [$monthStart, $monthEnd]
     * hay không — dùng chung cho tính revenue breakdown theo tháng và NRR/GRR.
     */
    public function wasSubscriptionActiveDuring(RestaurantSubscription $subscription, CarbonInterface $monthStart, CarbonInterface $monthEnd): bool
    {
        if (! $subscription->started_at || $subscription->started_at->greaterThan($monthEnd)) {
            return false;
        }

        if ($subscription->cancelled_at && $subscription->cancelled_at->lessThan($monthStart)) {
            return false;
        }

        if ($subscription->ended_at && $subscription->ended_at->lessThan($monthStart)) {
            return false;
        }

        return true;
    }

    /**
     * Tính NRR (Net Revenue Retention) và GRR (Gross Revenue Retention) bằng cách so
     * sánh MRR theo từng nhà hàng giữa tháng trước và tháng hiện tại, phân loại thành
     * mở rộng (expansion) / co lại (contraction) / mất đi (churned).
     *
     * NRR = (MRR đầu kỳ + mở rộng - co lại - mất đi) / MRR đầu kỳ * 100
     * GRR = (MRR đầu kỳ - co lại - mất đi) / MRR đầu kỳ * 100
     */
    public function revenueRetention(CarbonInterface $now): array
    {
        $currentStart = $now->copy()->startOfMonth();
        $currentEnd = $now->copy()->endOfMonth();
        $previousStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $previousStart->copy()->endOfMonth();

        $subscriptions = RestaurantSubscription::with(['plan'])
            ->whereNotNull('started_at')
            ->get();

        $mrrByRestaurant = function (CarbonInterface $monthStart, CarbonInterface $monthEnd) use ($subscriptions) {
            return $subscriptions
                ->filter(fn (RestaurantSubscription $s) => $this->wasSubscriptionActiveDuring($s, $monthStart, $monthEnd))
                ->groupBy('restaurant_id')
                ->map(fn ($group) => $group->sum(fn (RestaurantSubscription $s) => $this->monthlyRecurringRevenue($s)));
        };

        $previous = $mrrByRestaurant($previousStart, $previousEnd);
        $current = $mrrByRestaurant($currentStart, $currentEnd);

        $startingMrr = (float) $previous->sum();
        $expansion = 0.0;
        $contraction = 0.0;
        $churned = 0.0;

        foreach ($previous as $restaurantId => $previousMrr) {
            $currentMrr = (float) ($current[$restaurantId] ?? 0);

            if ($currentMrr <= 0) {
                $churned += $previousMrr;
            } elseif ($currentMrr > $previousMrr) {
                $expansion += $currentMrr - $previousMrr;
            } elseif ($currentMrr < $previousMrr) {
                $contraction += $previousMrr - $currentMrr;
            }
        }

        return [
            'period_label' => $currentStart->format('m/Y'),
            'previous_label' => $previousStart->format('m/Y'),
            'starting_mrr' => round($startingMrr),
            'expansion' => round($expansion),
            'contraction' => round($contraction),
            'churned' => round($churned),
            'nrr' => $startingMrr > 0 ? round((($startingMrr + $expansion - $contraction - $churned) / $startingMrr) * 100, 1) : null,
            'grr' => $startingMrr > 0 ? round((($startingMrr - $contraction - $churned) / $startingMrr) * 100, 1) : null,
        ];
    }

    /**
     * So sánh hiệu suất sử dụng giữa các gói: số tenant, tổng đơn hàng 30 ngày qua,
     * trung bình đơn/ngày/tenant và tỉ lệ tenant còn hoạt động (có đơn hàng).
     */
    public function planPerformance(CarbonInterface $now): array
    {
        $windowStart = $now->copy()->subDays(30);

        $orderCounts = Order::query()
            ->selectRaw('restaurant_id, COUNT(*) as cnt')
            ->where('created_at', '>=', $windowStart)
            ->groupBy('restaurant_id')
            ->pluck('cnt', 'restaurant_id');

        return SubscriptionPlan::query()
            ->with('restaurants:id,plan_id')
            ->get()
            ->map(function (SubscriptionPlan $plan) use ($orderCounts) {
                $restaurants = $plan->restaurants;
                $tenantCount = $restaurants->count();
                $ordersPerTenant = $restaurants->map(fn (Restaurant $r) => (int) ($orderCounts[$r->id] ?? 0));
                $totalOrders = $ordersPerTenant->sum();
                $activeTenants = $ordersPerTenant->filter(fn (int $count) => $count > 0)->count();

                return [
                    'plan_code' => $plan->code,
                    'plan_name' => $plan->name,
                    'tenant_count' => $tenantCount,
                    'orders_30d' => $totalOrders,
                    'avg_orders_per_tenant_per_day' => $tenantCount > 0 ? round(($totalOrders / 30) / $tenantCount, 2) : 0.0,
                    'active_tenant_ratio' => $tenantCount > 0 ? round(($activeTenants / $tenantCount) * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * So sánh các chỉ số chính với 30 ngày trước, dựa trên dữ liệu thật (created_at / started_at)
     * thay vì các con số tăng trưởng cố định hiển thị trên giao diện trước đây.
     */
    public function statChanges(CarbonInterface $now, array $stats, float $mrr): array
    {
        $cutoff = $now->copy()->subDays(30);

        $restaurantsBefore = Restaurant::where('created_at', '<=', $cutoff)->count();
        $usersBefore = User::where('created_at', '<=', $cutoff)->count();

        $activeSubscriptions = RestaurantSubscription::with('plan')
            ->whereIn('status', ['trial', 'active'])
            ->get();

        $proNewLast30Days = $activeSubscriptions
            ->filter(fn (RestaurantSubscription $s) => strtolower((string) $s->plan?->code) === 'pro'
                && $s->started_at
                && $s->started_at->greaterThan($cutoff))
            ->count();
        $proBefore = max(0, $stats['pro_plan'] - $proNewLast30Days);

        $mrrFromNewSubs = $activeSubscriptions
            ->filter(fn (RestaurantSubscription $s) => $s->started_at && $s->started_at->greaterThan($cutoff))
            ->sum(fn (RestaurantSubscription $s) => $this->monthlyRecurringRevenue($s));
        $mrrBefore = max(0, $mrr - $mrrFromNewSubs);

        return [
            'total_restaurants' => $this->periodChangeLabel($stats['total_restaurants'], $restaurantsBefore),
            'total_users' => $this->periodChangeLabel($stats['total_users'], $usersBefore),
            'pro_plan' => $this->periodChangeLabel($stats['pro_plan'], $proBefore),
            'mrr' => $this->periodChangeLabel($mrr, $mrrBefore),
        ];
    }

    /**
     * Trả về % thay đổi so với 30 ngày trước, kèm nhãn hiển thị và chiều xu hướng.
     * Khi không có dữ liệu để so sánh (mốc trước đó bằng 0), trả về nhãn trung lập
     * thay vì một con số % gây hiểu lầm.
     */
    public function periodChangeLabel(float $current, float $previous): array
    {
        if ($previous <= 0) {
            return [
                'percent' => null,
                'label' => $current > 0 ? 'Phát sinh mới trong 30 ngày qua' : 'Chưa đủ dữ liệu so sánh',
                'trend' => $current > 0 ? 'up' : 'neutral',
            ];
        }

        $percent = round((($current - $previous) / $previous) * 100, 1);
        $sign = $percent > 0 ? '+' : '';

        return [
            'percent' => $percent,
            'label' => "{$sign}{$percent}% so với 30 ngày trước",
            'trend' => $percent > 0 ? 'up' : ($percent < 0 ? 'down' : 'neutral'),
        ];
    }

    /**
     * Banner cảnh báo đầu trang dashboard: kết hợp các ngưỡng SaaS tính trực tiếp từ
     * dữ liệu hiện tại (tỉ lệ rời bỏ, tỉ lệ nhà hàng nguy cơ rời bỏ) với các SystemAlert
     * đang mở (status = open) — tái dùng hạ tầng SystemAlertRule/SystemAlert đã có.
     */
    public function dashboardAlerts(CarbonInterface $now, array $stats, array $saasMetrics, array $aiInsights): array
    {
        $alerts = [];

        $flaggedCount = (int) ($stats['flagged_inactive'] ?? Restaurant::where('is_inactive_flagged', true)->count());
        if ($flaggedCount > 0) {
            $alerts[] = [
                'source' => 'derived',
                'severity' => 'warning',
                'metric_key' => 'flagged_inactive_count',
                'title' => 'Cửa hàng không hoạt động cần lưu ý',
                'message' => sprintf('Phát hiện %d cửa hàng không có hoạt động trong thời gian gần đây dù gói dịch vụ vẫn còn hạn. Vui lòng rà soát danh sách để có phương án hậu mãi kịp thời.', $flaggedCount),
                'triggered_at' => $now->format('d/m/Y H:i'),
            ];
        }

        $churnRate = (float) ($saasMetrics['churn_rate'] ?? 0);
        if ($churnRate > 5) {
            $alerts[] = [
                'source' => 'derived',
                'severity' => 'critical',
                'metric_key' => 'churn_rate',
                'title' => 'Tỷ lệ rời bỏ tăng cao',
                'message' => sprintf('Tỷ lệ huỷ gói tháng này đang ở mức %s%%, vượt ngưỡng cảnh báo 5%%.', $churnRate),
                'triggered_at' => $now->format('d/m/Y H:i'),
            ];
        }

        $totalRestaurants = max(1, (int) ($stats['total_restaurants'] ?? 1));
        $atRiskCount = (int) ($aiInsights['segments']['at_risk'] ?? 0);
        $atRiskRatio = round(($atRiskCount / $totalRestaurants) * 100, 1);

        if ($atRiskCount > 0 && $atRiskRatio > 10) {
            $alerts[] = [
                'source' => 'derived',
                'severity' => 'warning',
                'metric_key' => 'at_risk_ratio',
                'title' => 'Nhiều nhà hàng có nguy cơ rời bỏ',
                'message' => sprintf('%s%% nhà hàng (%d/%d) đang được AI đánh giá có nguy cơ rời bỏ cao — cần can thiệp chăm sóc sớm.', $atRiskRatio, $atRiskCount, $totalRestaurants),
                'triggered_at' => $now->format('d/m/Y H:i'),
            ];
        }

        $systemAlerts = SystemAlert::query()
            ->where('status', 'open')
            ->latest('triggered_at')
            ->take(5)
            ->get()
            ->map(fn (SystemAlert $alert) => [
                'source' => 'system',
                'severity' => ($alert->metric_value !== null && $alert->threshold !== null && $alert->threshold > 0 && $alert->metric_value >= $alert->threshold * 1.5)
                    ? 'critical'
                    : 'warning',
                'metric_key' => $alert->metric_key,
                'title' => $alert->title,
                'message' => $alert->message,
                'triggered_at' => $alert->triggered_at?->format('d/m/Y H:i'),
            ]);

        return collect($alerts)->concat($systemAlerts)->values()->all();
    }
}
