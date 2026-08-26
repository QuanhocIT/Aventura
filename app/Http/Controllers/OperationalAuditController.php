<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ChecklistCompletion;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\CompanyPolicy;
use App\Models\OperationalCaseLink;
use App\Models\OperationalCorrectiveAction;
use App\Models\OperationalEvidence;
use App\Models\OperationalInfringementReport;
use App\Models\OperationalInspection;
use App\Models\OperationalInspectionPlan;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalAuditController extends Controller
{
    /**
     * Trang tổng quan thanh tra dùng cho menu riêng của thanh tra.
     * Dùng chung dữ liệu với cockpit quản lý biên bản nhưng có URL độc lập,
     * tránh bị DashboardController chuyển hướng ngược về /operations/audit.
     */
    public function overview(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = (int) $user->restaurant_id;
        $today = now()->startOfDay();

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
                'inspectionPlan:id,plan_code,title,status',
            ])
            ->orderByDesc('infringement_date')
            ->orderByDesc('id')
            ->get();

        $plans = OperationalInspectionPlan::where('restaurant_id', $restaurantId)
            ->with([
                'branch:id,name',
                'leadInspector:id,name,email',
            ])
            ->withCount([
                'reports',
                'reports as open_reports_count' => fn ($query) => $query->whereNotIn('status', ['closed', 'rejected']),
            ])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'planned' THEN 2 ELSE 3 END")
            ->orderBy('scheduled_date')
            ->orderByDesc('id')
            ->get();

        $inspections = OperationalInspection::where('restaurant_id', $restaurantId)
            ->with(['branch:id,name', 'leadInspector:id,name'])
            ->withCount([
                'reports',
                'checklistCompletions as failed_checklist_count' => fn ($query) => $query->where('result', 'fail'),
                'correctiveActions as open_actions_count' => fn ($query) => $query->whereNotIn('status', ['verified', 'cancelled']),
            ])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'draft' THEN 2 WHEN 'planned' THEN 3 ELSE 4 END")
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get();

        $activeReports = $reports->whereNotIn('status', ['closed', 'rejected']);
        $closedCount = $reports->where('status', 'closed')->count();
        $overdueReports = $activeReports->filter(
            fn (OperationalInfringementReport $report) => $report->remediation_deadline?->lt($today) === true,
        );
        $dueSoonReports = $activeReports->filter(function (OperationalInfringementReport $report) use ($today): bool {
            if (! $report->remediation_deadline) {
                return false;
            }

            return $report->remediation_deadline->betweenIncluded($today, $today->copy()->addDays(3));
        });

        $reportStats = [
            'total' => $reports->count(),
            'open' => $activeReports->count(),
            'pending_approval' => $reports->where('status', 'pending_owner_approval')->count(),
            'in_remediation' => $reports->where('status', 'remediation_in_progress')->count(),
            'reinspection_pending' => $reports->where('status', 'reinspection_pending')->count(),
            'closed' => $closedCount,
            'rejected' => $reports->where('status', 'rejected')->count(),
            'overdue' => $overdueReports->count(),
            'due_soon' => $dueSoonReports->count(),
            'critical' => $activeReports->whereIn('severity_level', ['severe', 'critical'])->count(),
            'unassigned' => $activeReports->whereNull('assigned_to')->count(),
            'closure_rate' => $reports->count() > 0 ? round(($closedCount / $reports->count()) * 100, 1) : 0,
        ];

        $branchReports = $reports->groupBy('branch_id');
        $branchPlans = $plans->groupBy('branch_id');
        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

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
            $monthReports = $reports->filter(fn (OperationalInfringementReport $report) => $report->infringement_date?->isSameMonth($month) === true);

            return [
                'label' => $month->format('m/Y'),
                'total' => $monthReports->count(),
                'closed' => $monthReports->where('status', 'closed')->count(),
                'critical' => $monthReports->where('severity_level', 'critical')->count(),
            ];
        })->values()->all();

        $serializePlan = static fn (OperationalInspectionPlan $plan): array => [
            'id' => $plan->id,
            'plan_code' => $plan->plan_code,
            'title' => $plan->title,
            'inspection_type' => $plan->inspection_type,
            'scheduled_date' => $plan->scheduled_date?->toDateString(),
            'due_date' => $plan->due_date?->toDateString(),
            'status' => $plan->status,
            'scope' => $plan->scope,
            'branch' => $plan->branch ? ['id' => $plan->branch->id, 'name' => $plan->branch->name] : null,
            'lead_inspector' => $plan->leadInspector ? ['id' => $plan->leadInspector->id, 'name' => $plan->leadInspector->name] : null,
            'reports_count' => (int) $plan->reports_count,
            'open_reports_count' => (int) $plan->open_reports_count,
            'is_overdue' => $plan->due_date?->lt($today) === true && ! in_array($plan->status, ['completed', 'cancelled'], true),
        ];

        $myQueue = $activeReports
            ->filter(fn (OperationalInfringementReport $report) => (int) $report->inspector_id === (int) $user->id || (int) $report->assigned_to === (int) $user->id)
            ->sortByDesc(fn (OperationalInfringementReport $report) => $report->remediation_deadline?->lt($today) === true ? 2 : ($report->severity_level === 'critical' ? 1 : 0))
            ->take(8)
            ->values()
            ->map(fn (OperationalInfringementReport $report) => $this->serializeReport($report, $user))
            ->all();

        $focusReports = $activeReports
            ->filter(fn (OperationalInfringementReport $report) => $report->remediation_deadline?->lt($today) === true || in_array($report->severity_level, ['severe', 'critical'], true))
            ->take(8)
            ->values()
            ->map(fn (OperationalInfringementReport $report) => $this->serializeReport($report, $user))
            ->all();

        return Inertia::render('operations/AuditOverview', [
            'roleLabel' => $user->hasRole('compliance_auditor') ? 'Thanh tra độc lập' : 'Giám sát viên vận hành',
            'reportStats' => $reportStats,
            'planStats' => [
                'planned' => $plans->where('status', 'planned')->count(),
                'in_progress' => $plans->where('status', 'in_progress')->count(),
                'completed' => $plans->where('status', 'completed')->count(),
                'overdue' => $plans->filter(fn (OperationalInspectionPlan $plan) => $plan->due_date?->lt($today) === true && ! in_array($plan->status, ['completed', 'cancelled'], true))->count(),
            ],
            'inspectionStats' => [
                'total' => $inspections->count(),
                'draft' => $inspections->where('status', 'draft')->count(),
                'in_progress' => $inspections->where('status', 'in_progress')->count(),
                'completed' => $inspections->where('status', 'completed')->count(),
                'failed_checklist' => (int) $inspections->sum('failed_checklist_count'),
                'open_actions' => (int) $inspections->sum('open_actions_count'),
            ],
            'activeInspections' => $inspections->whereIn('status', ['draft', 'planned', 'in_progress'])->take(6)->map(fn (OperationalInspection $inspection): array => [
                'id' => $inspection->id,
                'inspection_code' => $inspection->inspection_code,
                'title' => $inspection->title,
                'status' => $inspection->status,
                'branch' => $inspection->branch ? ['id' => $inspection->branch->id, 'name' => $inspection->branch->name] : null,
                'lead_inspector' => $inspection->leadInspector ? ['id' => $inspection->leadInspector->id, 'name' => $inspection->leadInspector->name] : null,
                'scheduled_at' => $inspection->scheduled_at?->format('d/m/Y H:i'),
                'reports_count' => (int) $inspection->reports_count,
                'failed_checklist_count' => (int) $inspection->failed_checklist_count,
                'open_actions_count' => (int) $inspection->open_actions_count,
            ])->values()->all(),
            'branchInsights' => $branchInsights,
            'trend' => $trend,
            'upcomingPlans' => $plans->filter(fn (OperationalInspectionPlan $plan) => in_array($plan->status, ['planned', 'in_progress'], true))->take(6)->map($serializePlan)->values()->all(),
            'myQueue' => $myQueue,
            'focusReports' => $focusReports,
            'recentReports' => $reports->take(8)->map(fn (OperationalInfringementReport $report) => $this->serializeReport($report, $user))->values()->all(),
            'capabilities' => [
                'create_report' => $user->isOwner() || $user->isSuperAdmin() || $user->can('operational_audit.report'),
                'manage_plans' => $this->canManagePlans($user),
                'reinspect' => $this->canReinspect($user),
            ],
        ]);
    }

    /**
     * Bảng điều hành hồ sơ thanh tra: phê duyệt, khắc phục, tái kiểm và truy vết.
     */
    public function page(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = (int) $user->restaurant_id;
        $isOwnerOrSuperAdmin = $user->isOwner() || $user->isSuperAdmin();
        $canManageRemediation = $this->canManageCapa($user);
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
                'inspection:id,inspection_code,title,status,score,risk_level',
                'correctiveActions:id,operational_report_id,title,status,priority,assigned_to,due_date,submitted_at,verified_at',
                'correctiveActions.assignee:id,name,email',
                'correctiveActions.verifier:id,name',
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
                'inspections',
                'inspections as completed_inspections_count' => fn ($query) => $query->where('status', 'completed'),
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
            'inspections_count' => (int) $plan->inspections_count,
            'completed_inspections_count' => (int) $plan->completed_inspections_count,
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
            'isOverview' => $request->routeIs('operations.audit.overview'),
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
            'canAcknowledge' => $isOwnerOrSuperAdmin || $user->hasRole('manager') || $user->can('operational_audit.branch_acknowledge'),
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
            'operational_inspection_id' => ['nullable', TenantRule::exists('operational_inspections')],
            'policy_id' => ['nullable', TenantRule::exists('company_policies')],
            'offender_user_id' => ['nullable', TenantRule::exists('users')],
            'infringement_date' => 'required|date',
            'severity_level' => 'sometimes|in:minor,moderate,severe,critical',
            'description' => 'required|string|min:10|max:4000',
            'finding_category' => 'nullable|string|max:255',
            'requirement_reference' => 'nullable|string|max:255',
            'observed_condition' => 'nullable|string|max:4000',
            'root_cause' => 'nullable|string|max:4000',
            'corrective_action' => 'nullable|string|max:4000',
            'preventive_action' => 'nullable|string|max:4000',
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

        $inspection = null;
        if (! empty($data['operational_inspection_id'])) {
            $inspection = $this->inspectionForTenant($user, (int) $data['operational_inspection_id']);
            if ($inspection->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'operational_inspection_id' => 'Chỉ phiên kiểm tra đang diễn ra mới nhận phát hiện mới.',
                ]);
            }
            if ((int) $inspection->branch_id !== (int) $data['branch_id']) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Chi nhánh của phát hiện phải khớp phiên kiểm tra hiện trường.',
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
            'operational_inspection_id' => $inspection?->id,
            'report_code' => $reportCode,
            'inspector_id' => $user->id,
            'policy_id' => $data['policy_id'] ?? null,
            'offender_user_id' => $data['offender_user_id'] ?? null,
            'infringement_date' => $data['infringement_date'],
            'severity_level' => $severityLevel,
            'description' => $data['description'],
            'finding_category' => $data['finding_category'] ?? null,
            'requirement_reference' => $data['requirement_reference'] ?? null,
            'observed_condition' => $data['observed_condition'] ?? null,
            'root_cause' => $data['root_cause'] ?? null,
            'corrective_action' => $data['corrective_action'] ?? null,
            'preventive_action' => $data['preventive_action'] ?? null,
            'proof_photo_url' => $proofPhotoUrl,
            'penalty_amount' => $data['penalty_amount'],
            'remediation_deadline' => $data['remediation_deadline'] ?? null,
            'remediation_plan' => $data['remediation_plan'] ?? null,
            'status' => 'pending_owner_approval',
            'assignment_status' => 'unassigned',
        ]);

        if ($report->remediation_plan || $report->remediation_deadline || $report->corrective_action) {
            OperationalCorrectiveAction::create([
                'restaurant_id' => $user->restaurant_id,
                'operational_report_id' => $report->id,
                'operational_inspection_id' => $inspection?->id,
                'title' => 'Khắc phục phát hiện '.$report->report_code,
                'description' => $report->remediation_plan ?: $report->description,
                'root_cause' => $report->root_cause,
                'corrective_action' => $report->corrective_action,
                'preventive_action' => $report->preventive_action,
                'priority' => in_array($severityLevel, ['severe', 'critical'], true) ? 'high' : 'normal',
                'due_date' => $report->remediation_deadline,
            ]);
        }

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

        $owner = User::where('restaurant_id', $user->restaurant_id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'owner'))
            ->first();
        $this->notifyAuditUser($owner, 'report_submitted', "Biên bản {$report->report_code} đang chờ bạn phê duyệt.", '/operations/audit');

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

    /**
     * Workspace thực địa: một phiên kiểm tra là hồ sơ độc lập, có thể hoàn tất
     * ngay cả khi không phát sinh biên bản vi phạm.
     */
    public function inspectionWorkspace(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = (int) $user->restaurant_id;

        $inspections = OperationalInspection::where('restaurant_id', $restaurantId)
            ->with([
                'branch:id,name',
                'plan:id,plan_code,title',
                'leadInspector:id,name,email',
                'creator:id,name',
                'reports:id,operational_inspection_id,report_code,status,severity_level,description,assigned_to,remediation_deadline',
                'reports.assignee:id,name,email',
                'correctiveActions:id,operational_inspection_id,operational_report_id,title,status,priority,assigned_to,due_date',
                'correctiveActions.assignee:id,name,email',
                'evidence:id,operational_inspection_id,operational_report_id,corrective_action_id,uploaded_by,collection,original_name,mime_type,file_size,sha256,captured_at,latitude,longitude,notes,created_at',
            ])
            ->withCount([
                'reports',
                'reports as open_reports_count' => fn ($query) => $query->whereNotIn('status', ['closed', 'rejected']),
                'checklistCompletions',
                'checklistCompletions as failed_checklist_count' => fn ($query) => $query->where('result', 'fail'),
                'correctiveActions',
                'correctiveActions as open_actions_count' => fn ($query) => $query->whereNotIn('status', ['verified', 'cancelled']),
            ])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'draft' THEN 2 WHEN 'planned' THEN 3 ELSE 4 END")
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get();

        $templates = ChecklistTemplate::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->with(['items:id,template_id,title,description,requires_photo,sort_order'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'sort_order'])
            ->map(fn (ChecklistTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'type' => $template->type,
                'items' => $template->items->map(fn (ChecklistItem $item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'requires_photo' => (bool) $item->requires_photo,
                ])->values()->all(),
            ])->values()->all();

        $serializeInspection = fn (OperationalInspection $inspection): array => [
            'id' => $inspection->id,
            'inspection_code' => $inspection->inspection_code,
            'title' => $inspection->title,
            'inspection_type' => $inspection->inspection_type,
            'status' => $inspection->status,
            'scheduled_at' => $inspection->scheduled_at?->format('Y-m-d H:i'),
            'started_at' => $inspection->started_at?->format('Y-m-d H:i'),
            'completed_at' => $inspection->completed_at?->format('Y-m-d H:i'),
            'scope' => $inspection->scope,
            'conclusion' => $inspection->conclusion,
            'score' => $inspection->score,
            'risk_level' => $inspection->risk_level,
            'location_note' => $inspection->location_note,
            'branch' => $inspection->branch ? ['id' => $inspection->branch->id, 'name' => $inspection->branch->name] : null,
            'plan' => $inspection->plan ? ['id' => $inspection->plan->id, 'plan_code' => $inspection->plan->plan_code, 'title' => $inspection->plan->title] : null,
            'lead_inspector' => $inspection->leadInspector ? ['id' => $inspection->leadInspector->id, 'name' => $inspection->leadInspector->name] : null,
            'reports_count' => (int) $inspection->reports_count,
            'open_reports_count' => (int) $inspection->open_reports_count,
            'checklist_count' => (int) $inspection->checklist_completions_count,
            'failed_checklist_count' => (int) $inspection->failed_checklist_count,
            'corrective_actions_count' => (int) $inspection->corrective_actions_count,
            'open_actions_count' => (int) $inspection->open_actions_count,
            'reports' => $inspection->reports->map(fn (OperationalInfringementReport $report): array => [
                'id' => $report->id,
                'report_code' => $report->report_code,
                'status' => $report->status,
                'severity_level' => $report->severity_level,
                'description' => $report->description,
                'assigned_to' => $report->assigned_to,
                'assignee' => $report->assignee ? ['id' => $report->assignee->id, 'name' => $report->assignee->name] : null,
                'remediation_deadline' => $report->remediation_deadline?->toDateString(),
            ])->values()->all(),
            'corrective_actions' => $inspection->correctiveActions->map(fn (OperationalCorrectiveAction $action): array => [
                'id' => $action->id,
                'title' => $action->title,
                'status' => $action->status,
                'priority' => $action->priority,
                'due_date' => $action->due_date?->toDateString(),
                'assigned_to' => $action->assigned_to,
                'assignee' => $action->assignee ? ['id' => $action->assignee->id, 'name' => $action->assignee->name] : null,
                'operational_report_id' => $action->operational_report_id,
            ])->values()->all(),
            'evidence' => $inspection->evidence->map(fn (OperationalEvidence $evidence): array => [
                'id' => $evidence->id,
                'collection' => $evidence->collection,
                'original_name' => $evidence->original_name,
                'mime_type' => $evidence->mime_type,
                'file_size' => $evidence->file_size,
                'sha256' => $evidence->sha256,
                'captured_at' => $evidence->captured_at?->format('d/m/Y H:i'),
                'latitude' => $evidence->latitude,
                'longitude' => $evidence->longitude,
                'notes' => $evidence->notes,
                'url' => route('operational-audit.evidence.download', ['id' => $evidence->id]),
            ])->values()->all(),
        ];

        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
        $inspectors = User::where('restaurant_id', $restaurantId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['operations_inspector', 'compliance_auditor', 'owner']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        $employees = User::where('restaurant_id', $restaurantId)
            ->whereNotIn('status', ['inactive', 'suspended'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'branch_id']);
        $plans = OperationalInspectionPlan::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['planned', 'in_progress'])
            ->orderBy('scheduled_date')
            ->get(['id', 'plan_code', 'title', 'branch_id', 'scheduled_date']);

        return Inertia::render('operations/InspectionWorkspace', [
            'inspections' => $inspections->map($serializeInspection)->values()->all(),
            'templates' => $templates,
            'branches' => $branches,
            'inspectors' => $inspectors,
            'employees' => $employees,
            'plans' => $plans,
            'currentUserId' => $user->id,
            'capabilities' => [
                'create' => $this->canCreateInspection($user),
                'execute' => $this->canExecuteInspection($user),
                'manage_actions' => $this->canManageCapa($user),
                'verify_actions' => $this->canVerifyCapa($user),
                'create_report' => $user->isOwner() || $user->isSuperAdmin() || $user->can('operational_audit.report'),
            ],
        ]);
    }

    /** Tạo hồ sơ phiên kiểm tra trước khi thanh tra viên xuống hiện trường. */
    public function storeInspection(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canCreateInspection($user), 403);

        $data = $request->validate([
            'branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'inspection_plan_id' => ['nullable', TenantRule::exists('operational_inspection_plans')],
            'title' => 'required|string|min:5|max:255',
            'inspection_type' => 'required|in:routine,thematic,surprise,follow_up',
            'scheduled_at' => 'nullable|date',
            'lead_inspector_id' => 'nullable|integer',
            'participants' => 'nullable|array|max:20',
            'participants.*' => 'integer',
            'scope' => 'required|string|min:5|max:4000',
            'location_note' => 'nullable|string|max:1000',
        ]);

        $branch = RestaurantBranch::where('restaurant_id', $user->restaurant_id)->findOrFail($data['branch_id']);
        $plan = null;
        if (! empty($data['inspection_plan_id'])) {
            $plan = $this->planForTenant($user, (int) $data['inspection_plan_id']);
            if ($plan->status === 'cancelled' || ($plan->branch_id !== null && (int) $plan->branch_id !== (int) $branch->id)) {
                throw ValidationException::withMessages(['inspection_plan_id' => 'Kế hoạch không còn hiệu lực hoặc không thuộc chi nhánh đã chọn.']);
            }
        }

        $leadInspector = $this->inspectorForTenant($user, $data['lead_inspector_id'] ?? null);
        $participantIds = collect($data['participants'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($participantIds !== []) {
            $validParticipantIds = User::where('restaurant_id', $user->restaurant_id)->whereIn('id', $participantIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($validParticipantIds) !== count($participantIds)) {
                throw ValidationException::withMessages(['participants' => 'Danh sách thành viên có tài khoản không thuộc nhà hàng.']);
            }
        }

        $inspection = DB::transaction(function () use ($data, $user, $plan, $leadInspector, $participantIds): OperationalInspection {
            return OperationalInspection::create([
                'restaurant_id' => $user->restaurant_id,
                'inspection_plan_id' => $plan?->id,
                'branch_id' => $data['branch_id'],
                'inspection_code' => $this->nextInspectionCode((int) $user->restaurant_id, $data['scheduled_at'] ?? now()->toDateString()),
                'title' => $data['title'],
                'inspection_type' => $data['inspection_type'],
                'status' => ! empty($data['scheduled_at']) ? 'planned' : 'draft',
                'lead_inspector_id' => $leadInspector?->id ?? $user->id,
                'created_by' => $user->id,
                'participants' => $participantIds,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'scope' => $data['scope'],
                'location_note' => $data['location_note'] ?? null,
            ]);
        });

        AuditLog::log('operational_inspection_created', 'created', $inspection, null, [
            'inspection_code' => $inspection->inspection_code,
            'branch_id' => $inspection->branch_id,
            'inspection_type' => $inspection->inspection_type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã tạo phiên kiểm tra và lưu người phụ trách.',
            'data' => $inspection->load(['branch:id,name', 'leadInspector:id,name,email', 'plan:id,plan_code,title']),
        ]);
    }

    /** Ghi giờ bắt đầu thực tế; từ đây checklist và phát hiện mới được ghi nhận. */
    public function startInspection(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canExecuteInspection($user), 403);
        $inspection = $this->inspectionForTenant($user, $id);
        abort_unless($this->canWorkOnInspection($inspection, $user), 403);

        if (! in_array($inspection->status, ['draft', 'planned'], true)) {
            throw ValidationException::withMessages(['status' => 'Phiên kiểm tra không ở trạng thái có thể bắt đầu.']);
        }

        $oldStatus = $inspection->status;
        $inspection->update(['status' => 'in_progress', 'started_at' => now()]);
        if ($inspection->plan && $inspection->plan->status === 'planned') {
            $inspection->plan->update(['status' => 'in_progress', 'started_at' => now()]);
        }
        AuditLog::log('operational_inspection_started', 'updated', $inspection, ['status' => $oldStatus], ['status' => 'in_progress', 'started_by' => $user->id]);

        return response()->json(['success' => true, 'message' => 'Phiên kiểm tra đã bắt đầu.', 'data' => $inspection->fresh()]);
    }

    /** Đóng phiên tại hiện trường; không bắt buộc có vi phạm vì kết quả đạt cũng là hồ sơ kiểm soát. */
    public function completeInspection(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canExecuteInspection($user), 403);
        $data = $request->validate([
            'conclusion' => 'required|string|min:5|max:4000',
            'score' => 'nullable|integer|min:0|max:100',
            'risk_level' => 'nullable|in:low,medium,high,critical',
        ]);
        $inspection = $this->inspectionForTenant($user, $id);
        abort_unless($this->canWorkOnInspection($inspection, $user), 403);

        if ($inspection->status !== 'in_progress') {
            throw ValidationException::withMessages(['status' => 'Chỉ phiên đang kiểm tra mới có thể hoàn tất.']);
        }

        // Không khóa phiên khi chưa có bất kỳ dấu vết kiểm tra nào. Nếu nhà
        // hàng chưa cấu hình checklist thì vẫn cho phép kết luận thủ công.
        if (ChecklistTemplate::where('restaurant_id', $user->restaurant_id)->where('is_active', true)->exists()
            && ! $inspection->checklistCompletions()->exists()) {
            throw ValidationException::withMessages(['status' => 'Cần ghi ít nhất một mục checklist trước khi khóa phiên kiểm tra.']);
        }

        $failedChecklist = $inspection->checklistCompletions()->where('result', 'fail')->count();
        $riskLevel = $data['risk_level'] ?? ($failedChecklist > 0 ? 'high' : (($data['score'] ?? 100) < 80 ? 'medium' : 'low'));
        $inspection->update([
            'status' => 'completed',
            'conclusion' => $data['conclusion'],
            'score' => $data['score'] ?? null,
            'risk_level' => $riskLevel,
            'completed_by' => $user->id,
            'completed_at' => now(),
        ]);

        AuditLog::log('operational_inspection_completed', 'updated', $inspection, ['status' => 'in_progress'], [
            'status' => 'completed',
            'score' => $inspection->score,
            'risk_level' => $inspection->risk_level,
        ]);

        return response()->json(['success' => true, 'message' => 'Đã hoàn tất phiên kiểm tra và khóa kết quả hiện trường.', 'data' => $inspection->fresh()]);
    }

    /** Ghi kết quả từng mục checklist vào đúng phiên thay vì chỉ đánh dấu ngày chung. */
    public function recordChecklistResult(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canExecuteInspection($user), 403);
        $data = $request->validate([
            'item_id' => ['required', 'exists:checklist_items,id'],
            'result' => 'required|in:pass,fail,na',
            'photo' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
            'finding_notes' => 'nullable|string|max:3000',
        ]);
        $inspection = $this->inspectionForTenant($user, $id);
        abort_unless($inspection->status === 'in_progress' && $this->canWorkOnInspection($inspection, $user), 403);
        $item = ChecklistItem::with('template')->findOrFail($data['item_id']);
        abort_unless($item->template && $item->template->restaurant_id === $user->restaurant_id, 403);
        if ($item->requires_photo && empty($data['photo']) && $data['result'] === 'fail') {
            throw ValidationException::withMessages(['photo' => 'Mục không đạt yêu cầu phải có ảnh hiện trường.']);
        }

        if ($data['result'] === 'fail' && blank($data['finding_notes'] ?? null)) {
            throw ValidationException::withMessages(['finding_notes' => 'Mục không đạt phải có mô tả sai lệch để lập phát hiện/CAPA.']);
        }

        $photoPath = null;
        if (! empty($data['photo']) && preg_match('/^data:image\/(\w+);base64,/', $data['photo'], $matches)) {
            $type = strtolower($matches[1]);
            if (! in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                throw ValidationException::withMessages(['photo' => 'Định dạng ảnh không được hỗ trợ.']);
            }
            $imageData = base64_decode(substr($data['photo'], strpos($data['photo'], ',') + 1), true);
            if ($imageData === false) {
                throw ValidationException::withMessages(['photo' => 'Ảnh hiện trường không hợp lệ.']);
            }
            $photoPath = 'checklists/'.'inspection_'.$inspection->id.'_'.$item->id.'_'.Str::random(8).'.'.$type;
            Storage::disk('public')->put($photoPath, $imageData);
        }

        $completion = ChecklistCompletion::where('item_id', $item->id)
            ->where('checked_date', now()->toDateString())
            ->where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $inspection->branch_id)
            ->where('operational_inspection_id', $inspection->id)
            ->first();
        if ($completion && $completion->operational_inspection_id && (int) $completion->operational_inspection_id !== (int) $inspection->id) {
            throw ValidationException::withMessages(['item_id' => 'Mục checklist đã được ghi nhận trong một phiên khác cùng ngày.']);
        }
        $completion ??= new ChecklistCompletion();
        $completion->fill([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $inspection->branch_id,
            'template_id' => $item->template_id,
            'item_id' => $item->id,
            'operational_inspection_id' => $inspection->id,
            'completed_by' => $user->id,
            'completed_at' => now(),
            'photo_path' => $photoPath ?? $completion->photo_path,
            'notes' => $data['notes'] ?? null,
            'result' => $data['result'],
            'finding_notes' => $data['finding_notes'] ?? null,
            'checked_date' => now()->toDateString(),
        ]);
        $completion->save();

        AuditLog::log('operational_inspection_checklist_recorded', 'updated', $inspection, null, [
            'item_id' => $item->id,
            'result' => $data['result'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $data['result'] === 'fail' ? 'Đã lưu mục không đạt; hãy tạo phát hiện/CAPA nếu cần.' : 'Đã lưu kết quả checklist.',
            'requires_finding' => $data['result'] === 'fail',
            'completion' => $completion->load('item:id,title'),
        ]);
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
        $this->notifyAuditUser($report->inspector, 'report_approved', "Biên bản {$report->report_code} đã được phê duyệt.", '/operations/audit');

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
        $this->notifyAuditUser($report->inspector, 'report_rejected', "Biên bản {$report->report_code} đã bị từ chối; xem lý do để cập nhật.", '/operations/audit');

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
            'assignment_status' => $assignee ? 'assigned' : 'unassigned',
            'assigned_at' => $assignee ? now() : null,
            'assignment_accepted_at' => null,
            'assignment_rejected_at' => null,
            'assignment_rejection_reason' => null,
            'remediation_deadline' => $data['remediation_deadline'],
            'remediation_plan' => $data['remediation_plan'],
            'status' => 'remediation_in_progress',
        ]);

        $action = $report->correctiveActions()->first();
        if ($action) {
            $action->update([
                'assigned_to' => $assignee?->id,
                'due_date' => $data['remediation_deadline'],
                'description' => $data['remediation_plan'],
            ]);
        } else {
            $action = OperationalCorrectiveAction::create([
                'restaurant_id' => $user->restaurant_id,
                'operational_report_id' => $report->id,
                'operational_inspection_id' => $report->operational_inspection_id,
                'title' => 'Khắc phục phát hiện '.$report->report_code,
                'description' => $data['remediation_plan'],
                'root_cause' => $report->root_cause,
                'corrective_action' => $report->corrective_action,
                'preventive_action' => $report->preventive_action,
                'assigned_to' => $assignee?->id,
                'priority' => in_array($report->severity_level, ['severe', 'critical'], true) ? 'high' : 'normal',
                'due_date' => $data['remediation_deadline'],
            ]);
        }

        $this->notifyAuditUser($assignee, 'assignment_created', "Bạn được giao xử lý {$report->report_code}, hạn {$report->remediation_deadline}.", '/operations/audit');

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

        $action = $report->correctiveActions()->where('assigned_to', $user->id)->latest('id')->first()
            ?: $report->correctiveActions()->latest('id')->first();
        if ($action) {
            $action->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'submission_notes' => $data['remediation_notes'],
            ]);
        } else {
            $action = OperationalCorrectiveAction::create([
                'restaurant_id' => $user->restaurant_id,
                'operational_report_id' => $report->id,
                'operational_inspection_id' => $report->operational_inspection_id,
                'title' => 'Khắc phục phát hiện '.$report->report_code,
                'description' => $report->remediation_plan ?: $report->description,
                'assigned_to' => $report->assigned_to,
                'priority' => in_array($report->severity_level, ['severe', 'critical'], true) ? 'high' : 'normal',
                'due_date' => $report->remediation_deadline,
                'status' => 'submitted',
                'submitted_at' => now(),
                'submission_notes' => $data['remediation_notes'],
            ]);
        }

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
        if (! ($user->isOwner() || $user->isSuperAdmin()) && (int) $report->inspector_id === (int) $user->id) {
            abort(403, 'Người lập biên bản không được tự tái kiểm kết quả của chính mình.');
        }
        if (! ($user->isOwner() || $user->isSuperAdmin())
            && ((int) $report->assigned_to === (int) $user->id
                || $report->correctiveActions()->where('assigned_to', $user->id)->whereIn('status', ['submitted', 'in_progress', 'rejected'])->exists())) {
            abort(403, 'Người xử lý CAPA không được tự tái kiểm kết quả do mình nộp.');
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

        $report->correctiveActions()
            ->whereIn('status', ['submitted', 'in_progress', 'rejected'])
            ->get()
            ->each(function (OperationalCorrectiveAction $action) use ($passed, $user, $data): void {
                $action->update($passed ? [
                    'status' => 'verified',
                    'verified_by' => $user->id,
                    'verified_at' => now(),
                    'verification_notes' => $data['reinspection_notes'],
                ] : [
                    'status' => 'rejected',
                    'rejection_reason' => $data['reinspection_notes'],
                    'verification_notes' => $data['reinspection_notes'],
                ]);
            });

        AuditLog::log('operational_audit_reinspected', 'updated', $report, ['status' => $oldStatus], [
            'status' => $report->status,
            'result' => $data['result'],
            'reinspected_by' => $user->id,
        ]);
        $this->notifyAuditUser($report->assignee, 'report_reinspected', "Biên bản {$report->report_code} đã được tái kiểm: {$data['result']}.", '/operations/audit');

        return response()->json([
            'success' => true,
            'message' => $passed
                ? 'Tái kiểm đạt. Hồ sơ đã được đóng và khóa kết quả.'
                : 'Tái kiểm chưa đạt. Hồ sơ đã quay lại hàng đợi khắc phục.',
            'data' => $report->fresh(['assignee', 'closer', 'reinspector']),
        ]);
    }

    /** Nhân sự được giao phải xác nhận nhận việc; từ chối bắt buộc nêu lý do để giao lại. */
    public function acceptAssignment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $report = $this->reportForTenant($user, $id);
        abort_unless((int) $report->assigned_to === (int) $user->id || $this->canManageCapa($user), 403);

        if ($report->assignment_status !== 'assigned') {
            throw ValidationException::withMessages(['assignment_status' => 'Hồ sơ không ở trạng thái chờ nhận việc.']);
        }

        $report->update([
            'assignment_status' => 'accepted',
            'assignment_accepted_at' => now(),
            'status' => $report->status === 'approved' ? 'remediation_in_progress' : $report->status,
        ]);
        AuditLog::log('operational_audit_assignment_accepted', 'updated', $report, ['assignment_status' => 'assigned'], ['assignment_status' => 'accepted', 'user_id' => $user->id]);

        return response()->json(['success' => true, 'message' => 'Đã nhận trách nhiệm xử lý hồ sơ.', 'data' => $report->fresh(['assignee'])]);
    }

    public function rejectAssignment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['reason' => 'required|string|min:5|max:2000']);
        $report = $this->reportForTenant($user, $id);
        abort_unless((int) $report->assigned_to === (int) $user->id, 403);

        if ($report->assignment_status !== 'assigned') {
            throw ValidationException::withMessages(['assignment_status' => 'Hồ sơ không ở trạng thái chờ nhận việc.']);
        }

        $report->update([
            'assignment_status' => 'rejected',
            'assignment_rejected_at' => now(),
            'assignment_rejection_reason' => $data['reason'],
            'status' => 'remediation_in_progress',
        ]);
        AuditLog::log('operational_audit_assignment_rejected', 'updated', $report, ['assignment_status' => 'assigned'], ['assignment_status' => 'rejected', 'reason' => $data['reason']]);

        return response()->json(['success' => true, 'message' => 'Đã từ chối nhận việc và gửi lý do để phân công lại.', 'data' => $report->fresh(['assignee'])]);
    }

    /** Chi nhánh xác nhận đã nhận phát hiện và cam kết xử lý, tách khỏi phê duyệt tiền phạt. */
    public function acknowledgeReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['response' => 'required|string|min:5|max:3000']);
        $report = $this->reportForTenant($user, $id);
        $isOwner = $user->isOwner() || $user->isSuperAdmin();
        abort_unless($isOwner || $user->assignedBranchId() === (int) $report->branch_id, 403);

        if ($report->status === 'rejected') {
            throw ValidationException::withMessages(['status' => 'Biên bản đã bị từ chối và không cần xác nhận chi nhánh.']);
        }

        $report->update([
            'branch_acknowledged_by' => $user->id,
            'branch_acknowledged_at' => now(),
            'branch_response' => $data['response'],
        ]);
        AuditLog::log('operational_audit_branch_acknowledged', 'updated', $report, null, [
            'branch_acknowledged_by' => $user->id,
            'response' => $data['response'],
        ]);
        $this->notifyAuditUser($report->inspector, 'branch_acknowledged', "Chi nhánh đã xác nhận biên bản {$report->report_code}.", '/operations/audit');

        return response()->json(['success' => true, 'message' => 'Đã xác nhận tiếp nhận và phản hồi xử lý.', 'data' => $report->fresh(['branch', 'inspector'])]);
    }

    /** Tạo CAPA độc lập; một phát hiện có thể có nhiều hành động khắc phục/phòng ngừa. */
    public function storeCorrectiveAction(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManageCapa($user), 403);
        $data = $request->validate([
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:5|max:4000',
            'root_cause' => 'nullable|string|max:4000',
            'corrective_action' => 'nullable|string|max:4000',
            'preventive_action' => 'nullable|string|max:4000',
            'assigned_to' => 'nullable|integer',
            'priority' => 'required|in:low,normal,high,critical',
            'due_date' => 'nullable|date|after_or_equal:today',
        ]);
        $report = $this->reportForTenant($user, $id);
        $assignee = $this->assigneeForReport($user, $report, $data['assigned_to'] ?? null);

        $action = OperationalCorrectiveAction::create([
            'restaurant_id' => $user->restaurant_id,
            'operational_report_id' => $report->id,
            'operational_inspection_id' => $report->operational_inspection_id,
            'title' => $data['title'],
            'description' => $data['description'],
            'root_cause' => $data['root_cause'] ?? $report->root_cause,
            'corrective_action' => $data['corrective_action'] ?? $report->corrective_action,
            'preventive_action' => $data['preventive_action'] ?? $report->preventive_action,
            'assigned_to' => $assignee?->id,
            'priority' => $data['priority'],
            'due_date' => $data['due_date'] ?? $report->remediation_deadline,
            'status' => $assignee ? 'open' : 'open',
        ]);

        if ($assignee) {
            $report->update([
                'assigned_to' => $assignee->id,
                'assignment_status' => 'assigned',
                'assigned_at' => now(),
                'remediation_deadline' => $data['due_date'] ?? $report->remediation_deadline,
                'status' => $report->status === 'approved' ? 'remediation_in_progress' : $report->status,
            ]);
            $this->notifyAuditUser($assignee, 'action_assigned', "Bạn được giao hành động khắc phục {$action->title}.", '/operations/inspection-workspace');
        }

        AuditLog::log('operational_audit_corrective_action_created', 'created', $action, null, [
            'report_id' => $report->id,
            'assigned_to' => $action->assigned_to,
            'priority' => $action->priority,
        ]);

        return response()->json(['success' => true, 'message' => 'Đã tạo hành động khắc phục và cập nhật trách nhiệm.', 'data' => $action->load('assignee:id,name,email')]);
    }

    /** CAPA cấp phiên dùng cho rủi ro hệ thống chưa cần lập biên bản phạt riêng. */
    public function storeInspectionCorrectiveAction(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManageCapa($user), 403);
        $data = $request->validate([
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:5|max:4000',
            'root_cause' => 'nullable|string|max:4000',
            'corrective_action' => 'nullable|string|max:4000',
            'preventive_action' => 'nullable|string|max:4000',
            'assigned_to' => 'nullable|integer',
            'priority' => 'required|in:low,normal,high,critical',
            'due_date' => 'nullable|date|after_or_equal:today',
        ]);
        $inspection = $this->inspectionForTenant($user, $id);
        $assignee = null;
        if (! empty($data['assigned_to'])) {
            $assignee = User::where('restaurant_id', $user->restaurant_id)->findOrFail($data['assigned_to']);
            if ($assignee->assignedBranchId() !== (int) $inspection->branch_id) {
                throw ValidationException::withMessages(['assigned_to' => 'Người phụ trách phải thuộc đúng chi nhánh của phiên kiểm tra.']);
            }
        }

        $action = OperationalCorrectiveAction::create([
            'restaurant_id' => $user->restaurant_id,
            'operational_inspection_id' => $inspection->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'root_cause' => $data['root_cause'] ?? null,
            'corrective_action' => $data['corrective_action'] ?? null,
            'preventive_action' => $data['preventive_action'] ?? null,
            'assigned_to' => $assignee?->id,
            'priority' => $data['priority'],
            'due_date' => $data['due_date'] ?? null,
        ]);

        $this->notifyAuditUser($assignee, 'action_assigned', "Bạn được giao hành động khắc phục {$action->title}.", '/operations/inspection-workspace');
        AuditLog::log('operational_audit_corrective_action_created', 'created', $action, null, [
            'inspection_id' => $inspection->id,
            'assigned_to' => $action->assigned_to,
        ]);

        return response()->json(['success' => true, 'message' => 'Đã tạo CAPA cho phiên kiểm tra.', 'data' => $action->load('assignee:id,name,email')]);
    }

    /**
     * Cập nhật trạng thái CAPA theo chuyển trạng thái có kiểm soát:
     * người nhận việc nộp, thanh tra độc lập xác minh, không tự đóng hồ sơ.
     */
    public function updateCorrectiveAction(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $action = $this->actionForTenant($user, $id);
        $data = $request->validate([
            'status' => 'required|in:accepted,in_progress,submitted,verified,rejected,cancelled',
            'submission_notes' => 'nullable|string|max:4000',
            'verification_notes' => 'nullable|string|max:4000',
            'rejection_reason' => 'nullable|string|max:2000',
        ]);
        $nextStatus = $data['status'];
        $isAssignee = (int) $action->assigned_to === (int) $user->id;
        $isManager = $this->canManageCapa($user);
        $isVerifier = $this->canVerifyCapa($user);

        if (in_array($nextStatus, ['accepted', 'in_progress', 'submitted'], true)) {
            abort_unless($isAssignee || $isManager, 403);
        } elseif ($nextStatus === 'verified') {
            abort_unless($isVerifier && ! $isAssignee, 403, 'Người lập hoặc người xử lý không được tự xác minh CAPA của mình.');
            $originatorId = $action->report?->inspector_id ?? $action->inspection?->lead_inspector_id;
            abort_unless((int) $originatorId !== (int) $user->id, 403, 'Người lập phiên/biên bản không được tự xác minh CAPA của mình.');
        } else {
            abort_unless($isManager || $isVerifier, 403);
        }

        if ($nextStatus === 'submitted' && blank($data['submission_notes'] ?? null)) {
            throw ValidationException::withMessages(['submission_notes' => 'Khi nộp kết quả phải mô tả việc đã thực hiện.']);
        }
        if ($nextStatus === 'rejected' && blank($data['rejection_reason'] ?? null)) {
            throw ValidationException::withMessages(['rejection_reason' => 'Từ chối kết quả phải nêu rõ lý do.']);
        }

        $oldStatus = $action->status;
        $timestamps = match ($nextStatus) {
            'accepted' => ['accepted_at' => now()],
            'in_progress' => ['started_at' => $action->started_at ?? now()],
            'submitted' => ['submitted_at' => now()],
            'verified' => ['verified_at' => now(), 'verified_by' => $user->id],
            default => [],
        };
        $action->update(array_merge([
            'status' => $nextStatus,
            'submission_notes' => $data['submission_notes'] ?? $action->submission_notes,
            'verification_notes' => $data['verification_notes'] ?? $action->verification_notes,
            'rejection_reason' => $data['rejection_reason'] ?? $action->rejection_reason,
        ], $timestamps));

        $this->syncReportFromActions($action, $user);
        AuditLog::log('operational_audit_corrective_action_status_changed', 'updated', $action, ['status' => $oldStatus], ['status' => $nextStatus, 'changed_by' => $user->id]);

        if ($action->assignee && $action->assignee->id !== $user->id && in_array($nextStatus, ['verified', 'rejected'], true)) {
            $this->notifyAuditUser($action->assignee, 'action_reviewed', "Hành động khắc phục {$action->title} đã được cập nhật: {$nextStatus}.", '/operations/inspection-workspace');
        }

        return response()->json(['success' => true, 'message' => 'Đã cập nhật trạng thái hành động khắc phục.', 'data' => $action->fresh(['assignee', 'verifier'])]);
    }

    /** Lưu nhiều bằng chứng hiện trường/khắc phục, thay cho một URL ảnh duy nhất trên biên bản cũ. */
    public function storeEvidence(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'file' => 'required|file|image|max:10240',
            'collection' => 'required|in:inspection,finding,remediation,reinspection',
            'operational_inspection_id' => 'nullable|integer',
            'operational_report_id' => 'nullable|integer',
            'corrective_action_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
            'captured_at' => 'nullable|date',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $inspection = ! empty($data['operational_inspection_id']) ? $this->inspectionForTenant($user, (int) $data['operational_inspection_id']) : null;
        $report = ! empty($data['operational_report_id']) ? $this->reportForTenant($user, (int) $data['operational_report_id']) : null;
        $action = ! empty($data['corrective_action_id']) ? $this->actionForTenant($user, (int) $data['corrective_action_id']) : null;
        if (! $inspection && ! $report && ! $action) {
            throw ValidationException::withMessages(['operational_report_id' => 'Bằng chứng phải gắn với phiên, biên bản hoặc hành động khắc phục.']);
        }
        if ($inspection && $report && (int) $report->operational_inspection_id !== (int) $inspection->id) {
            throw ValidationException::withMessages(['operational_inspection_id' => 'Phiên và biên bản không cùng một hồ sơ.']);
        }
        if ($action && $report && (int) $action->operational_report_id !== (int) $report->id) {
            throw ValidationException::withMessages(['corrective_action_id' => 'Hành động khắc phục không thuộc biên bản đã chọn.']);
        }

        $file = $request->file('file');
        $path = $file->store('operational-evidence', 'local');
        $evidence = OperationalEvidence::create([
            'restaurant_id' => $user->restaurant_id,
            'operational_inspection_id' => $inspection?->id ?? $action?->operational_inspection_id ?? $report?->operational_inspection_id,
            'operational_report_id' => $report?->id ?? $action?->operational_report_id,
            'corrective_action_id' => $action?->id,
            'uploaded_by' => $user->id,
            'collection' => $data['collection'],
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'captured_at' => $data['captured_at'] ?? now(),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLog::log('operational_evidence_uploaded', 'created', $evidence, null, ['collection' => $evidence->collection]);

        return response()->json(['success' => true, 'message' => 'Đã lưu bằng chứng có mã băm kiểm tra.', 'data' => [
            'id' => $evidence->id,
            'collection' => $evidence->collection,
            'original_name' => $evidence->original_name,
            'mime_type' => $evidence->mime_type,
            'file_size' => $evidence->file_size,
            'sha256' => $evidence->sha256,
            'captured_at' => $evidence->captured_at?->format('d/m/Y H:i'),
            'url' => route('operational-audit.evidence.download', ['id' => $evidence->id]),
        ]]);
    }

    /** Tải bằng chứng thanh tra qua ID đã scope theo tenant, không nhận path tùy ý từ client. */
    public function downloadEvidence(Request $request, int $id): BinaryFileResponse
    {
        $user = $request->user();
        $evidence = OperationalEvidence::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        abort_unless($evidence->disk === 'local' && Storage::disk('local')->exists($evidence->path), 404, 'Không tìm thấy bằng chứng.');

        // audit_logs hiện chỉ cho phép created/updated/deleted; dùng updated
        // cho lần truy cập có kiểm soát và giữ action mô tả đúng nghiệp vụ.
        AuditLog::log('operational_evidence_downloaded', 'updated', $evidence, null, [
            'original_name' => $evidence->original_name,
        ]);

        return response()->download(
            Storage::disk('local')->path($evidence->path),
            $evidence->original_name ?: basename($evidence->path),
            array_filter(['Content-Type' => $evidence->mime_type]),
        );
    }

    /** Liên kết biên bản với sự cố, vi phạm nhân sự hoặc tài sản để truy nguyên nguyên nhân. */
    public function storeCaseLink(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'link_type' => 'required|in:incident,violation_report,fixed_asset',
            'link_id' => 'required|integer',
            'operational_report_id' => 'nullable|integer',
            'operational_inspection_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
        ]);
        $report = ! empty($data['operational_report_id']) ? $this->reportForTenant($user, (int) $data['operational_report_id']) : null;
        $inspection = ! empty($data['operational_inspection_id']) ? $this->inspectionForTenant($user, (int) $data['operational_inspection_id']) : null;
        if (! $report && ! $inspection) {
            throw ValidationException::withMessages(['operational_report_id' => 'Liên kết phải gắn với một biên bản hoặc phiên kiểm tra.']);
        }
        if ($report && $inspection && (int) $report->operational_inspection_id !== (int) $inspection->id) {
            throw ValidationException::withMessages(['operational_inspection_id' => 'Biên bản và phiên kiểm tra không cùng một hồ sơ.']);
        }
        $targetTable = match ($data['link_type']) {
            'incident' => 'incidents',
            'violation_report' => 'violation_reports',
            'fixed_asset' => 'fixed_assets',
        };
        abort_unless(DB::table($targetTable)->where('restaurant_id', $user->restaurant_id)->where('id', $data['link_id'])->exists(), 422, 'Bản ghi được liên kết không thuộc nhà hàng.');

        $duplicate = OperationalCaseLink::where('restaurant_id', $user->restaurant_id)
            ->where('link_type', $data['link_type'])
            ->where('link_id', $data['link_id'])
            ->when($report, fn ($query) => $query->where('operational_report_id', $report->id))
            ->when(! $report, fn ($query) => $query->whereNull('operational_report_id'))
            ->exists();
        if ($duplicate) {
            return response()->json(['success' => true, 'message' => 'Liên kết này đã tồn tại.']);
        }

        $link = OperationalCaseLink::create([
            'restaurant_id' => $user->restaurant_id,
            'operational_report_id' => $report?->id,
            'operational_inspection_id' => $inspection?->id,
            'link_type' => $data['link_type'],
            'link_id' => $data['link_id'],
            'linked_by' => $user->id,
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLog::log('operational_case_linked', 'created', $link, null, ['link_type' => $link->link_type, 'link_id' => $link->link_id]);

        return response()->json(['success' => true, 'message' => 'Đã liên kết hồ sơ để truy nguyên.', 'data' => $link]);
    }

    private function inspectionForTenant(User $user, int $id): OperationalInspection
    {
        return OperationalInspection::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
    }

    private function actionForTenant(User $user, int $id): OperationalCorrectiveAction
    {
        return OperationalCorrectiveAction::where('restaurant_id', $user->restaurant_id)
            ->with(['report', 'inspection', 'assignee', 'verifier'])
            ->findOrFail($id);
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
            || $user->can('operational_inspection.manage');
    }

    private function canCreateInspection(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->can('operational_inspection.create')
            || $user->can('operational_inspection.manage')
            || $user->can('operational_audit.report');
    }

    private function canExecuteInspection(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->can('operational_inspection.execute')
            || $user->can('operational_inspection.manage')
            || $user->can('operational_audit.report');
    }

    private function canWorkOnInspection(OperationalInspection $inspection, User $user): bool
    {
        return $this->canManageInspection($user)
            || (int) $inspection->lead_inspector_id === (int) $user->id
            || in_array((int) $user->id, array_map('intval', $inspection->participants ?? []), true);
    }

    private function canManageInspection(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->can('operational_inspection.manage')
            || $user->can('operational_audit.manage');
    }

    private function canManageCapa(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->can('operational_audit.capa.manage')
            || $user->can('operational_audit.manage');
    }

    private function canVerifyCapa(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->can('operational_audit.capa.verify')
            || $user->can('operational_audit.verify')
            || $user->can('operational_audit.reinspect')
            || $user->canCloseInspection();
    }

    private function inspectorForTenant(User $user, mixed $id): ?User
    {
        if (empty($id)) {
            return null;
        }

        return User::where('restaurant_id', $user->restaurant_id)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['operations_inspector', 'compliance_auditor', 'owner']))
            ->findOrFail($id);
    }

    private function assigneeForReport(User $user, OperationalInfringementReport $report, mixed $id): ?User
    {
        if (empty($id)) {
            return null;
        }

        $assignee = User::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        if ($assignee->assignedBranchId() !== (int) $report->branch_id) {
            throw ValidationException::withMessages(['assigned_to' => 'Người phụ trách phải thuộc đúng chi nhánh của hồ sơ.']);
        }

        return $assignee;
    }

    private function nextInspectionCode(int $restaurantId, string $scheduledAt): string
    {
        $prefix = 'CHK-'.Carbon::parse($scheduledAt)->format('Ymd').'-';
        $sequence = OperationalInspection::where('restaurant_id', $restaurantId)
            ->where('inspection_code', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $code = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (OperationalInspection::where('restaurant_id', $restaurantId)->where('inspection_code', $code)->exists());

        return $code;
    }

    private function syncReportFromActions(OperationalCorrectiveAction $action, User $actor): void
    {
        $report = $action->report;
        if (! $report) {
            return;
        }

        $actions = $report->correctiveActions()->get();
        if (in_array($report->status, ['pending_owner_approval', 'rejected'], true)) {
            return;
        }

        $activeActions = $actions->whereNotIn('status', ['cancelled']);
        if ($activeActions->isNotEmpty() && $activeActions->every(fn (OperationalCorrectiveAction $row) => in_array($row->status, ['verified', 'cancelled'], true))) {
            $report->update([
                'status' => 'closed',
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'reinspection_result' => 'pass',
                'reinspected_by' => $actor->id,
                'reinspected_at' => now(),
            ]);
        } elseif ($activeActions->isNotEmpty() && $activeActions->every(fn (OperationalCorrectiveAction $row) => in_array($row->status, ['submitted', 'verified'], true))) {
            $report->update([
                'status' => 'reinspection_pending',
                'remediation_submitted_at' => now(),
                'remediation_notes' => $action->submission_notes ?: $report->remediation_notes,
            ]);
        } elseif (in_array($action->status, ['rejected', 'in_progress', 'accepted', 'open'], true)) {
            $report->update([
                'status' => 'remediation_in_progress',
                'reopen_count' => $action->status === 'rejected' ? ((int) $report->reopen_count + 1) : $report->reopen_count,
                'last_reopened_at' => $action->status === 'rejected' ? now() : $report->last_reopened_at,
            ]);
        }
    }

    private function notifyAuditUser(?User $user, string $type, string $message, string $url): void
    {
        if ($user && $user->id) {
            $user->notify(new \App\Notifications\OperationalAuditNotification($type, $message, $url));
        }
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
            'inspection' => $report->inspection ? [
                'id' => $report->inspection->id,
                'inspection_code' => $report->inspection->inspection_code,
                'title' => $report->inspection->title,
                'status' => $report->inspection->status,
                'score' => $report->inspection->score,
                'risk_level' => $report->inspection->risk_level,
            ] : null,
            'finding_category' => $report->finding_category,
            'requirement_reference' => $report->requirement_reference,
            'observed_condition' => $report->observed_condition,
            'root_cause' => $report->root_cause,
            'corrective_action' => $report->corrective_action,
            'preventive_action' => $report->preventive_action,
            'assignment_status' => $report->assignment_status ?? ($report->assigned_to ? 'assigned' : 'unassigned'),
            'assigned_at' => $report->assigned_at?->format('d/m/Y H:i'),
            'assignment_accepted_at' => $report->assignment_accepted_at?->format('d/m/Y H:i'),
            'assignment_rejection_reason' => $report->assignment_rejection_reason,
            'branch_acknowledged_at' => $report->branch_acknowledged_at?->format('d/m/Y H:i'),
            'branch_response' => $report->branch_response,
            'actions' => $report->correctiveActions->map(fn (OperationalCorrectiveAction $action): array => [
                'id' => $action->id,
                'title' => $action->title,
                'status' => $action->status,
                'priority' => $action->priority,
                'assigned_to' => $action->assigned_to,
                'assignee' => $action->assignee ? ['id' => $action->assignee->id, 'name' => $action->assignee->name] : null,
                'due_date' => $action->due_date?->toDateString(),
                'submitted_at' => $action->submitted_at?->format('d/m/Y H:i'),
                'verified_at' => $action->verified_at?->format('d/m/Y H:i'),
            ])->values()->all(),
            'is_overdue' => (bool) $isOverdue,
            'can_submit_remediation' => $this->canManageRemediation($viewer)
                || (int) $report->assigned_to === (int) $viewer->id,
        ];
    }
}
