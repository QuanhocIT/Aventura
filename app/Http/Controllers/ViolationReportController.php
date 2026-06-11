<?php

namespace App\Http\Controllers;

use App\Models\ViolationReport;
use App\Models\Employee;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\AuditLog;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class ViolationReportController extends Controller
{
    /**
     * Hiển thị danh sách vé tố cáo sai phạm nội bộ (Chỉ Owner & Manager).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasRole('owner') || $user->hasRole('manager'), 403);
        $restaurantId = $user->restaurant_id;

        // 1. Lấy danh sách vé tố cáo, map ẩn danh để bảo vệ người tố giác
        $reportsModels = ViolationReport::where('restaurant_id', $restaurantId)
            ->with(['employee', 'reportedBy'])
            ->latest()
            ->get();

        $reports = $reportsModels->map(function ($r) {
            return [
                'id' => $r->id,
                'employee_id' => $r->employee_id,
                'employee_name' => $r->employee?->full_name ?? 'Không xác định',
                'employee_code' => $r->employee?->employee_code ?? 'N/A',
                'job_title' => $r->employee?->job_title ?? 'N/A',
                'reported_by_name' => 'Ẩn danh (Bảo mật hệ thống)',
                'violation_type' => $r->violation_type,
                'severity' => $r->severity,
                'description' => $r->description,
                'penalty_amount' => (float) $r->penalty_amount,
                'occurred_at' => $r->occurred_at->format('Y-m-d H:i:s'),
                'occurred_at_display' => $r->occurred_at->format('d/m/Y H:i'),
                'status' => $r->status,
                'is_anonymous' => (bool) $r->is_anonymous,
                'created_at' => $r->created_at->format('d/m/Y H:i'),
            ];
        });

        // 2. Lấy danh sách nhân sự đang làm việc tại nhà hàng để Owner/Manager hoặc Nhân viên tố cáo chọn
        $employees = Employee::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'job_title' => $e->job_title,
                'employee_code' => $e->employee_code,
            ]);

        return Inertia::render('violations/Index', [
            'reports' => $reports,
            'employees' => $employees,
            'currentUserRole' => $user->roles->first()?->name ?? 'staff',
        ]);
    }

    /**
     * Gửi đơn tố cáo nội bộ (Hòm thư tố cáo ẩn danh an toàn).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $branchId = $user->branch_id;

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'violation_type' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:2000'],
            'is_anonymous' => ['required', 'boolean'],
            'occurred_at' => ['required', 'date'],
        ]);

        $report = ViolationReport::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'employee_id' => $data['employee_id'],
            'reported_by' => $user->id,
            'is_anonymous' => $data['is_anonymous'],
            'violation_type' => $data['violation_type'],
            'severity' => 'low', // Mặc định ban đầu
            'description' => $data['description'],
            'penalty_amount' => 0,
            'occurred_at' => $data['occurred_at'],
            'status' => 'open',
        ]);

        // Ghi Audit Log cho hành vi tạo tố cáo
        AuditLog::log('violation_reported', 'created', $report, null, [
            'violation_type' => $report->violation_type,
            'is_anonymous' => (bool)$report->is_anonymous,
        ]);

        return back()->with('success', 'Gửi tố cáo nội bộ thành công! Ban quản trị sẽ bảo mật tuyệt đối danh tính và xem xét vụ việc.');
    }

    /**
     * Phê duyệt kỷ luật sai phạm nội bộ & tự động cấn trừ lương (Chỉ dành cho Owner).
     */
    public function resolve(Request $request, ViolationReport $report): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('owner'), 403);
        abort_if($report->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'severity' => ['required', 'in:low,medium,high,critical'],
            'penalty_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:resolved,dismissed'],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldValues = [
            'status' => $report->status,
            'severity' => $report->severity,
            'penalty_amount' => (float)$report->penalty_amount,
        ];

        DB::transaction(function () use ($report, $data) {
            $penaltyAmount = (float)$data['penalty_amount'];

            // Tự động tích hợp cấn trừ lương khi xác định vi phạmresolved và có phạt tiền
            if ($data['status'] === 'resolved' && $penaltyAmount > 0) {
                $employeeId = $report->employee_id;
                $restaurantId = $report->restaurant_id;

                // Lấy kỳ lương tháng hiện tại (Đầu tháng -> Cuối tháng xảy ra vi phạm)
                $startOfMonth = now()->startOfMonth()->toDateString();
                $endOfMonth = now()->endOfMonth()->toDateString();

                // 1. Tìm hoặc khởi tạo bản ghi Salary Nháp của nhân viên vi phạm
                $salary = Salary::firstOrCreate([
                    'restaurant_id' => $restaurantId,
                    'employee_id' => $employeeId,
                    'pay_period_start' => $startOfMonth,
                    'pay_period_end' => $endOfMonth,
                ], [
                    'branch_id' => $report->branch_id,
                    'base_salary' => $report->employee->base_salary ?? 0,
                    'bonus_amount' => 0,
                    'deduction_amount' => 0,
                    'net_salary' => $report->employee->base_salary ?? 0,
                    'status' => 'draft',
                ]);

                // 2. Tạo Salary Adjustment loại violation cấn trừ phạt lương
                SalaryAdjustment::create([
                    'salary_id' => $salary->id,
                    'restaurant_id' => $restaurantId,
                    'employee_id' => $employeeId,
                    'type' => 'violation',
                    'amount' => $penaltyAmount,
                    'reason' => "Cấn trừ phạt sai phạm nội bộ (Tố cáo ID #{$report->id}: {$report->violation_type})",
                    'reference_id' => $report->id,
                    'reference_type' => ViolationReport::class,
                ]);

                // 3. Tính toán lại tổng khấu trừ và thực nhận Net Salary cho bảng lương
                $totalDeductions = SalaryAdjustment::where('salary_id', $salary->id)
                    ->whereIn('type', ['penalty', 'cash_shortage', 'inventory_loss', 'violation'])
                    ->sum('amount');

                $totalBonuses = SalaryAdjustment::where('salary_id', $salary->id)
                    ->where('type', 'bonus')
                    ->sum('amount');

                $netSalary = max(0.0, (float)$salary->base_salary + $totalBonuses - $totalDeductions);

                $salary->update([
                    'deduction_amount' => $totalDeductions,
                    'bonus_amount' => $totalBonuses,
                    'net_salary' => $netSalary,
                ]);
            }

            // Cập nhật trạng thái vé tố cáo
            $report->update([
                'status' => $data['status'],
                'severity' => $data['severity'],
                'penalty_amount' => $penaltyAmount,
            ]);
        });

        // Ghi Audit Log cho hành vi xử lý
        AuditLog::log('violation_resolved', 'updated', $report, $oldValues, [
            'status' => $report->status,
            'severity' => $report->severity,
            'penalty_amount' => (float)$report->penalty_amount,
            'resolved_by' => $user->name,
            'resolution_notes' => $data['resolution_notes'] ?? null,
        ]);

        return back()->with('success', 'Đã phê duyệt phương án xử lý kỷ luật và tự động cập nhật cấn trừ lương nhân viên!');
    }
}
