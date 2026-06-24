<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeKpi;
use App\Models\KpiMetric;
use App\Models\PerformanceReview;
use App\Services\KpiService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KpiController extends Controller
{
    public function __construct(
        protected KpiService $kpiService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $period = $request->input('period', now()->format('Y-m'));

        // 1. Fetch KPI config rules
        $kpiConfigs = KpiMetric::where('restaurant_id', $restaurantId)->get();

        // 2. Fetch employees and eager load their finalized/draft KPIs for the target period
        $employees = Employee::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->with(['role', 'kpis' => function ($query) use ($period) {
                $query->where('period', $period)->with('metrics');
            }])
            ->get()
            ->map(function ($emp) use ($kpiService) {
                return [
                    'id' => $emp->id,
                    'full_name' => $emp->full_name,
                    'job_title' => $emp->job_title ?? 'Staff',
                    'role_name' => $emp->role?->name ?? 'None',
                    'role' => $kpiService->getKpiRole($emp),
                    'current_kpi' => $emp->kpis->first() ? [
                        'id' => $emp->kpis->first()->id,
                        'total_score' => (float)$emp->kpis->first()->total_score,
                        'total_bonus' => (float)$emp->kpis->first()->total_bonus,
                        'total_commission' => (float)$emp->kpis->first()->total_commission,
                        'status' => $emp->kpis->first()->status,
                        'metrics' => $emp->kpis->first()->metrics->map(fn($m) => [
                            'id' => $m->id,
                            'metric_code' => $m->metric_code,
                            'metric_name' => $m->metric_name,
                            'actual_value' => (float)$m->actual_value,
                            'target_value' => (float)$m->target_value,
                            'score' => (float)$m->score,
                            'is_achieved' => (bool)$m->is_achieved,
                            'bonus_earned' => (float)$m->bonus_earned,
                            'commission_earned' => (float)$m->commission_earned,
                        ])
                    ] : null
                ];
            });

        // 3. Leaderboard list (Sorted by total_score from employee_kpis)
        $leaderboard = EmployeeKpi::where('restaurant_id', $restaurantId)
            ->where('period', $period)
            ->with('employee:id,full_name,job_title')
            ->orderByDesc('total_score')
            ->get()
            ->map(fn($kpi) => [
                'id' => $kpi->id,
                'employee_name' => $kpi->employee?->full_name ?? 'Unknown',
                'job_title' => $kpi->employee?->job_title ?? '',
                'total_score' => (float) $kpi->total_score,
                'total_bonus' => (float) $kpi->total_bonus,
                'total_commission' => (float) $kpi->total_commission,
            ]);

        // 4. Eager load 360-degree reviews for the period
        $reviews = PerformanceReview::where('restaurant_id', $restaurantId)
            ->where('period', $period)
            ->with(['employee:id,full_name', 'reviewer:id,name'])
            ->get()
            ->map(fn($rev) => [
                'id' => $rev->id,
                'employee_id' => $rev->employee_id,
                'employee_name' => $rev->employee?->full_name ?? 'Unknown',
                'reviewer_name' => $rev->reviewer?->name ?? 'Unknown',
                'reviewer_type' => $rev->reviewer_type,
                'ratings' => $rev->ratings,
                'average_score' => (float)$rev->average_score,
                'comments' => $rev->comments,
                'status' => $rev->status,
                'created_at' => $rev->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('kpis/Index', [
            'kpiConfigs' => $kpiConfigs,
            'employees' => $employees,
            'leaderboard' => $leaderboard,
            'reviews' => $reviews,
            'period' => $period,
            'canManage' => $user->hasAnyRole(['owner', 'manager']),
        ]);
    }

    /**
     * Trigger manual recalculation for a month's KPIs.
     */
    public function recalculate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $period = $request->input('period', now()->format('Y-m'));

        $employees = Employee::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->get();

        $calculatedCount = 0;
        foreach ($employees as $employee) {
            $kpi = $this->kpiService->calculateKpi($employee, $period);
            if ($kpi) {
                $calculatedCount++;
            }
        }

        return back()->with('success', "Đã tính toán thành công KPI cho {$calculatedCount} nhân sự thuộc kỳ {$period}.");
    }

    /**
     * Finalize the KPI score so it can be picked up by the payroll system.
     */
    public function finalize(Request $request, EmployeeKpi $kpi): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($kpi->restaurant_id !== $user->restaurant_id, 403);

        $kpi->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $user->id,
        ]);

        return back()->with('success', "Đã chốt duyệt thành công bảng KPI của nhân viên {$kpi->employee->full_name}. Số tiền thưởng và hoa hồng sẽ tự động cập nhật vào bảng lương kỳ này.");
    }

    /**
     * Submit a 360-degree performance review.
     */
    public function storeReview(Request $request): RedirectResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'reviewer_type' => ['required', 'in:self,manager,peer'],
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'ratings' => ['required', 'array'],
            'ratings.communication' => ['required', 'integer', 'between:1,5'],
            'ratings.teamwork' => ['required', 'integer', 'between:1,5'],
            'ratings.reliability' => ['required', 'integer', 'between:1,5'],
            'ratings.skills' => ['required', 'integer', 'between:1,5'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $ratings = $data['ratings'];
        $avgScore = array_sum($ratings) / count($ratings);

        PerformanceReview::updateOrCreate(
            [
                'restaurant_id' => $restaurantId,
                'employee_id' => $data['employee_id'],
                'reviewer_id' => $user->id,
                'period' => $data['period'],
            ],
            [
                'reviewer_type' => $data['reviewer_type'],
                'ratings' => $ratings,
                'average_score' => round($avgScore, 2),
                'comments' => $data['comments'] ?? null,
                'status' => 'submitted',
            ]
        );

        return back()->with('success', 'Đã ghi nhận phiếu đánh giá 360° thành công.');
    }

    /**
     * Update target settings for a specific KPI config.
     */
    public function updateMetricConfig(Request $request, KpiMetric $metric): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($metric->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'weight' => ['required', 'numeric', 'between:0,100'],
            'target' => ['required', 'numeric', 'min:0'],
            'bonus_amount' => ['required', 'numeric', 'min:0'],
            'commission_rate' => ['required', 'numeric', 'between:0,100'],
        ]);

        $metric->update($data);

        return back()->with('success', "Đã cập nhật cấu hình chỉ tiêu KPI '{$metric->metric_name}' thành công.");
    }
}
