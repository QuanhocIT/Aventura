<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Models\ViolationReport;
use App\Services\ApprovalAuthorityService;
use App\Services\ApprovalService;
use App\Services\FraudDetectionService;
use App\Services\QuotaService;
use App\Services\SalaryService;
use Carbon\Carbon;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class FraudController extends Controller
{
    public function __construct(
        private SalaryService $salaryService,
        private ApprovalService $approvalService,
        private FraudDetectionService $fraudService,
        private ApprovalAuthorityService $authorityService,
        private TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'super_admin']) || $request->user()->can('view_fraud_detection'), 403);

        $user = $request->user();

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'fraud_detection')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'fraud_detection',
                'feature_label' => 'Phát hiện Gian lận',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $user->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();
        $period = $request->input('period', today()->format('Y-m'));
        $activeTab = $request->input('tab', 'ai');

        [$year, $month] = explode('-', $period);
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $end = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        // Clamp to today to avoid querying future data
        if ($end > today()->toDateString()) {
            $end = today()->toDateString();
        }

        // Lazy-tab: only run the active tab's heavy detail query
        $data = match ($activeTab) {
            'ai' => $this->fraudService->detectAiFraudAlerts($restaurantId, $start, $end, $branchId),
            'audit' => $this->fraudService->getAuditLogs($restaurantId, $branchId),
            'discount' => $this->fraudService->detectDiscountAnomalies($restaurantId, $start, $end, $branchId),
            'cancel' => $this->fraudService->detectSuspiciousCancellations($restaurantId, $start, $end, $branchId),
            'waste' => $this->fraudService->detectInventoryWasteSpikes($restaurantId, $start, $end, $branchId),
            'revenue' => $this->fraudService->detectRevenueDiscrepancies($restaurantId, $start, $end, $branchId),
            default => $this->fraudService->detectCashShortfalls($restaurantId, $start, $end, $branchId),
        };

        $violations = ViolationReport::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('branch_id', $branchId)))
            ->with(['employee', 'reportedBy'])
            ->latest()
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'employee_id' => $r->employee_id,
                    'employee_name' => $r->employee?->full_name ?? 'Không xác định',
                    'reported_by_name' => $r->is_anonymous ? 'Ẩn danh' : ($r->reportedBy?->name ?? 'Hệ thống AI'),
                    'violation_type' => $r->violation_type,
                    'severity' => $r->severity,
                    'description' => $r->description,
                    'penalty_amount' => (float) $r->penalty_amount,
                    'occurred_at' => $r->occurred_at->format('Y-m-d H:i:s'),
                    'status' => $r->status,
                ];
            });

        return Inertia::render('fraud/Index', [
            'period' => $period,
            'activeTab' => $activeTab,
            'summary' => $this->fraudService->getSummary($restaurantId, $start, $end, $branchId),
            'data' => $data,
            'violations' => $violations,
            'canAct' => $user->can('approve_requests') || $user->can('manage_violations'),
            'dateRange' => ['start' => $start, 'end' => $end],
            'branchContext' => [
                'scope' => $this->tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
        ]);
    }

    public function createViolation(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('report_violations'), 403);

        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $data = $request->validate([
            'employee_id' => ['required', 'integer', "exists:employees,id,restaurant_id,{$restaurantId}"],
            'violation_type' => ['required', 'string', 'max:100'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'description' => ['required', 'string', 'max:2000'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'occurred_at' => ['required', 'date'],
            'apply_deduction' => ['nullable', 'boolean'],
        ]);

        // Always create ViolationReport directly (recording a fact — no approval needed)
        $violation = ViolationReport::create([
            'restaurant_id' => $restaurantId,
            'employee_id' => $data['employee_id'],
            'reported_by' => $user->id,
            'violation_type' => $data['violation_type'],
            'severity' => $data['severity'],
            'description' => $data['description'],
            'penalty_amount' => $data['penalty_amount'] ?? 0,
            'occurred_at' => $data['occurred_at'],
            'status' => 'open',
        ]);

        // Salary deduction — owner direct, manager via ApprovalService
        if ($request->boolean('apply_deduction') && ($data['penalty_amount'] ?? 0) > 0) {
            $employee = Employee::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->findOrFail($data['employee_id']);

            // Trước đây dùng can('approve_requests'), mà vai trò manager cũng có
            // quyền đó — nên Quản lý tự áp khấu trừ lương được, trái với quy định
            // "không duyệt lương, thưởng, phạt tiền".
            if ($this->authorityService->canActDirectly($user, 'salary_adjustment', $employee->branch_id)) {
                $salary = $this->salaryService->getOrCreateDraft(
                    $restaurantId,
                    $employee,
                    Carbon::parse($data['occurred_at'])->toDateString()
                );
                $this->salaryService->addAdjustment($salary, [
                    'employee_id' => $employee->id,
                    'type' => 'violation',
                    'amount' => (float) $data['penalty_amount'],
                    'reason' => "Vi phạm: {$data['violation_type']} — {$data['description']}",
                    'reference_id' => $violation->id,
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
                    'type' => 'violation',
                    'amount' => (float) $data['penalty_amount'],
                    'reason' => "Vi phạm: {$data['violation_type']} — {$data['description']}",
                ], $user);
            }
        }

        $msg = $request->boolean('apply_deduction')
            && ! $this->authorityService->canActDirectly($user, 'salary_adjustment', $violation->employee?->branch_id)
            ? 'Đã ghi vi phạm. Yêu cầu khấu trừ lương đã gửi Chủ nhà hàng để phê duyệt.'
            : 'Đã ghi nhận vi phạm thành công.';

        return back()->with('success', $msg);
    }

    public function verifyManagerPin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ]);

        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $ip = $request->ip();
        $cacheKey = "verify_manager_pin_fails:{$restaurantId}:".($user->id ?: "ip:{$ip}");
        $failedAttempts = (int) Cache::get($cacheKey, 0);

        if ($failedAttempts >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã nhập sai mã PIN quá 5 lần. Chức năng xác thực bị khóa tạm thời trong 15 phút.',
            ], 429);
        }

        if (($user->hasAnyRole(['owner', 'manager']) || $user->can('approve_requests')) && $user->pin_code) {
            if (Hash::check($data['pin'], $user->pin_code)) {
                Cache::forget($cacheKey);

                return response()->json([
                    'success' => true,
                    'message' => 'Xác thực PIN thành công.',
                    'verified_by' => $user->name,
                ]);
            }
        }

        $managers = User::where('restaurant_id', $restaurantId)
            ->whereNotNull('pin_code')
            ->where(function ($q) {
                $q->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', ['owner', 'manager', 'super_admin']))
                    ->orWhereHas('permissions', fn ($permQuery) => $permQuery->where('name', 'approve_requests'));
            })
            ->get();

        foreach ($managers as $m) {
            if ($m->pin_code && Hash::check($data['pin'], $m->pin_code)) {
                Cache::forget($cacheKey);

                return response()->json([
                    'success' => true,
                    'message' => 'Xác thực mã PIN Quản lý thành công.',
                    'verified_by' => $m->name,
                ]);
            }
        }

        Cache::put($cacheKey, $failedAttempts + 1, now()->addMinutes(15));

        return response()->json([
            'success' => false,
            'message' => 'Mã PIN Quản lý không chính xác hoặc không đủ quyền hạn. Còn lại '.(5 - ($failedAttempts + 1)).' lần thử.',
        ], 422);
    }
}
