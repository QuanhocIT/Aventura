<?php

namespace App\Services\SuperAdmin;

use App\Models\BillingAdjustment;
use App\Models\BillingInvoice;
use App\Models\CommissionLog;
use App\Models\PaymentWebhook;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tính toán cho các trang billing/tài chính nền tảng của SuperAdmin (KPI MRR/ARR,
 * invoice aging, nợ xấu, sổ cái tổng hợp, ghi nhận doanh thu trả trước, dunning,
 * vòng đời subscription) — tách khỏi BillingController để controller chỉ còn lo
 * HTTP/Inertia, theo đúng khuôn đã áp dụng cho PlatformMetricsService.
 */
class BillingAnalyticsService
{
    /**
     * Dữ liệu cho trang Analytics: KPI MRR/ARR/churn, doanh thu theo tháng/gói,
     * danh sách sắp hết hạn, invoice aging + bad debt.
     */
    public function analytics(Carbon $now): array
    {
        $mrr = RestaurantSubscription::query()
            ->where('status', 'active')
            ->whereHas('plan', fn ($q) => $q->where('price', '>', 0))
            ->get(['price', 'billing_cycle'])
            ->sum(function (RestaurantSubscription $subscription): float {
                return (float) $subscription->price / match (strtolower((string) $subscription->billing_cycle)) {
                    'quarterly' => 3,
                    'half_yearly' => 6,
                    'yearly' => 12,
                    'biennial' => 24,
                    default => 1,
                };
            });
        $arr = $mrr * 12;

        $activeCount = RestaurantSubscription::query()
            ->where('status', 'active')
            ->whereHas('plan', fn ($q) => $q->where('price', '>', 0))
            ->distinct('restaurant_id')
            ->count('restaurant_id');

        $churnedCount = Restaurant::query()
            ->where('status', 'expired')
            ->whereDate('subscription_ends_at', '>=', $now->copy()->subDays(30)->toDateString())
            ->whereDate('subscription_ends_at', '<', $now->toDateString())
            ->count();

        $churnRate = $activeCount > 0 ? round(($churnedCount / max($activeCount, 1)) * 100, 1) : 0;

        // MySQL: YEAR()/MONTH(); SQLite (test suite) không có 2 hàm này — dùng strftime().
        $driver = DB::connection()->getDriverName();
        $yearExpr = $driver === 'sqlite' ? "CAST(strftime('%Y', created_at) AS INTEGER)" : 'YEAR(created_at)';
        $monthExpr = $driver === 'sqlite' ? "CAST(strftime('%m', created_at) AS INTEGER)" : 'MONTH(created_at)';

        $monthlyRevenue = BillingInvoice::query()
            ->where('type', 'payment_success')
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $now->copy()->subMonths(12)->startOfMonth())
            ->select(
                DB::raw("{$yearExpr} as year"),
                DB::raw("{$monthExpr} as month"),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as count')
            )
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->orderByRaw("{$yearExpr}, {$monthExpr}")
            ->get()
            ->map(fn ($row) => [
                'label' => Carbon::createFromDate($row->year, $row->month, 1)->format('M Y'),
                'revenue' => (float) $row->revenue,
                'count' => (int) $row->count,
            ])
            ->values()
            ->toArray();

        $expiring7 = Restaurant::whereDate('subscription_ends_at', '>=', $now->toDateString())
            ->whereDate('subscription_ends_at', '<=', $now->copy()->addDays(7)->toDateString())
            ->where('status', 'active')
            ->count();
        $expiring14 = Restaurant::whereDate('subscription_ends_at', '>', $now->copy()->addDays(7)->toDateString())
            ->whereDate('subscription_ends_at', '<=', $now->copy()->addDays(14)->toDateString())
            ->where('status', 'active')
            ->count();
        $expiring30 = Restaurant::whereDate('subscription_ends_at', '>', $now->copy()->addDays(14)->toDateString())
            ->whereDate('subscription_ends_at', '<=', $now->copy()->addDays(30)->toDateString())
            ->where('status', 'active')
            ->count();

        $revenueByPlan = DB::table('billing_invoices')
            ->join('restaurant_subscriptions', 'billing_invoices.restaurant_subscription_id', '=', 'restaurant_subscriptions.id')
            ->join('subscription_plans', 'restaurant_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('billing_invoices.type', 'payment_success')
            ->where('billing_invoices.payment_status', 'paid')
            ->select(
                'subscription_plans.name as plan',
                DB::raw('SUM(billing_invoices.total) as revenue'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('subscription_plans.id', 'subscription_plans.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'plan' => $row->plan,
                'revenue' => (float) $row->revenue,
                'count' => (int) $row->count,
            ])
            ->toArray();

        $totalWebhooks = PaymentWebhook::count();
        $processedWebhooks = PaymentWebhook::where('status', 'processed')->count();
        $webhookSuccessRate = $totalWebhooks > 0 ? round(($processedWebhooks / $totalWebhooks) * 100, 1) : 100;

        $expiringList = Restaurant::query()
            ->where('status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->whereDate('subscription_ends_at', '>=', $now->toDateString())
            ->whereDate('subscription_ends_at', '<=', $now->copy()->addDays(30)->toDateString())
            ->with('plan:id,name')
            ->orderBy('subscription_ends_at')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'plan' => $r->plan?->name ?? 'N/A',
                'expires_on' => Carbon::parse($r->subscription_ends_at)->format('d/m/Y'),
                'days_left' => $now->diffInDays(Carbon::parse($r->subscription_ends_at), false),
            ]);

        return [
            'kpis' => [
                'mrr' => number_format($mrr, 0, ',', '.'),
                'arr' => number_format($arr, 0, ',', '.'),
                'active_count' => $activeCount,
                'churn_rate' => $churnRate,
                'churned_30d' => $churnedCount,
                'expiring_7d' => $expiring7,
                'expiring_14d' => $expiring14,
                'expiring_30d' => $expiring30,
                'webhook_success_rate' => $webhookSuccessRate,
            ],
            'monthly_revenue' => $monthlyRevenue,
            'revenue_by_plan' => $revenueByPlan,
            'expiring_list' => $expiringList,
            'aging_buckets' => $this->agingBuckets($now),
            'bad_debt' => $this->badDebtSummary(),
        ];
    }

    /**
     * Sổ cái tổng hợp (invoice + adjustment + commission) đã format cho hiển thị,
     * sắp xếp giảm dần theo ngày — dùng cho trang Ledger.
     */
    public function ledgerEntries(Carbon $dateFrom, Carbon $dateTo, ?int $restaurantId, ?string $typeFilter): Collection
    {
        $entries = collect();

        // 1. Invoices
        if (! $typeFilter || $typeFilter === 'payment') {
            $invQuery = BillingInvoice::with('restaurant')
                ->whereBetween('created_at', [$dateFrom, $dateTo]);
            if ($restaurantId) {
                $invQuery->where('restaurant_id', $restaurantId);
            }
            $invQuery->get()->each(function ($inv) use (&$entries) {
                $entries->push([
                    'sort_date' => $inv->created_at,
                    'date_label' => $inv->created_at->format('d/m/Y H:i'),
                    'type' => 'payment',
                    'icon' => '💳',
                    'description' => "Hóa đơn {$inv->invoice_number}",
                    'detail' => $inv->type,
                    'restaurant' => $inv->restaurant?->name ?? '—',
                    'amount' => (float) $inv->total,
                    'amount_fmt' => number_format((float) $inv->total),
                    'direction' => 'credit',
                    'status' => $inv->status,
                    'ref_id' => $inv->id,
                ]);
            });
        }

        // 2. Billing Adjustments
        if (! $typeFilter || $typeFilter === 'adjustment') {
            $adjQuery = BillingAdjustment::with(['restaurant', 'creator'])
                ->whereBetween('created_at', [$dateFrom, $dateTo]);
            if ($restaurantId) {
                $adjQuery->where('restaurant_id', $restaurantId);
            }
            $adjQuery->get()->each(function ($adj) use (&$entries) {
                $entries->push([
                    'sort_date' => $adj->created_at,
                    'date_label' => $adj->created_at->format('d/m/Y H:i'),
                    'type' => 'adjustment',
                    'icon' => '✏️',
                    'description' => $adj->reason ?? 'Điều chỉnh billing',
                    'detail' => "Loại: {$adj->type}".($adj->creator ? " | Bởi: {$adj->creator->name}" : ''),
                    'restaurant' => $adj->restaurant?->name ?? '—',
                    'amount' => (float) $adj->discount_amount,
                    'amount_fmt' => number_format((float) $adj->discount_amount),
                    'direction' => 'debit',
                    'status' => 'applied',
                    'ref_id' => $adj->id,
                ]);
            });
        }

        // 3. Commission Logs
        if (! $typeFilter || $typeFilter === 'commission') {
            $commQuery = CommissionLog::with('restaurant')
                ->whereBetween('created_at', [$dateFrom, $dateTo]);
            if ($restaurantId) {
                $commQuery->where('restaurant_id', $restaurantId);
            }
            $commQuery->get()->each(function ($comm) use (&$entries) {
                $restaurantName = $comm->restaurant?->name ?? '—';
                $entries->push([
                    'sort_date' => $comm->created_at,
                    'date_label' => $comm->created_at->format('d/m/Y H:i'),
                    'type' => 'commission',
                    'icon' => '🤝',
                    'description' => "Hoa hồng referral ({$comm->commission_percentage}%)",
                    'detail' => 'Từ giao dịch '.number_format((float) $comm->amount).' VND',
                    'restaurant' => $restaurantName,
                    'amount' => (float) $comm->commission_amount,
                    'amount_fmt' => number_format((float) $comm->commission_amount),
                    'direction' => 'debit',
                    'status' => 'paid',
                    'ref_id' => $comm->id,
                ]);
            });
        }

        return $entries->sortByDesc('sort_date')->values();
    }

    /**
     * Ghi nhận doanh thu trả trước (deferred revenue recognition) theo tỉ lệ
     * thời gian đã trôi qua của mỗi subscription đang hoạt động.
     */
    public function revenueRecognition(Carbon $now): array
    {
        $subscriptions = RestaurantSubscription::with(['restaurant', 'plan'])
            ->whereIn('status', ['trial', 'active'])
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->orderByDesc('started_at')
            ->get();

        $totalCash = 0.0;
        $totalEarned = 0.0;
        $totalDeferred = 0.0;

        $details = $subscriptions->map(function (RestaurantSubscription $sub) use ($now, &$totalCash, &$totalEarned, &$totalDeferred) {
            $price = (float) $sub->price;
            $startedAt = $sub->started_at;
            $endedAt = $sub->ended_at;

            if (! $startedAt || ! $endedAt || $endedAt->isPast()) {
                $earnedRevenue = $price;
                $deferredRevenue = 0.0;
            } else {
                $totalDays = max(1, (int) $startedAt->diffInDays($endedAt));
                $elapsedDays = max(0, min((int) $startedAt->diffInDays($now), $totalDays));
                $earnedRevenue = round($price * ($elapsedDays / $totalDays), 2);
                $deferredRevenue = round($price - $earnedRevenue, 2);
            }

            $totalCash += $price;
            $totalEarned += $earnedRevenue;
            $totalDeferred += $deferredRevenue;

            return [
                'restaurant' => $sub->restaurant?->name ?? '—',
                'plan' => $sub->plan?->name ?? '—',
                'status' => $sub->status,
                'price' => number_format($price),
                'started_at' => $startedAt?->format('d/m/Y'),
                'ended_at' => $endedAt?->format('d/m/Y'),
                'earned_revenue' => number_format($earnedRevenue),
                'deferred_revenue' => number_format($deferredRevenue),
                'earn_percent' => $price > 0 ? round(($earnedRevenue / $price) * 100) : 0,
            ];
        })->values()->all();

        return [
            'summary' => [
                'total_cash' => number_format($totalCash),
                'total_earned' => number_format($totalEarned),
                'total_deferred' => number_format($totalDeferred),
                'earn_rate' => $totalCash > 0 ? round(($totalEarned / $totalCash) * 100, 1) : 0,
                'subscription_count' => count($details),
                'total_cash_raw' => round($totalCash),
                'total_earned_raw' => round($totalEarned),
                'total_deferred_raw' => round($totalDeferred),
            ],
            'details' => $details,
        ];
    }

    /**
     * Dashboard giám sát dunning (nhắc gia hạn): nhóm subscription theo mức độ
     * khẩn cấp (d7/d3/d0/overdue/converted) + tỉ lệ chuyển đổi sau khi nhắc.
     */
    public function dunningDashboard(Carbon $now): array
    {
        // Lấy subscriptions đang trong dunning hoặc sắp hết hạn trong 7 ngày
        $candidates = RestaurantSubscription::with(['restaurant.owner'])
            ->where(function ($q) use ($now) {
                // Đã được nhắc trong 45 ngày qua
                $q->whereNotNull('last_notified_at')
                    ->where('last_notified_at', '>=', $now->copy()->subDays(45));
            })
            ->orWhere(function ($q) use ($now) {
                // Sắp hết hạn trong 7 ngày và chưa được nhắc
                $q->whereIn('status', ['trial', 'active'])
                    ->whereNotNull('ended_at')
                    ->whereBetween('ended_at', [$now, $now->copy()->addDays(7)]);
            })
            ->orWhere(function ($q) use ($now) {
                // Đã expired trong 7 ngày qua
                $q->where('status', 'expired')
                    ->whereNotNull('ended_at')
                    ->where('ended_at', '>=', $now->copy()->subDays(7));
            })
            ->get();

        $groups = ['d7' => [], 'd3' => [], 'd0' => [], 'overdue' => [], 'converted' => []];

        foreach ($candidates as $sub) {
            $stage = $sub->billing_meta['dunning_stage'] ?? null;
            $isPaused = isset($sub->billing_meta['dunning_paused_until'])
                && Carbon::parse($sub->billing_meta['dunning_paused_until'])->isFuture();
            $daysLeft = $sub->ended_at
                ? (int) round($now->diffInDays($sub->ended_at, false))
                : null;

            // Đã thanh toán sau khi nhắc → "Converted"
            $isConverted = $sub->last_paid_at
                && $sub->last_notified_at
                && $sub->last_paid_at->greaterThan($sub->last_notified_at)
                && $sub->last_paid_at->greaterThan($now->copy()->subDays(30));

            if ($isConverted) {
                $groups['converted'][] = $this->formatDunningEntry($sub, $stage, $daysLeft, $isPaused);

                continue;
            }

            if ($daysLeft !== null && $daysLeft < 0) {
                $groups['overdue'][] = $this->formatDunningEntry($sub, $stage, $daysLeft, $isPaused);
            } elseif ($daysLeft !== null && $daysLeft === 0) {
                $groups['d0'][] = $this->formatDunningEntry($sub, $stage, $daysLeft, $isPaused);
            } elseif ($daysLeft !== null && $daysLeft <= 3) {
                $groups['d3'][] = $this->formatDunningEntry($sub, $stage, $daysLeft, $isPaused);
            } elseif ($stage || ($daysLeft !== null && $daysLeft <= 7)) {
                $groups['d7'][] = $this->formatDunningEntry($sub, $stage, $daysLeft, $isPaused);
            }
        }

        // Conversion rate: % thanh toán trong 7 ngày sau email nhắc (30 ngày qua)
        $notified30d = RestaurantSubscription::whereNotNull('last_notified_at')
            ->where('last_notified_at', '>=', $now->copy()->subDays(30))
            ->count();

        $converted30d = RestaurantSubscription::whereNotNull('last_notified_at')
            ->whereNotNull('last_paid_at')
            ->where('last_notified_at', '>=', $now->copy()->subDays(30))
            ->whereColumn('last_paid_at', '>', 'last_notified_at')
            ->count();

        $conversionRate = $notified30d > 0 ? round(($converted30d / $notified30d) * 100, 1) : 0.0;

        return [
            'groups' => [
                'd7' => array_values($groups['d7']),
                'd3' => array_values($groups['d3']),
                'd0' => array_values($groups['d0']),
                'overdue' => array_values($groups['overdue']),
                'converted' => array_values($groups['converted']),
            ],
            'stats' => [
                'total_in_dunning' => $notified30d,
                'converted_count' => $converted30d,
                'conversion_rate' => $conversionRate,
                'd7_count' => count($groups['d7']),
                'd3_count' => count($groups['d3']),
                'd0_count' => count($groups['d0']),
                'overdue_count' => count($groups['overdue']),
            ],
        ];
    }

    /**
     * Phân tích vòng đời subscription: thời gian sống trung bình theo gói, tỉ
     * lệ gia hạn 6 tháng gần nhất, phân phối gói hiện tại, phễu chuyển đổi.
     */
    public function lifecycle(Carbon $now): array
    {
        // Thời gian sống trung bình per plan (subscription đã kết thúc).
        // MySQL: DATEDIFF(); SQLite (test suite) không có hàm này — dùng julianday().
        $dateDiffExpr = DB::connection()->getDriverName() === 'sqlite'
            ? '(julianday(rs.ended_at) - julianday(rs.started_at))'
            : 'DATEDIFF(rs.ended_at, rs.started_at)';

        $avgLifetimes = DB::table('restaurant_subscriptions as rs')
            ->join('subscription_plans as sp', 'rs.plan_id', '=', 'sp.id')
            ->whereIn('rs.status', ['expired', 'cancelled'])
            ->whereNotNull('rs.started_at')
            ->whereNotNull('rs.ended_at')
            ->whereRaw('rs.ended_at > rs.started_at')
            ->selectRaw("sp.name as plan_name, sp.code as plan_code, ROUND(AVG({$dateDiffExpr})) as avg_days, COUNT(*) as subscription_count, ROUND(AVG(rs.price)) as avg_price")
            ->groupBy('sp.id', 'sp.name', 'sp.code')
            ->orderByDesc('subscription_count')
            ->get()
            ->map(fn ($row) => [
                'plan_name' => $row->plan_name,
                'plan_code' => $row->plan_code,
                'avg_days' => (int) $row->avg_days,
                'avg_months' => $row->avg_days > 0 ? round($row->avg_days / 30, 1) : 0,
                'subscription_count' => (int) $row->subscription_count,
                'avg_price' => (int) $row->avg_price,
                'estimated_ltv' => round((float) $row->avg_price * (max(1, $row->avg_days) / 30)),
            ])->values()->all();

        // Tỷ lệ gia hạn theo tháng (6 tháng gần nhất)
        $renewalRates = collect(range(5, 0))->map(function (int $offset) use ($now) {
            $monthStart = $now->copy()->subMonths($offset)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $dueForRenewal = RestaurantSubscription::whereNotNull('renewal_at')
                ->whereBetween('renewal_at', [$monthStart, $monthEnd])
                ->where('status', '!=', 'trial')
                ->count();

            $actuallyPaid = RestaurantSubscription::whereNotNull('last_paid_at')
                ->whereBetween('last_paid_at', [$monthStart, $monthEnd])
                ->count();

            return [
                'label' => $monthStart->format('m/Y'),
                'month' => $monthStart->format('Y-m'),
                'due' => $dueForRenewal,
                'renewed' => $actuallyPaid,
                'rate' => $dueForRenewal > 0 ? round(($actuallyPaid / $dueForRenewal) * 100, 1) : null,
            ];
        })->values()->all();

        // Thống kê phân phối gói hiện tại
        $planStats = DB::table('subscription_plans as sp')
            ->leftJoin('restaurants as r', 'r.plan_id', '=', 'sp.id')
            ->where('sp.is_custom', false)
            ->selectRaw('sp.name, sp.code, sp.price, COUNT(r.id) as active_count')
            ->groupBy('sp.id', 'sp.name', 'sp.code', 'sp.price')
            ->orderByDesc('active_count')
            ->get()
            ->map(fn ($row) => [
                'plan_name' => $row->name,
                'plan_code' => $row->code,
                'plan_price' => (int) $row->price,
                'active_count' => (int) $row->active_count,
            ])->values()->all();

        // Phễu chuyển đổi (tổng quan)
        $funnelData = [
            'total_registered' => Restaurant::count(),
            'trial_completed' => RestaurantSubscription::where('status', 'active')
                ->whereHas('plan', fn ($q) => $q->where('price', '>', 0))
                ->whereNotNull('last_paid_at')
                ->distinct('restaurant_id')
                ->count('restaurant_id'),
            'active_paid' => RestaurantSubscription::where('status', 'active')
                ->whereHas('plan', fn ($q) => $q->where('price', '>', 0))
                ->distinct('restaurant_id')
                ->count('restaurant_id'),
            'renewed_at_least_once' => RestaurantSubscription::whereNotNull('last_paid_at')
                ->distinct('restaurant_id')
                ->count('restaurant_id'),
        ];

        return [
            'avg_lifetimes' => $avgLifetimes,
            'renewal_rates' => $renewalRates,
            'plan_stats' => $planStats,
            'funnel' => $funnelData,
        ];
    }

    /**
     * Phân loại invoice pending theo số ngày quá hạn (Invoice Aging Report).
     */
    private function agingBuckets(Carbon $now): array
    {
        $pendingInvoices = BillingInvoice::where('status', 'pending')
            ->whereNotNull('due_on')
            ->select(['id', 'total', 'due_on'])
            ->get();

        $buckets = [
            'current' => ['label' => 'Chưa đến hạn',      'count' => 0, 'amount' => 0.0, 'color' => 'emerald'],
            '0_7' => ['label' => '0–7 ngày quá hạn',   'count' => 0, 'amount' => 0.0, 'color' => 'yellow'],
            '8_30' => ['label' => '8–30 ngày quá hạn',  'count' => 0, 'amount' => 0.0, 'color' => 'amber'],
            '31_60' => ['label' => '31–60 ngày quá hạn', 'count' => 0, 'amount' => 0.0, 'color' => 'orange'],
            'over_60' => ['label' => '>60 ngày quá hạn',   'count' => 0, 'amount' => 0.0, 'color' => 'rose'],
        ];

        foreach ($pendingInvoices as $inv) {
            $dueDate = Carbon::parse($inv->due_on);
            $daysOverdue = (int) $dueDate->diffInDays($now, false); // positive = overdue
            $amount = (float) $inv->total;

            if ($daysOverdue <= 0) {
                $buckets['current']['count']++;
                $buckets['current']['amount'] += $amount;
            } elseif ($daysOverdue <= 7) {
                $buckets['0_7']['count']++;
                $buckets['0_7']['amount'] += $amount;
            } elseif ($daysOverdue <= 30) {
                $buckets['8_30']['count']++;
                $buckets['8_30']['amount'] += $amount;
            } elseif ($daysOverdue <= 60) {
                $buckets['31_60']['count']++;
                $buckets['31_60']['amount'] += $amount;
            } else {
                $buckets['over_60']['count']++;
                $buckets['over_60']['amount'] += $amount;
            }
        }

        foreach ($buckets as &$bucket) {
            $bucket['amount_fmt'] = number_format($bucket['amount']);
        }

        return $buckets;
    }

    /**
     * Thống kê nợ xấu và tỷ lệ thu hồi hiệu quả.
     */
    private function badDebtSummary(): array
    {
        $writtenOff = BillingInvoice::where('status', 'written_off')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as amount')
            ->first();

        $totalInvoiced = (float) BillingInvoice::where('type', 'payment_success')
            ->where('payment_status', 'paid')
            ->sum('total');

        $totalCollected = (float) BillingInvoice::where('type', 'payment_success')
            ->where('payment_status', 'paid')
            ->sum('total');

        $totalAdjustments = (float) BillingAdjustment::sum('discount_amount');

        $effectiveCollectionRate = $totalInvoiced > 0
            ? round(($totalCollected / $totalInvoiced) * 100, 1)
            : 100.0;

        return [
            'written_off_count' => (int) ($writtenOff->cnt ?? 0),
            'written_off_amount' => number_format((float) ($writtenOff->amount ?? 0)),
            'total_adjustments' => number_format($totalAdjustments),
            'effective_collection_rate' => $effectiveCollectionRate,
            'total_invoiced' => number_format($totalInvoiced),
            'total_collected' => number_format($totalCollected),
        ];
    }

    /**
     * Format một subscription entry cho Dunning Dashboard.
     */
    private function formatDunningEntry(
        RestaurantSubscription $sub,
        ?string $stage,
        ?int $daysLeft,
        bool $isPaused
    ): array {
        return [
            'id' => $sub->id,
            'restaurant_id' => $sub->restaurant_id,
            'restaurant_name' => $sub->restaurant?->name ?? '—',
            'owner_name' => $sub->restaurant?->owner?->name ?? '—',
            'owner_email' => $sub->restaurant?->owner?->email ?? '—',
            'ended_at' => $sub->ended_at?->format('d/m/Y'),
            'days_left' => $daysLeft,
            'stage' => $stage,
            'last_notified_at' => $sub->last_notified_at?->format('d/m/Y H:i'),
            'last_paid_at' => $sub->last_paid_at?->format('d/m/Y H:i'),
            'is_paused' => $isPaused,
            'paused_until' => isset($sub->billing_meta['dunning_paused_until'])
                ? Carbon::parse($sub->billing_meta['dunning_paused_until'])->format('d/m/Y')
                : null,
        ];
    }
}
