<?php

namespace App\Http\Controllers;

use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\User;
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
        abort_unless($request->user()->can('manage_salary'), 403);

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
            ->with(['employee:id,full_name,job_title,employment_type,branch_id', 'adjustments'])
            ->get()
            ->map(function (Salary $s) {
                return [
                    'id'               => $s->id,
                    'employee_name'    => $s->employee?->full_name ?? '—',
                    'job_title'        => $s->employee?->job_title ?? '',
                    'employment_type'  => $s->employee?->employment_type ?? '',
                    'branch_id'        => $s->employee?->branch_id,
                    'base_salary'      => (float) $s->base_salary,
                    'bonus_amount'     => (float) $s->bonus_amount,
                    'deduction_amount' => (float) $s->deduction_amount,
                    'net_salary'       => (float) $s->net_salary,
                    'status'           => $s->status,
                    'paid_at'          => $s->paid_at?->format('d/m/Y H:i'),
                    'adjustments'      => $s->adjustments->map(fn (SalaryAdjustment $a) => [
                        'id'             => $a->id,
                        'type'           => $a->type,
                        'amount'         => (float) $a->amount,
                        'reason'         => $a->reason,
                        'status'         => $a->status,
                        'dispute_reason' => $a->dispute_reason,
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

        // Lấy danh sách chi nhánh phục vụ bộ lọc ở Frontend
        $branches = \App\Models\RestaurantBranch::where('restaurant_id', $restaurantId)->get(['id', 'name']);

        return Inertia::render('salaries/Index', [
            'salaries'   => $salaries,
            'totals'     => $totals,
            'period'     => $period,
            'branches'   => $branches,
            'canApprove' => $user->hasAnyRole(['owner', 'manager']),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage_salary'), 403);

        $period = $request->input('period', today()->format('Y-m'));
        $result = $this->salaryService->generateMonthlyDrafts($request->user()->restaurant_id, $period);

        $msg = "Đã tạo {$result['created']} bảng lương, bỏ qua {$result['skipped']} (đã tồn tại).";

        return back()->with('success', $msg);
    }

    public function approve(Request $request, Salary $salary): RedirectResponse
    {
        abort_unless($request->user()->can('manage_salary'), 403);
        abort_if($salary->status === 'paid', 422);

        $salary->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Đã duyệt bảng lương.');
    }

    public function markPaid(Request $request, Salary $salary): RedirectResponse
    {
        abort_unless($request->user()->can('manage_salary') && $request->user()->can('approve_requests'), 403);
        abort_unless($salary->status === 'approved', 422);

        $salary->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Đã đánh dấu đã thanh toán lương.');
    }

    public function storeAdjustment(Request $request, Salary $salary): RedirectResponse
    {
        abort_unless($request->user()->can('manage_salary'), 403);
        abort_if($salary->status === 'paid', 422);

        $data = $request->validate([
            'type'   => ['required', 'in:bonus,penalty,violation'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (! $request->user()->can('approve_requests')) {
            $this->approvalService->submitRequest('salary_adjustment', array_merge($data, [
                'salary_id' => $salary->id,
            ]), $request->user());
            return back()->with('success', 'Yêu cầu điều chỉnh lương đã gửi Chủ nhà hàng để phê duyệt.');
        }

        $this->salaryService->addAdjustment($salary, array_merge($data, [
            'employee_id' => $salary->employee_id,
            'status'      => 'applied',
        ]));

        return back()->with('success', 'Đã thêm điều chỉnh lương.');
    }

    /**
     * Áp dụng cấn trừ/thưởng phạt hàng loạt cho nhiều nhân sự được chọn.
     */
    public function storeBulkAdjustment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage_salary'), 403);

        $data = $request->validate([
            'salary_ids'   => ['required', 'array'],
            'salary_ids.*' => ['required', 'exists:salaries,id'],
            'type'         => ['required', 'in:bonus,penalty,violation'],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'reason'       => ['required', 'string', 'max:500'],
        ]);

        $salaries = Salary::withoutGlobalScopes()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereIn('id', $data['salary_ids'])
            ->get();

        $count = 0;
        foreach ($salaries as $salary) {
            if ($salary->status === 'paid') continue;

            if (!$request->user()->can('approve_requests')) {
                $this->approvalService->submitRequest('salary_adjustment', [
                    'salary_id' => $salary->id,
                    'type'      => $data['type'],
                    'amount'    => $data['amount'],
                    'reason'    => $data['reason'],
                ], $request->user());
            } else {
                $this->salaryService->addAdjustment($salary, [
                    'employee_id' => $salary->employee_id,
                    'type'        => $data['type'],
                    'amount'      => $data['amount'],
                    'reason'      => $data['reason'],
                    'status'      => 'applied',
                ]);
            }
            $count++;
        }

        $msg = $request->user()->can('approve_requests')
            ? "Đã áp dụng điều chỉnh lương hàng loạt cho {$count} nhân sự thành công."
            : "Đã gửi đề xuất điều chỉnh lương hàng loạt cho {$count} nhân sự lên Chủ nhà hàng phê duyệt.";

        return back()->with('success', $msg);
    }

    /**
     * Nhân viên gửi khiếu nại cấn trừ lương.
     */
    public function disputeAdjustment(Request $request, SalaryAdjustment $adjustment): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $employee->id === $adjustment->employee_id, 403);
        abort_if($adjustment->salary->status === 'paid', 422);

        $data = $request->validate([
            'dispute_reason' => ['required', 'string', 'max:500'],
        ]);

        $adjustment->update([
            'status'         => 'disputed',
            'dispute_reason' => $data['dispute_reason'],
        ]);

        // Tính toán lại để đóng băng khoản cấn trừ lương net
        $this->salaryService->recalculate($adjustment->salary);

        // Gửi thông báo đến Owner (Database & Real-time notification)
        $owner = User::where('restaurant_id', $adjustment->restaurant_id)
            ->role('owner')
            ->first();

        if ($owner) {
            $owner->notify(new \App\Notifications\SalaryDisputeNotification($adjustment, $request->user()));
        }

        return back()->with('success', 'Đã gửi khiếu nại cấn trừ lương thành công. Khoản phạt này đã tạm thời được đóng băng chờ Owner giải quyết.');
    }
}
