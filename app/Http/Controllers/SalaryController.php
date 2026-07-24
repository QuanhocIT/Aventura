<?php

namespace App\Http\Controllers;

use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\SalaryService;
use App\Notifications\SalaryReadyNotification;
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

        $user = $request->user();

        $restaurant = $user->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'hr_full')) {
            return Inertia::render('FeatureGate', [
                'feature'       => 'hr_full',
                'feature_label' => 'Quản lý Lương & Nhân sự',
                'plan_name'     => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $user->restaurant_id;

        $period = $request->input('period', today()->format('Y-m'));
        [$year, $month] = explode('-', $period);

        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $periodEnd   = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $salaries = Salary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('pay_period_start', $periodStart)
            ->where('pay_period_end', $periodEnd)
            ->with(['employee:id,employee_code,full_name,job_title,employment_type,compensation_type,pay_rate,base_salary,branch_id', 'employee.trustScore', 'adjustments'])
            ->get()
            ->map(function (Salary $s) {
                $breakdown = $this->salaryService->getSalaryCalculationDetails($s);

                return [
                    'id'                     => $s->id,
                    'employee_id'            => $s->employee_id,
                    'employee_code'          => $s->employee?->employee_code ?? 'NV-' . $s->employee_id,
                    'employee_name'          => $s->employee?->full_name ?? '—',
                    'job_title'              => $s->employee?->job_title ?? '',
                    'employment_type'        => $s->employee?->employment_type ?? 'full-time',
                    'compensation_type'      => $s->employee?->compensation_type ?? 'fixed',
                    'pay_rate'               => (float) ($s->employee?->pay_rate ?? 0),
                    'contract_base_salary'   => (float) ($s->employee?->base_salary ?? 0),
                    'trust_score'            => $s->employee?->trustScore?->score ?? 100,
                    'branch_id'              => $s->employee?->branch_id,
                    'base_salary'            => (float) $s->base_salary,
                    'bonus_amount'           => (float) $s->bonus_amount,
                    'deduction_amount'       => (float) $s->deduction_amount,
                    'net_salary'             => (float) $s->net_salary,
                    'status'                 => $s->status,
                    'paid_at'                => $s->paid_at?->format('d/m/Y H:i'),
                    'breakdown'              => $breakdown,
                    'adjustments'            => $s->adjustments->map(fn (SalaryAdjustment $a) => [
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

    public function bulkApprove(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage_salary'), 403);

        $data = $request->validate([
            'salary_ids'   => ['required', 'array', 'min:1'],
            'salary_ids.*' => ['integer', 'exists:salaries,id'],
        ]);

        $approvedCount = $this->salaryService->bulkApprove(
            $request->user()->restaurant_id,
            $data['salary_ids'],
            $request->user()->id
        );

        return back()->with('success', "Đã phê duyệt thành công {$approvedCount} bảng lương.");
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

        $employeeUser = $salary->employee?->user;
        if ($employeeUser) {
            $periodStr = $salary->pay_period_start ? Carbon::parse($salary->pay_period_start)->format('m/Y') : '';
            $employeeUser->notify(new SalaryReadyNotification(
                $salary,
                "Phiếu lương kỳ {$periodStr} đã được phê duyệt. Bạn có thể xem chi tiết ngay."
            ));
        }

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

        $employeeUser = $salary->employee?->user;
        if ($employeeUser) {
            $periodStr = $salary->pay_period_start ? Carbon::parse($salary->pay_period_start)->format('m/Y') : '';
            $employeeUser->notify(new SalaryReadyNotification(
                $salary,
                "Lương kỳ {$periodStr} đã được thanh toán. Hãy kiểm tra tài khoản của bạn."
            ));
        }

        return back()->with('success', 'Đã đánh dấu đã thanh toán lương.');
    }

    public function storeAdjustment(Request $request, Salary $salary): RedirectResponse
    {
        abort_unless($request->user()->can('manage_salary'), 403);
        abort_if($salary->status === 'paid', 422);

        $data = $request->validate([
            'type'   => ['required', 'in:bonus,penalty,violation,advance'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($salary, $data, $request) {
                // Lock the salary record
                $lockedSalary = Salary::where('id', $salary->id)->lockForUpdate()->firstOrFail();

                if ($data['type'] === 'advance') {
                    $employee = $lockedSalary->employee;
                    if (!$employee) {
                        throw new \Exception('Nhân viên không tồn tại.');
                    }
                    $salaryMonth = Carbon::parse($lockedSalary->pay_period_start);
                    $calculationDate = today()->isSameMonth($salaryMonth) ? today() : $salaryMonth->endOfMonth();
                    $earnedWages = $this->salaryService->calculateEarnedWagesForMonth($employee, $calculationDate->toDateString());
                    
                    $existingAdvanceAmount = SalaryAdjustment::withoutGlobalScopes()
                        ->where('salary_id', $lockedSalary->id)
                        ->where('type', 'advance')
                        ->where('status', 'applied')
                        ->sum('amount');
                        
                    $pendingAdvanceAmount = (float) \App\Models\ApprovalRequest::forRestaurant($request->user()->restaurant_id)
                        ->where('status', 'pending')
                        ->where('operation_type', 'salary_adjustment')
                        ->where('operation_data->salary_id', $lockedSalary->id)
                        ->where('operation_data->type', 'advance')
                        ->sum('operation_data->amount');
                        
                    $limit = $earnedWages * 0.50;
                    if (($existingAdvanceAmount + $pendingAdvanceAmount + $data['amount']) > $limit) {
                        throw new \Exception(sprintf('Yêu cầu tạm ứng vượt quá giới hạn 50%% tiền lương tích lũy trong tháng (Tích lũy: %sđ, Hạn mức tối đa: %sđ, Đã tạm ứng/đang chờ: %sđ).', number_format($earnedWages), number_format($limit), number_format($existingAdvanceAmount + $pendingAdvanceAmount)));
                    }
                }

                if (! $request->user()->can('approve_requests')) {
                    $this->approvalService->submitRequest('salary_adjustment', array_merge($data, [
                        'salary_id' => $lockedSalary->id,
                    ]), $request->user());
                } else {
                    $this->salaryService->addAdjustment($lockedSalary, array_merge($data, [
                        'employee_id' => $lockedSalary->employee_id,
                        'status'      => 'applied',
                    ]));
                }
            });
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', $request->user()->can('approve_requests') ? 'Đã thêm điều chỉnh lương.' : 'Yêu cầu điều chỉnh lương đã gửi Chủ nhà hàng để phê duyệt.');
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
            'type'         => ['required', 'in:bonus,penalty,violation,advance'],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'reason'       => ['required', 'string', 'max:500'],
        ]);

        $salaries = Salary::withoutGlobalScopes()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereIn('id', $data['salary_ids'])
            ->get();

        // Check advances limits first
        if ($data['type'] === 'advance') {
            foreach ($salaries as $salary) {
                if ($salary->status === 'paid') continue;
                $employee = $salary->employee;
                if ($employee) {
                    $salaryMonth = Carbon::parse($salary->pay_period_start);
                    $calculationDate = today()->isSameMonth($salaryMonth) ? today() : $salaryMonth->endOfMonth();
                    $earnedWages = $this->salaryService->calculateEarnedWagesForMonth($employee, $calculationDate->toDateString());
                    
                    $existingAdvanceAmount = SalaryAdjustment::withoutGlobalScopes()
                        ->where('salary_id', $salary->id)
                        ->where('type', 'advance')
                        ->where('status', 'applied')
                        ->sum('amount');
                        
                    $pendingAdvanceAmount = (float) \App\Models\ApprovalRequest::forRestaurant($request->user()->restaurant_id)
                        ->where('status', 'pending')
                        ->where('operation_type', 'salary_adjustment')
                        ->where('operation_data->salary_id', $salary->id)
                        ->where('operation_data->type', 'advance')
                        ->sum('operation_data->amount');
                        
                    $limit = $earnedWages * 0.50;
                    if (($existingAdvanceAmount + $pendingAdvanceAmount + $data['amount']) > $limit) {
                        return back()->withErrors(['amount' => sprintf('Yêu cầu tạm ứng cho nhân viên %s vượt quá giới hạn 50%% tiền lương tích lũy trong tháng (Tích lũy: %sđ, Hạn mức tối đa: %sđ).', $employee->full_name, number_format($earnedWages), number_format($limit))]);
                    }
                }
            }
        }

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
