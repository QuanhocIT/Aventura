<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ViolationReport;
use App\Services\QuotaService;
use App\Services\SalaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ViolationReportController extends Controller
{
    /**
     * Hiển thị danh sách vé tố cáo sai phạm nội bộ.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('view_violations') || $user->can('report_violations'), 403);

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'hr_full')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'hr_full',
                'feature_label' => 'Báo cáo Vi phạm Nội bộ',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $user->restaurant_id;

        // 1. Lấy danh sách vé tố cáo, map ẩn danh để bảo vệ người tố giác
        $query = ViolationReport::where('restaurant_id', $restaurantId);

        // Nhân viên thường chỉ thấy: đơn MÌNH tố cáo + biên bản lập với CHÍNH MÌNH
        // (để có thể kháng cáo/tự bảo vệ).
        $myEmployeeId = $user->employee?->id;
        if (! $user->can('view_violations')) {
            $query->where(function ($q) use ($user, $myEmployeeId) {
                $q->where('reported_by', $user->id);
                if ($myEmployeeId) {
                    $q->orWhere('employee_id', $myEmployeeId);
                }
            });
        }

        // Biên bản vi phạm chỉ tăng chứ không bao giờ giảm, nên ->get() sẽ đổ
        // toàn bộ lịch sử của nhà hàng ra một trang.
        $reportsPage = $query->with(['employee', 'reportedBy', 'appealReviewedBy'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $reportsModels = $reportsPage->getCollection();

        $canManage = $user->can('manage_violations');
        $reports = $reportsModels->map(function ($r) use ($user, $canManage) {
            $isOffender = $r->employee?->user_id === $user->id;

            return [
                'id' => $r->id,
                'employee_id' => $r->employee_id,
                'employee_name' => $r->employee?->full_name ?? 'Không xác định',
                'employee_code' => $r->employee?->employee_code ?? 'N/A',
                'job_title' => $r->employee?->job_title ?? 'N/A',
                'reported_by_name' => $r->is_anonymous ? 'Ẩn danh (Bảo vệ AI)' : ($r->reportedBy?->name ?? 'Không xác định'),
                'violation_type' => $r->violation_type,
                'severity' => $r->severity,
                'description' => $r->description,
                'penalty_amount' => (float) $r->penalty_amount,
                'occurred_at' => $r->occurred_at->format('Y-m-d H:i:s'),
                'occurred_at_display' => $r->occurred_at->format('d/m/Y H:i'),
                'status' => $r->status,
                'is_anonymous' => (bool) $r->is_anonymous,
                'created_at' => $r->created_at->format('d/m/Y H:i'),
                // Kháng cáo
                'appeal_status' => $r->appeal_status,
                'appeal_reason' => $r->appeal_reason,
                'appealed_at_display' => $r->appealed_at?->format('d/m/Y H:i'),
                'appeal_review_note' => $r->appeal_review_note,
                'appeal_reviewed_by_name' => $r->appealReviewedBy?->name,
                'appeal_reviewed_at_display' => $r->appeal_reviewed_at?->format('d/m/Y H:i'),
                'is_offender' => $isOffender,           // người đang xem là nhân viên bị lập biên bản
                'can_appeal' => $isOffender && $r->isAppealable(),
                'can_review_appeal' => $canManage && $r->appeal_status === 'pending',
            ];
        });

        // 2. Lấy danh sách nhân sự đang làm việc tại nhà hàng để Owner/Manager hoặc Nhân viên tố cáo chọn
        $employees = Employee::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'job_title' => $e->job_title,
                'employee_code' => $e->employee_code,
            ]);

        return Inertia::render('violations/Index', [
            'reports' => $reports->values()->all(),
            'pagination' => [
                'links' => $reportsPage->linkCollection()->toArray(),
                'current_page' => $reportsPage->currentPage(),
                'last_page' => $reportsPage->lastPage(),
                'total' => $reportsPage->total(),
            ],
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
        // Biên bản vi phạm kích hoạt SalaryRecalculationObserver, tức là ghi
        // nhận một biên bản sẽ TÍNH LẠI LƯƠNG của nhân viên bị nêu tên. Thiếu
        // gate ở đây đồng nghĩa bất kỳ tài khoản nào cũng trừ lương người khác
        // được — index() đã yêu cầu quyền này từ đầu, chỉ store() bị bỏ sót.
        abort_unless($user->can('report_violations'), 403, 'Bạn không có quyền lập biên bản vi phạm.');

        $restaurantId = $user->restaurant_id;
        $branchId = $user->branch_id;

        $data = $request->validate([
            'employee_id' => ['required', "exists:employees,id,restaurant_id,{$restaurantId}"],
            'violation_type' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:2000'],
            'is_anonymous' => ['required', 'boolean'],
            'occurred_at' => ['required', 'date'],
        ]);

        // Không ai được tự lập biên bản cho chính mình để thao túng lương của
        // bản thân (appeal() đã có kiểm tra cùng dạng theo chiều ngược lại).
        $target = Employee::where('restaurant_id', $restaurantId)
            ->whereKey($data['employee_id'])
            ->first();

        abort_if(
            $target !== null && (int) $target->user_id === (int) $user->id && ! $user->can('manage_violations'),
            403,
            'Bạn không thể tự lập biên bản vi phạm cho chính mình.',
        );

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
            'is_anonymous' => (bool) $report->is_anonymous,
        ]);

        return back()->with('success', 'Gửi tố cáo nội bộ thành công! Ban quản trị sẽ bảo mật tuyệt đối danh tính và xem xét vụ việc.');
    }

    /**
     * Phê duyệt kỷ luật sai phạm nội bộ & tự động cấn trừ lương (Chỉ dành cho Owner).
     */
    public function resolve(Request $request, ViolationReport $report): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_violations'), 403);
        abort_if($report->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'severity' => ['required', 'in:low,medium,high,critical'],
            'penalty_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:resolved,dismissed,investigating'],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldValues = [
            'status' => $report->status,
            'severity' => $report->severity,
            'penalty_amount' => (float) $report->penalty_amount,
        ];

        DB::transaction(function () use ($report, $data) {
            $penaltyAmount = (float) $data['penalty_amount'];

            // Tự động tích hợp cấn trừ lương khi xác định vi phạmresolved và có phạt tiền
            if ($data['status'] === 'resolved' && $penaltyAmount > 0) {
                $employee = $report->employee;
                if ($employee) {
                    $salaryService = app(SalaryService::class);
                    // 1. Tìm hoặc khởi tạo bản ghi Salary Nháp của nhân viên vi phạm dùng Carbon thống nhất
                    $salary = $salaryService->getOrCreateDraft($report->restaurant_id, $employee, now()->toDateString());

                    // 2. Tạo Salary Adjustment loại violation cấn trừ phạt lương
                    SalaryAdjustment::create([
                        'salary_id' => $salary->id,
                        'restaurant_id' => $report->restaurant_id,
                        'employee_id' => $employee->id,
                        'type' => 'violation',
                        'amount' => $penaltyAmount,
                        'reason' => "Cấn trừ phạt sai phạm nội bộ (Tố cáo ID #{$report->id}: {$report->violation_type})",
                        'reference_id' => $report->id,
                        'reference_type' => ViolationReport::class,
                    ]);

                    // 3. Tính toán lại tổng khấu trừ và thực nhận Net Salary cho bảng lương
                    $salaryService->recalculate($salary);
                }
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
            'penalty_amount' => (float) $report->penalty_amount,
            'resolved_by' => $user->name,
            'resolution_notes' => $data['resolution_notes'] ?? null,
        ]);

        return back()->with('success', 'Đã phê duyệt phương án xử lý kỷ luật và tự động cập nhật cấn trừ lương nhân viên!');
    }

    /**
     * Nhân viên bị lập biên bản gửi đơn KHÁNG CÁO (chỉ chính chủ, trong cửa sổ cho phép).
     */
    public function appeal(Request $request, ViolationReport $report): RedirectResponse
    {
        $user = $request->user();
        abort_if($report->restaurant_id !== $user->restaurant_id, 403);
        // Chỉ đúng nhân viên bị lập biên bản mới được kháng cáo.
        abort_unless($report->employee?->user_id === $user->id, 403, 'Bạn chỉ được kháng cáo biên bản của chính mình.');

        if (! $report->isAppealable()) {
            return back()->with('error', 'Biên bản này không thể kháng cáo (chưa xử lý, không có phạt tiền, đã kháng cáo, hoặc đã quá hạn '.ViolationReport::APPEAL_WINDOW_DAYS.' ngày).');
        }

        $data = $request->validate([
            'appeal_reason' => ['required', 'string', 'min:10', 'max:2000'],
            'appeal_evidence' => ['nullable', 'image', 'max:2048'],
        ]);

        // Ảnh bằng chứng lưu disk 'local' (private) — không để lộ qua /storage.
        $evidencePath = null;
        if ($request->hasFile('appeal_evidence')) {
            $evidencePath = $request->file('appeal_evidence')->store('violation_appeals', 'local');
        }

        try {
            $report->update([
                'appeal_status' => 'pending',
                'appeal_reason' => $data['appeal_reason'],
                'appeal_evidence_path' => $evidencePath,
                'appealed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            if ($evidencePath) {
                Storage::disk('local')->delete($evidencePath);
            }

            return back()->with('error', 'Không thể kháng cáo: '.$e->getMessage());
        }

        AuditLog::log('violation_appealed', 'updated', $report, null, [
            'appeal_status' => 'pending',
            'by' => $user->name,
        ]);

        return back()->with('success', 'Đã gửi đơn kháng cáo lên Chủ nhà hàng. Vui lòng chờ xem xét.');
    }

    /**
     * Chủ nhà hàng xét đơn kháng cáo. Chấp nhận → waive khoản cấn trừ lương (giữ audit);
     * Bác → giữ nguyên phạt. Không cho tự xử khi bảng lương kỳ đó đã khóa.
     */
    public function reviewAppeal(Request $request, ViolationReport $report): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_violations'), 403);
        abort_if($report->restaurant_id !== $user->restaurant_id, 403);

        if ($report->appeal_status !== 'pending') {
            return back()->with('error', 'Đơn kháng cáo này không ở trạng thái chờ xử lý.');
        }

        $data = $request->validate([
            'decision' => ['required', 'in:accepted,rejected'],
            'appeal_review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($report, $data, $user) {
                if ($data['decision'] === 'accepted') {
                    // Waive tất cả khoản cấn trừ lương gắn với biên bản này (giữ nguyên bản ghi).
                    $adjustments = SalaryAdjustment::withoutGlobalScopes()
                        ->where('reference_type', ViolationReport::class)
                        ->where('reference_id', $report->id)
                        ->where('type', 'violation')
                        ->where('status', 'applied')
                        ->get();

                    $salaryService = app(SalaryService::class);
                    foreach ($adjustments as $adj) {
                        $adj->update(['status' => 'waived']);
                        $salary = Salary::withoutGlobalScopes()->find($adj->salary_id);
                        if ($salary) {
                            $salaryService->recalculate($salary);
                        }
                    }
                }

                $report->update([
                    'appeal_status' => $data['decision'],
                    'appeal_reviewed_by' => $user->id,
                    'appeal_review_note' => $data['appeal_review_note'] ?? null,
                    'appeal_reviewed_at' => now(),
                    // Kháng cáo thành công → biên bản coi như đã hủy phạt.
                    'status' => $data['decision'] === 'accepted' ? 'dismissed' : $report->status,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể xử lý kháng cáo: '.$e->getMessage());
        }

        AuditLog::log('violation_appeal_reviewed', 'updated', $report, null, [
            'appeal_status' => $report->appeal_status,
            'reviewed_by' => $user->name,
            'note' => $data['appeal_review_note'] ?? null,
        ]);

        $msg = $data['decision'] === 'accepted'
            ? 'Đã CHẤP NHẬN kháng cáo — hoàn lại khoản cấn trừ lương cho nhân viên.'
            : 'Đã BÁC kháng cáo — giữ nguyên hình thức xử lý.';

        return back()->with('success', $msg);
    }
}
