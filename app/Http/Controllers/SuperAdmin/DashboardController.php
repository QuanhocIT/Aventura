<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DashboardReportSubscription;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SystemAlert;
use App\Models\User;
use App\Services\AiInsightsClient;
use App\Services\SupportPortalService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    public function __construct(protected SupportPortalService $supportPortal) {}

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
            $mrr = $activeSubscriptions->sum(fn (RestaurantSubscription $subscription) => $this->monthlyRecurringRevenue($subscription));

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
                    ->filter(fn (RestaurantSubscription $subscription) => $this->monthlyRecurringRevenue($subscription) > 0)
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
                'active'            => Restaurant::where('status', 'active')->count(),
                'suspended'         => Restaurant::where('status', 'suspended')->count(),
                'expired'           => Restaurant::where('status', 'expired')->count(),
                'total_users'       => User::count(),
                'pro_plan'          => Restaurant::whereHas('plan', fn ($q) => $q->whereRaw('LOWER(code) = ?', ['pro']))->count(),
                'flagged_inactive'  => Restaurant::where('is_inactive_flagged', true)->count(),
            ];
        });

        $tenantGrowthSeries = Cache::remember(
            "superadmin_dashboard_tenant_growth_{$range['key']}",
            self::CACHE_TTL,
            fn () => $this->tenantGrowthSeries($now, $range['months'])
        );

        $tenantGrowthCompare = $compare ? Cache::remember(
            "superadmin_dashboard_tenant_growth_compare_{$range['key']}",
            self::CACHE_TTL,
            fn () => $this->tenantGrowthSeries($now, $range['months'], $range['months'])
        ) : null;

        $statChanges = $this->statChanges($now, $stats, $mrr);

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
                    'id'                           => $r->id,
                    'name'                         => $r->name,
                    'plan_code'                    => $r->plan?->code ?? 'free',
                    'status'                       => $r->status,
                    'is_trial'                     => $r->activeSubscription?->status === 'trial',
                    'days_since_created'           => (int) $r->created_at->diffInDays($now),
                    'days_until_subscription_ends' => $r->subscription_ends_at
                        ? (int) $now->diffInDays($r->subscription_ends_at, false)
                        : -1,
                    'order_count_30d'              => (int) ($orderCounts[$r->id] ?? 0),
                    'subscription_status'          => $r->activeSubscription?->status ?? 'none',
                ])->toArray();

            $aiInsights = app(AiInsightsClient::class)->getInsights($restaurantsData, $tenantGrowthSeries);
        }

        $recentRestaurants = Cache::remember('superadmin_dashboard_recent_restaurants', self::CACHE_TTL, fn () => Restaurant::with(['plan', 'owner'])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'name'       => $r->name,
                'status'     => $r->status,
                'plan'       => $r->plan?->name ?? '-',
                'plan_code'  => $r->plan?->code ?? 'free',
                'owner'      => $r->owner?->name ?? '-',
                'created_at' => $r->created_at->format('d/m/Y'),
            ]));

        $planDistribution = Cache::remember('superadmin_dashboard_plan_distribution', self::CACHE_TTL, fn () => SubscriptionPlan::withCount('restaurants')
            ->get()
            ->map(fn ($p) => [
                'name'  => $p->name,
                'code'  => $p->code,
                'count' => $p->restaurants_count,
            ]));

        $cohortAnalysis = Cache::remember(
            'superadmin_dashboard_cohort_analysis',
            self::CACHE_TTL,
            fn () => $this->cohortAnalysis($now)
        );

        $revenueBreakdown = Cache::remember(
            'superadmin_dashboard_revenue_breakdown',
            self::CACHE_TTL,
            fn () => $this->revenueBreakdownByPlan($now, 6)
        );

        $revenueRetention = Cache::remember(
            'superadmin_dashboard_revenue_retention',
            self::CACHE_TTL,
            fn () => $this->revenueRetention($now)
        );

        $planPerformance = Cache::remember(
            'superadmin_dashboard_plan_performance',
            self::CACHE_TTL,
            fn () => $this->planPerformance($now)
        );

        $dashboardAlerts = $this->dashboardAlerts($now, $stats, [
            'mrr' => round($mrr),
            'arr' => round($mrr * 12),
            'churn_rate' => round(($cancelledThisMonth / $activeBase) * 100, 2),
        ], $aiInsights);

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
            'aiInsights'  => $aiInsights,
            'resourceInsights' => [
                'top_order_restaurants' => Cache::remember(
                    "superadmin_dashboard_top_order_restaurants_{$range['key']}",
                    self::CACHE_TTL,
                    fn () => $this->topOrderRestaurants($rangeWindowStart)
                ),
                'top_storage_restaurants' => Cache::remember(
                    'superadmin_dashboard_top_storage_restaurants',
                    self::CACHE_TTL,
                    fn () => $this->topStorageRestaurants()
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
    public function exportReport(Request $request): \Illuminate\View\View
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
        $mrr = $activeSubscriptions->sum(fn (RestaurantSubscription $subscription) => $this->monthlyRecurringRevenue($subscription));

        $cancelledThisMonth = RestaurantSubscription::where('status', 'cancelled')
            ->whereBetween('cancelled_at', [$monthStart, $monthEnd])
            ->distinct('restaurant_id')
            ->count('restaurant_id');

        $activeBase = max(1, Restaurant::count());

        $stats = [
            'total_restaurants' => Restaurant::count(),
            'active'            => Restaurant::where('status', 'active')->count(),
            'suspended'         => Restaurant::where('status', 'suspended')->count(),
            'expired'           => Restaurant::where('status', 'expired')->count(),
            'total_users'       => User::count(),
            'pro_plan'          => Restaurant::whereHas('plan', fn ($q) => $q->whereRaw('LOWER(code) = ?', ['pro']))->count(),
            'flagged_inactive'  => Restaurant::where('is_inactive_flagged', true)->count(),
        ];

        $saasMetrics = [
            'mrr' => round($mrr),
            'arr' => round($mrr * 12),
            'churn_rate' => round(($cancelledThisMonth / $activeBase) * 100, 2),
            'churned_this_month' => $cancelledThisMonth,
            'active_subscriptions' => $activeSubscriptions->pluck('restaurant_id')->unique()->count(),
            'paid_tenants' => $activeSubscriptions
                ->filter(fn (RestaurantSubscription $subscription) => $this->monthlyRecurringRevenue($subscription) > 0)
                ->pluck('restaurant_id')
                ->unique()
                ->count(),
        ];

        return [
            'stats' => $stats,
            'saasMetrics' => $saasMetrics,
            'tenantGrowth' => $this->tenantGrowthSeries($now, 6),
            'planDistribution' => SubscriptionPlan::withCount('restaurants')->get()->map(fn ($plan) => [
                'name' => $plan->name,
                'code' => $plan->code,
                'count' => $plan->restaurants_count,
            ])->all(),
            'planPerformance' => $this->planPerformance($now),
            'cohortAnalysis' => $this->cohortAnalysis($now),
            'revenueRetention' => $this->revenueRetention($now),
            'topOrderRestaurants' => $this->topOrderRestaurants($windowStart),
            'topStorageRestaurants' => $this->topStorageRestaurants(),
            'alerts' => $this->dashboardAlerts($now, $stats, $saasMetrics, []),
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
    public function updateReportSubscription(Request $request): \Illuminate\Http\RedirectResponse
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
     * So sánh các chỉ số chính với 30 ngày trước, dựa trên dữ liệu thật (created_at / started_at)
     * thay vì các con số tăng trưởng cố định hiển thị trên giao diện trước đây.
     */
    protected function statChanges(CarbonInterface $now, array $stats, float $mrr): array
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
    protected function periodChangeLabel(float $current, float $previous): array
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

    protected function monthlyRecurringRevenue(RestaurantSubscription $subscription): float
    {
        $price = (float) ($subscription->price > 0 ? $subscription->price : ($subscription->plan?->price ?? 0));
        $cycle = strtolower((string) ($subscription->plan?->billing_cycle ?? 'monthly'));

        return match ($cycle) {
            'yearly' => $price / 12,
            'quarterly' => $price / 3,
            default => $price,
        };
    }

    protected function freeToProConversionsForMonth($subscriptions, CarbonInterface $monthStart, CarbonInterface $monthEnd): int
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

    protected function tenantGrowthSeries(CarbonInterface $now, int $months, int $offsetMonths = 0): array
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

    protected function topOrderRestaurants(CarbonInterface $windowStart): array
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

    protected function topStorageRestaurants(): array
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
    protected function cohortAnalysis(CarbonInterface $now): array
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
    protected function revenueBreakdownByPlan(CarbonInterface $now, int $months = 6): array
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
    protected function wasSubscriptionActiveDuring(RestaurantSubscription $subscription, CarbonInterface $monthStart, CarbonInterface $monthEnd): bool
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
    protected function revenueRetention(CarbonInterface $now): array
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
    protected function planPerformance(CarbonInterface $now): array
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
     * Banner cảnh báo đầu trang dashboard: kết hợp các ngưỡng SaaS tính trực tiếp từ
     * dữ liệu hiện tại (tỉ lệ rời bỏ, tỉ lệ nhà hàng nguy cơ rời bỏ) với các SystemAlert
     * đang mở (status = open) — tái dùng hạ tầng SystemAlertRule/SystemAlert đã có.
     */
    protected function dashboardAlerts(CarbonInterface $now, array $stats, array $saasMetrics, array $aiInsights): array
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

        $orderCounts = Order::query()
            ->selectRaw('restaurant_id, COUNT(*) as cnt')
            ->where('created_at', '>=', $windowStart)
            ->groupBy('restaurant_id')
            ->pluck('cnt', 'restaurant_id');

        $matches = function (Restaurant $r) use ($segment, $orderCounts, $now): bool {
            $plan = strtolower($r->plan?->code ?? 'free');
            $status = strtolower($r->status);
            $isTrial = $r->activeSubscription?->status === 'trial';
            $orders30d = (int) ($orderCounts[$r->id] ?? 0);

            if ($status !== 'active') {
                return false;
            }

            return match ($segment) {
                'active_pro' => $plan === 'pro',
                'trial_active' => $isTrial,
                'free_inactive' => $plan === 'free' && $orders30d < 5,
                'at_risk' => $orders30d === 0,
                default => false,
            };
        };

        $restaurants = Restaurant::with(['plan', 'owner', 'activeSubscription'])
            ->get()
            ->filter($matches)
            ->take(50)
            ->map(fn (Restaurant $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'status' => $r->status,
                'plan_code' => $r->plan?->code ?? 'free',
                'owner_name' => $r->owner?->name ?? 'N/A',
                'owner_email' => $r->owner?->email ?? 'N/A',
                'subscription_ends_at' => $r->subscription_ends_at
                    ? \Illuminate\Support\Carbon::parse($r->subscription_ends_at)->format('d/m/Y')
                    : 'N/A',
            ])
            ->values();

        return response()->json([
            'restaurants' => $restaurants,
        ]);
    }
}

