<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ViolationReport;
use App\Services\ApprovalService;
use App\Services\FraudDetectionService;
use App\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FraudController extends Controller
{
    public function __construct(
        private SalaryService $salaryService,
        private ApprovalService $approvalService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view_fraud_detection'), 403);

        $user         = $request->user();
        $restaurantId = $user->restaurant_id;
        $period       = $request->input('period', today()->format('Y-m'));
        $activeTab    = $request->input('tab', 'ai');

        [$year, $month] = explode('-', $period);
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $end   = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        // Clamp to today to avoid querying future data
        if ($end > today()->toDateString()) {
            $end = today()->toDateString();
        }

        $service = new FraudDetectionService($restaurantId, $start, $end);

        // Lazy-tab: only run the active tab's heavy detail query
        $data = match ($activeTab) {
            'ai'       => $service->detectAiFraudAlerts(),
            'audit'    => $service->getAuditLogs(),
            'discount' => $service->detectDiscountAnomalies(),
            'cancel'   => $service->detectSuspiciousCancellations(),
            'waste'    => $service->detectInventoryWasteSpikes(),
            'revenue'  => $service->detectRevenueDiscrepancies(),
            default    => $service->detectCashShortfalls(),
        };

        return Inertia::render('fraud/Index', [
            'period'    => $period,
            'activeTab' => $activeTab,
            'summary'   => $service->getSummary(),
            'data'      => $data,
            'canAct'    => $user->can('approve_requests'),
            'dateRange' => ['start' => $start, 'end' => $end],
        ]);
    }

    public function createViolation(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('report_violations'), 403);

        $data = $request->validate([
            'employee_id'     => ['required', 'integer', 'exists:employees,id'],
            'violation_type'  => ['required', 'string', 'max:100'],
            'severity'        => ['required', 'in:low,medium,high,critical'],
            'description'     => ['required', 'string', 'max:2000'],
            'penalty_amount'  => ['nullable', 'numeric', 'min:0'],
            'occurred_at'     => ['required', 'date'],
            'apply_deduction' => ['nullable', 'boolean'],
        ]);

        $user         = $request->user();
        $restaurantId = $user->restaurant_id;

        // Always create ViolationReport directly (recording a fact — no approval needed)
        $violation = ViolationReport::create([
            'restaurant_id'  => $restaurantId,
            'employee_id'    => $data['employee_id'],
            'reported_by'    => $user->id,
            'violation_type' => $data['violation_type'],
            'severity'       => $data['severity'],
            'description'    => $data['description'],
            'penalty_amount' => $data['penalty_amount'] ?? 0,
            'occurred_at'    => $data['occurred_at'],
            'status'         => 'open',
        ]);

        // Salary deduction — owner direct, manager via ApprovalService
        if ($request->boolean('apply_deduction') && ($data['penalty_amount'] ?? 0) > 0) {
            $employee = Employee::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->findOrFail($data['employee_id']);

            if ($user->can('approve_requests')) {
                $salary = $this->salaryService->getOrCreateDraft(
                    $restaurantId,
                    $employee,
                    Carbon::parse($data['occurred_at'])->toDateString()
                );
                $this->salaryService->addAdjustment($salary, [
                    'employee_id'    => $employee->id,
                    'type'           => 'violation',
                    'amount'         => (float) $data['penalty_amount'],
                    'reason'         => "Vi phạm: {$data['violation_type']} — {$data['description']}",
                    'reference_id'   => $violation->id,
                    'reference_type' => ViolationReport::class,
                ]);
            } else {
                // Manager: route through ApprovalService using existing 'salary_adjustment' op type
                $salary = $this->salaryService->getOrCreateDraft(
                    $restaurantId,
                    $employee,
                    Carbon::parse($data['occurred_at'])->toDateString()
                );
                $this->approvalService->submitRequest('salary_adjustment', [
                    'salary_id' => $salary->id,
                    'type'      => 'violation',
                    'amount'    => (float) $data['penalty_amount'],
                    'reason'    => "Vi phạm: {$data['violation_type']} — {$data['description']}",
                ], $user);
            }
        }

        $msg = $request->boolean('apply_deduction') && ! $user->can('approve_requests')
            ? 'Đã ghi vi phạm. Yêu cầu khấu trừ lương đã gửi chủ nhà hàng để phê duyệt.'
            : 'Đã ghi nhận vi phạm thành công.';

        return back()->with('success', $msg);
    }
}
