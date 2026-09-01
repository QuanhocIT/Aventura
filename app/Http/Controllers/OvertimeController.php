<?php

namespace App\Http\Controllers;

use App\Mail\OvertimeRequestMail;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\OvertimeHoliday;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Notifications\OvertimeRequestNotification;
use App\Services\OvertimePolicyService;
use App\Services\OvertimeAttendanceService;
use App\Support\Tenant\TenantContext;
use App\Services\QuotaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;
        $canManage = $this->canManage($user);
        $restaurant = $employee?->restaurant ?: $user->restaurant;
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'hr_timekeeping')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'hr_timekeeping',
                'feature_label' => 'Tăng ca & chấm công',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn phí',
                'required_plan' => 'Cơ bản',
            ]);
        }
        $branchId = app(TenantContext::class)->activeBranchId();
        $policy = app(OvertimePolicyService::class);
        $attendance = app(OvertimeAttendanceService::class);

        $requests = OvertimeRequest::where('restaurant_id', $user->restaurant_id)
            ->when(! $canManage, fn ($q) => $q->where('employee_id', $employee?->id ?: 0))
            ->when($canManage && $branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['employee:id,full_name,employee_code,branch_id', 'requester:id,name'])
            ->latest('scheduled_date')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (OvertimeRequest $overtime) => $this->serialize($overtime))
            ->values();

        $employees = $canManage
            ? Employee::where('restaurant_id', $user->restaurant_id)
                ->where('status', 'active')
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_code', 'branch_id', 'compensation_type', 'pay_rate', 'base_salary'])
            : collect();

        return Inertia::render('overtime/Index', [
            'requests' => $requests,
            'employees' => $employees->map(function (Employee $item) use ($policy) {
                return [
                    'id' => $item->id,
                    'full_name' => $item->full_name,
                    'employee_code' => $item->employee_code,
                    'overtime_hourly_rate' => $policy->hourlyRate($item),
                    'compensation_type' => $item->compensation_type ?? 'fixed',
                ];
            })->values(),
            'canManage' => $canManage,
            'employeeId' => $employee?->id,
            'currentEmployee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                'overtime_hourly_rate' => $policy->hourlyRate($employee),
                'compensation_type' => $employee->compensation_type ?? 'fixed',
            ] : null,
            'policy' => [
                'types' => collect($policy->types())->map(fn (array $type, string $value) => [
                    'value' => $value,
                    'label' => $type['label'],
                    'description' => $type['description'],
                    'multiplier' => $restaurant ? $policy->multiplier($restaurant, $value, $employee, today()->toDateString()) : 1.50,
                ])->values(),
                'max_daily_hours' => OvertimePolicyService::MAX_DAILY_HOURS,
                'max_monthly_hours' => OvertimePolicyService::MAX_MONTHLY_HOURS,
            ],
            'policySettings' => $this->policySettings($user, $employee, $restaurant),
            'report' => $canManage ? $this->reportData($user, $branchId) : null,
            'attendanceSettings' => $employee ? $attendance->settings($employee, today()->toDateString()) : null,
            'holidays' => $canManage ? OvertimeHoliday::where('restaurant_id', $user->restaurant_id)->where('is_active', true)->whereBetween('holiday_date', [today()->toDateString(), today()->addMonths(12)->toDateString()])->orderBy('holiday_date')->get(['id', 'holiday_date', 'name', 'multiplier'])->map(fn (OvertimeHoliday $holiday) => ['id' => $holiday->id, 'holiday_date' => $holiday->holiday_date->toDateString(), 'name' => $holiday->name, 'multiplier' => (float) ($holiday->multiplier ?? 0)])->values() : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $canManage = $this->canManage($user);
        $data = $this->validated($request, $canManage);
        $employee = $canManage
            ? $this->findEmployeeForManager($user, (int) $data['employee_id'])
            : $user->employee;

        if (! $employee) {
            throw ValidationException::withMessages(['employee_id' => 'Không tìm thấy hồ sơ nhân viên hợp lệ.']);
        }

        $overtime = $this->createRequest($user, $employee, $data, $canManage ? 'management' : 'employee');

        return back()->with('success', $canManage
            ? 'Đã gửi yêu cầu tăng ca đột xuất. Nhân viên đã được thông báo qua Gmail.'
            : 'Đã gửi đơn xin tăng ca, đang chờ cấp quản lý duyệt.');
    }

    public function approve(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $user = $request->user();
        $this->assertManagerCanAccess($user, $overtimeRequest);
        abort_unless($overtimeRequest->status === 'pending', 422, 'Yêu cầu tăng ca không còn ở trạng thái chờ duyệt.');
        $policy = app(OvertimePolicyService::class);
        $settings = $policy->policyFor($overtimeRequest->employee, $overtimeRequest->scheduled_date);
        abort_if(
            $overtimeRequest->request_source === 'management'
            && ($settings['require_employee_acceptance'] ?? true)
            && $overtimeRequest->employee_response !== 'accepted',
            422,
            'Yêu cầu tăng ca đột xuất phải được nhân viên xác nhận trước.',
        );
        $this->validateBusinessRules($overtimeRequest);

        $overtimeRequest->update(array_merge([
            'status' => 'approved',
            'hours_approved' => $overtimeRequest->hours_requested,
            'approved_by' => $user->id,
            'employee_response' => $overtimeRequest->employee_response ?: 'accepted',
            'payroll_status' => 'pending_attendance',
            'actual_amount' => 0,
            'workflow_status' => 'approved',
            'reviewed_at' => now(),
            'last_action_at' => now(),
            'last_action_by' => $user->id,
        ], $this->approvalSnapshot($overtimeRequest)));

        AuditLog::log('overtime_approved', 'updated', $overtimeRequest, null, ['hours' => $overtimeRequest->hours_approved]);

        $this->notifyEmployee($overtimeRequest, 'approved', 'Yêu cầu tăng ca ngày '.$overtimeRequest->scheduled_date?->format('d/m/Y').' đã được duyệt.');

        return back()->with('success', 'Đã duyệt yêu cầu tăng ca.');
    }

    public function reject(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $user = $request->user();
        $this->assertManagerCanAccess($user, $overtimeRequest);
        abort_unless($overtimeRequest->status === 'pending', 422, 'Yêu cầu tăng ca không còn ở trạng thái chờ xử lý.');

        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);
        $overtimeRequest->update([
            'status' => 'rejected',
            'hours_approved' => 0,
            'approved_by' => $user->id,
            'employee_response' => $overtimeRequest->request_source === 'management' ? 'declined' : $overtimeRequest->employee_response,
            'rejection_reason' => $data['rejection_reason'] ?? 'Không được duyệt.',
            'payroll_status' => 'not_eligible',
            'actual_amount' => 0,
            'workflow_status' => 'rejected',
            'reviewed_at' => now(),
            'last_action_at' => now(),
            'last_action_by' => $user->id,
        ]);
        AuditLog::log('overtime_rejected', 'updated', $overtimeRequest, null, ['reason' => $overtimeRequest->rejection_reason]);
        $this->notifyEmployee($overtimeRequest, 'rejected', 'Yêu cầu tăng ca ngày '.$overtimeRequest->scheduled_date?->format('d/m/Y').' đã bị từ chối.');

        return back()->with('success', 'Đã từ chối yêu cầu tăng ca.');
    }

    public function accept(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $this->assertEmployeeOwns($request, $overtimeRequest);
        abort_unless($overtimeRequest->request_source === 'management' && $overtimeRequest->status === 'pending', 422, 'Yêu cầu này không chờ nhân viên xác nhận.');

        $overtimeRequest->update([
            'employee_response' => 'accepted',
            'employee_responded_at' => now(),
            'workflow_status' => 'awaiting_manager',
            'last_action_at' => now(),
            'last_action_by' => $request->user()->id,
        ]);
        AuditLog::log('overtime_employee_accepted', 'updated', $overtimeRequest);
        $this->notifyRequester($overtimeRequest, 'accepted', 'Nhân viên '.$overtimeRequest->employee?->full_name.' đã đồng ý tăng ca.');

        return back()->with('success', 'Đã xác nhận tăng ca. Đang chờ quản lý duyệt để tính lương.');
    }

    public function decline(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $this->assertEmployeeOwns($request, $overtimeRequest);
        abort_unless($overtimeRequest->request_source === 'management' && $overtimeRequest->status === 'pending', 422, 'Yêu cầu này không chờ nhân viên xác nhận.');

        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);
        $overtimeRequest->update([
            'status' => 'rejected',
            'hours_approved' => 0,
            'employee_response' => 'declined',
            'employee_responded_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? 'Nhân viên từ chối yêu cầu tăng ca.',
            'payroll_status' => 'not_eligible',
            'actual_amount' => 0,
            'workflow_status' => 'rejected',
            'last_action_at' => now(),
            'last_action_by' => $request->user()->id,
        ]);
        AuditLog::log('overtime_employee_declined', 'updated', $overtimeRequest, null, ['reason' => $overtimeRequest->rejection_reason]);
        $this->notifyRequester($overtimeRequest, 'declined', 'Nhân viên '.$overtimeRequest->employee?->full_name.' đã từ chối yêu cầu tăng ca.');

        return back()->with('success', 'Đã từ chối yêu cầu tăng ca.');
    }

    public function portalIndex(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Không tìm thấy hồ sơ nhân viên.');

        return response()->json([
            'success' => true,
            'requests' => OvertimeRequest::where('employee_id', $employee->id)
                ->where('branch_id', $employee->branch_id)
                ->with('requester:id,name')
                ->latest('scheduled_date')
                ->get()
                ->map(fn (OvertimeRequest $item) => $this->serialize($item)),
        ]);
    }

    public function portalStore(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;
        abort_unless($employee, 403, 'Không tìm thấy hồ sơ nhân viên.');
        $data = $this->validated($request, false);
        $overtime = $this->createRequest($user, $employee, $data, 'employee');

        return response()->json(['success' => true, 'message' => 'Đã gửi đơn xin tăng ca.', 'request' => $this->serialize($overtime)], 201);
    }

    public function portalRespond(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless(
            $employee
            && $overtimeRequest->employee_id === $employee->id
            && (int) $overtimeRequest->branch_id === (int) $employee->branch_id,
            403,
        );
        abort_unless($overtimeRequest->request_source === 'management' && $overtimeRequest->status === 'pending', 422);
        $action = $request->validate(['action' => ['required', 'in:accept,decline']])['action'];

        if ($action === 'accept') {
            $overtimeRequest->update([
                'employee_response' => 'accepted',
                'employee_responded_at' => now(),
                'workflow_status' => 'awaiting_manager',
                'last_action_at' => now(),
                'last_action_by' => $request->user()->id,
            ]);
        } else {
            $overtimeRequest->update([
                'status' => 'rejected',
                'employee_response' => 'declined',
                'employee_responded_at' => now(),
                'rejection_reason' => 'Nhân viên từ chối yêu cầu tăng ca.',
                'payroll_status' => 'not_eligible',
                'workflow_status' => 'rejected',
                'last_action_at' => now(),
                'last_action_by' => $request->user()->id,
            ]);
        }

        return response()->json(['success' => true, 'message' => $action === 'accept' ? 'Đã chấp nhận tăng ca, đang chờ quản lý duyệt.' : 'Đã từ chối tăng ca.', 'request' => $this->serialize($overtimeRequest->fresh())]);
    }

    public function checkIn(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $this->assertEmployeeOwns($request, $overtimeRequest);
        abort_unless($overtimeRequest->status === 'approved', 422, 'Chỉ đơn OT đã được duyệt mới có thể chấm công.');
        abort_unless($overtimeRequest->scheduled_date?->isToday(), 422, 'Chỉ có thể check-in OT đúng ngày đã đăng ký.');
        abort_if($overtimeRequest->check_in_at, 422, 'Đơn OT này đã được check-in.');

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'is_mock' => ['nullable', 'boolean'],
            'qr_code' => ['nullable', 'string', 'max:100'],
            'check_in_photo' => ['nullable', 'string', 'max:4000000'],
        ]);

        $now = now();
        $start = $overtimeRequest->scheduled_start_at ?? $now->copy()->subMinute();
        $end = $overtimeRequest->scheduled_end_at ?? $now->copy()->addHours((float) $overtimeRequest->hours_approved);
        abort_unless($now->greaterThanOrEqualTo($start->copy()->subHours(2)) && $now->lessThanOrEqualTo($end), 422, 'Chưa đến hoặc đã quá khung giờ check-in OT.');

        $meta = app(OvertimeAttendanceService::class)->verify($overtimeRequest->employee, $overtimeRequest, $data, 'check-in');
        $snapshot = $this->approvalSnapshot($overtimeRequest);
        $overtimeRequest->update(array_merge([
            'check_in_at' => $now,
            'payroll_status' => 'in_progress',
            'workflow_status' => 'in_progress',
            'check_in_latitude' => $meta['latitude'],
            'check_in_longitude' => $meta['longitude'],
            'gps_distance_meters' => $meta['distance'],
            'check_in_method' => $meta['method'],
            'check_in_photo_path' => $meta['photo_path'],
            'last_action_at' => now(),
            'last_action_by' => $request->user()->id,
        ], $overtimeRequest->hourly_rate ? [] : $snapshot));

        AuditLog::log('overtime_check_in', 'updated', $overtimeRequest, null, ['method' => $meta['method'], 'distance' => $meta['distance']]);

        return back()->with('success', 'Đã check-in ca tăng ca.');
    }

    public function checkOut(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $this->assertEmployeeOwns($request, $overtimeRequest);
        abort_unless($overtimeRequest->status === 'approved', 422, 'Chỉ đơn OT đã được duyệt mới có thể chấm công.');
        abort_unless($overtimeRequest->check_in_at, 422, 'Bạn cần check-in OT trước khi check-out.');
        abort_if($overtimeRequest->check_out_at, 422, 'Đơn OT này đã được check-out.');

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'is_mock' => ['nullable', 'boolean'],
            'qr_code' => ['nullable', 'string', 'max:100'],
            'check_out_photo' => ['nullable', 'string', 'max:4000000'],
        ]);

        $now = now();
        $workedHours = round(min(
            (float) $overtimeRequest->hours_approved,
            $overtimeRequest->check_in_at->diffInSeconds($now) / 3600,
        ), 2);
        $snapshot = $this->approvalSnapshot($overtimeRequest);
        $hourlyRate = (float) ($overtimeRequest->hourly_rate ?: ($snapshot['hourly_rate'] ?? 0));
        $multiplier = (float) ($overtimeRequest->overtime_multiplier ?: ($snapshot['overtime_multiplier'] ?? 1.5));
        $actualAmount = round($workedHours * $hourlyRate * $multiplier, 2);
        $meta = app(OvertimeAttendanceService::class)->verify($overtimeRequest->employee, $overtimeRequest, $data, 'check-out');

        $overtimeRequest->update(array_merge([
            'check_out_at' => $now,
            'worked_hours' => $workedHours,
            'actual_amount' => $actualAmount,
            'payroll_status' => $workedHours > 0 ? 'ready' : 'not_ready',
            'workflow_status' => $workedHours > 0 ? 'ready_for_payroll' : 'needs_review',
            'check_out_latitude' => $meta['latitude'],
            'check_out_longitude' => $meta['longitude'],
            'gps_distance_meters' => $meta['distance'] ?: $overtimeRequest->gps_distance_meters,
            'check_out_method' => $meta['method'],
            'check_out_photo_path' => $meta['photo_path'],
            'attendance_verified_at' => now(),
            'last_action_at' => now(),
            'last_action_by' => $request->user()->id,
        ], $overtimeRequest->hourly_rate ? [] : $snapshot));

        AuditLog::log('overtime_check_out', 'updated', $overtimeRequest, null, ['worked_hours' => $workedHours, 'actual_amount' => $actualAmount]);

        return back()->with('success', 'Đã check-out OT. Giờ thực tế sẽ được đối soát khi tạo bảng lương.');
    }

    public function withdraw(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $this->assertEmployeeOwns($request, $overtimeRequest);
        abort_unless($overtimeRequest->status === 'pending', 422, 'Đơn OT này không còn có thể rút.');
        abort_unless(in_array($overtimeRequest->workflow_status, ['submitted', 'awaiting_manager'], true), 422, 'Đơn OT đã được xử lý, không thể rút.');

        $reason = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:500']])['cancel_reason'] ?? 'Nhân viên rút đơn OT.';
        $overtimeRequest->update([
            'status' => 'rejected',
            'workflow_status' => 'withdrawn',
            'payroll_status' => 'not_eligible',
            'hours_approved' => 0,
            'rejection_reason' => $reason,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
            'cancel_reason' => $reason,
            'last_action_at' => now(),
            'last_action_by' => $request->user()->id,
        ]);
        AuditLog::log('overtime_withdrawn', 'updated', $overtimeRequest, null, ['reason' => $reason]);

        return back()->with('success', 'Đã rút đơn xin tăng ca.');
    }

    public function cancel(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $user = $request->user();
        $this->assertManagerCanAccess($user, $overtimeRequest);
        abort_unless(! in_array($overtimeRequest->workflow_status, ['included', 'paid'], true), 422, 'Đơn OT đã vào bảng lương, không thể huỷ tại đây.');
        $reason = $request->validate(['cancel_reason' => ['required', 'string', 'max:500']])['cancel_reason'];

        $overtimeRequest->update([
            'status' => 'rejected',
            'workflow_status' => 'cancelled',
            'payroll_status' => 'not_eligible',
            'hours_approved' => 0,
            'rejection_reason' => $reason,
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
            'cancel_reason' => $reason,
            'last_action_at' => now(),
            'last_action_by' => $user->id,
        ]);
        AuditLog::log('overtime_cancelled', 'updated', $overtimeRequest, null, ['reason' => $reason]);

        return back()->with('success', 'Đã huỷ yêu cầu tăng ca.');
    }

    public function reconcile(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $user = $request->user();
        $this->assertManagerCanAccess($user, $overtimeRequest);
        abort_unless($overtimeRequest->status === 'approved', 422, 'Chỉ đơn OT đã duyệt mới được đối soát.');

        $data = $request->validate([
            'worked_hours' => ['required', 'numeric', 'min:0', 'max:'.$overtimeRequest->hours_approved],
            'actual_amount' => ['nullable', 'numeric', 'min:0'],
            'adjustment_reason' => ['required', 'string', 'max:500'],
        ]);
        $hours = round((float) $data['worked_hours'], 2);
        $amount = array_key_exists('actual_amount', $data) && $data['actual_amount'] !== null
            ? round((float) $data['actual_amount'], 2)
            : round($hours * (float) $overtimeRequest->hourly_rate * (float) $overtimeRequest->overtime_multiplier, 2);

        $overtimeRequest->update([
            'worked_hours' => $hours,
            'actual_amount' => $amount,
            'manager_adjusted_hours' => $hours,
            'manager_adjusted_amount' => $amount,
            'adjustment_reason' => $data['adjustment_reason'],
            'attendance_verified_by' => $user->id,
            'attendance_verified_at' => now(),
            'payroll_status' => $hours > 0 ? 'ready' : 'not_ready',
            'workflow_status' => $hours > 0 ? 'ready_for_payroll' : 'needs_review',
            'last_action_at' => now(),
            'last_action_by' => $user->id,
        ]);
        AuditLog::log('overtime_reconciled', 'updated', $overtimeRequest, null, ['worked_hours' => $hours, 'actual_amount' => $amount, 'reason' => $data['adjustment_reason']]);

        return back()->with('success', 'Đã đối soát giờ và tiền OT.');
    }

    public function updatePolicy(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);
        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'normal_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'night_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'rest_day_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'holiday_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'max_daily_hours' => ['required', 'numeric', 'min:.25', 'max:12'],
            'max_weekly_hours' => ['required', 'numeric', 'min:.25', 'max:60'],
            'max_monthly_hours' => ['required', 'numeric', 'min:.25', 'max:200'],
            'minimum_rest_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'require_gps' => ['nullable', 'boolean'],
            'require_qr' => ['nullable', 'boolean'],
            'require_photo' => ['nullable', 'boolean'],
            'employee_can_request' => ['nullable', 'boolean'],
            'require_employee_acceptance' => ['nullable', 'boolean'],
        ]);

        $policy = OvertimePolicy::withoutGlobalScopes()->where('restaurant_id', $user->restaurant_id)
            ->whereNull('branch_id')->whereNull('role_id')->whereNull('employee_id')
            ->whereDate('effective_from', $data['effective_from'])->first();
        if ($policy) {
            $policy->update($data + ['is_active' => true]);
        } else {
            $policy = OvertimePolicy::create($data + [
                'restaurant_id' => $user->restaurant_id,
                'name' => 'Chính sách tăng ca mặc định',
                'is_active' => true,
            ]);
        }
        AuditLog::log('overtime_policy_updated', 'updated', $policy, null, $data);

        return back()->with('success', 'Đã cập nhật chính sách tăng ca.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);
        $data = $request->validate([
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:150'],
            'multiplier' => ['nullable', 'numeric', 'min:1', 'max:10'],
        ]);
        $holiday = OvertimeHoliday::updateOrCreate([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => null,
            'holiday_date' => $data['holiday_date'],
        ], $data + ['is_active' => true]);
        AuditLog::log('overtime_holiday_updated', 'updated', $holiday, null, $data);

        return back()->with('success', 'Đã cập nhật ngày lễ tính OT.');
    }

    public function destroyHoliday(Request $request, OvertimeHoliday $overtimeHoliday): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);
        abort_if($overtimeHoliday->restaurant_id !== $user->restaurant_id, 403);
        $overtimeHoliday->update(['is_active' => false]);
        AuditLog::log('overtime_holiday_disabled', 'updated', $overtimeHoliday);

        return back()->with('success', 'Đã tắt ngày lễ tính OT.');
    }

    public function export(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);
        $branchId = app(TenantContext::class)->activeBranchId();
        $rows = $this->reportQuery($user, $branchId)->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nhân viên', 'Mã NV', 'Ngày', 'Loại OT', 'Nguồn', 'Trạng thái', 'Giờ duyệt', 'Giờ thực tế', 'Tiền dự kiến', 'Tiền thực tế', 'Trạng thái bảng lương']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row->employee?->full_name, $row->employee?->employee_code, $row->scheduled_date, $row->overtime_type, $row->request_source, $row->workflow_status, $row->hours_approved, $row->worked_hours, $row->estimated_amount, $row->actual_amount, $row->payroll_status]);
            }
            fclose($handle);
        }, 'bao-cao-tang-ca-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function createRequest(User $requester, Employee $employee, array $data, string $source): OvertimeRequest
    {
        $exists = OvertimeRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereDate('scheduled_date', $data['scheduled_date'])
            ->whereIn('status', ['pending', 'approved'])
            ->whereNotIn('workflow_status', ['cancelled', 'withdrawn', 'rejected', 'paid'])
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['scheduled_date' => 'Nhân viên đã có một yêu cầu tăng ca cho ngày này.']);
        }

        $policy = app(OvertimePolicyService::class);
        $restaurant = $employee->restaurant;
        $hours = (float) $data['hours_requested'];
        $type = $data['overtime_type'] ?? 'normal';
        if ($type === 'normal' && $policy->holidayFor($employee, $data['scheduled_date'])) {
            $type = 'holiday';
        }
        $settings = $policy->policyFor($employee, $data['scheduled_date']);
        if ($source === 'employee' && ! ($settings['employee_can_request'] ?? true)) {
            throw ValidationException::withMessages(['employee_id' => 'Chính sách hiện tại không cho phép nhân viên tự đăng ký OT.']);
        }
        abort_if(
            \App\Models\Salary::isPeriodLocked((int) $employee->restaurant_id, $employee->id, $data['scheduled_date']),
            422,
            'Kỳ lương của ngày tăng ca đã được chốt, không thể đăng ký thêm OT.',
        );

        $window = $policy->window($data['scheduled_date'], $data['start_time'] ?? null, $data['end_time'] ?? null, $hours);
        $windowHours = round($window['start']->diffInSeconds($window['end']) / 3600, 2);
        if (abs($windowHours - $hours) > 0.01) {
            throw ValidationException::withMessages([
                'hours_requested' => 'Số giờ OT phải khớp với khoảng thời gian bắt đầu và kết thúc đã chọn.',
            ]);
        }
        $policy->validateRequest($employee, $data['scheduled_date'], $window['start'], $window['end'], $hours);
        $quote = $policy->quote($employee, $restaurant, $hours, $type, $data['scheduled_date']);
        $workflow = $source === 'management'
            ? (($settings['require_employee_acceptance'] ?? true) ? 'awaiting_employee' : 'submitted')
            : 'submitted';

        $overtime = OvertimeRequest::create([
            'restaurant_id' => $employee->restaurant_id,
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'requested_by' => $requester->id,
            'scheduled_date' => $data['scheduled_date'],
            'hours_requested' => $hours,
            'hours_approved' => 0,
            'reason' => $data['reason'] ?? null,
            'request_source' => $source,
            'overtime_type' => $type,
            'scheduled_start_at' => $window['start'],
            'scheduled_end_at' => $window['end'],
            'hourly_rate' => $quote['hourly_rate'],
            'overtime_multiplier' => $quote['multiplier'],
            'estimated_amount' => $quote['estimated_amount'],
            'actual_amount' => 0,
            'payroll_status' => 'not_ready',
            'employee_response' => $source === 'management'
                ? (($settings['require_employee_acceptance'] ?? true) ? 'pending' : 'accepted')
                : 'submitted',
            'status' => 'pending',
            'workflow_status' => $workflow,
            'last_action_at' => now(),
            'last_action_by' => $requester->id,
        ])->load(['employee', 'requester']);

        AuditLog::log('overtime_requested', 'created', $overtime, null, [
            'source' => $source,
            'hours' => $hours,
            'estimated_amount' => $quote['estimated_amount'],
        ]);

        if ($source === 'management') {
            $this->notifyEmployee($overtime, 'requested', 'Quản lý đã gửi yêu cầu tăng ca đột xuất, vui lòng xác nhận.');
            $email = $employee->email ?: $employee->user?->email;
            if ($email) {
                try {
                    Mail::to($email)->send(new OvertimeRequestMail($overtime));
                } catch (\Throwable $exception) {
                    Log::error('Không thể gửi email yêu cầu tăng ca.', ['overtime_request_id' => $overtime->id, 'error' => $exception->getMessage()]);
                }
            }
        } else {
            User::where('restaurant_id', $employee->restaurant_id)
                ->where(function ($query) use ($employee) {
                    $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', ['owner', 'warehouse_manager']))
                        ->orWhere(function ($managerQuery) use ($employee) {
                            $managerQuery->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'manager'))
                                ->where(function ($branchQuery) use ($employee) {
                                    $branchQuery->where('branch_id', $employee->branch_id)
                                        ->orWhere('warehouse_branch_id', $employee->branch_id)
                                        ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery->where('branch_id', $employee->branch_id));
                                });
                        });
                })
                ->get()
                ->each(fn (User $manager) => $manager->notify(new OvertimeRequestNotification(
                    $overtime,
                    'requested',
                    'Nhân viên '.$employee->full_name.' đã xin tăng ca ngày '.Carbon::parse($overtime->scheduled_date)->format('d/m/Y').'.',
                )));
        }

        return $overtime;
    }

    private function validated(Request $request, bool $manager): array
    {
        return $request->validate([
            'employee_id' => [$manager ? 'required' : 'nullable', 'integer'],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'hours_requested' => ['required', 'numeric', 'min:0.25', 'max:12'],
            'overtime_type' => ['nullable', 'string', 'in:normal,night,rest_day,holiday'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function validateQuota(Employee $employee, float $hours, string $date, ?int $ignoreId = null): void
    {
        if ($hours > OvertimePolicyService::MAX_DAILY_HOURS) {
            throw ValidationException::withMessages([
                'hours_requested' => 'Mỗi ngày chỉ được đăng ký tối đa '.OvertimePolicyService::MAX_DAILY_HOURS.' giờ OT theo chính sách hiện tại.',
            ]);
        }

        $query = OvertimeRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId));

        $dailyHours = (float) (clone $query)->whereDate('scheduled_date', $date)->sum('hours_requested');
        if ($dailyHours + $hours > OvertimePolicyService::MAX_DAILY_HOURS) {
            throw ValidationException::withMessages([
                'hours_requested' => 'Tổng OT của nhân viên trong ngày này không được vượt quá '.OvertimePolicyService::MAX_DAILY_HOURS.' giờ.',
            ]);
        }

        $month = Carbon::parse($date);
        $monthlyHours = (float) (clone $query)
            ->whereDate('scheduled_date', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('scheduled_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->sum('hours_requested');
        if ($monthlyHours + $hours > OvertimePolicyService::MAX_MONTHLY_HOURS) {
            throw ValidationException::withMessages([
                'hours_requested' => 'Tổng OT trong tháng không được vượt quá '.OvertimePolicyService::MAX_MONTHLY_HOURS.' giờ.',
            ]);
        }
    }

    private function approvalSnapshot(OvertimeRequest $overtime): array
    {
        $employee = $overtime->employee;
        $restaurant = $employee?->restaurant;
        if (! $employee || ! $restaurant) {
            return [];
        }

        $quote = app(OvertimePolicyService::class)->quote(
            $employee,
            $restaurant,
            (float) $overtime->hours_requested,
            $overtime->overtime_type ?: 'normal',
            $overtime->scheduled_date?->toDateString(),
        );

        return [
            'hourly_rate' => $quote['hourly_rate'],
            'overtime_multiplier' => $quote['multiplier'],
            'estimated_amount' => $quote['estimated_amount'],
        ];
    }

    private function validateBusinessRules(OvertimeRequest $overtime): void
    {
        $policy = app(OvertimePolicyService::class);
        $window = $overtime->scheduled_start_at && $overtime->scheduled_end_at
            ? ['start' => $overtime->scheduled_start_at, 'end' => $overtime->scheduled_end_at]
            : $policy->window(
                $overtime->scheduled_date->toDateString(),
                null,
                null,
                (float) $overtime->hours_requested,
            );
        $policy->validateRequest(
            $overtime->employee,
            $overtime->scheduled_date->toDateString(),
            $window['start'],
            $window['end'],
            (float) $overtime->hours_requested,
            $overtime->id,
        );
    }

    private function policySettings(User $user, ?Employee $employee, $restaurant): array
    {
        if ($employee) {
            return app(OvertimePolicyService::class)->policyFor($employee, today()->toDateString());
        }

        $normal = max(1.0, (float) ($restaurant?->ot_multiplier ?? 1.50));

        return [
            'id' => null,
            'name' => 'Chính sách mặc định',
            'normal_multiplier' => $normal,
            'night_multiplier' => max($normal, 2.0),
            'rest_day_multiplier' => max($normal, 2.0),
            'holiday_multiplier' => max($normal, 3.0),
            'max_daily_hours' => OvertimePolicyService::MAX_DAILY_HOURS,
            'max_weekly_hours' => OvertimePolicyService::MAX_WEEKLY_HOURS,
            'max_monthly_hours' => OvertimePolicyService::MAX_MONTHLY_HOURS,
            'minimum_rest_hours' => OvertimePolicyService::MINIMUM_REST_HOURS,
            'require_gps' => true,
            'require_qr' => false,
            'require_photo' => false,
            'employee_can_request' => true,
            'require_employee_acceptance' => true,
        ];
    }

    private function reportQuery(User $user, ?int $branchId = null)
    {
        return OvertimeRequest::withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('scheduled_date', [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString()])
            ->with('employee:id,full_name,employee_code,branch_id')
            ->latest('scheduled_date');
    }

    private function reportData(User $user, ?int $branchId = null): array
    {
        $requests = $this->reportQuery($user, $branchId)->get();

        return [
            'period' => today()->format('Y-m'),
            'total_requests' => $requests->count(),
            'approved_hours' => round((float) $requests->where('status', 'approved')->sum('hours_approved'), 2),
            'worked_hours' => round((float) $requests->sum('worked_hours'), 2),
            'estimated_amount' => round((float) $requests->sum('estimated_amount'), 2),
            'actual_amount' => round((float) $requests->sum('actual_amount'), 2),
            'pending_reconciliation' => $requests->whereIn('workflow_status', ['approved', 'in_progress', 'needs_review'])->count(),
            'by_type' => $requests->groupBy('overtime_type')->map(fn ($items, $type) => [
                'type' => $type,
                'requests' => $items->count(),
                'hours' => round((float) $items->sum('worked_hours'), 2),
                'amount' => round((float) $items->sum('actual_amount'), 2),
            ])->values()->all(),
            'by_employee' => $requests->groupBy('employee_id')->map(fn ($items) => [
                'employee_name' => $items->first()->employee?->full_name,
                'employee_code' => $items->first()->employee?->employee_code,
                'hours' => round((float) $items->sum('worked_hours'), 2),
                'amount' => round((float) $items->sum('actual_amount'), 2),
            ])->sortByDesc('amount')->values()->take(10)->all(),
        ];
    }

    private function canManage(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isOwner() || $user->hasAnyRole(['manager', 'warehouse_manager']);
    }

    private function findEmployeeForManager(User $user, int $employeeId): Employee
    {
        $employee = Employee::withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->findOrFail($employeeId);
        abort_unless($user->canAccessBranch($employee->branch_id), 403, 'Bạn không có quyền thao tác nhân viên ở chi nhánh này.');

        if (app(TenantContext::class)->isBranchScoped()) {
            abort_unless((int) app(TenantContext::class)->activeBranchId() === (int) $employee->branch_id, 403, 'Employee is outside the active branch.');
        }

        return $employee;
    }

    private function assertManagerCanAccess(User $user, OvertimeRequest $overtime): void
    {
        abort_unless($this->canManage($user), 403);
        abort_if($overtime->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch($overtime->branch_id), 403);
        if (app(TenantContext::class)->isBranchScoped()) {
            abort_unless((int) app(TenantContext::class)->activeBranchId() === (int) $overtime->branch_id, 403, 'Overtime request is outside the active branch.');
        }
    }

    private function assertEmployeeOwns(Request $request, OvertimeRequest $overtime): void
    {
        $employee = $request->user()->employee;
        abort_unless(
            $employee
            && $employee->id === $overtime->employee_id
            && (int) $overtime->branch_id === (int) $employee->branch_id,
            403,
        );
    }

    private function notifyEmployee(OvertimeRequest $overtime, string $action, string $message): void
    {
        if ($overtime->employee?->user) {
            $overtime->employee->user->notify(new OvertimeRequestNotification($overtime, $action, $message));
        }
    }

    private function notifyRequester(OvertimeRequest $overtime, string $action, string $message): void
    {
        if ($overtime->requester) {
            $overtime->requester->notify(new OvertimeRequestNotification($overtime, $action, $message));
        }
    }

    private function serialize(OvertimeRequest $overtime): array
    {
        return [
            'id' => $overtime->id,
            'employee_id' => $overtime->employee_id,
            'employee_name' => $overtime->employee?->full_name,
            'employee_code' => $overtime->employee?->employee_code,
            'scheduled_date' => $overtime->scheduled_date?->toDateString(),
            'hours_requested' => (float) $overtime->hours_requested,
            'hours_approved' => (float) $overtime->hours_approved,
            'reason' => $overtime->reason,
            'status' => $overtime->status,
            'request_source' => $overtime->request_source,
            'employee_response' => $overtime->employee_response,
            'rejection_reason' => $overtime->rejection_reason,
            'requester_name' => $overtime->requester?->name,
            'overtime_type' => $overtime->overtime_type ?: 'normal',
            'overtime_type_label' => app(OvertimePolicyService::class)->types()[$overtime->overtime_type ?: 'normal']['label'] ?? 'Ngày thường',
            'scheduled_start_at' => $overtime->scheduled_start_at?->format('H:i'),
            'scheduled_end_at' => $overtime->scheduled_end_at?->format('H:i'),
            'check_in_at' => $overtime->check_in_at?->toIso8601String(),
            'check_out_at' => $overtime->check_out_at?->toIso8601String(),
            'worked_hours' => (float) ($overtime->worked_hours ?? 0),
            'hourly_rate' => (float) ($overtime->hourly_rate ?? 0),
            'overtime_multiplier' => (float) ($overtime->overtime_multiplier ?? 0),
            'estimated_amount' => (float) ($overtime->estimated_amount ?? 0),
            'actual_amount' => (float) ($overtime->actual_amount ?? 0),
            'payroll_status' => $overtime->payroll_status ?? 'not_ready',
            'workflow_status' => $overtime->workflow_status ?? $overtime->status,
            'salary_id' => $overtime->salary_id,
            'manager_adjusted_hours' => (float) ($overtime->manager_adjusted_hours ?? 0),
            'manager_adjusted_amount' => (float) ($overtime->manager_adjusted_amount ?? 0),
            'attendance_verified_at' => $overtime->attendance_verified_at?->toIso8601String(),
            'check_in_method' => $overtime->check_in_method,
            'check_out_method' => $overtime->check_out_method,
            'gps_distance_meters' => (float) ($overtime->gps_distance_meters ?? 0),
        ];
    }
}
