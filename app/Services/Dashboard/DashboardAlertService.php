<?php

namespace App\Services\Dashboard;

use App\Models\CashRegister;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantRevenueSummary;
use App\Models\ScheduleAssignment;
use App\Services\ForecastService;
use App\Services\OrderStatsCacheService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;

/**
 * Chuyển nguyên logic từ app/Http/Controllers/DashboardController.php (helper
 * private cũ getDashboardAlerts) — di chuyển thuần tuý, không đổi hành vi.
 * Lời gọi nội bộ $this->getHealthScore(...) trong bản gốc nay trỏ sang
 * DashboardHealthService (tách trong cùng đợt refactor).
 */
class DashboardAlertService
{
    public function __construct(
        private ForecastService $forecast,
        private OrderStatsCacheService $orderStatsCache,
        private QuotaService $quotaService,
        private DashboardHealthService $healthService,
    ) {}

    public function getDashboardAlerts(
        Restaurant $restaurant,
        ?int $branchId,
        bool $hasAiForecasting,
        bool $hasAdvancedAnalytics,
        bool $hasHrTimekeeping,
        bool $hasInventoryBasic
    ): array {
        $rid = $restaurant->id;
        $alerts = [];

        // Compute health score dynamically
        $healthScore = $this->healthService->getHealthScore($rid, $branchId);

        // ── Dự báo doanh thu ngày mai ────────────────────────────────────
        $forecastData = $hasAiForecasting ? $this->forecast->forecastTomorrow($rid, $branchId) : null;

        // ── Today Live Stats ─────────────────────────────────────────────
        $todayLiveStats = $this->orderStatsCache->getTodayStats($rid, $branchId);
        $totalToday = $todayLiveStats['total'];
        $completedToday = $todayLiveStats['completed'];
        $cancelledToday = $todayLiveStats['cancelled'];

        $todaySummary = RestaurantRevenueSummary::where('restaurant_id', $rid)
            ->where('summary_date', today())
            ->where('summary_type', 'daily')
            ->where('scope_key', TenantContext::summaryScopeKey($branchId))
            ->first();
        $revenueToday = (float) ($todaySummary?->net_revenue ?? 0);

        // ── AI Cảnh báo mới ──────────────────────────────────────────────
        // Alert 1: Pending > 30 phút
        $stuckPending = Order::where('restaurant_id', $rid)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();
        if ($stuckPending > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$stuckPending} đơn hàng đang chờ xử lý quá 30 phút",
                'href' => '/orders?status=pending',
            ];
        }

        // Alert 2: Tỉ lệ hủy cao
        if ($totalToday > 0 && ($cancelledToday / $totalToday) > 0.2) {
            $pct = round(($cancelledToday / $totalToday) * 100);
            $alerts[] = [
                'type' => 'danger',
                'message' => "Tỉ lệ huỷ đơn hôm nay cao: {$pct}% ({$cancelledToday}/{$totalToday} đơn)",
                'href' => '/orders?status=cancelled',
            ];
        }

        // Alert 3: Đơn đang chế biến lâu
        $stuckProcessing = Order::where('restaurant_id', $rid)
            ->whereIn('status', ['confirmed', 'preparing'])
            ->where('updated_at', '<', now()->subHour())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();
        if ($stuckProcessing > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$stuckProcessing} đơn đang chế biến chưa được cập nhật trạng thái",
                'href' => '/orders',
            ];
        }

        // Alert 4 (AI): Doanh thu hôm nay thấp hơn dự báo > 30%
        if ($hasAiForecasting && $this->quotaService->hasFeature($restaurant, 'ai_advisor') && $forecastData) {
            $forecast = $forecastData['amount'] ?? 0;
            if ($forecast > 0 && $revenueToday > 0 && now()->hour >= 14) {
                $pctOfForecast = $revenueToday / $forecast * 100;
                if ($pctOfForecast < 70) {
                    $gap = round(100 - $pctOfForecast);
                    $alerts[] = [
                        'type' => 'warning',
                        'ai' => true,
                        'message' => "⚡ Doanh thu hôm nay thấp hơn dự báo {$gap}% — hãy kích hoạt khuyến mãi flash",
                        'href' => '/promotions',
                    ];
                }
            }
        }

        // Alert 5 (AI): Nhân viên chưa check-in dù đã qua giờ ca
        if ($hasHrTimekeeping) {
            $missingCheckIns = ScheduleAssignment::where('restaurant_id', $rid)
                ->where('scheduled_date', today())
                ->where('status', 'scheduled')
                ->whereHas('shift', fn ($q) => $q->where('start_time', '<=', now()->format('H:i:s')))
                ->when($branchId, function ($q) use ($branchId) {
                    $q->whereHas('employee', fn ($emp) => $emp->where('branch_id', $branchId));
                })
                ->count();
            if ($missingCheckIns > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'ai' => true,
                    'message' => "⚡ {$missingCheckIns} nhân viên chưa check-in dù đã qua giờ bắt đầu ca",
                    'href' => '/schedules',
                ];
            }
        }

        // Alert 6 (Fraud): Đơn bị tách chưa đối soát
        if ($this->quotaService->hasFeature($restaurant, 'fraud_detection')) {
            $splitAlertsCount = Order::where('restaurant_id', $rid)
                ->where('is_split', true)
                ->where('is_red_flagged', true)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->count();
            if ($splitAlertsCount > 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => "⚠️ Phát hiện {$splitAlertsCount} đơn hàng bị tách chưa được đối soát (Có nguy cơ gian lận!)",
                    'href' => '/orders',
                ];
            }
        }

        // Alert 7 (Cash discrepancy > 2% of expected cash at close)
        if ($hasInventoryBasic || $hasAdvancedAnalytics) {
            $closedRegisters = CashRegister::where('restaurant_id', $rid)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where('status', 'closed')
                ->where('closed_at', '>=', now()->subDays(7))
                ->where('expected_closing_balance', '>', 0)
                ->get();

            $discrepancyRegistersCount = $closedRegisters->filter(function ($r) {
                return (float) $r->expected_closing_balance > 0
                    && (abs((float) $r->difference) / (float) $r->expected_closing_balance) > 0.02;
            })->count();
            if ($discrepancyRegistersCount > 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => "⚠️ Phát hiện {$discrepancyRegistersCount} ca chốt két tiền mặt chênh lệch vượt quá 2% so với hệ thống trong 7 ngày qua!",
                    'href' => '/cash-flow',
                ];
            }

            // Alert 8 (Active registers expense budget overrun)
            $overrunRegistersCount = CashRegister::where('restaurant_id', $rid)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where('status', 'open')
                ->where('expense_budget', '>', 0)
                ->withSum(['transactions as expense_total' => fn ($q) => $q->where('type', 'out')], 'amount')
                ->get()
                ->filter(fn ($r) => (float) ($r->expense_total ?? 0) > (float) $r->expense_budget)
                ->count();
            if ($overrunRegistersCount > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => '⚠️ Ca trực hiện tại đang có chi tiêu tiền mặt vượt quá ngân sách chi ngoài hệ thống!',
                    'href' => '/cash-flow',
                ];
            }
        }

        return $alerts;
    }
}
