<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DashboardReportSubscription;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AiInsightsClient;
use App\Services\SuperAdmin\PlatformMetricsService;
use App\Services\SupportPortalService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Thời gian sống cache cho các khối thống kê nặng của dashboard (giây).
     * Giảm tải DB khi nhiều super admin cùng mở dashboard trong cùng một khoảng ngắn.
     */
    private const CACHE_TTL = 300;

    /**
     * Các khoảng thời gian cho phép chọn ở bộ lọc tăng trưởng tenant (key => số tháng).
     */
    private const RANGE_OPTIONS = [
        '3m' => 3,
        '6m' => 6,
        '12m' => 12,
    ];

    /**
     * Các cache key cố định (không phụ thuộc bộ lọc) của dashboard cần xoá khi dữ
     * liệu nhà hàng/gói/subscription thay đổi.
     */
    private const CACHE_KEYS = [
        'superadmin_dashboard_recent_restaurants',
        'superadmin_dashboard_plan_distribution',
        'superadmin_dashboard_cohort_analysis',
        'superadmin_dashboard_revenue_breakdown',
        'superadmin_dashboard_revenue_retention',
        'superadmin_dashboard_plan_performance',
    ];

    public function __construct(
        protected SupportPortalService $supportPortal,
        protected PlatformMetricsService $metrics,
    ) {}

    /**
     * Xoá toàn bộ cache thống kê của dashboard — gọi mỗi khi dữ liệu nguồn thay đổi
     * (tạo/sửa nhà hàng, đổi gói, áp billing override...). Bao gồm cả các biến thể
     * theo bộ lọc khoảng thời gian (range) vì key được tham số hoá theo range.
     */
    public static function forgetCache(): void
    {
        foreach (self::CACHE_KEYS as $key) {
            Cache::forget($key);
        }

        foreach (array_keys(self::RANGE_OPTIONS) as $rangeKey) {
            Cache::forget("superadmin_dashboard_tenant_growth_{$rangeKey}");
            Cache::forget("superadmin_dashboard_tenant_growth_compare_{$rangeKey}");
            Cache::forget("superadmin_dashboard_top_order_restaurants_{$rangeKey}");
        }
    }

    /**
     * Phân giải tham số `range` từ query string thành key hợp lệ + số tháng tương ứng.
     * Mặc định về '6m' nếu giá trị không hợp lệ hoặc không truyền.
     */
    protected function resolveRange(?string $value): array
    {
        $key = $value !== null && array_key_exists($value, self::RANGE_OPTIONS) ? $value : '6m';

        return ['key' => $key, 'months' => self::RANGE_OPTIONS[$key]];
    }

    public function index(Request $request): Response
    {
        $range = $this->resolveRange($request->query('range'));
        $compare = $request->boolean('compare');

        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $windowStart = $now->copy()->subDays(30);
        $rangeWindowStart = $now->copy()->subMonths($range['months'])->startOfDay();

        // 1. Cache SaaS Metrics to prevent querying all subscriptions on every request
        $saasMetricsData = Cache::remember('superadmin_dashboard_saas_metrics', self::CACHE_TTL, function () use ($monthStart, $monthEnd) {
            $activeSubscriptions = RestaurantSubscription::with('plan')
                ->whereIn('status', ['trial', 'active'])
                ->get();
            $mrr = $activeSubscriptions->sum(fn (RestaurantSubscription $subscription) => $this->metrics->monthlyRecurringRevenue($subscription));

            $cancelledThisMonth = RestaurantSubscription::where('status', 'cancelled')
                ->whereBetween('cancelled_at', [$monthStart, $monthEnd])
                ->distinct('restaurant_id')
                ->count('restaurant_id');

            $activeBase = max(1, Restaurant::count());

            return [
                'mrr' => $mrr,
                'cancelled_this_month' => $cancelledThisMonth,
                'active_base' => $activeBase,
                'active_subscriptions_count' => $activeSubscriptions->pluck('restaurant_id')->unique()->count(),
                'paid_tenants_count' => $activeSubscriptions
                    ->filter(fn (RestaurantSubscription $subscription) => $this->metrics->monthlyRecurringRevenue($subscription) > 0)
                    ->pluck('restaurant_id')
                    ->unique()
                    ->count(),
            ];
        });

        $mrr = $saasMetricsData['mrr'];
        $cancelledThisMonth = $saasMetricsData['cancelled_this_month'];
        $activeBase = $saasMetricsData['active_base'];

        // 2. Cache general stats
        $stats = Cache::remember('superadmin_dashboard_general_stats', self::CACHE_TTL, function () {
            return [
                'total_restaurants' => Restaurant::count(),
                'active' => Restaurant::where('status', 'active')->count(),
                'suspended' => Restaurant::where('status', 'suspended')->count(),
                'expired' => Restaurant::where('status', 'expired')->count(),
                'total_users' => User::count(),
                'pro_plan' => Restaurant::whereHas('plan', fn ($q) => $q->whereRaw('LOWER(code) = ?', ['pro']))->count(),
                'flagged_inactive' => Restaurant::where('is_inactive_flagged', true)->count(),
            ];
        });

        $tenantGrowthSeries = Cache::remember(
            "superadmin_dashboard_tenant_growth_{$range['key']}",
            self::CACHE_TTL,
            fn () => $this->metrics->tenantGrowthSeries($now, $range['months'])
        );

        $tenantGrowthCompare = $compare ? Cache::remember(
            "superadmin_dashboard_tenant_growth_compare_{$range['key']}",
            self::CACHE_TTL,
            fn () => $this->metrics->tenantGrowthSeries($now, $range['months'], $range['months'])
        ) : null;

        $statChanges = $this->metrics->statChanges($now, $stats, $mrr);

        // 3. Lazy AI Insights: Skip DB query and mapping if cache exists
        if (Cache::has('superadmin_ai_insights')) {
            $aiInsights = Cache::get('superadmin_ai_insights');
        } else {
            $orderCounts = Order::query()
                ->selectRaw('restaurant_id, COUNT(*) as cnt')
                ->where('created_at', '>=', $windowStart)
                ->groupBy('restaurant_id')
                ->pluck('cnt', 'restaurant_id');

            $restaurantsData = Restaurant::with(['plan', 'activeSubscription'])
                ->get()
                ->map(fn (Restaurant $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'plan_code' => $r->plan?->code ?? 'free',
                    'status' => $r->status,
                    'is_trial' => $r->activeSubscription?->status === 'trial',
                    'days_since_created' => (int) $r->created_at->diffInDays($now),
                    'days_until_subscription_ends' => $r->subscription_ends_at
                        ? (int) $now->diffInDays($r->subscription_ends_at, false)
                        : -1,
                    'order_count_30d' => (int) ($orderCounts[$r->id] ?? 0),
                    'subscription_status' => $r->activeSubscription?->status ?? 'none',
                ])->toArray();

            $aiInsights = app(AiInsightsClient::class)->getInsights($restaurantsData, $tenantGrowthSeries);
        }

        $recentRestaurants = Cache::remember('superadmin_dashboard_recent_restaurants', self::CACHE_TTL, fn () => Restaurant::with(['plan', 'owner'])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'status' => $r->status,
                'plan' => $r->plan?->name ?? '-',
                'plan_code' => $r->plan?->code ?? 'free',
                'owner' => $r->owner?->name ?? '-',
                'created_at' => $r->created_at->format('d/m/Y'),
            ])
            ->all());

        $planDistribution = Cache::remember('superadmin_dashboard_plan_distribution', self::CACHE_TTL, fn () => SubscriptionPlan::withCount('restaurants')
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'code' => $p->code,
                'count' => $p->restaurants_count,
            ])
            ->all());

        $cohortAnalysis = Cache::remember(
            'superadmin_dashboard_cohort_analysis',
            self::CACHE_TTL,
            fn () => $this->metrics->cohortAnalysis($now)
        );

        $revenueBreakdown = Cache::remember(
            'superadmin_dashboard_revenue_breakdown',
            self::CACHE_TTL,
            fn () => $this->metrics->revenueBreakdownByPlan($now, 6)
        );

        $revenueRetention = Cache::remember(
            'superadmin_dashboard_revenue_retention',
            self::CACHE_TTL,
            fn () => $this->metrics->revenueRetention($now)
        );

        $planPerformance = Cache::remember(
            'superadmin_dashboard_plan_performance',
            self::CACHE_TTL,
            fn () => $this->metrics->planPerformance($now)
        );

        $dashboardAlerts = $this->metrics->dashboardAlerts($now, $stats, [
            'mrr' => round($mrr),
            'arr' => round($mrr * 12),
            'churn_rate' => round(($cancelledThisMonth / $activeBase) * 100, 2),
        ], $aiInsights);

        $churnRiskAlerts = Restaurant::where('churn_risk_level', 'high')
            ->whereIn('status', ['active', 'suspended'])
            ->with(['plan', 'owner'])
            ->orderBy('health_score', 'asc')
            ->take(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'health_score' => $r->health_score,
                'churn_risk_reason' => $r->churn_risk_reason,
                'owner_name' => $r->owner?->name ?? '-',
                'owner_email' => $r->owner?->email ?? $r->email ?? '-',
                'owner_phone' => $r->owner?->phone ?? $r->phone ?? '-',
            ]);

        return Inertia::render('super-admin/Dashboard', [
            'stats' => $stats,
            'saasMetrics' => [
                'mrr' => round($mrr),
                'arr' => round($mrr * 12),
                'churn_rate' => round(($cancelledThisMonth / $activeBase) * 100, 2),
                'churned_this_month' => $cancelledThisMonth,
                'active_subscriptions' => $saasMetricsData['active_subscriptions_count'],
                'paid_tenants' => $saasMetricsData['paid_tenants_count'],
            ],
            'tenantGrowth' => $tenantGrowthSeries,
            'tenantGrowthCompare' => $tenantGrowthCompare,
            'filters' => [
                'range' => $range['key'],
                'compare' => $compare,
            ],
            'statChanges' => $statChanges,
            'aiInsights' => $aiInsights,
            'resourceInsights' => [
                'top_order_restaurants' => Cache::remember(
                    "superadmin_dashboard_top_order_restaurants_{$range['key']}",
                    self::CACHE_TTL,
                    fn () => $this->metrics->topOrderRestaurants($rangeWindowStart)
                ),
                'top_storage_restaurants' => Cache::remember(
                    'superadmin_dashboard_top_storage_restaurants',
                    self::CACHE_TTL,
                    fn () => $this->metrics->topStorageRestaurants()
                ),
                'totals' => [
                    'orders_last_30_days' => Order::where('created_at', '>=', $windowStart)->count(),
                    'storage_bytes' => (int) MediaAsset::sum('size_bytes'),
                ],
            ],
            'recentRestaurants' => $recentRestaurants,
            'planDistribution' => $planDistribution,
            'cohortAnalysis' => $cohortAnalysis,
            'revenueBreakdown' => $revenueBreakdown,
            'revenueRetention' => $revenueRetention,
            'planPerformance' => $planPerformance,
            'dashboardAlerts' => $dashboardAlerts,
            'reportSubscription' => $this->currentReportSubscription($request),
            'supportOverview' => [
                'monitoring' => $this->supportPortal->monitoringSnapshot(),
                'stats' => $this->supportPortal->dashboardMetrics(),
            ],
            'churnRiskAlerts' => $churnRiskAlerts,
        ]);
    }

    /**
     * Xuất snapshot tổng quan dashboard ra CSV (mở được bằng Excel/Google Sheets) — tái
     * dùng đúng cơ chế dựng CSV thủ công như BillingController::exportCsv (không cần
     * thêm thư viện ngoài).
     */
    public function exportCsv(Request $request): HttpResponse
    {
        $now = now();
        $data = $this->buildReportSnapshot($now);

        $lines = [];
        $row = fn (array $values) => $this->csvRow($values);

        $lines[] = $row(['BÁO CÁO TỔNG QUAN HỆ THỐNG AVENTURA']);
        $lines[] = $row(['Xuất lúc', $now->format('d/m/Y H:i')]);
        $lines[] = '';

        $lines[] = $row(['CHỈ SỐ TỔNG QUAN']);
        $lines[] = $row(['Chỉ số', 'Giá trị']);
        $lines[] = $row(['Tổng số nhà hàng', $data['stats']['total_restaurants']]);
        $lines[] = $row(['Đang hoạt động', $data['stats']['active']]);
        $lines[] = $row(['Tạm ngưng', $data['stats']['suspended']]);
        $lines[] = $row(['Hết hạn', $data['stats']['expired']]);
        $lines[] = $row(['Tổng số người dùng', $data['stats']['total_users']]);
        $lines[] = $row(['Số nhà hàng gói Pro', $data['stats']['pro_plan']]);
        $lines[] = '';

        $lines[] = $row(['CHỈ SỐ SAAS']);
        $lines[] = $row(['MRR (VNĐ)', $data['saasMetrics']['mrr']]);
        $lines[] = $row(['ARR (VNĐ)', $data['saasMetrics']['arr']]);
        $lines[] = $row(['Tỷ lệ rời bỏ (%)', $data['saasMetrics']['churn_rate']]);
        $lines[] = $row(['Số gói huỷ trong tháng', $data['saasMetrics']['churned_this_month']]);
        $lines[] = $row(['Số thuê bao đang hoạt động', $data['saasMetrics']['active_subscriptions']]);
        $lines[] = $row(['Số tenant trả phí', $data['saasMetrics']['paid_tenants']]);
        $lines[] = '';

        $retention = $data['revenueRetention'];
        $lines[] = $row(['GIỮ CHÂN DOANH THU '.$retention['period_label'].' so với '.$retention['previous_label']]);
        $lines[] = $row(['MRR đầu kỳ', 'Mở rộng', 'Co lại', 'Mất đi', 'NRR (%)', 'GRR (%)']);
        $lines[] = $row([
            $retention['starting_mrr'],
            $retention['expansion'],
            $retention['contraction'],
            $retention['churned'],
            $retention['nrr'] ?? 'N/A',
            $retention['grr'] ?? 'N/A',
        ]);
        $lines[] = '';

        $lines[] = $row(['TĂNG TRƯỞNG TENANT THEO THÁNG (6 THÁNG GẦN NHẤT)']);
        $lines[] = $row(['Tháng', 'Tenant mới', 'Chuyển Free→Pro', 'Tỷ lệ chuyển đổi (%)']);
        foreach ($data['tenantGrowth'] as $month) {
            $lines[] = $row([$month['label'], $month['new_tenants'], $month['free_to_pro'], $month['conversion_rate']]);
        }
        $lines[] = '';

        $lines[] = $row(['PHÂN BỔ THEO GÓI']);
        $lines[] = $row(['Gói', 'Mã', 'Số lượng nhà hàng']);
        foreach ($data['planDistribution'] as $plan) {
            $lines[] = $row([$plan['name'], $plan['code'], $plan['count']]);
        }
        $lines[] = '';

        $lines[] = $row(['HIỆU SUẤT THEO GÓI (30 NGÀY QUA)']);
        $lines[] = $row(['Gói', 'Số tenant', 'Tổng đơn hàng', 'TB đơn/tenant/ngày', 'Tỷ lệ tenant hoạt động (%)']);
        foreach ($data['planPerformance'] as $plan) {
            $lines[] = $row([$plan['plan_name'], $plan['tenant_count'], $plan['orders_30d'], $plan['avg_orders_per_tenant_per_day'], $plan['active_tenant_ratio']]);
        }
        $lines[] = '';

        $lines[] = $row(['PHÂN TÍCH COHORT GIỮ CHÂN TENANT']);
        $lines[] = $row(['Cohort', 'Tổng số tenant', 'Giữ chân M+1 (%)', 'Giữ chân M+3 (%)', 'Giữ chân M+6 (%)']);
        foreach ($data['cohortAnalysis'] as $cohort) {
            $lines[] = $row([
                $cohort['cohort'],
                $cohort['total'],
                $cohort['m1'] ?? 'N/A',
                $cohort['m3'] ?? 'N/A',
                $cohort['m6'] ?? 'N/A',
            ]);
        }
        $lines[] = '';

        $lines[] = $row(['TOP 5 NHÀ HÀNG THEO SỐ ĐƠN HÀNG (30 NGÀY)']);
        $lines[] = $row(['Nhà hàng', 'Mã', 'Số đơn hàng']);
        foreach ($data['topOrderRestaurants'] as $restaurant) {
            $lines[] = $row([$restaurant['name'], $restaurant['code'] ?? '-', $restaurant['orders_count']]);
        }
        $lines[] = '';

        $lines[] = $row(['TOP 5 NHÀ HÀNG THEO DUNG LƯỢNG LƯU TRỮ']);
        $lines[] = $row(['Nhà hàng', 'Mã', 'Dung lượng (byte)', 'Số tệp']);
        foreach ($data['topStorageRestaurants'] as $restaurant) {
            $lines[] = $row([$restaurant['name'], $restaurant['code'] ?? '-', $restaurant['storage_bytes'], $restaurant['files_count']]);
        }

        if ($data['alerts']) {
            $lines[] = '';
            $lines[] = $row(['CẢNH BÁO ĐANG MỞ']);
            $lines[] = $row(['Mức độ', 'Tiêu đề', 'Nội dung', 'Thời điểm']);
            foreach ($data['alerts'] as $alert) {
                $lines[] = $row([$alert['severity'], $alert['title'], $alert['message'], $alert['triggered_at']]);
            }
        }

        $csv = "\xEF\xBB\xBF".implode(PHP_EOL, $lines);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=baocao-tongquan-'.$now->format('Ymd-His').'.csv',
        ]);
    }

    /**
     * Trang báo cáo dạng in được (HTML + CSS in ấn) — người dùng dùng chức năng "In / Lưu
     * thành PDF" của trình duyệt để xuất PDF, không cần thêm thư viện PDF cho dự án.
     */
    public function exportReport(Request $request): View
    {
        $now = now();

        return view('super-admin.reports.dashboard', [
            'now' => $now,
            'data' => $this->buildReportSnapshot($now),
        ]);
    }

    /**
     * Gom dữ liệu snapshot dùng chung cho xuất CSV, báo cáo HTML/in, và email báo cáo
     * định kỳ (xem SendDashboardReportEmail) — tránh lặp lại logic truy vấn đã có
     * trong index() và các hàm protected bên dưới.
     */
    public function buildReportSnapshot(CarbonInterface $now): array
    {
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $windowStart = $now->copy()->subDays(30);

        $activeSubscriptions = RestaurantSubscription::with('plan')
            ->whereIn('status', ['trial', 'active'])
            ->get();
        $mrr = $activeSubscriptions->sum(fn (RestaurantSubscription $subscription) => $this->metrics->monthlyRecurringRevenue($subscription));

        $cancelledThisMonth = RestaurantSubscription::where('status', 'cancelled')
            ->whereBetween('cancelled_at', [$monthStart, $monthEnd])
            ->distinct('restaurant_id')
            ->count('restaurant_id');

        $activeBase = max(1, Restaurant::count());

        $stats = [
            'total_restaurants' => Restaurant::count(),
            'active' => Restaurant::where('status', 'active')->count(),
            'suspended' => Restaurant::where('status', 'suspended')->count(),
            'expired' => Restaurant::where('status', 'expired')->count(),
            'total_users' => User::count(),
            'pro_plan' => Restaurant::whereHas('plan', fn ($q) => $q->whereRaw('LOWER(code) = ?', ['pro']))->count(),
            'flagged_inactive' => Restaurant::where('is_inactive_flagged', true)->count(),
        ];

        $saasMetrics = [
            'mrr' => round($mrr),
            'arr' => round($mrr * 12),
            'churn_rate' => round(($cancelledThisMonth / $activeBase) * 100, 2),
            'churned_this_month' => $cancelledThisMonth,
            'active_subscriptions' => $activeSubscriptions->pluck('restaurant_id')->unique()->count(),
            'paid_tenants' => $activeSubscriptions
                ->filter(fn (RestaurantSubscription $subscription) => $this->metrics->monthlyRecurringRevenue($subscription) > 0)
                ->pluck('restaurant_id')
                ->unique()
                ->count(),
        ];

        return [
            'stats' => $stats,
            'saasMetrics' => $saasMetrics,
            'tenantGrowth' => $this->metrics->tenantGrowthSeries($now, 6),
            'planDistribution' => SubscriptionPlan::withCount('restaurants')->get()->map(fn ($plan) => [
                'name' => $plan->name,
                'code' => $plan->code,
                'count' => $plan->restaurants_count,
            ])->all(),
            'planPerformance' => $this->metrics->planPerformance($now),
            'cohortAnalysis' => $this->metrics->cohortAnalysis($now),
            'revenueRetention' => $this->metrics->revenueRetention($now),
            'topOrderRestaurants' => $this->metrics->topOrderRestaurants($windowStart),
            'topStorageRestaurants' => $this->metrics->topStorageRestaurants(),
            'alerts' => $this->metrics->dashboardAlerts($now, $stats, $saasMetrics, []),
        ];
    }

    /**
     * Dựng một dòng CSV có trích dẫn đúng chuẩn (escape dấu ngoặc kép) — tái dùng cùng
     * cách dựng chuỗi như BillingController::exportCsv để đảm bảo mở được bằng Excel.
     */
    private function csvRow(array $values): string
    {
        return implode(',', array_map(
            static fn ($value) => '"'.str_replace('"', '""', (string) $value).'"',
            $values
        ));
    }

    /**
     * Trạng thái đăng ký nhận báo cáo định kỳ qua email của super admin hiện tại —
     * dùng cho khối "Báo cáo định kỳ" trên dashboard (bật/tắt + chọn tần suất).
     */
    private function currentReportSubscription(Request $request): array
    {
        $subscription = DashboardReportSubscription::where('user_id', $request->user()->id)->first();

        return [
            'is_active' => (bool) ($subscription?->is_active ?? false),
            'frequency' => $subscription?->frequency ?? 'weekly',
            'last_sent_at' => $subscription?->last_sent_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * Bật/tắt và cấu hình tần suất nhận báo cáo định kỳ qua email cho super admin
     * hiện tại — lưu vào bảng dashboard_report_subscriptions (xem
     * SendScheduledDashboardReports / SendDashboardReportEmail).
     */
    public function updateReportSubscription(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'frequency' => ['required', 'in:weekly,monthly'],
        ]);

        DashboardReportSubscription::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'is_active' => $validated['is_active'],
                'frequency' => $validated['frequency'],
            ],
        );

        return back()->with('success', $validated['is_active']
            ? 'Đã bật nhận báo cáo định kỳ qua email.'
            : 'Đã tắt nhận báo cáo định kỳ qua email.');
    }

    /**
     * Trả về danh sách nhà hàng thuộc một phân khúc AI Insights (Pro đang hoạt động /
     * Trial / Free ít hoạt động / Nguy cơ rời bỏ) — dùng cho hộp thoại drill-down khi
     * bấm vào segment card trên dashboard. Tiêu chí phân loại đồng bộ với
     * AiInsightsClient::generateMockInsights để hai khối không "nói khác nhau".
     */
    public function segmentRestaurants(string $segment): JsonResponse
    {
        $now = now();
        $windowStart = $now->copy()->subDays(30);

        $query = Restaurant::query()
            ->where('status', 'active')
            ->with(['plan', 'owner', 'activeSubscription']);

        if ($segment === 'active_pro') {
            $query->whereHas('plan', fn ($q) => $q->whereRaw('LOWER(code) = ?', ['pro']));
        } elseif ($segment === 'trial_active') {
            $query->whereHas('activeSubscription', fn ($q) => $q->where('status', 'trial'));
        } elseif ($segment === 'free_inactive') {
            $query->where(fn ($q) => $q->whereHas('plan', fn ($p) => $p->whereRaw('LOWER(code) = ?', ['free']))->orWhereNull('plan_id'))
                ->whereHas('orders', fn ($q) => $q->where('created_at', '>=', $windowStart), '<', 5);
        } elseif ($segment === 'at_risk') {
            $query->whereDoesntHave('orders', fn ($q) => $q->where('created_at', '>=', $windowStart));
        } else {
            return response()->json(['restaurants' => []]);
        }

        $restaurants = $query->take(50)
            ->get()
            ->map(fn (Restaurant $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'status' => $r->status,
                'plan_code' => $r->plan?->code ?? 'free',
                'owner_name' => $r->owner?->name ?? 'N/A',
                'owner_email' => $r->owner?->email ?? 'N/A',
                'subscription_ends_at' => $r->subscription_ends_at
                    ? Carbon::parse($r->subscription_ends_at)->format('d/m/Y')
                    : 'N/A',
            ])
            ->values();

        return response()->json([
            'restaurants' => $restaurants,
        ]);
    }
}
