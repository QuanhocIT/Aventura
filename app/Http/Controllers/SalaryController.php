<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Notifications\SalaryDisputeNotification;
use App\Notifications\SalaryReadyNotification;
use App\Services\ApprovalAuthorityService;
use App\Services\ApprovalService;
use App\Services\CashPostingService;
use App\Services\FinancialPostingService;
use App\Services\QuotaService;
use App\Services\SalaryService;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalaryController extends Controller
{
    public function __construct(
        private SalaryService $salaryService,
        private ApprovalService $approvalService,
        private TenantContext $tenantContext,
        private ApprovalAuthorityService $authorityService,
        private CashPostingService $cashPostingService,
        private FinancialPostingService $financialPostingService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('manage_salary'), 403);

        $user = $request->user();

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'hr_full')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'hr_full',
                'feature_label' => 'Quản lý Lương & Nhân sự',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $user->restaurant_id;

        $period = $request->validate(['period' => ['nullable', 'date_format:Y-m']])['period'] ?? today()->format('Y-m');
        [$year, $month] = explode('-', $period);

        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $periodEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        // TRƯỚC ĐÂY KHÔNG LỌC CHI NHÁNH: branch_id chỉ được lấy ra để HIỂN THỊ
        // (dòng 'branch_id' => $s->employee?->branch_id bên dưới), không dùng để
        // lọc — owner chuyển chi nhánh nhưng vẫn thấy bảng lương MỌI chi nhánh.
        $branchId = $this->tenantContext->activeBranchId();

        $salaries = Salary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('pay_period_start', $periodStart)
            ->where('pay_period_end', $periodEnd)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['employee:id,employee_code,full_name,job_title,employment_type,compensation_type,pay_rate,base_salary,branch_id', 'employee.trustScore', 'adjustments', 'approvedBy:id,name'])
            ->get()
            ->map(function (Salary $s) {
                $breakdown = $this->salaryService->getSalaryCalculationDetails($s);

                return [
                    'id' => $s->id,
                    'employee_id' => $s->employee_id,
                    'employee_code' => $s->employee?->employee_code ?? 'NV-'.$s->employee_id,
                    'employee_name' => $s->employee?->full_name ?? '—',
                    'job_title' => $s->employee?->job_title ?? '',
                    'employment_type' => $s->employee?->employment_type ?? 'full-time',
                    'compensation_type' => $s->employee?->compensation_type ?? 'fixed',
                    'pay_rate' => (float) ($s->employee?->pay_rate ?? 0),
                    'contract_base_salary' => (float) ($s->employee?->base_salary ?? 0),
                    'trust_score' => $s->employee?->trustScore?->score ?? 100,
                    'branch_id' => $s->employee?->branch_id,
                    'base_salary' => (float) $s->base_salary,
                    'bonus_amount' => (float) $s->bonus_amount,
                    'overtime_amount' => (float) ($s->overtime_amount ?? 0),
                    'deduction_amount' => (float) $s->deduction_amount,
                    'net_salary' => (float) $s->net_salary,
                    'status' => $s->status,
                    'paid_at' => $s->paid_at?->format('d/m/Y H:i'),
                    'approved_by_name' => $s->approvedBy?->name,
                    'created_at' => $s->created_at?->format('d/m/Y H:i'),
                    'breakdown' => $breakdown,
                    'adjustments' => $s->adjustments->map(fn (SalaryAdjustment $a) => [
                        'id' => $a->id,
                        'type' => $a->type,
                        'amount' => (float) $a->amount,
                        'reason' => $a->reason,
                        'status' => $a->status,
                        'dispute_reason' => $a->dispute_reason,
                    ])->values(),
                ];
            })
            ->values();

        $totals = [
            'total_payroll' => (float) $salaries->sum('net_salary'),
            'total_deductions' => (float) $salaries->sum('deduction_amount'),
            'total_bonuses' => (float) $salaries->sum('bonus_amount'),
            'headcount' => $salaries->count(),
        ];

        // Lấy danh sách chi nhánh phục vụ bộ lọc ở Frontend
        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->when(! $user->canViewAllBranches(), fn ($q) => $q->where('id', $branchId))
            ->get(['id', 'name']);
        $eligibleEmployees = Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();
        $statusCounts = [
            'draft' => $salaries->where('status', 'draft')->count(),
            'approved' => $salaries->where('status', 'approved')->count(),
            'paid' => $salaries->where('status', 'paid')->count(),
        ];

        return Inertia::render('salaries/Index', [
            'salaries' => $salaries,
            'totals' => $totals,
            'period' => $period,
            'branches' => $branches,
            'canApprove' => $user->isOwner() || $user->isSuperAdmin(),
            'generation' => [
                'eligible_employees' => $eligibleEmployees,
                'salary_rows' => $salaries->count(),
                'status_counts' => $statusCounts,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
        ]);
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->can('manage_salary')
                && ($request->user()->isOwner() || $request->user()->isSuperAdmin()),
            403,
            'Chỉ Chủ doanh nghiệp mới được duyệt bảng lương.'
        );

        $data = $request->validate([
            'salary_ids' => ['required', 'array', 'min:1'],
            'salary_ids.*' => ['integer', TenantRule::exists('salaries')],
        ]);

        $approvedCount = $this->salaryService->bulkApprove(
            $request->user()->restaurant_id,
            $data['salary_ids'],
            $request->user()->id,
            $this->tenantContext->activeBranchId(),
        );

        return back()->with('success', "Đã phê duyệt thành công {$approvedCount} bảng lương.");
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage_salary'), 403);

        $period = $request->validate(['period' => ['nullable', 'date_format:Y-m']])['period'] ?? today()->format('Y-m');
        $result = $this->salaryService->generateMonthlyDrafts(
            $request->user()->restaurant_id,
            $period,
            $this->tenantContext->activeBranchId(),
        );

        $msg = "Đã tạo {$result['created']} bảng lương mới, tính lại {$result['updated']} bản nháp";
        if (($result['locked'] ?? 0) > 0) {
            $msg .= ", giữ nguyên {$result['locked']} bảng đã duyệt/đã trả";
        }
        $msg .= '.';

        return back()->with('success', $msg);
    }

    public function approve(Request $request, Salary $salary): RedirectResponse
    {
        abort_unless(
            $request->user()->can('manage_salary')
                && ($request->user()->isOwner() || $request->user()->isSuperAdmin()),
            403,
            'Chỉ Chủ doanh nghiệp mới được duyệt bảng lương.'
        );
        $this->authorizeSalaryBranch($request->user(), $salary);
        // Không ai được duyệt bảng lương của chính mình — kể cả Chủ, vì duyệt
        // xong là kỳ lương bị khóa, không sửa lại được.
        abort_if(
            $this->authorityService->isSelf($request->user(), $salary->employee_id),
            403,
            'Bạn không thể duyệt bảng lương của chính mình.',
        );
        abort_unless($salary->status === 'draft', 422, 'Chỉ bảng lương bản nháp mới được duyệt.');

        $salary = $this->salaryService->refreshDraftSalary($salary);

        $salary->update([
            'status' => 'approved',
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
        // Đánh dấu đã trả lương là xác nhận một khoản chi — thuộc nhóm việc
        // không giao cho Quản lý chi nhánh.
        abort_unless(
            $request->user()->can('manage_salary')
                && ($request->user()->isOwner() || $request->user()->isSuperAdmin()),
            403,
            'Chỉ Chủ doanh nghiệp mới được xác nhận đã chi trả lương.',
        );
        $this->authorizeSalaryBranch($request->user(), $salary);
        abort_if(
            $this->authorityService->isSelf($request->user(), $salary->employee_id),
            403,
            'Bạn không thể xác nhận chi trả lương cho chính mình.',
        );
        abort_unless($salary->status === 'approved', 422);

        $data = $request->validate([
            'payment_method' => ['nullable', 'in:cash,bank_transfer'],
            'payment_reference' => ['nullable', 'string', 'max:150'],
        ]);
        $paymentMethod = $data['payment_method'] ?? 'bank_transfer';

        DB::transaction(function () use ($salary, $request, $paymentMethod, $data): void {
            $lockedSalary = Salary::withoutGlobalScopes()->lockForUpdate()->findOrFail($salary->id);
            abort_unless($lockedSalary->status === 'approved', 422, 'Bảng lương đã được xử lý.');
            $amount = (float) $lockedSalary->net_salary;
            $idempotencyKey = 'salary-payment:'.$lockedSalary->id;

            if ($paymentMethod === 'cash') {
                $this->cashPostingService->record([
                    'restaurant_id' => $lockedSalary->restaurant_id,
                    'branch_id' => $lockedSalary->branch_id,
                    'type' => 'out',
                    'amount' => $amount,
                    'source' => 'payroll',
                    'reference_id' => $lockedSalary->id,
                    'reference_type' => Salary::class,
                    'idempotency_key' => $idempotencyKey,
                    'debit_account' => '6221',
                    'credit_account' => '1111',
                    'journal_source_type' => Salary::class,
                    'journal_source_id' => $lockedSalary->id,
                    'notes' => 'Thanh toán lương #'.$lockedSalary->id,
                    'created_by' => $request->user()->id,
                    'occurred_at' => now(),
                ]);
            } else {
                $this->financialPostingService->post([
                    'restaurant_id' => $lockedSalary->restaurant_id,
                    'branch_id' => $lockedSalary->branch_id,
                    'entry_date' => today(),
                    'source_type' => Salary::class,
                    'source_id' => $lockedSalary->id,
                    'idempotency_key' => $idempotencyKey,
                    'description' => 'Thanh toán lương #'.$lockedSalary->id,
                    'created_by' => $request->user()->id,
                    'posted_by' => $request->user()->id,
                    'metadata' => ['payment_method' => $paymentMethod, 'payment_reference' => $data['payment_reference'] ?? null],
                    'lines' => [
                        ['account' => '6221', 'debit' => $amount, 'credit' => 0],
                        ['account' => '1121', 'debit' => 0, 'credit' => $amount],
                    ],
                ]);
            }

            SalaryPayment::firstOrCreate(
                ['restaurant_id' => $lockedSalary->restaurant_id, 'idempotency_key' => $idempotencyKey],
                [
                    'salary_id' => $lockedSalary->id,
                    'branch_id' => $lockedSalary->branch_id,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'paid_at' => now(),
                    'created_by' => $request->user()->id,
                ],
            );

            $lockedSalary->update(['status' => 'paid', 'paid_at' => now()]);
            OvertimeRequest::withoutGlobalScopes()
                ->where('salary_id', $lockedSalary->id)
                ->where('workflow_status', 'included')
                ->update([
                    'workflow_status' => 'paid',
                    'payroll_status' => 'paid',
                    'last_action_at' => now(),
                    'last_action_by' => $request->user()->id,
                ]);
        });
        $salary->refresh();

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
        $this->authorizeSalaryBranch($request->user(), $salary);
        abort_unless($salary->status === 'draft', 422, 'Chỉ bảng lương bản nháp mới được điều chỉnh.');

        $data = $request->validate([
            'type' => ['required', 'in:bonus,penalty,violation,advance'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($salary, $data, $request) {
                // Lock the salary record
                $lockedSalary = Salary::where('id', $salary->id)->lockForUpdate()->firstOrFail();

                if ($data['type'] === 'advance') {
                    $employee = $lockedSalary->employee;
                    if (! $employee) {
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

                    $pendingAdvanceAmount = (float) ApprovalRequest::forRestaurant($request->user()->restaurant_id)
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

                if (! $this->authorityService->canActDirectly($request->user(), 'salary_adjustment', $lockedSalary->branch_id)) {
                    $this->approvalService->submitRequest('salary_adjustment', array_merge($data, [
                        'salary_id' => $lockedSalary->id,
                        'employee_id' => $lockedSalary->employee_id,
                    ]), $request->user());
                } else {
                    $this->salaryService->addAdjustment($lockedSalary, array_merge($data, [
                        'employee_id' => $lockedSalary->employee_id,
                        'status' => 'applied',
                    ]));
                }
            });
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', $this->authorityService->canActDirectly($request->user(), 'salary_adjustment')
            ? 'Đã thêm điều chỉnh lương.'
            : 'Yêu cầu điều chỉnh lương đã gửi Chủ nhà hàng để phê duyệt.');
    }

    /**
     * Áp dụng cấn trừ/thưởng phạt hàng loạt cho nhiều nhân sự được chọn.
     */
    public function storeBulkAdjustment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage_salary'), 403);

        $data = $request->validate([
            'salary_ids' => ['required', 'array'],
            'salary_ids.*' => ['required', TenantRule::exists('salaries')],
            'type' => ['required', 'in:bonus,penalty,violation,advance'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $requestedIds = collect($data['salary_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $draftCount = Salary::withoutGlobalScopes()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereIn('id', $requestedIds)
            ->where('status', 'draft')
            ->when($this->tenantContext->activeBranchId(), fn ($q) => $q->where('branch_id', $this->tenantContext->activeBranchId()))
            ->count();
        if ($draftCount !== $requestedIds->count()) {
            throw ValidationException::withMessages(['salary_ids' => 'Chỉ bảng lương bản nháp trong phạm vi hiện tại mới được điều chỉnh.']);
        }

        // Eager-load employee: trước đây $salary->employee trong vòng lặp bên dưới
        // bắn 1 query/bảng lương (N+1) khi điều chỉnh lương hàng loạt.
        $salaries = Salary::withoutGlobalScopes()
            ->with('employee')
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereIn('id', $data['salary_ids'])
            ->where('status', 'draft')
            ->when($this->tenantContext->activeBranchId(), fn ($q) => $q->where('branch_id', $this->tenantContext->activeBranchId()))
            ->get();

        $canApprove = $this->authorityService->canActDirectly($request->user(), 'salary_adjustment');

        // Check advances limits first
        if ($data['type'] === 'advance') {
            $salaryIds = $salaries->pluck('id');

            // Gộp 2 truy vấn sum() vốn chạy trong vòng lặp thành 2 truy vấn tổng.
            $existingAdvances = SalaryAdjustment::withoutGlobalScopes()
                ->whereIn('salary_id', $salaryIds)
                ->where('type', 'advance')
                ->where('status', 'applied')
                ->groupBy('salary_id')
                ->selectRaw('salary_id, SUM(amount) as total')
                ->pluck('total', 'salary_id');

            $pendingAdvances = ApprovalRequest::forRestaurant($request->user()->restaurant_id)
                ->where('status', 'pending')
                ->where('operation_type', 'salary_adjustment')
                ->where('operation_data->type', 'advance')
                ->get(['operation_data'])
                ->groupBy(fn ($r) => (int) data_get($r->operation_data, 'salary_id'))
                ->map(fn ($group) => (float) $group->sum(fn ($r) => (float) data_get($r->operation_data, 'amount')));

            foreach ($salaries as $salary) {
                if ($salary->status === 'paid') {
                    continue;
                }
                $employee = $salary->employee;
                if ($employee) {
                    $salaryMonth = Carbon::parse($salary->pay_period_start);
                    $calculationDate = today()->isSameMonth($salaryMonth) ? today() : $salaryMonth->endOfMonth();
                    $earnedWages = $this->salaryService->calculateEarnedWagesForMonth($employee, $calculationDate->toDateString());

                    $existingAdvanceAmount = (float) ($existingAdvances[$salary->id] ?? 0);
                    $pendingAdvanceAmount = (float) ($pendingAdvances[$salary->id] ?? 0);

                    $limit = $earnedWages * 0.50;
                    if (($existingAdvanceAmount + $pendingAdvanceAmount + $data['amount']) > $limit) {
                        return back()->withErrors(['amount' => sprintf('Yêu cầu tạm ứng cho nhân viên %s vượt quá giới hạn 50%% tiền lương tích lũy trong tháng (Tích lũy: %sđ, Hạn mức tối đa: %sđ).', $employee->full_name, number_format($earnedWages), number_format($limit))]);
                    }
                }
            }
        }

        $count = 0;
        foreach ($salaries as $salary) {
            if ($salary->status === 'paid') {
                continue;
            }

            if (! $canApprove) {
                $this->approvalService->submitRequest('salary_adjustment', [
                    'salary_id' => $salary->id,
                    'type' => $data['type'],
                    'amount' => $data['amount'],
                    'reason' => $data['reason'],
                ], $request->user());
            } else {
                $this->salaryService->addAdjustment($salary, [
                    'employee_id' => $salary->employee_id,
                    'type' => $data['type'],
                    'amount' => $data['amount'],
                    'reason' => $data['reason'],
                    'status' => 'applied',
                ]);
            }
            $count++;
        }

        $msg = $canApprove
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
            'status' => 'disputed',
            'dispute_reason' => $data['dispute_reason'],
        ]);

        // Tính toán lại để đóng băng khoản cấn trừ lương net
        $this->salaryService->recalculate($adjustment->salary);

        // Gửi thông báo đến Owner (Database & Real-time notification)
        $owner = User::where('restaurant_id', $adjustment->restaurant_id)
            ->role('owner')
            ->first();

        if ($owner) {
            $owner->notify(new SalaryDisputeNotification($adjustment, $request->user()));
        }

        return back()->with('success', 'Đã gửi khiếu nại cấn trừ lương thành công. Khoản phạt này đã tạm thời được đóng băng chờ Owner giải quyết.');
    }

    private function authorizeSalaryBranch(User $user, Salary $salary): void
    {
        abort_unless($salary->restaurant_id === $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch($salary->branch_id !== null ? (int) $salary->branch_id : null), 403);

        $activeBranchId = $this->tenantContext->activeBranchId();
        if ($activeBranchId !== null && (int) $salary->branch_id !== $activeBranchId) {
            abort(403, 'Bảng lương không thuộc chi nhánh hiện tại.');
        }
    }
}
