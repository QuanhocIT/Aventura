<?php

namespace App\Http\Controllers;

use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Services\ApprovalService;
use App\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaryController extends Controller
{
    public function __construct(
        private SalaryService $salaryService,
        private ApprovalService $approvalService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user         = $request->user();
        $restaurantId = $user->restaurant_id;

        $period = $request->input('period', today()->format('Y-m'));
        [$year, $month] = explode('-', $period);

        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $periodEnd   = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $salaries = Salary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('pay_period_start', $periodStart)
            ->where('pay_period_end', $periodEnd)
            ->with(['employee:id,full_name,job_title,employment_type', 'adjustments'])
            ->get()
            ->map(function (Salary $s) {
                return [
                    'id'               => $s->id,
                    'employee_name'    => $s->employee?->full_name ?? '—',
                    'job_title'        => $s->employee?->job_title ?? '',
                    'employment_type'  => $s->employee?->employment_type ?? '',
                    'base_salary'      => (float) $s->base_salary,
                    'bonus_amount'     => (float) $s->bonus_amount,
                    'deduction_amount' => (float) $s->deduction_amount,
                    'net_salary'       => (float) $s->net_salary,
                    'status'           => $s->status,
                    'paid_at'          => $s->paid_at?->format('d/m/Y H:i'),
                    'adjustments'      => $s->adjustments->map(fn (SalaryAdjustment $a) => [
                        'id'     => $a->id,
                        'type'   => $a->type,
                        'amount' => (float) $a->amount,
                        'reason' => $a->reason,
                    ])->values(),
                ];
            })
            ->values();

        $totals = [
            'total_payroll'    => (float) $salaries->sum('net_salary'),
            'total_deductions' => (float) $salaries->sum('deduction_amount'),
            'total_bonuses'    => (float) $salaries->sum('bonus_amount'),
            'headcount'        => $salaries->count(),
        ];

        return Inertia::render('salaries/Index', [
            'salaries'   => $salaries,
            'totals'     => $totals,
            'period'     => $period,
            'canApprove' => $user->hasAnyRole(['owner', 'manager']),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $period = $request->input('period', today()->format('Y-m'));
        $result = $this->salaryService->generateMonthlyDrafts($request->user()->restaurant_id, $period);

        $msg = "Đã tạo {$result['created']} bảng lương, bỏ qua {$result['skipped']} (đã tồn tại).";

        return back()->with('success', $msg);
    }

    public function approve(Request $request, Salary $salary): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($salary->status === 'paid', 422);

        $salary->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Đã duyệt bảng lương.');
    }

    public function markPaid(Request $request, Salary $salary): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner']), 403);
        abort_unless($salary->status === 'approved', 422);

        $salary->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Đã đánh dấu đã thanh toán lương.');
    }

    public function storeAdjustment(Request $request, Salary $salary): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($salary->status === 'paid', 422);

        $data = $request->validate([
            'type'   => ['required', 'in:bonus,penalty,violation'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (! $request->user()->hasRole('owner')) {
            $this->approvalService->submitRequest('salary_adjustment', array_merge($data, [
                'salary_id' => $salary->id,
            ]), $request->user());
            return back()->with('success', 'Yêu cầu điều chỉnh lương đã gửi Chủ nhà hàng để phê duyệt.');
        }

        $this->salaryService->addAdjustment($salary, array_merge($data, [
            'employee_id' => $salary->employee_id,
        ]));

        return back()->with('success', 'Đã thêm điều chỉnh lương.');
    }
}
