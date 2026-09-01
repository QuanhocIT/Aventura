<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Services\BestSellerAnalyticsService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Màn hình "Phân tích món bán chạy".
 *
 * Chi nhánh luôn lấy theo bộ chọn chi nhánh toàn cục (TenantContext) giống
 * Menu Engineering, nên trang này không nhận branch_id từ query để tránh hai
 * nguồn sự thật về phạm vi dữ liệu.
 */
class BestSellerController extends Controller
{
    /** Số ngày tối đa cho một kỳ phân tích. */
    private const MAX_RANGE_DAYS = 366;

    public function __construct(
        private BestSellerAnalyticsService $analytics,
        private TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        if ($gate = $this->featureGate($request)) {
            return $gate;
        }

        $filters = $this->resolveFilters($request);
        $restaurantId = (int) $request->user()->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();

        return Inertia::render('best-sellers/Index', [
            'analytics' => $this->analytics->analyze(
                $restaurantId,
                $filters['from'],
                $filters['to'],
                $branchId,
                $filters['options'],
            ),
            'filters' => [
                'from' => $filters['from']->toDateString(),
                'to' => $filters['to']->toDateString(),
                'preset' => $filters['preset'],
                'metric' => $filters['options']['metric'],
                'category_id' => $filters['options']['category_id'],
                'limit' => $filters['options']['limit'],
            ],
            'categories' => ProductCategory::where('restaurant_id', $restaurantId)
                ->when($branchId !== null, fn ($query) => $query->where(function ($scope) use ($branchId) {
                    $scope->whereNull('branch_id')->orWhere('branch_id', $branchId);
                }))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ProductCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->values()
                ->all(),
            'branchContext' => [
                'scope' => $this->tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
        ]);
    }

    /**
     * Nạp lại số liệu khi người dùng đổi bộ lọc, không reload cả trang.
     */
    public function analytics(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        if ($denied = $this->featureDenied($request)) {
            return $denied;
        }

        $filters = $this->resolveFilters($request);

        return response()->json($this->analytics->analyze(
            (int) $request->user()->restaurant_id,
            $filters['from'],
            $filters['to'],
            $this->tenantContext->activeBranchId(),
            $filters['options'],
        ));
    }

    /**
     * Drill-down một món cụ thể.
     */
    public function dish(Request $request, int $product): JsonResponse
    {
        $this->authorizeView($request);

        if ($denied = $this->featureDenied($request)) {
            return $denied;
        }

        $filters = $this->resolveFilters($request);

        $detail = $this->analytics->dishDetail(
            (int) $request->user()->restaurant_id,
            $product,
            $filters['from'],
            $filters['to'],
            $this->tenantContext->activeBranchId(),
        );

        abort_if($detail === null, 404, 'Không tìm thấy món ăn.');

        return response()->json($detail);
    }

    /**
     * Xuất bảng xếp hạng ra CSV (mở được bằng Excel, có BOM UTF-8).
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeView($request);
        abort_unless($this->hasAnalyticsFeature($request), 403, 'Tính năng phân tích nâng cao yêu cầu gói Chuyên Nghiệp trở lên.');

        $filters = $this->resolveFilters($request);
        $result = $this->analytics->analyze(
            (int) $request->user()->restaurant_id,
            $filters['from'],
            $filters['to'],
            $this->tenantContext->activeBranchId(),
            $filters['options'],
        );
        $rows = $this->analytics->exportRows($result['ranking']);

        $filename = sprintf(
            'mon_ban_chay_%s_%s.csv',
            $filters['from']->format('Ymd'),
            $filters['to']->format('Ymd'),
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 để Excel không vỡ tiếng Việt.
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, preset: string, options: array{metric: string, category_id: int|null, limit: int}}
     */
    private function resolveFilters(Request $request): array
    {
        $data = $request->validate([
            'preset' => ['nullable', 'string', 'in:7,30,90,365,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'metric' => ['nullable', 'string', 'in:'.implode(',', BestSellerAnalyticsService::METRICS)],
            'category_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:3', 'max:30'],
        ]);

        $preset = $data['preset'] ?? '30';
        $today = CarbonImmutable::today();

        if ($preset === 'custom' && isset($data['from'], $data['to'])) {
            $from = CarbonImmutable::parse($data['from'])->startOfDay();
            $to = CarbonImmutable::parse($data['to'])->endOfDay();
        } else {
            $preset = in_array($preset, ['7', '30', '90', '365'], true) ? $preset : '30';
            $to = $today->endOfDay();
            $from = $today->subDays((int) $preset - 1)->startOfDay();
        }

        if ($to->lessThan($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        // Chặn kỳ quá dài để không kéo sập truy vấn phân tích.
        if ($from->startOfDay()->diffInDays($to->startOfDay()) + 1 > self::MAX_RANGE_DAYS) {
            $from = $to->subDays(self::MAX_RANGE_DAYS - 1)->startOfDay();
        }

        $categoryId = isset($data['category_id'])
            ? (int) $data['category_id']
            : null;

        // Chỉ chấp nhận danh mục thuộc nhà hàng hiện tại.
        if ($categoryId !== null) {
            $exists = ProductCategory::where('restaurant_id', $request->user()->restaurant_id)
                ->whereKey($categoryId)
                ->when($this->tenantContext->activeBranchId() !== null, fn ($query) => $query->where(function ($scope) {
                    $scope->whereNull('branch_id')->orWhere('branch_id', $this->tenantContext->activeBranchId());
                }))
                ->exists();

            if (! $exists) {
                $categoryId = null;
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'options' => [
                'metric' => $this->analytics->normalizeMetric($data['metric'] ?? null),
                'category_id' => $categoryId,
                'limit' => (int) ($data['limit'] ?? 10),
            ],
        ];
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->canViewAnalytics(), 403, 'Bạn không có quyền xem báo cáo phân tích.');
    }

    private function hasAnalyticsFeature(Request $request): bool
    {
        $restaurant = $request->user()->restaurant;

        if (! $restaurant) {
            // Super admin không gắn nhà hàng vẫn xem được.
            return $request->user()->isSuperAdmin();
        }

        $restaurant->loadMissing('plan');

        return app(QuotaService::class)->hasFeature($restaurant, 'advanced_analytics');
    }

    private function featureGate(Request $request): ?Response
    {
        $restaurant = $request->user()->restaurant;

        if (! $restaurant) {
            abort_unless($request->user()->isSuperAdmin(), 403, 'Không tìm thấy nhà hàng.');

            return null;
        }

        if ($this->hasAnalyticsFeature($request)) {
            return null;
        }

        return Inertia::render('FeatureGate', [
            'feature' => 'advanced_analytics',
            'feature_label' => 'Phân tích món bán chạy',
            'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
            'required_plan' => 'Chuyên Nghiệp',
        ]);
    }

    private function featureDenied(Request $request): ?JsonResponse
    {
        if ($this->hasAnalyticsFeature($request)) {
            return null;
        }

        return response()->json([
            'error' => 'Tính năng phân tích nâng cao yêu cầu gói Chuyên Nghiệp trở lên.',
            'feature' => 'advanced_analytics',
        ], 403);
    }
}
