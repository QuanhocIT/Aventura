<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanyPolicy;
use App\Models\OperationalInfringementReport;
use App\Models\OperationalInspectionPlan;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalAuditController extends Controller
{
    /**
     * Bảng điều hành hồ sơ thanh tra: phê duyệt, khắc phục, tái kiểm và truy vết.
     */
    public function page(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = (int) $user->restaurant_id;
        $isOwnerOrSuperAdmin = $user->isOwner() || $user->isSuperAdmin();
        $canManageRemediation = $isOwnerOrSuperAdmin
            || $user->can('operational_audit.manage')
            || $user->can('operational_audit.report');
        $canReinspect = $isOwnerOrSuperAdmin
            || $user->can('operational_audit.reinspect')
            || $user->canCloseInspection();

        $reports = OperationalInfringementReport::where('restaurant_id', $restaurantId)
            ->with([
                'branch:id,name',
                'inspector:id,name,email',
                'policy:id,title,policy_code',
                'offender:id,name,email',
                'approver:id,name',
                'assignee:id,name,email',
                'closer:id,name',
                'reinspector:id,name',
                'inspectionPlan:id,plan_code,title,inspection_type,status',
            ])
            ->orderByDesc('infringement_date')
            ->orderByDesc('id')
            ->get();

        $reportIds = $reports->pluck('id');
        $auditTrail = $reportIds->isEmpty()
            ? []
            : AuditLog::where('restaurant_id', $restaurantId)
                ->where('subject_type', OperationalInfringementReport::class)
                ->whereIn('subject_id', $reportIds)
                ->with('user:id,name')
                ->latest('created_at')
                ->get()
                ->groupBy('subject_id')
                ->map(fn ($logs) => $logs->take(20)->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'event' => $log->event,
                    'user_name' => $log->user?->name ?? 'Hệ thống',
                    'new_values' => $log->new_values,
                    'created_at' => $log->created_at?->format('d/m/Y H:i'),
                ])->values()->all())
                ->all();

        $serializedReports = $reports->map(fn (OperationalInfringementReport $report) => $this->serializeReport($report, $user))->values();
        $today = now()->startOfDay();
        $activeReports = $reports->whereNotIn('status', ['closed', 'rejected']);

        $stats = [
            'total' => $reports->count(),
            'pending_approval' => $reports->where('status', 'pending_owner_approval')->count(),
            'approved' => $reports->where('status', 'approved')->count(),
            'in_remediation' => $reports->where('status', 'remediation_in_progress')->count(),
            'reinspection_pending' => $reports->where('status', 'reinspection_pending')->count(),
            'closed' => $reports->where('status', 'closed')->count(),
            'rejected' => $reports->where('status', 'rejected')->count(),
            'overdue' => $activeReports->filter(fn (OperationalInfringementReport $report) => $report->remediation_deadline?->lt($today) === true
            )->count(),
            'approved_penalty' => (float) $reports
                ->whereIn('status', ['approved', 'remediation_in_progress', 'reinspection_pending', 'closed'])
                ->sum(fn (OperationalInfringementReport $report) => (float) $report->penalty_amount),
        ];

        $plans = OperationalInspectionPlan::where('restaurant_id', $restaurantId)
            ->with([
                'branch:id,name',
                'leadInspector:id,name,email',
                'creator:id,name',
                'completer:id,name',
            ])
            ->withCount([
                'reports',
                'reports as open_reports_count' => fn ($query) => $query->whereNotIn('status', ['closed', 'rejected']),
                'reports as pending_reports_count' => fn ($query) => $query->where('status', 'pending_owner_approval'),
            ])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'planned' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END")
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->get();

        $serializedPlans = $plans->map(fn (OperationalInspectionPlan $plan) => [
            'id' => $plan->id,
            'plan_code' => $plan->plan_code,
            'title' => $plan->title,
            'inspection_type' => $plan->inspection_type,
            'scheduled_date' => $plan->scheduled_date?->toDateString(),
            'due_date' => $plan->due_date?->toDateString(),
            'status' => $plan->status,
            'scope' => $plan->scope,
            'notes' => $plan->notes,
            'started_at' => $plan->started_at?->format('d/m/Y H:i'),
            'completed_at' => $plan->completed_at?->format('d/m/Y H:i'),
            'branch' => $plan->branch ? ['id' => $plan->branch->id, 'name' => $plan->branch->name] : null,
            'lead_inspector' => $plan->leadInspector ? ['id' => $plan->leadInspector->id, 'name' => $plan->leadInspector->name, 'email' => $plan->leadInspector->email] : null,
            'creator' => $plan->creator ? ['id' => $plan->creator->id, 'name' => $plan->creator->name] : null,
            'completer' => $plan->completer ? ['id' => $plan->completer->id, 'name' => $plan->completer->name] : null,
            'reports_count' => (int) $plan->reports_count,
            'open_reports_count' => (int) $plan->open_reports_count,
            'pending_reports_count' => (int) $plan->pending_reports_count,
            'is_overdue' => $plan->due_date?->lt($today) === true && ! in_array($plan->status, ['completed', 'cancelled'], true),
        ])->values();

        $planStats = [
            'planned' => $plans->where('status', 'planned')->count(),
            'in_progress' => $plans->where('status', 'in_progress')->count(),
            'completed' => $plans->where('status', 'completed')->count(),
            'cancelled' => $plans->where('status', 'cancelled')->count(),
            'overdue' => $plans->filter(fn (OperationalInspectionPlan $plan) => $plan->due_date?->lt($today) === true && ! in_array($plan->status, ['completed', 'cancelled'], true)
            )->count(),
        ];

        $policies = CompanyPolicy::where('restaurant_id', $restaurantId)
            ->where('status', 'published')
            ->orderBy('category')
            ->orderBy('title')
            ->get();

        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $branchReports = $reports->groupBy('branch_id');
        $branchPlans = $plans->groupBy('branch_id');
        $branchInsights = $branches->map(function (RestaurantBranch $branch) use ($branchReports, $branchPlans, $today): array {
            $branchRows = $branchReports->get($branch->id, collect());
            $open = $branchRows->whereNotIn('status', ['closed', 'rejected']);
            $overdue = $open->filter(fn (OperationalInfringementReport $report) => $report->remediation_deadline?->lt($today) === true)->count();
            $critical = $open->where('severity_level', 'critical')->count();
            $severe = $open->where('severity_level', 'severe')->count();
            $riskScore = min(100, ($open->count() * 8) + ($overdue * 15) + ($critical * 20) + ($severe * 10));

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'total_reports' => $branchRows->count(),
                'open_reports' => $open->count(),
                'overdue_reports' => $overdue,
                'critical_reports' => $critical,
                'risk_score' => $riskScore,
                'risk_level' => $riskScore >= 70 ? 'critical' : ($riskScore >= 35 ? 'warning' : 'stable'),
                'active_plans' => $branchPlans->get($branch->id, collect())->whereIn('status', ['planned', 'in_progress'])->count(),
            ];
        })->values()->all();

        $trend = collect(range(5, 0))->map(function (int $monthsAgo) use ($reports): array {
            $month = now()->startOfMonth()->subMonths($monthsAgo);
            $monthReports = $reports->filter(fn (OperationalInfringementReport $report) => $report->infringement_date?->isSameMonth($month) === true
            );

            return [
                'label' => $month->format('m/Y'),
                'total' => $monthReports->count(),
                'closed' => $monthReports->where('status', 'closed')->count(),
                'critical' => $monthReports->where('severity_level', 'critical')->count(),
            ];
        })->values()->all();

        $inspectors = User::where('restaurant_id', $restaurantId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['operations_inspector', 'compliance_auditor', 'owner']))
            ->select('id', 'name', 'email', 'branch_id')
            ->orderBy('name')
            ->get();

        $employees = User::where('restaurant_id', $restaurantId)
            ->select('id', 'name', 'email', 'branch_id')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', [
                'supplier',
                'owner',
                'super_admin',
                'operations_inspector',
                'compliance_auditor',
            ]))
            ->orderBy('name')
            ->get();

        return Inertia::render('operations/OperationalAudit', [
            'reports' => $serializedReports,
            'reportStats' => $stats,
            'auditTrail' => $auditTrail,
            'policies' => $policies,
            'branches' => $branches,
            'employees' => $employees,
            'currentUserId' => $user->id,
            'isOwner' => $isOwnerOrSuperAdmin,
            'isInspector' => $isOwnerOrSuperAdmin || $user->can('operational_audit.report'),
            'canManageRemediation' => $canManageRemediation,
            'canReinspect' => $canReinspect,
            'inspectionPlans' => $serializedPlans,
            'planStats' => $planStats,
            'branchInsights' => $branchInsights,
            'trend' => $trend,
            'inspectors' => $inspectors,
            'canManagePlans' => $isOwnerOrSuperAdmin || $user->can('operational_audit.manage') || $user->can('operational_audit.report'),
        ]);
    }

    /** Lập biên bản, luôn bắt đầu ở trạng thái chờ Chủ duyệt. */
    public function storeReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'inspection_plan_id' => ['nullable', TenantRule::exists('operational_inspection_plans')],
            'policy_id' => ['nullable', TenantRule::exists('company_policies')],
            'offender_user_id' => ['nullable', TenantRule::exists('users')],
            'infringement_date' => 'required|date',
            'severity_level' => 'sometimes|in:minor,moderate,severe,critical',
            'description' => 'required|string|min:10|max:4000',
            'proof_photo_url' => 'nullable|string|max:1000',
            'proof_photo' => 'nullable|file|image|max:5120',
            'penalty_amount' => 'required|numeric|min:0',
            'remediation_deadline' => 'nullable|date|after_or_equal:infringement_date',
            'remediation_plan' => 'nullable|string|max:4000',
        ]);

        $user = $request->user();
        $severityLevel = $data['severity_level'] ?? 'moderate';
        if ($severityLevel === 'critical' && blank($data['remediation_plan'] ?? null)) {
            throw ValidationException::withMessages([
                'remediation_plan' => 'Vi phạm mức nghiêm trọng phải có kế hoạch khắc phục ngay khi lập biên bản.',
            ]);
        }

        if (in_array($severityLevel, ['severe', 'critical'], true) && blank($data['remediation_deadline'] ?? null)) {
            throw ValidationException::withMessages([
                'remediation_deadline' => 'Vi phạm mức cao hoặc nghiêm trọng phải có hạn khắc phục để theo dõi SLA.',
            ]);
        }

        $inspectionPlan = null;
        if (! empty($data['inspection_plan_id'])) {
            $inspectionPlan = OperationalInspectionPlan::where('restaurant_id', $user->restaurant_id)
                ->findOrFail($data['inspection_plan_id']);

            if ($inspectionPlan->status === 'cancelled' || $inspectionPlan->status === 'completed') {
                throw ValidationException::withMessages([
                    'inspection_plan_id' => 'Kế hoạch đã kết thúc và không nhận thêm biên bản.',
                ]);
            }

            if ($inspectionPlan->branch_id !== null && (int) $inspectionPlan->branch_id !== (int) $data['branch_id']) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Chi nhánh của biên bản phải khớp kế hoạch kiểm tra.',
                ]);
            }
        }

        if (! empty($data['policy_id'])) {
            $policy = CompanyPolicy::where('restaurant_id', $user->restaurant_id)
                ->where('status', 'published')
                ->findOrFail($data['policy_id']);

            $appliesToBranch = $policy->applies_to_all_branches
                || in_array((int) $data['branch_id'], array_map('intval', $policy->applicable_branch_ids ?? []), true);

            if (! $appliesToBranch) {
                throw ValidationException::withMessages([
                    'policy_id' => 'Quy định này không áp dụng cho chi nhánh đang thanh tra.',
                ]);
            }
        }

        if (! empty($data['offender_user_id'])) {
            $offender = User::where('restaurant_id', $user->restaurant_id)->findOrFail($data['offender_user_id']);
            $offenderBranchId = $offender->assignedBranchId();

            if ($offenderBranchId === null || $offenderBranchId !== (int) $data['branch_id']) {
                throw ValidationException::withMessages([
                    'offender_user_id' => 'Nhân sự vi phạm không thuộc chi nhánh đang thanh tra.',
                ]);
            }
        }

        $proofPhotoUrl = $data['proof_photo_url'] ?? null;
        if ($request->hasFile('proof_photo')) {
            $path = $request->file('proof_photo')->store('audit-proofs', 'local');
            $proofPhotoUrl = route('secure-files.download', ['path' => $path]);
        }

        $reportCode = 'INF-'.Carbon::now()->format('Ymd').'-'.str_pad(
            (string) (OperationalInfringementReport::where('restaurant_id', $user->restaurant_id)->count() + 1),
            4,
            '0',
            STR_PAD_LEFT,
        );

        $report = OperationalInfringementReport::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $data['branch_id'],
            'inspection_plan_id' => $inspectionPlan?->id,
            'report_code' => $reportCode,
            'inspector_id' => $user->id,
            'policy_id' => $data['policy_id'] ?? null,
            'offender_user_id' => $data['offender_user_id'] ?? null,
            'infringement_date' => $data['infringement_date'],
            'severity_level' => $severityLevel,
            'description' => $data['description'],
            'proof_photo_url' => $proofPhotoUrl,
            'penalty_amount' => $data['penalty_amount'],
            'remediation_deadline' => $data['remediation_deadline'] ?? null,
            'remediation_plan' => $data['remediation_plan'] ?? null,
            'status' => 'pending_owner_approval',
        ]);

        AuditLog::log('operational_audit_report_created', 'created', $report, null, [
            'report_code' => $report->report_code,
            'severity_level' => $report->severity_level,
            'branch_id' => $report->branch_id,
        ]);

        if ($inspectionPlan && $inspectionPlan->status === 'planned') {
            $inspectionPlan->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
            AuditLog::log('operational_inspection_plan_started', 'updated', $inspectionPlan, ['status' => 'planned'], [
                'status' => 'in_progress',
                'trigger' => 'report_created',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã lập Biên bản Vi phạm và gửi trình Chủ doanh nghiệp phê duyệt.',
            'data' => $report->load(['branch', 'policy', 'offender']),
        ]);
    }

    /** Tạo kế hoạch kiểm tra để chủ động quản lý phạm vi và trách nhiệm thanh tra. */
    public function storeInspectionPlan(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManagePlans($user), 403);

        $data = $request->validate([
            'branch_id' => ['nullable', TenantRule::exists('restaurant_branches')],
            'title' => 'required|string|min:5|max:255',
            'inspection_type' => 'required|in:routine,thematic,surprise,follow_up',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'due_date' => 'nullable|date|after_or_equal:scheduled_date',
            'lead_inspector_id' => 'nullable|integer',
            'scope' => 'required|string|min:10|max:4000',
        ]);

        $leadInspector = null;
        if (! empty($data['lead_inspector_id'])) {
            $leadInspector = User::where('restaurant_id', $user->restaurant_id)
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['operations_inspector', 'compliance_auditor', 'owner']))
                ->findOrFail($data['lead_inspector_id']);
        }

        $planCode = $this->nextPlanCode((int) $user->restaurant_id, $data['scheduled_date']);
        $plan = OperationalInspectionPlan::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $data['branch_id'] ?? null,
            'plan_code' => $planCode,
            'title' => $data['title'],
            'inspection_type' => $data['inspection_type'],
            'scheduled_date' => $data['scheduled_date'],
            'due_date' => $data['due_date'] ?? null,
            'lead_inspector_id' => $leadInspector?->id ?? $user->id,
            'created_by' => $user->id,
            'status' => 'planned',
            'scope' => $data['scope'],
        ]);

        AuditLog::log('operational_inspection_plan_created', 'created', $plan, null, [
            'plan_code' => $plan->plan_code,
            'inspection_type' => $plan->inspection_type,
            'branch_id' => $plan->branch_id,
            'lead_inspector_id' => $plan->lead_inspector_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã tạo kế hoạch kiểm tra và phân công đầu mối.',
            'data' => $plan->load(['branch:id,name', 'leadInspector:id,name,email']),
        ]);
    }

    /** Bắt đầu kế hoạch, ghi nhận thời điểm thực tế để đo tiến độ. */
    public function startInspectionPlan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManagePlans($user), 403);
        $plan = $this->planForTenant($user, $id);

        if ($plan->status !== 'planned') {
            throw ValidationException::withMessages(['status' => 'Chỉ kế hoạch đang chờ thực hiện mới có thể bắt đầu.']);
        }

        $plan->update(['status' => 'in_progress', 'started_at' => now()]);
        AuditLog::log('operational_inspection_plan_started', 'updated', $plan, ['status' => 'planned'], ['status' => 'in_progress']);

        return response()->json(['success' => true, 'message' => 'Kế hoạch đã chuyển sang đang thực hiện.']);
    }

    /** Đóng kế hoạch sau khi đã rà soát các biên bản phát sinh. */
    public function completeInspectionPlan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManagePlans($user), 403);
        $data = $request->validate(['notes' => 'required|string|min:10|max:3000']);
        $plan = $this->planForTenant($user, $id);

        if (! in_array($plan->status, ['planned', 'in_progress'], true)) {
            throw ValidationException::withMessages(['status' => 'Kế hoạch đã được xử lý trước đó.']);
        }

        $pendingReports = $plan->reports()->where('status', 'pending_owner_approval')->count();
        if ($pendingReports > 0) {
            throw ValidationException::withMessages([
                'status' => "Không thể đóng kế hoạch khi còn {$pendingReports} biên bản chờ Chủ doanh nghiệp duyệt.",
            ]);
        }

        $oldStatus = $plan->status;
        $plan->update([
            'status' => 'completed',
            'notes' => $data['notes'],
            'completed_by' => $user->id,
            'completed_at' => now(),
        ]);
        AuditLog::log('operational_inspection_plan_completed', 'updated', $plan, ['status' => $oldStatus], [
            'status' => 'completed',
            'completed_by' => $user->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Đã hoàn tất kế hoạch và khóa phạm vi phát sinh mới.']);
    }

    /** Hủy kế hoạch khi có quyết định thay đổi lịch; bắt buộc lưu lý do. */
    public function cancelInspectionPlan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManagePlans($user), 403);
        $data = $request->validate(['reason' => 'required|string|min:10|max:2000']);
        $plan = $this->planForTenant($user, $id);

        if (! in_array($plan->status, ['planned', 'in_progress'], true)) {
            throw ValidationException::withMessages(['status' => 'Chỉ kế hoạch chưa hoàn tất mới có thể hủy.']);
        }

        $oldStatus = $plan->status;
        $plan->update(['status' => 'cancelled', 'notes' => $data['reason']]);
        AuditLog::log('operational_inspection_plan_cancelled', 'updated', $plan, ['status' => $oldStatus], [
            'status' => 'cancelled',
            'reason' => $data['reason'],
        ]);

        return response()->json(['success' => true, 'message' => 'Đã hủy kế hoạch và lưu lý do.']);
    }

    /** Xuất danh sách hồ sơ theo bộ lọc hiện tại để phục vụ họp vận hành/thanh tra. */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $query = OperationalInfringementReport::where('restaurant_id', $user->restaurant_id)
            ->with(['branch:id,name', 'policy:id,title', 'inspector:id,name', 'assignee:id,name', 'inspectionPlan:id,plan_code,title']);

        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity_level', $request->string('severity')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('infringement_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('infringement_date', '<=', $request->date('to')));

        $reports = $query->latest('infringement_date')->get();
        $filename = 'bao-cao-thanh-tra-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($reports): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Mã biên bản', 'Kế hoạch', 'Chi nhánh', 'Ngày phát hiện', 'Mức độ', 'Trạng thái', 'Quy định', 'Người lập', 'Người phụ trách', 'Hạn khắc phục', 'Tiền phạt', 'Mô tả']);
            foreach ($reports as $report) {
                fputcsv($handle, [
                    $report->report_code,
                    $report->inspectionPlan?->plan_code,
                    $report->branch?->name,
                    $report->infringement_date?->toDateString(),
                    $report->severity_level,
                    $report->status,
                    $report->policy?->title,
                    $report->inspector?->name,
                    $report->assignee?->name,
                    $report->remediation_deadline?->toDateString(),
                    $report->penalty_amount,
                    $report->description,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Chủ duyệt mức phạt; hồ sơ có kế hoạch sẽ tự chuyển sang hàng đợi khắc phục. */
    public function approveReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isOwner() || $user->isSuperAdmin())) {
            return response()->json(['success' => false, 'message' => 'Chỉ Chủ doanh nghiệp mới có quyền phê duyệt biên bản phạt.'], 403);
        }

        $request->validate(['owner_notes' => 'nullable|string|max:2000']);
        $report = $this->reportForTenant($user, $id);

        if ($report->status !== 'pending_owner_approval') {
            return response()->json(['success' => false, 'message' => 'Biên bản này đã được xử lý trước đó.'], 422);
        }

        $oldValues = ['status' => $report->status];
        $nextStatus = $report->remediation_plan || $report->remediation_deadline
            ? 'remediation_in_progress'
            : 'approved';

        $report->update([
            'status' => $nextStatus,
            'owner_notes' => $request->input('owner_notes'),
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        AuditLog::log('operational_audit_report_approved', 'updated', $report, $oldValues, [
            'status' => $nextStatus,
            'approved_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => $nextStatus === 'remediation_in_progress'
                ? 'Đã duyệt biên bản và chuyển sang theo dõi khắc phục.'
                : 'Đã phê duyệt biên bản vi phạm và ghi nhận mức phạt tài chính.',
            'data' => $report->fresh(['branch', 'policy', 'offender', 'approver']),
        ]);
    }

    /** Từ chối phải kèm lý do để giữ đầy đủ dấu vết kiểm soát. */
    public function rejectReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isOwner() || $user->isSuperAdmin())) {
            return response()->json(['success' => false, 'message' => 'Chỉ Chủ doanh nghiệp mới có quyền từ chối biên bản phạt.'], 403);
        }

        $data = $request->validate(['owner_notes' => 'required|string|min:5|max:2000']);
        $report = $this->reportForTenant($user, $id);

        if ($report->status !== 'pending_owner_approval') {
            return response()->json(['success' => false, 'message' => 'Biên bản này đã được xử lý trước đó.'], 422);
        }

        $report->update([
            'status' => 'rejected',
            'owner_notes' => $data['owner_notes'],
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        AuditLog::log('operational_audit_report_rejected', 'updated', $report, ['status' => 'pending_owner_approval'], [
            'status' => 'rejected',
            'reason' => $data['owner_notes'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã từ chối biên bản phạt và lưu lý do.',
            'data' => $report->fresh(['branch', 'policy', 'offender', 'approver']),
        ]);
    }

    /** Giao người phụ trách và thiết lập SLA khắc phục. */
    public function assignReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManageRemediation($user), 403);

        $data = $request->validate([
            'assigned_to' => 'nullable|integer',
            'remediation_deadline' => 'required|date|after_or_equal:today',
            'remediation_plan' => 'required|string|min:10|max:4000',
        ]);
        $report = $this->reportForTenant($user, $id);

        if (in_array($report->status, ['pending_owner_approval', 'rejected', 'closed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ hồ sơ đã được duyệt và chưa đóng mới được giao khắc phục.',
            ]);
        }

        $assignee = null;
        if (! empty($data['assigned_to'])) {
            $assignee = User::where('restaurant_id', $user->restaurant_id)->findOrFail($data['assigned_to']);
            if ($assignee->assignedBranchId() !== (int) $report->branch_id) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Người phụ trách phải thuộc đúng chi nhánh của hồ sơ.',
                ]);
            }
        }

        $oldValues = [
            'assigned_to' => $report->assigned_to,
            'remediation_deadline' => $report->remediation_deadline?->toDateString(),
            'status' => $report->status,
        ];

        $report->update([
            'assigned_to' => $assignee?->id,
            'remediation_deadline' => $data['remediation_deadline'],
            'remediation_plan' => $data['remediation_plan'],
            'status' => 'remediation_in_progress',
        ]);

        AuditLog::log('operational_audit_remediation_assigned', 'updated', $report, $oldValues, [
            'assigned_to' => $report->assigned_to,
            'remediation_deadline' => $report->remediation_deadline?->toDateString(),
            'status' => $report->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã giao trách nhiệm và kích hoạt SLA khắc phục.',
            'data' => $report->fresh(['assignee']),
        ]);
    }

    /** Người được giao nộp kết quả khắc phục và bằng chứng để chờ tái kiểm. */
    public function submitRemediation(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'remediation_notes' => 'required|string|min:5|max:3000',
            'remediation_proof' => 'nullable|file|image|max:5120',
            'remediation_proof_url' => 'nullable|string|max:1000',
        ]);
        $report = $this->reportForTenant($user, $id);

        $canSubmit = $this->canManageRemediation($user) || (int) $report->assigned_to === (int) $user->id;
        abort_unless($canSubmit, 403, 'Bạn không phải người được giao xử lý hồ sơ này.');

        if (! in_array($report->status, ['approved', 'remediation_in_progress'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hồ sơ chưa ở trạng thái có thể nộp khắc phục.',
            ]);
        }

        if (in_array($report->severity_level, ['severe', 'critical'], true)
            && ! $request->hasFile('remediation_proof')
            && blank($data['remediation_proof_url'] ?? $report->remediation_proof_url)) {
            throw ValidationException::withMessages([
                'remediation_proof' => 'Vi phạm nặng phải có bằng chứng khắc phục.',
            ]);
        }

        $proofUrl = $data['remediation_proof_url'] ?? $report->remediation_proof_url;
        if ($request->hasFile('remediation_proof')) {
            $path = $request->file('remediation_proof')->store('audit-remediation', 'local');
            $proofUrl = route('secure-files.download', ['path' => $path]);
        }

        $report->update([
            'remediation_notes' => $data['remediation_notes'],
            'remediation_proof_url' => $proofUrl,
            'remediation_submitted_at' => now(),
            'status' => 'reinspection_pending',
        ]);

        AuditLog::log('operational_audit_remediation_submitted', 'updated', $report, null, [
            'status' => 'reinspection_pending',
            'submitted_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã nộp kết quả khắc phục. Hồ sơ đang chờ tái kiểm.',
            'data' => $report->fresh(['assignee']),
        ]);
    }

    /** Tái kiểm: đạt thì đóng hồ sơ, không đạt thì trả lại hàng đợi khắc phục. */
    public function reinspectReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canReinspect($user), 403);

        $data = $request->validate([
            'result' => 'required|in:pass,fail',
            'reinspection_notes' => 'required|string|min:5|max:3000',
            'reinspection_proof' => 'nullable|file|image|max:5120',
            'reinspection_proof_url' => 'nullable|string|max:1000',
        ]);
        $report = $this->reportForTenant($user, $id);

        if ($report->status !== 'reinspection_pending') {
            throw ValidationException::withMessages([
                'status' => 'Chỉ hồ sơ đã nộp khắc phục mới được tái kiểm.',
            ]);
        }

        $oldStatus = $report->status;
        $passed = $data['result'] === 'pass';
        $proofUrl = $data['reinspection_proof_url'] ?? null;
        if ($request->hasFile('reinspection_proof')) {
            $path = $request->file('reinspection_proof')->store('audit-reinspection', 'local');
            $proofUrl = route('secure-files.download', ['path' => $path]);
        }
        $report->update([
            'status' => $passed ? 'closed' : 'remediation_in_progress',
            'reinspection_result' => $data['result'],
            'reinspection_notes' => $data['reinspection_notes'],
            'reinspection_proof_url' => $proofUrl,
            'reinspected_by' => $user->id,
            'reinspected_at' => now(),
            'closed_by' => $passed ? $user->id : null,
            'closed_at' => $passed ? now() : null,
            'remediation_submitted_at' => $passed ? $report->remediation_submitted_at : null,
        ]);

        AuditLog::log('operational_audit_reinspected', 'updated', $report, ['status' => $oldStatus], [
            'status' => $report->status,
            'result' => $data['result'],
            'reinspected_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => $passed
                ? 'Tái kiểm đạt. Hồ sơ đã được đóng và khóa kết quả.'
                : 'Tái kiểm chưa đạt. Hồ sơ đã quay lại hàng đợi khắc phục.',
            'data' => $report->fresh(['assignee', 'closer', 'reinspector']),
        ]);
    }

    private function reportForTenant(User $user, int $id): OperationalInfringementReport
    {
        return OperationalInfringementReport::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
    }

    private function planForTenant(User $user, int $id): OperationalInspectionPlan
    {
        return OperationalInspectionPlan::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
    }

    private function canManagePlans(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->can('operational_audit.manage')
            || $user->can('operational_audit.report');
    }

    private function nextPlanCode(int $restaurantId, string $scheduledDate): string
    {
        $prefix = 'INS-'.Carbon::parse($scheduledDate)->format('Ymd').'-';
        $sequence = OperationalInspectionPlan::where('restaurant_id', $restaurantId)
            ->where('plan_code', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $code = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (OperationalInspectionPlan::where('restaurant_id', $restaurantId)->where('plan_code', $code)->exists());

        return $code;
    }

    private function canManageRemediation(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->can('operational_audit.manage')
            || $user->can('operational_audit.report');
    }

    private function canReinspect(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->can('operational_audit.reinspect')
            || $user->canCloseInspection();
    }

    private function serializeReport(OperationalInfringementReport $report, User $viewer): array
    {
        $isOverdue = $report->remediation_deadline?->isBefore(now()->startOfDay())
            && ! in_array($report->status, ['closed', 'rejected'], true);

        return [
            'id' => $report->id,
            'report_code' => $report->report_code,
            'branch' => $report->branch ? ['id' => $report->branch->id, 'name' => $report->branch->name] : null,
            'inspector' => $report->inspector ? ['id' => $report->inspector->id, 'name' => $report->inspector->name, 'email' => $report->inspector->email] : null,
            'policy' => $report->policy ? ['id' => $report->policy->id, 'title' => $report->policy->title, 'policy_code' => $report->policy->policy_code] : null,
            'offender' => $report->offender ? ['id' => $report->offender->id, 'name' => $report->offender->name, 'email' => $report->offender->email] : null,
            'assignee' => $report->assignee ? ['id' => $report->assignee->id, 'name' => $report->assignee->name, 'email' => $report->assignee->email] : null,
            'approver' => $report->approver ? ['id' => $report->approver->id, 'name' => $report->approver->name] : null,
            'closer' => $report->closer ? ['id' => $report->closer->id, 'name' => $report->closer->name] : null,
            'reinspector' => $report->reinspector ? ['id' => $report->reinspector->id, 'name' => $report->reinspector->name] : null,
            'infringement_date' => $report->infringement_date?->toDateString(),
            'description' => $report->description,
            'severity_level' => $report->severity_level,
            'proof_photo_url' => $report->proof_photo_url,
            'penalty_amount' => (float) $report->penalty_amount,
            'status' => $report->status,
            'owner_notes' => $report->owner_notes,
            'approved_at' => $report->approved_at?->format('d/m/Y H:i'),
            'remediation_deadline' => $report->remediation_deadline?->toDateString(),
            'remediation_plan' => $report->remediation_plan,
            'remediation_notes' => $report->remediation_notes,
            'remediation_proof_url' => $report->remediation_proof_url,
            'remediation_submitted_at' => $report->remediation_submitted_at?->format('d/m/Y H:i'),
            'reinspection_result' => $report->reinspection_result,
            'reinspection_notes' => $report->reinspection_notes,
            'reinspection_proof_url' => $report->reinspection_proof_url,
            'reinspected_at' => $report->reinspected_at?->format('d/m/Y H:i'),
            'closed_at' => $report->closed_at?->format('d/m/Y H:i'),
            'inspection_plan' => $report->inspectionPlan ? [
                'id' => $report->inspectionPlan->id,
                'plan_code' => $report->inspectionPlan->plan_code,
                'title' => $report->inspectionPlan->title,
                'status' => $report->inspectionPlan->status,
            ] : null,
            'is_overdue' => (bool) $isOverdue,
            'can_submit_remediation' => $this->canManageRemediation($viewer)
                || (int) $report->assigned_to === (int) $viewer->id,
        ];
    }
}
