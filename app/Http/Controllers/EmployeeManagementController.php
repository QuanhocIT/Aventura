<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Mail\EmployeeInvitationMail;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleRegistration;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeManagementController extends Controller
{
    use PasswordValidationRules;

    private const BRANCH_MANAGER_STAFF_ROLES = ['cashier', 'waiter', 'kitchen', 'shipper'];

    /**
     * Trang Nhân sự & Lịch biểu (Dành cho Day 3).
     */
    public function employeesPage(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeEmployeeManagement($user);
        $tenantContext = app(TenantContext::class);
        $branchId = $tenantContext->activeBranchId();
        $isBranchManager = $user->isBranchManager();
        $isWarehouseManager = $user->hasRole('warehouse_manager') && ! $user->isOwner() && ! $user->isSuperAdmin();
        $centralWarehouse = $isWarehouseManager
            ? app(\App\Services\CentralWarehouseService::class)->getCentralWarehouse((int) $user->restaurant_id)
            : null;
        $payrollBranchId = $isWarehouseManager
            ? ($user->warehouse_branch_id ?: $centralWarehouse?->id ?: $branchId)
            : $branchId;
        $viewBranchId = $payrollBranchId ?: $branchId;

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'hr_timekeeping')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'hr_timekeeping',
                'feature_label' => 'Quản lý Nhân sự',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $canViewSensitivePii = $user->hasAnyRole(['owner', 'manager']) || $user->hasRole('super_admin');

        $employees = Employee::where('restaurant_id', $user->restaurant_id)
            ->when($tenantContext->isBranchScoped() && ! $isWarehouseManager, fn ($q) => $q->where('branch_id', $branchId))
            ->when($isWarehouseManager && $payrollBranchId, fn ($q) => $q->where(function ($scope) use ($payrollBranchId) {
                $scope->where('branch_id', $payrollBranchId)
                    ->orWhereExists(function ($userQuery) use ($payrollBranchId) {
                        $userQuery->select(DB::raw('1'))
                            ->from('users')
                            ->whereColumn('users.id', 'employees.user_id')
                            ->where('users.warehouse_branch_id', $payrollBranchId);
                    });
            }))
            ->when($restaurant?->owner_user_id, fn ($q, $ownerUserId) => $q->where(function ($ownerQuery) use ($ownerUserId) {
                $ownerQuery->whereNull('user_id')->orWhere('user_id', '!=', $ownerUserId);
            }))
            ->whereDoesntHave('user.roles', fn ($q) => $q->where('name', 'owner')->where('guard_name', 'web'))
            ->with(['user.roles'])
            ->with('branch:id,name')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'employee_code' => $e->employee_code,
                'full_name' => $e->full_name,
                'email' => $e->email,
                'phone' => $e->phone,
                'job_title' => $e->job_title,
                'status' => $e->status,
                'role' => $e->user && $e->user->roles->isNotEmpty() ? $e->user->roles->first()->name : 'Staff',
                'date_of_birth' => $e->date_of_birth ? $e->date_of_birth->toDateString() : '',
                'address' => $e->address,
                'citizen_id_number' => $canViewSensitivePii ? $e->citizen_id_number : (strlen($e->citizen_id_number ?? '') > 4 ? Str::mask($e->citizen_id_number, '*', 3, -3) : '***'),
                // URL route có auth thay vì đường dẫn /storage/ công khai — ảnh CCCD
                // là PII nhạy cảm, không được phép tải bởi người chưa đăng nhập.
                'citizen_id_front_url' => $canViewSensitivePii && $e->citizen_id_front_url ? route('employees.citizen-id', [$e->id, 'front']) : null,
                'citizen_id_back_url' => $canViewSensitivePii && $e->citizen_id_back_url ? route('employees.citizen-id', [$e->id, 'back']) : null,
                'hire_date' => $e->hire_date ? $e->hire_date->toDateString() : '',
                'compensation_type' => $e->compensation_type ?? 'fixed',
                'pay_rate' => (float) ($e->pay_rate ?? 0),
                'base_salary' => (float) ($e->base_salary ?? 0),
                'rating_star' => (int) ($e->rating_count ?? 0) > 0
                    ? (float) ($e->rating_star ?? 0)
                    : null,
                'rating_count' => (int) ($e->rating_count ?? 0),
                'branch_id' => $e->branch_id,
                'branch_name' => $e->branch?->name,
                'wage_tier_id' => $e->wage_tier_id,
            ]);

        // Query or seed shifts dynamically
        $shiftsQuery = $user->restaurant_id
            ? WorkShift::where('restaurant_id', $user->restaurant_id)
                ->when($tenantContext->isBranchScoped(), fn ($q) => $q->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                }))
                ->get()
            : collect();
        if ($shiftsQuery->isEmpty() && $user->restaurant_id) {
            $defaultShifts = [
                ['name' => 'Ca Sáng', 'code' => 'CA_SANG', 'start_time' => '06:00', 'end_time' => '14:00'],
                ['name' => 'Ca Chiều', 'code' => 'CA_CHIEU', 'start_time' => '14:00', 'end_time' => '22:00'],
                ['name' => 'Ca Tối', 'code' => 'CA_TOI', 'start_time' => '18:00', 'end_time' => '23:00'],
            ];
            foreach ($defaultShifts as $ds) {
                $code = $ds['code'];
                $counter = 1;
                while (WorkShift::withTrashed()->where('restaurant_id', $user->restaurant_id)->where('code', $code)->exists()) {
                    $code = $ds['code'].'_'.$counter;
                    $counter++;
                }
                WorkShift::create(array_merge($ds, [
                    'code' => $code,
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $branchId,
                ]));
            }
            $shiftsQuery = WorkShift::where('restaurant_id', $user->restaurant_id)
                ->when($tenantContext->isBranchScoped(), fn ($q) => $q->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                }))
                ->get();
        }

        $shifts = $shiftsQuery->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'start' => substr($s->start_time, 0, 5),
            'end' => substr($s->end_time, 0, 5),
        ]);

        // Load assignments for current week
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        // Clean up un-checked-in past shifts automatically
        app(\App\Services\ScheduleAssignmentService::class)->cleanupUncheckedInPastShifts($user->restaurant_id, $branchId);

        $assignmentsQuery = ScheduleAssignment::where('restaurant_id', $user->restaurant_id)
            ->when($tenantContext->isBranchScoped(), fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->with(['employee', 'shift'])
            ->get();

        $schedules = $assignmentsQuery->map(fn ($a) => [
            'id' => $a->id,
            'day' => Carbon::parse($a->scheduled_date)->format('l'), // 'Monday', 'Tuesday', etc.
            'employee_name' => $a->employee?->full_name ?? 'Không rõ',
            'branch_name' => $a->employee?->branch?->name ?? ($a->branch?->name ?? ''),
            'shift_name' => $a->shift?->name ? explode(' (', $a->shift->name)[0] : 'Ca Mới',
            'shift_id' => $a->shift_id,
            'start_time' => $a->shift?->start_time ? substr($a->shift->start_time, 0, 5) : '',
            'end_time' => $a->shift?->end_time ? substr($a->shift->end_time, 0, 5) : '',
        ]);

        $leaveRequests = LeaveRequest::where('restaurant_id', $user->restaurant_id)
            ->when($tenantContext->isBranchScoped(), fn ($q) => $q->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('branch_id', $branchId)))
            ->with(['employee'])
            ->latest()
            ->get()
            ->map(fn ($lr) => [
                'id' => $lr->id,
                'employee_id' => $lr->employee_id,
                'employee_name' => $lr->employee?->full_name ?? 'Không rõ',
                'leave_type' => $lr->leave_type,
                'start_date' => $lr->start_date->toDateString(),
                'end_date' => $lr->end_date->toDateString(),
                'reason' => $lr->reason,
                'status' => $lr->status,
                'created_at' => $lr->created_at->format('H:i d/m/Y'),
            ]);

        $registrations = ScheduleRegistration::where('restaurant_id', $user->restaurant_id)
            ->when($tenantContext->isBranchScoped(), fn ($q) => $q->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('branch_id', $branchId)))
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->with(['employee:id,full_name', 'shift:id,name'])
            ->get()
            ->map(fn ($r) => [
                'employee_name' => $r->employee?->full_name ?? 'Không rõ',
                'shift_name' => $r->shift?->name ?? '—',
                'day' => Carbon::parse($r->scheduled_date)->format('l'),
            ]);

        $pendingSwaps = ShiftSwap::where('restaurant_id', $user->restaurant_id)
            ->when($tenantContext->isBranchScoped(), fn ($q) => $q
                ->whereHas('requesterAssignment', fn ($assignmentQuery) => $assignmentQuery->where('branch_id', $branchId))
                ->whereHas('receiverAssignment', fn ($assignmentQuery) => $assignmentQuery->where('branch_id', $branchId)))
            ->where('status', 'accepted')
            ->with([
                'requesterAssignment.employee',
                'requesterAssignment.shift',
                'receiverAssignment.employee',
                'receiverAssignment.shift',
            ])
            ->latest()
            ->get()
            ->map(fn ($sw) => [
                'id' => $sw->id,
                'notes' => $sw->notes,
                'status' => $sw->status,
                'created_at' => $sw->created_at->format('H:i d/m/Y'),
                'requester_name' => $sw->requesterAssignment?->employee?->full_name ?? 'Không rõ',
                'requester_shift' => $sw->requesterAssignment?->shift?->name ?? '—',
                'requester_date' => $sw->requesterAssignment?->scheduled_date instanceof Carbon ? $sw->requesterAssignment->scheduled_date->toDateString() : Carbon::parse($sw->requesterAssignment?->scheduled_date)->toDateString(),
                'receiver_name' => $sw->receiverAssignment?->employee?->full_name ?? 'Không rõ',
                'receiver_shift' => $sw->receiverAssignment?->shift?->name ?? '—',
                'receiver_date' => $sw->receiverAssignment?->scheduled_date instanceof Carbon ? $sw->receiverAssignment->scheduled_date->toDateString() : Carbon::parse($sw->receiverAssignment?->scheduled_date)->toDateString(),
            ]);

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $hourExpr = $isSqlite ? "strftime('%H', completed_at)" : 'HOUR(completed_at)';

        $peakHours = DB::table('orders_unified')
            ->where('restaurant_id', $user->restaurant_id)
            ->when($tenantContext->isBranchScoped(), fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(30))
            ->selectRaw("{$hourExpr} as hour, SUM(total_amount) as revenue")
            ->groupBy(DB::raw($hourExpr))
            ->get()
            ->map(function ($r) {
                $r->hour = (int) $r->hour;

                return $r;
            });
        $totalRevenuePeak = $peakHours->sum('revenue');

        $shiftWeights = [];
        foreach ($shiftsQuery as $s) {
            $startH = (int) substr($s->start_time, 0, 2);
            $endH = (int) substr($s->end_time, 0, 2);
            if ($endH <= $startH) {
                $endH += 24; // overnight
            }

            if ($totalRevenuePeak > 0) {
                $shiftRevenue = $peakHours
                    ->filter(function ($r) use ($startH, $endH) {
                        $hour = $r->hour;
                        if ($endH > 24) {
                            return ($hour >= $startH && $hour < 24) || ($hour >= 0 && $hour < ($endH - 24));
                        }

                        return $hour >= $startH && $hour < $endH;
                    })
                    ->sum('revenue');
                $pct = $shiftRevenue / $totalRevenuePeak;

                $numShifts = max(1, $shiftsQuery->count());
                $weight = $pct * $numShifts;
                $shiftWeights[$s->id] = max(0.5, min(2.0, $weight));
            } else {
                if ($startH >= 17 || $startH < 4) {
                    $shiftWeights[$s->id] = 1.5;
                } elseif ($startH >= 11) {
                    $shiftWeights[$s->id] = 1.2;
                } else {
                    $shiftWeights[$s->id] = 1.0;
                }
            }
        }

        $dailyForecasts = [];
        $conditions = ['sunny', 'rainy', 'cloudy', 'windy'];
        $startOfWeekCarbon = Carbon::parse($startOfWeek);
        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeekCarbon->copy()->addDays($i);
            $dateStr = $date->toDateString();
            $dayOfWeek = $date->format('l');

            $hash = crc32($dateStr);
            $cond = $conditions[$hash % count($conditions)];

            $temp = 25.0;
            if ($cond === 'sunny') {
                $temp = 31.0 + ($hash % 5);
            } elseif ($cond === 'rainy') {
                $temp = 20.0 + ($hash % 4);
            } elseif ($cond === 'cloudy') {
                $temp = 26.0 + ($hash % 4);
            } else {
                $temp = 24.0 + ($hash % 5);
            }

            $dayMultiplier = 1.0;
            if (in_array($dayOfWeek, ['Friday', 'Saturday', 'Sunday'])) {
                $dayMultiplier = 1.3;
            }

            $weatherMultiplier = 1.0;
            if ($cond === 'rainy') {
                $weatherMultiplier = 0.75;
            } elseif ($cond === 'sunny') {
                $weatherMultiplier = 1.1;
            }

            $totalDemandMultiplier = $dayMultiplier * $weatherMultiplier;

            if ($totalDemandMultiplier < 0.9) {
                $demandLevel = 'low';
                $demandLabel = 'Thấp';
            } elseif ($totalDemandMultiplier >= 1.25) {
                $demandLevel = 'high';
                $demandLabel = 'Cao';
            } else {
                $demandLevel = 'normal';
                $demandLabel = 'Trung bình';
            }

            $dayShiftsSuggestions = [];
            foreach ($shiftsQuery as $s) {
                $baseWeight = $shiftWeights[$s->id] ?? 1.0;
                $shiftDemand = $baseWeight * $totalDemandMultiplier;

                if ($shiftDemand < 0.9) {
                    $optimalStaff = 1;
                } elseif ($shiftDemand >= 1.5) {
                    $optimalStaff = 3;
                } else {
                    $optimalStaff = 2;
                }

                $currentStaff = $assignmentsQuery->filter(function ($a) use ($dateStr, $s) {
                    return $a->scheduled_date->toDateString() === $dateStr && $a->shift_id === $s->id;
                })->count();

                $status = 'optimal';
                if ($currentStaff < $optimalStaff) {
                    $status = 'understaffed';
                } elseif ($currentStaff > $optimalStaff) {
                    $status = 'overstaffed';
                }

                $dayShiftsSuggestions[] = [
                    'shift_id' => $s->id,
                    'shift_name' => explode(' (', $s->name)[0],
                    'optimal_staff' => $optimalStaff,
                    'current_staff' => $currentStaff,
                    'status' => $status,
                ];
            }

            $dailyForecasts[$dateStr] = [
                'date' => $dateStr,
                'condition' => $cond,
                'temperature' => (float) $temp,
                'demand_level' => $demandLevel,
                'demand_label' => $demandLabel,
                'shifts' => $dayShiftsSuggestions,
            ];
        }

        return Inertia::render('employees/Index', [
            'employees' => $employees,
            'shifts' => $shifts,
            'schedules' => $schedules,
            'registrations' => $registrations,
            'leaveRequests' => $leaveRequests,
            'pendingSwaps' => $pendingSwaps,
            'autoSchedule' => (bool) $user->restaurant->auto_schedule,
            'dailyForecasts' => $dailyForecasts,
            'branches' => ($user->isOwner() || $user->isSuperAdmin())
                ? $restaurant->branches()->where('status', 'active')->get(['id', 'name', 'warehouse_type', 'is_central_warehouse'])->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'warehouse_type' => $b->warehouse_type,
                    'is_central_warehouse' => (bool) $b->is_central_warehouse,
                    'is_central' => (bool) ($b->is_central_warehouse || $b->warehouse_type === 'central'),
                ])
                : $restaurant->branches()->whereKey($viewBranchId)->get(['id', 'name', 'warehouse_type', 'is_central_warehouse'])->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'warehouse_type' => $b->warehouse_type,
                    'is_central_warehouse' => (bool) $b->is_central_warehouse,
                    'is_central' => (bool) ($b->is_central_warehouse || $b->warehouse_type === 'central'),
                ]),
            'activeBranchId' => $viewBranchId,
            'branchScope' => $tenantContext->scope(),
            'isBranchManager' => $isBranchManager,
            'isWarehouseManager' => $isWarehouseManager,
            'canManagePayrollBudget' => $user->isOwner() || $user->isSuperAdmin(),
            'payrollBudget' => ($isBranchManager || $isWarehouseManager || ($user->isOwner() && $viewBranchId)) && $viewBranchId
                ? app(\App\Services\PayrollBudgetService::class)->summary((int) $user->restaurant_id, (int) $viewBranchId)
                : null,
            // Bậc lương do Chủ quy định. Quản lý BẮT BUỘC chọn khi tạo nhân viên
            // (không tự nhập mức). Kèm branch_id để frontend lọc theo chi nhánh.
            'wageTiers' => \App\Models\WageTier::where('restaurant_id', $restaurant->id)
                ->active()->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'branch_id', 'compensation_type', 'rate'])
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'branch_id' => $t->branch_id,
                    'compensation_type' => $t->compensation_type,
                    'rate' => (float) $t->rate,
                    'estimated_monthly' => $t->estimatedMonthly(),
                ]),
        ]);
    }

    /**
     * Thêm nhân viên mới & phân quyền.
     */
    public function storeEmployee(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeEmployeeManagement($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->where('restaurant_id', $user->restaurant_id)],
            'phone' => ['required', 'string', 'max:20'],
            'citizen_id_number' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'citizen_id_front' => ['required', 'image', 'max:2048'],
            'citizen_id_back' => ['required', 'image', 'max:2048'],
            'hire_date' => ['required', 'date'],
            'compensation_type' => ['sometimes', 'string', 'in:fixed,hourly,shift'],
            'pay_rate' => ['nullable', 'numeric', 'min:0'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'role' => ['required', 'string', 'in:cashier,kitchen,manager,waiter,shipper,inventory_staff,warehouse_staff,warehouse_manager,operations_inspector'],
            'job_title' => ['required', 'string', 'max:100'],
            // TRƯỚC ĐÂY KHÔNG CÓ — employees.branch_id/users.branch_id không
            // bao giờ được ghi, khiến việc gán nhân viên theo chi nhánh không
            // hoạt động. nullable vì form hiện tại chưa có ô chọn chi nhánh;
            // mặc định lấy theo chi nhánh đang xem của người tạo (session
            // active_branch_id qua User::getBranchIdAttribute()) khi bỏ trống.
            'branch_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('restaurant_branches', 'id')->where('restaurant_id', $user->restaurant_id),
            ],
            // Bậc lương do Chủ quy định. Quản lý BẮT BUỘC chọn (không tự nhập mức).
            'wage_tier_id' => [
                'nullable', 'integer',
                Rule::exists('wage_tiers', 'id')->where('restaurant_id', $user->restaurant_id),
            ],
        ]);

        $this->assertRoleAssignmentAllowed($user, $data['role']);

        $isWarehouseRole = in_array($data['role'], ['warehouse_staff', 'warehouse_manager'], true);
        $isInspectorRole = $data['role'] === 'operations_inspector';

        if ($isInspectorRole) {
            $branchId = null;
            $data['branch_id'] = null;
        } else {
            // $user->branch_id (owner/manager) chỉ có giá trị nếu đã từng bấm nút
            // chuyển chi nhánh trong phiên hiện tại (session active_branch_id) —
            // nếu chưa, rơi về chi nhánh đầu tiên của nhà hàng làm mặc định hợp lý.
            $tenantContext = app(TenantContext::class);
            $branchId = (int) ($data['branch_id'] ?? $tenantContext->activeBranchId() ?? $user->assignedBranchId() ?? 0);
            $data['branch_id'] = $branchId;
            if (! $user->canAccessBranch($branchId)) {
                throw ValidationException::withMessages(['branch_id' => 'Bạn không có quyền gán nhân viên vào chi nhánh này.']);
            }

            if ($tenantContext->isBranchScoped()
                && $branchId !== $tenantContext->activeBranchId()
                && ! $isWarehouseRole) {
                throw ValidationException::withMessages(['branch_id' => 'Chi nhánh nhân viên phải trùng chi nhánh hiện tại.']);
            }

            if ($isWarehouseRole) {
                $centralBranch = app(\App\Services\CentralWarehouseService::class)->getCentralWarehouse((int) $user->restaurant_id);
                if (! $centralBranch) {
                    throw ValidationException::withMessages(['branch_id' => 'Chưa thiết lập chi nhánh Kho Tổng cho nhà hàng.']);
                }

                if ($branchId !== 0 && $branchId !== (int) $centralBranch->id) {
                    throw ValidationException::withMessages([
                        'branch_id' => 'Trưởng kho Tổng và Nhân viên Kho Tổng chỉ được phép xếp làm việc tại chi nhánh Tổng kho, không được xếp vào chi nhánh kinh doanh.',
                    ]);
                }

                // Nhân sự Kho Tổng luôn thuộc ngân sách và phạm vi của Kho Tổng
                $branchId = (int) $centralBranch->id;
                $data['branch_id'] = $branchId;
            }
            if ($data['role'] === 'manager') {
                $this->assertManagerSlotAvailable($user->restaurant_id, $branchId);
            }
        }

        // ── Bậc lương & Quỹ lương chi nhánh ───────────────────────────────────
        // Nếu chọn bậc lương (do Chủ quy định) thì KHOÁ mức lương theo bậc.
        $wageTier = null;
        if (! empty($data['wage_tier_id'])) {
            $wageTierQuery = \App\Models\WageTier::where('restaurant_id', $user->restaurant_id)
                ->where('id', $data['wage_tier_id'])
                ->active();
            if ($branchId !== null) {
                $wageTierQuery->forBranch($branchId);
            }
            $wageTier = $wageTierQuery->first();
            if (! $wageTier) {
                throw ValidationException::withMessages(['wage_tier_id' => 'Bậc lương không hợp lệ cho phạm vi này.']);
            }
            $data['compensation_type'] = $wageTier->compensation_type;
            $data['pay_rate'] = (float) $wageTier->rate;
            $data['base_salary'] = (float) $wageTier->rate;
        }

        // Khi Chủ đã công bố bậc lương cho chi nhánh, Quản lý phải chọn đúng
        // bậc đó. Nếu chi nhánh chưa có bậc lương thì vẫn cho nhập mức lương
        // thủ công, nhưng chỉ trong phạm vi quỹ đã được Chủ cấp.
        $isOwner = $user->isOwner() || $user->isSuperAdmin();
        $hasPublishedWageTiers = $branchId !== null && \App\Models\WageTier::where('restaurant_id', $user->restaurant_id)
            ->forBranch($branchId)
            ->active()
            ->exists();
        if (! $isOwner && $hasPublishedWageTiers && ! $wageTier) {
            throw ValidationException::withMessages([
                'wage_tier_id' => 'Quản lý bắt buộc chọn bậc lương do Chủ quy định (không tự nhập mức lương). Nếu chi nhánh chưa có bậc lương, vui lòng liên hệ Chủ nhà hàng để thiết lập trước.',
            ]);
        }

        // ── [SECURITY P1] Kiểm tra quỹ lương bên trong DB::transaction với lockForUpdate ────────
        // Chống race condition: 2 request đồng thời đều vượt qua check trước transaction.
        $budgets = app(\App\Services\PayrollBudgetService::class);
        $monthlyWage = $wageTier
            ? $wageTier->estimatedMonthly()
            : $budgets->estimateMonthly($data['compensation_type'] ?? 'fixed', (float) ($data['pay_rate'] ?? 0), (float) ($data['base_salary'] ?? 0));

        // Disk 'local' (private) — KHÔNG dùng disk public: ảnh CCCD truy cập được
        // qua /storage/... không cần đăng nhập là lộ PII nghiêm trọng.
        $frontUrl = null;
        if ($request->hasFile('citizen_id_front')) {
            $frontUrl = $request->file('citizen_id_front')->store('citizen_ids', 'local');
        }

        $backUrl = null;
        if ($request->hasFile('citizen_id_back')) {
            $backUrl = $request->file('citizen_id_back')->store('citizen_ids', 'local');
        }

        try {
            [$newUser, $newEmployee] = DB::transaction(function () use ($data, $user, $branchId, $frontUrl, $backUrl, $budgets, $monthlyWage, $isWarehouseRole): array {
                // ── [SECURITY P1] Re-check quỹ lương bên trong lock — chống race condition ────────
                if ($branchId !== null) {
                    $budgetLocked = \App\Models\BranchPayrollBudget::where('restaurant_id', $user->restaurant_id)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();
                    if (! $budgetLocked && ! $user->isOwner() && ! $user->isSuperAdmin()) {
                        throw ValidationException::withMessages([
                            'base_salary' => 'Chưa có quỹ lương được cấp cho chi nhánh này. Vui lòng thiết lập/cấp quỹ lương chi nhánh trước khi thêm nhân sự.',
                        ]);
                    }
                    $committedLocked = $budgetLocked
                        ? $budgets->committedMonthlyWages($user->restaurant_id, $branchId)
                        : 0.0;
                    if ($budgetLocked && $committedLocked + $monthlyWage > (float) $budgetLocked->budget_amount + 0.01) {
                        $remaining = max(0.0, (float) $budgetLocked->budget_amount - $committedLocked);
                        $excess = ($committedLocked + $monthlyWage) - (float) $budgetLocked->budget_amount;
                        throw ValidationException::withMessages([
                            'base_salary' => 'Thao tác bị chặn: Thêm nhân sự này sẽ làm tổng lương vượt quỹ lương chi nhánh được cấp (vượt '.number_format($excess).'đ). Quỹ còn lại '.number_format($remaining).'đ, nhân sự mới cần '.number_format($monthlyWage).'đ/tháng.',
                        ]);
                    }
                }

                $randomPassword  = Str::random(12);
                $activationToken = Str::random(40);

                $centralBranch = app(\App\Services\CentralWarehouseService::class)->getCentralWarehouse($user->restaurant_id);

                $newUser = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => bcrypt($randomPassword),
                    'phone' => $data['phone'],
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $branchId ?: null,
                    'warehouse_branch_id' => ($isWarehouseRole && $centralBranch) ? $centralBranch->id : null,
                    'status' => 'active',
                    'email_verified_at' => null,
                    'must_change_password' => true,
                    'activation_token' => $activationToken,
                    'activation_expires_at' => now()->addDays(7),
                ]);

                $role = Role::firstOrCreate([
                    'name' => $data['role'],
                    'guard_name' => 'web',
                ]);
                $newUser->assignRole($role);

                $newEmployee = Employee::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $branchId ?: null,
                    'user_id' => $newUser->id,
                    'employee_code' => 'EMP-'.Str::upper(Str::random(5)),
                    'full_name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'date_of_birth' => $data['date_of_birth'],
                    'citizen_id_number' => $data['citizen_id_number'],
                    'citizen_id_front_url' => $frontUrl,
                    'citizen_id_back_url' => $backUrl,
                    'address' => $data['address'],
                    'hire_date' => $data['hire_date'],
                    'compensation_type' => $data['compensation_type'] ?? 'fixed',
                    'pay_rate' => $data['pay_rate'] ?? 0,
                    'base_salary' => $data['base_salary'] ?? 0,
                    'wage_tier_id' => $data['wage_tier_id'] ?? null,
                    'job_title' => $data['job_title'],
                    'employment_type' => 'full_time',
                    'status' => 'active',
                    'role_id' => $role->id,
                ]);

                if ($data['role'] === 'manager') {
                    DB::table('restaurant_branches')
                        ->where('id', $branchId)
                        ->where('restaurant_id', $user->restaurant_id)
                        ->update(['manager_user_id' => $newUser->id]);
                }

                return [$newUser, $newEmployee];
            });
        } catch (\Throwable $e) {
            if ($frontUrl) {
                Storage::disk('local')->delete($frontUrl);
            }
            if ($backUrl) {
                Storage::disk('local')->delete($backUrl);
            }

            throw $e;
        }

        // Tạo signed URL hạn dùng 3 ngày để xác nhận lời mời nhận việc
        try {
            app(\App\Services\TrainingService::class)->autoAssignRequiredCourses($newEmployee);
        } catch (\Throwable $trainingError) {
            Log::warning('Không thể tự động giao khóa đào tạo onboarding cho nhân viên mới.', [
                'employee_id' => $newEmployee->id,
                'restaurant_id' => $newEmployee->restaurant_id,
                'error' => $trainingError->getMessage(),
            ]);
        }

        $verificationUrl = URL::temporarySignedRoute(
            'employees.verify',
            now()->addDays(3),
            ['user' => $newUser->id]
        );

        // Gửi email mời nhận việc & xác thực
        try {
            Mail::to($data['email'])->send(
                new EmployeeInvitationMail(
                    $data['name'],
                    $user->restaurant->name ?? 'Aventura Restaurant',
                    $data['job_title'],
                    $verificationUrl
                )
            );
        } catch (\Exception $e) {
            logger()->error('Failed to send employee invitation email: '.$e->getMessage());

            return back()->with('error', 'Đã tạo hồ sơ nhưng không gửi được email kích hoạt. Vui lòng kiểm tra cấu hình mail và gửi lại lời mời.');
        }

        return back()->with('success', "Đã tạo tài khoản nhân viên thành công với mật khẩu mặc định là 'password'.");
    }

    /**
     * Xác thực và kích hoạt tài khoản nhân viên từ link Gmail.
     */
    public function verifyEmployee(Request $request, User $user): Response|RedirectResponse
    {
        abort_unless($user->employee, 404);

        if ($user->status === 'active') {
            return redirect()->route('login')->with('success', 'Tài khoản của bạn đã được kích hoạt trước đó. Vui lòng đăng nhập.');
        }

        if ($request->isMethod('get')) {
            return Inertia::render('auth/EmployeeActivation', [
                'employeeName' => $user->name,
                'email' => $user->email,
                'jobTitle' => $user->employee?->job_title,
                'activationUrl' => $request->fullUrl(),
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
            ]);
        }

        $data = $request->validate([
            'password' => $this->passwordRules(),
        ]);

        DB::transaction(function () use ($user, $data) {
            $user->update([
                'password' => $data['password'],
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $employee = $user->employee;
            if ($employee) {
                $employee->update([
                    'status' => 'active',
                ]);
            }
        });

        return redirect()->route('login')->with('success', 'Xác thực tài khoản và kích hoạt vai trò nhân viên thành công! Hãy đăng nhập để trải nghiệm hệ thống.');
    }

    /**
     * Kích hoạt / vô hiệu hóa tài khoản nhân viên (chỉ Owner).
     */
    public function toggleEmployeeStatus(Request $request, Employee $employee): RedirectResponse
    {
        $user = $request->user();
        abort_if($employee->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->isOwner() || $user->isSuperAdmin() || $user->canAccessBranch((int) $employee->branch_id), 403);

        if ($user->hasRole('warehouse_manager') && ! $user->isOwner() && ! $user->isSuperAdmin()) {
            $isWarehouseStaff = $employee->user?->hasRole('warehouse_staff')
                || (int) $employee->branch_id === (int) $user->warehouse_branch_id
                || (int) $employee->user?->warehouse_branch_id === (int) $user->warehouse_branch_id;
            abort_unless($isWarehouseStaff, 403, 'Trưởng kho chỉ được quản lý nhân sự thuộc bộ phận Kho.');
        }

        $newStatus = $employee->status === 'active' ? 'inactive' : 'active';
        if ($newStatus === 'active') {
            $budgetBranchId = (int) ($employee->branch_id ?: $employee->user?->warehouse_branch_id ?: 0);
            $budgets = app(\App\Services\PayrollBudgetService::class);
            $budget = $budgets->budgetFor((int) $user->restaurant_id, $budgetBranchId);
            if (! $budget) {
                throw ValidationException::withMessages([
                    'status' => 'Chưa có quỹ lương được cấp cho chi nhánh này. Vui lòng cấp quỹ lương trước khi kích hoạt nhân sự.',
                ]);
            }
            $committed = $budgets->committedMonthlyWages((int) $user->restaurant_id, $budgetBranchId);
            $empMonthlyWage = $budgets->monthlyWageOf($employee);
            if ($committed + $empMonthlyWage > (float) $budget->budget_amount + 0.01) {
                $remaining = max(0.0, (float) $budget->budget_amount - $committed);
                $excess = ($committed + $empMonthlyWage) - (float) $budget->budget_amount;
                throw ValidationException::withMessages([
                    'status' => 'Thao tác bị chặn: Không thể kích hoạt nhân viên vì tổng lương sẽ vượt quỹ chi nhánh được cấp (vượt '.number_format($excess).'đ). Quỹ còn lại '.number_format($remaining).'đ, nhân sự này cần '.number_format($empMonthlyWage).'đ/tháng. Vui lòng tăng quỹ lương chi nhánh trước.',
                ]);
            }
        }
        $employee->update(['status' => $newStatus]);

        if ($employee->user) {
            $employee->user->update(['status' => $newStatus]);
            $employee->user->increment('security_session_version');
        }

        $msg = $newStatus === 'active' ? 'Đã kích hoạt tài khoản nhân viên.' : 'Đã vô hiệu hóa tài khoản nhân viên.';

        return back()->with('success', $msg);
    }

    /**
     * Stream ảnh CCCD từ disk private — chỉ Owner/Manager cùng nhà hàng.
     */
    public function citizenIdImage(Request $request, Employee $employee, string $side): StreamedResponse
    {
        $user = $request->user();

        abort_unless(in_array($side, ['front', 'back'], true), 404);
        abort_unless($user->hasAnyRole(['owner', 'manager']) || $user->hasRole('super_admin'), 403, 'Chỉ Owner hoặc Manager mới có quyền xem ảnh CCCD nhân viên.');
        abort_if($employee->restaurant_id !== $user->restaurant_id && ! $user->hasRole('super_admin'), 403);
        abort_unless($user->isOwner() || $user->isSuperAdmin() || $user->canAccessBranch((int) $employee->branch_id), 403);

        $value = $side === 'front' ? $employee->citizen_id_front_url : $employee->citizen_id_back_url;
        $resolved = $this->resolveCitizenIdFile($value);
        abort_if($resolved === null, 404);

        [$disk, $path] = $resolved;

        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        return $storage->response($path);
    }

    /**
     * Phân giải giá trị cột citizen_id_*_url về [disk, path].
     * Giá trị mới: 'citizen_ids/x.jpg' trên disk local (private).
     * Giá trị cũ (trước khi vá): '/storage/citizen_ids/x.jpg' trên disk public.
     */
    private function resolveCitizenIdFile(?string $value): ?array
    {
        if (empty($value)) {
            return null;
        }

        if (str_starts_with($value, '/storage/')) {
            $path = substr($value, strlen('/storage/'));

            return Storage::disk('public')->exists($path) ? ['public', $path] : null;
        }

        return Storage::disk('local')->exists($value) ? ['local', $value] : null;
    }

    private function citizenIdDataUri(?string $value): ?string
    {
        $resolved = $this->resolveCitizenIdFile($value);
        if ($resolved === null) {
            return null;
        }

        [$disk, $path] = $resolved;

        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = Storage::disk($disk);
        $mime = $storage->mimeType($path) ?: 'image/jpeg';

        return "data:{$mime};base64,".base64_encode($storage->get($path));
    }

    /**
     * Xuất hồ sơ pháp lý & lý lịch trích ngang nhân sự.
     */
    public function exportEmployeeProfile(Request $request, Employee $employee): \Illuminate\Http\Response
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']) || $user->hasRole('super_admin'), 403, 'Chỉ Owner hoặc Manager mới có quyền xuất hồ sơ pháp lý & CCCD nhân viên.');
        abort_if($employee->restaurant_id !== $user->restaurant_id, 403, 'Không có quyền truy cập hồ sơ này.');
        abort_unless($user->isOwner() || $user->isSuperAdmin() || $user->canAccessBranch((int) $employee->branch_id), 403);

        $restaurantName = e($user->restaurant?->name ?? 'Aventura Restaurant');
        $name = e($employee->full_name);
        $code = e($employee->employee_code);
        $dob = $employee->date_of_birth ? $employee->date_of_birth->format('d/m/Y') : 'Chưa khai báo';
        $phone = e($employee->phone ?? 'Chưa khai báo');
        $email = e($employee->email ?? 'Chưa khai báo');
        $address = e($employee->address ?? 'Chưa khai báo');
        $citizenIdNumber = e($employee->citizen_id_number ?? 'Chưa khai báo');
        $jobTitle = e($employee->job_title ?? 'Chưa khai báo');
        $hireDate = $employee->hire_date ? $employee->hire_date->format('d/m/Y') : 'Chưa khai báo';
        $baseSalary = number_format($employee->base_salary).' VND';
        $status = $employee->status === 'active' ? 'Đang hoạt động' : ($employee->status === 'inactive' ? 'Tạm ngưng' : 'Đã chấm dứt');
        $roleName = e($employee->user ? ($employee->user->roles()->pluck('name')->first() ?? 'Staff') : 'Staff');

        // Nhúng base64 thay vì URL /storage/ — ảnh giờ nằm trên disk private,
        // và data URI hoạt động cho cả in trình duyệt lẫn render PDF server-side.
        $frontUrl = $this->citizenIdDataUri($employee->citizen_id_front_url);
        $backUrl = $this->citizenIdDataUri($employee->citizen_id_back_url);

        $frontImg = $frontUrl ? "<img src='{$frontUrl}' alt='Mặt trước CCCD' />" : "<div class='no-image'>Chưa tải ảnh mặt trước CCCD</div>";
        $backImg = $backUrl ? "<img src='{$backUrl}' alt='Mặt sau CCCD' />" : "<div class='no-image'>Chưa tải ảnh mặt sau CCCD</div>";

        $html = "
<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <title>Hồ sơ nhân viên - {$name}</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #1e293b;
            line-height: 1.5;
            background-color: #f8fafc;
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            position: relative;
        }
        .print-btn-container {
            text-align: right;
            margin-bottom: 20px;
        }
        .print-btn {
            background-color: #4f46e5;
            color: #fff;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
            transition: all 0.2s;
        }
        .print-btn:hover {
            background-color: #4338ca;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header-left h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .header-left p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #64748b;
        }
        .header-right {
            text-align: right;
        }
        .badge {
            background-color: #e0e7ff;
            color: #4338ca;
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .title {
            text-align: center;
            margin-bottom: 30px;
        }
        .title h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .title p {
            margin: 8px 0 0 0;
            font-size: 13px;
            color: #64748b;
            font-style: italic;
        }
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
            margin-bottom: 15px;
            margin-top: 30px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 30px;
        }
        .info-group {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 2px;
        }
        .cccd-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }
        .cccd-card {
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 10px;
            background-color: #f8fafc;
            text-align: center;
        }
        .cccd-card h4 {
            margin: 0 0 10px 0;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
        }
        .cccd-card img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            object-fit: contain;
        }
        .no-image {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #94a3b8;
            background-color: #f1f5f9;
            border-radius: 8px;
            border: 1px dashed #cbd5e1;
        }
        .signatures {
            margin-top: 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            text-align: center;
            gap: 50px;
        }
        .signature-title {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }
        .signature-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 5px;
        }
        .signature-space {
            height: 80px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .container {
                box-shadow: none;
                border: none;
                padding: 0;
                max-width: 100%;
            }
            .print-btn-container {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class='print-btn-container'>
        <button class='print-btn' onclick='window.print()'>In hồ sơ pháp lý</button>
    </div>
    <div class='container'>
        <div class='header'>
            <div class='header-left'>
                <h2>HỆ THỐNG QUẢN LÝ NHÀ HÀNG AVENTURA</h2>
                <p>Nền tảng SaaS quản trị vận hành thông minh</p>
            </div>
            <div class='header-right'>
                <span class='badge'>{$status}</span>
            </div>
        </div>

        <div class='title'>
            <h1>HỒ SƠ PHÁP LÝ & LÝ LỊCH TRÍCH NGANG NHÂN SỰ</h1>
            <p>Dữ liệu đã xác thực công dân - Lưu trữ bảo mật an ninh đầu vào</p>
        </div>

        <div class='section-title'>I. Thông tin cơ bản nhân sự</div>
        <div class='grid'>
            <div class='info-group'>
                <span class='info-label'>Mã nhân viên</span>
                <span class='info-value'>{$code}</span>
            </div>
            <div class='info-group'>
                <span class='info-label'>Họ và tên</span>
                <span class='info-value'>{$name}</span>
            </div>
            <div class='info-group'>
                <span class='info-label'>Ngày sinh</span>
                <span class='info-value'>{$dob}</span>
            </div>
            <div class='info-group'>
                <span class='info-label'>Số điện thoại</span>
                <span class='info-value'>{$phone}</span>
            </div>
            <div class='info-group'>
                <span class='info-label'>Địa chỉ Email</span>
                <span class='info-value'>{$email}</span>
            </div>
            <div class='info-group'>
                <span class='info-label'>Địa chỉ tạm trú</span>
                <span class='info-value'>{$address}</span>
            </div>
        </div>

        <div class='section-title'>II. Hợp đồng & Vai trò vận hành</div>
        <div class='grid'>
            <div class='info-group'>
                <span class='info-label'>Chức vụ chuyên môn</span>
                <span class='info-value'>{$jobTitle}</span>
            </div>
            <div class='info-group'>
                <span class='info-label'>Nhóm quyền hệ thống</span>
                <span class='info-value'>{$roleName}</span>
            </div>
            <div class='info-group'>
                <span class='info-label'>Ngày nhận việc</span>
                <span class='info-value'>{$hireDate}</span>
            </div>
            <div class='info-group'>
                <span class='info-label'>Mức lương cơ bản</span>
                <span class='info-value'>{$baseSalary}</span>
            </div>
        </div>

        <div class='section-title'>III. Giấy tờ tùy thân xác thực (CCCD/CMND)</div>
        <div class='info-group' style='margin-bottom: 15px;'>
            <span class='info-label'>Số định danh cá nhân / CCCD</span>
            <span class='info-value' style='font-size: 16px; color: #4f46e5;'>{$citizenIdNumber}</span>
        </div>
        <div class='cccd-container'>
            <div class='cccd-card'>
                <h4>Ảnh Mặt Trước CCCD</h4>
                {$frontImg}
            </div>
            <div class='cccd-card'>
                <h4>Ảnh Mặt Sau CCCD</h4>
                {$backImg}
            </div>
        </div>

        <div class='signatures'>
            <div>
                <span class='signature-title'>Nhân viên khai báo</span>
                <p class='signature-sub'>(Ký và ghi rõ họ tên)</p>
                <div class='signature-space'></div>
                <strong style='font-size: 14px;'>{$name}</strong>
            </div>
            <div>
                <span class='signature-title'>Đại diện nhà hàng</span>
                <p class='signature-sub'>(Ký, đóng dấu và ghi rõ họ tên)</p>
                <div class='signature-space'></div>
                <strong style='font-size: 14px;'>{$restaurantName}</strong>
            </div>
        </div>

        <div class='footer'>
            Hồ sơ được trích xuất tự động từ hệ thống Aventura lúc ".now()->format('d/m/Y H:i:s').".<br/>
            Bản quyền thuộc về nhà hàng {$restaurantName} & Aventura SaaS.
        </div>
    </div>
</body>
</html>
";

        return response($html);
    }

    /**
     * Cập nhật trạng thái nhân viên (active/inactive).
     */
    public function updateEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $user = $request->user();
        abort_if($employee->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $employee->branch_id), 403);

        if ($user->hasRole('warehouse_manager') && ! $user->isOwner() && ! $user->isSuperAdmin()) {
            $isWarehouseStaff = $employee->user?->hasRole('warehouse_staff')
                || (int) $employee->branch_id === (int) $user->warehouse_branch_id
                || (int) $employee->user?->warehouse_branch_id === (int) $user->warehouse_branch_id;
            abort_unless($isWarehouseStaff, 403, 'Trưởng kho chỉ được cập nhật nhân sự thuộc bộ phận Kho.');
        }

        $tenantContext = app(TenantContext::class);
        $oldBranchId = $employee->branch_id ? (int) $employee->branch_id : null;
        $oldRole = $employee->user?->roles?->first()?->name;

        $data = $request->validate([
            'status' => ['sometimes', 'in:active,inactive'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'job_title' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', 'string', 'in:cashier,kitchen,manager,waiter,shipper,inventory_staff,warehouse_staff,warehouse_manager,operations_inspector'],
            'compensation_type' => ['sometimes', 'string', 'in:fixed,hourly,shift'],
            'pay_rate' => ['sometimes', 'numeric', 'min:0'],
            'base_salary' => ['sometimes', 'numeric', 'min:0'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'citizen_id_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'citizen_id_front' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'citizen_id_back' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'wage_tier_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('wage_tiers', 'id')->where('restaurant_id', $user->restaurant_id),
            ],
            'branch_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('restaurant_branches', 'id')->where('restaurant_id', $user->restaurant_id),
            ],
        ]);

        if (isset($data['role'])) {
            $this->assertRoleAssignmentAllowed($user, $data['role'], $oldRole);
        }

        // Xác định branch_id mới TRƯỚC khi validate wage_tier (cần $newBranchId để check branch scope)
        $newBranchId = $oldBranchId;
        $targetRole = $data['role'] ?? $oldRole;
        $isTargetWarehouseRole = in_array($targetRole, ['warehouse_staff', 'warehouse_manager'], true);
        $isTargetInspectorRole = $targetRole === 'operations_inspector';

        if ($isTargetInspectorRole) {
            $newBranchId = null;
            $data['branch_id'] = null;
        } elseif (array_key_exists('branch_id', $data)) {
            $newBranchId = $data['branch_id'] !== null ? (int) $data['branch_id'] : null;
            if ($newBranchId === null || ! $user->canAccessBranch($newBranchId)) {
                throw ValidationException::withMessages(['branch_id' => 'Bạn không có quyền gán nhân viên vào chi nhánh này.']);
            }
            if ($tenantContext->isBranchScoped() && $newBranchId !== $tenantContext->activeBranchId() && ! $isTargetWarehouseRole) {
                throw ValidationException::withMessages(['branch_id' => 'Chi nhánh nhân viên phải trùng chi nhánh hiện tại.']);
            }
        }

        if ($isTargetWarehouseRole) {
            $centralBranch = app(\App\Services\CentralWarehouseService::class)->getCentralWarehouse((int) $user->restaurant_id);
            if (! $centralBranch) {
                throw ValidationException::withMessages(['branch_id' => 'Chưa thiết lập chi nhánh Kho Tổng cho nhà hàng.']);
            }
            if (array_key_exists('branch_id', $data) && $newBranchId !== null && $newBranchId !== (int) $centralBranch->id) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Trưởng kho Tổng và Nhân viên Kho Tổng chỉ được phép xếp làm việc tại chi nhánh Tổng kho, không được xếp vào chi nhánh kinh doanh.',
                ]);
            }
            $newBranchId = (int) $centralBranch->id;
            $data['branch_id'] = $newBranchId;
        }

        if (array_key_exists('wage_tier_id', $data) && ! empty($data['wage_tier_id'])) {
            // ── [SECURITY P0] Validate bậc lương đúng chi nhánh và đang hoạt động ─────────
            $wageTierQuery = \App\Models\WageTier::where('restaurant_id', $user->restaurant_id)
                ->where('id', $data['wage_tier_id'])
                ->active();
            if ($newBranchId !== null) {
                $wageTierQuery->forBranch($newBranchId);
            }
            $wageTier = $wageTierQuery->first();
            if (! $wageTier) {
                throw ValidationException::withMessages(['wage_tier_id' => 'Bậc lương không hợp lệ cho phạm vi này.']);
            }
            $data['compensation_type'] = $wageTier->compensation_type;
            $data['pay_rate'] = (float) $wageTier->rate;
            $data['base_salary'] = (float) $wageTier->rate;
            $data['wage_tier_id'] = $wageTier->id;
        }


        if ($request->hasFile('citizen_id_front')) {
            $employee->citizen_id_front_url = $request->file('citizen_id_front')->store('citizen_ids', 'local');
        }

        if ($request->hasFile('citizen_id_back')) {
            $employee->citizen_id_back_url = $request->file('citizen_id_back')->store('citizen_ids', 'local');
        }

        // Sync associated User Spatie roles and update role_id in employees
        $linkedUser = $employee->user ?? ($employee->user_id ? User::find($employee->user_id) : null);
        if ($linkedUser && isset($data['role'])) {
            $role = Role::firstOrCreate([
                'name' => $data['role'],
                'guard_name' => 'web',
            ]);
            $linkedUser->syncRoles([$role]);
            $employee->update(['role_id' => $role->id]);

            // [P1.7]: Tự động đồng bộ warehouse_branch_id khi chuyển sang role kho hoặc rời role kho
            if (in_array($data['role'], ['warehouse_staff', 'warehouse_manager'])) {
                $centralBranch = app(\App\Services\CentralWarehouseService::class)->getCentralWarehouse((int) $user->restaurant_id);
                if ($centralBranch) {
                    $linkedUser->update(['warehouse_branch_id' => $centralBranch->id]);
                }
            } else {
                $linkedUser->update(['warehouse_branch_id' => null]);
            }
        }

        // Sync full_name to User name
        if ($linkedUser && isset($data['full_name'])) {
            $linkedUser->update(['name' => $data['full_name']]);
        }

        $employeeData = array_filter($data, fn ($v) => $v !== null || isset($v));
        unset($employeeData['role']);
        unset($employeeData['citizen_id_front']);
        unset($employeeData['citizen_id_back']);
        if (array_key_exists('branch_id', $data)) {
            $employeeData['branch_id'] = $newBranchId;
        }

        $salaryChanged = isset($data['base_salary']) || isset($data['pay_rate']) || isset($data['compensation_type']);
        $isOwner = $user->isOwner() || $user->isSuperAdmin();
        $newBase = isset($data['base_salary']) ? (float) $data['base_salary'] : (float) $employee->base_salary;
        $newRate = isset($data['pay_rate']) ? (float) $data['pay_rate'] : (float) $employee->pay_rate;
        $newType = $data['compensation_type'] ?? $employee->compensation_type;
        $branchChanged = array_key_exists('branch_id', $data) && $newBranchId !== $oldBranchId;
        $newStatus = $data['status'] ?? $employee->status;
        $newBudgetBranchId = $newBranchId ?: (int) ($employee->user?->warehouse_branch_id ?: 0);

        if ($salaryChanged) {
            if ($isOwner) {
                DB::table('salary_change_requests')->insert([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $newBranchId,
                    'employee_id' => $employee->id,
                    'old_base_salary' => (float) $employee->base_salary,
                    'new_base_salary' => $newBase,
                    'old_pay_rate' => (float) $employee->pay_rate,
                    'new_pay_rate' => $newRate,
                    'old_compensation_type' => $employee->compensation_type,
                    'new_compensation_type' => $newType,
                    'effective_date' => now()->toDateString(),
                    'proposed_by' => $user->id,
                    'approved_by' => $user->id,
                    'status' => 'approved',
                    'notes' => 'Chủ nhà hàng phê duyệt điều chỉnh trực tiếp',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('salary_change_requests')->insert([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $newBranchId,
                    'employee_id' => $employee->id,
                    'old_base_salary' => (float) $employee->base_salary,
                    'new_base_salary' => $newBase,
                    'old_pay_rate' => (float) $employee->pay_rate,
                    'new_pay_rate' => $newRate,
                    'old_compensation_type' => $employee->compensation_type,
                    'new_compensation_type' => $newType,
                    'effective_date' => now()->toDateString(),
                    'proposed_by' => $user->id,
                    'status' => 'pending',
                    'notes' => 'Quản lý gửi đề xuất điều chỉnh lương, chờ Chủ nhà hàng phê duyệt',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                unset($employeeData['base_salary']);
                unset($employeeData['pay_rate']);
                unset($employeeData['compensation_type']);
            }
        }

        if ($employee->user) {
            $employee->user->update(['branch_id' => $newBranchId]);
        }

        $employee->update($employeeData);
        $employee->save();

        if (array_key_exists('status', $data) && $employee->user) {
            $employee->user->update(['status' => $data['status']]);
            $employee->user->increment('security_session_version');
        }

        $newRole = $data['role'] ?? $oldRole;
        if ($newRole === 'manager' && $newBranchId) {
            $this->assertManagerSlotAvailable($user->restaurant_id, $newBranchId, $employee->user_id);
        }
        if ($oldRole === 'manager' && ($newRole !== 'manager' || $oldBranchId !== $newBranchId)) {
            DB::table('restaurant_branches')
                ->where('restaurant_id', $user->restaurant_id)
                ->where('manager_user_id', $employee->user_id)
                ->update(['manager_user_id' => null]);
        }
        if ($newRole === 'manager' && $newBranchId) {
            DB::table('restaurant_branches')
                ->where('id', $newBranchId)
                ->where('restaurant_id', $user->restaurant_id)
                ->update(['manager_user_id' => $employee->user_id]);
        }

        return back()->with('success', 'Đã cập nhật thông tin nhân viên.');
    }

    /**
     * Đồng bộ hóa danh sách ca làm việc của nhà hàng.
     */
    public function syncShifts(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeEmployeeManagement($user);
        $tenantContext = app(TenantContext::class);
        $branchId = $tenantContext->activeBranchId();
        if ($branchId === null) {
            throw ValidationException::withMessages([
                'branch_id' => 'Hãy chọn chi nhánh hiện tại trước khi cấu hình ca làm.',
            ]);
        }
        if (! $user->canAccessBranch($branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Bạn không có quyền truy cập chi nhánh này.',
            ]);
        }
        $data = $request->validate([
            'shifts' => ['required', 'array'],
            'shifts.*.name' => ['required', 'string', 'max:100'],
            'shifts.*.start' => ['required', 'string'],
            'shifts.*.end' => ['required', 'string'],
        ]);

        $existingIds = [];
        $shiftIds = collect($data['shifts'])->pluck('id')->filter(fn ($id) => is_numeric($id) && $id < 1000000000)->toArray();
        $existingShifts = WorkShift::withTrashed()
            ->where('restaurant_id', $user->restaurant_id)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->whereIn('id', $shiftIds)
            ->get()
            ->keyBy('id');

        foreach ($data['shifts'] as $index => $s) {
            $shift = null;
            if (isset($s['id']) && is_numeric($s['id']) && $s['id'] < 1000000000) {
                $shift = $existingShifts->get($s['id']);
            }

            if ($shift) {
                if ($shift->trashed()) {
                    $shift->restore();
                }

                // [SECURITY P1] Không cho sửa giờ ca đã có chấm công — tránh làm sai lệch tính lương.
                $timeChanged = ($shift->start_time !== $s['start']) || ($shift->end_time !== $s['end']);
                if ($timeChanged) {
                    $hasAttendance = \App\Models\ScheduleAssignment::where('work_shift_id', $shift->id)
                        ->where('status', 'completed')
                        ->exists();
                    if ($hasAttendance) {
                        throw ValidationException::withMessages([
                            "shifts.$index.start" => "Ca '{$shift->name}' đã có chấm công hoàn tất, không thể thay đổi giờ làm để tránh sai lệch tính lương. Hãy tạo ca mới nếu cần thay đổi.",
                        ]);
                    }
                }

                $shift->update([
                    'branch_id'  => $branchId,
                    'name'       => $s['name'],
                    'start_time' => $s['start'],
                    'end_time'   => $s['end'],
                    'status'     => 'active',
                ]);

            } else {
                $slug = Str::upper(Str::slug($s['name'], '_'));
                $baseCode = 'SHIFT_'.($slug ?: 'WORK');
                $code = $baseCode.'_'.($index + 1);
                $counter = 1;

                while (WorkShift::withTrashed()->where('restaurant_id', $user->restaurant_id)->where('code', $code)->exists()) {
                    $code = $baseCode.'_'.($index + 1).'_'.$counter;
                    $counter++;
                }

                $shift = WorkShift::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $branchId,
                    'name' => $s['name'],
                    'code' => $code,
                    'start_time' => $s['start'],
                    'end_time' => $s['end'],
                    'status' => 'active',
                ]);
            }
            $existingIds[] = $shift->id;
        }

        // Delete shifts that are not in the payload
        WorkShift::where('restaurant_id', $user->restaurant_id)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->whereNotIn('id', $existingIds)
            ->delete();

        return back()->with('success', 'Đã lưu cấu hình ca làm việc mới.');
    }

    private function assertManagerSlotAvailable(int $restaurantId, int $branchId, ?int $exceptUserId = null): void
    {
        $occupied = DB::table('restaurant_branches')
            ->where('restaurant_id', $restaurantId)
            ->where('id', $branchId)
            ->whereNotNull('manager_user_id')
            ->when($exceptUserId !== null, fn ($q) => $q->where('manager_user_id', '!=', $exceptUserId))
            ->exists();

        if ($occupied) {
            throw ValidationException::withMessages([
                'role' => 'Chi nhánh này đã có quản lý. Vui lòng gỡ quản lý hiện tại trước khi gán người mới.',
            ]);
        }
    }

    private function authorizeEmployeeManagement(User $user): void
    {
        abort_unless(
            $user->isSuperAdmin() || $user->can('manage_employees') || $user->hasRole('warehouse_manager'),
            403,
            'Bạn không có quyền quản lý nhân viên.'
        );
    }

    private function assertRoleAssignmentAllowed(User $user, string $role, ?string $existingRole = null): void
    {
        $isOwnerOrAdmin = $user->isSuperAdmin() || $user->isOwner();

        if (! $isOwnerOrAdmin) {
            if ($user->hasRole('warehouse_manager')) {
                if ($role !== 'warehouse_staff' && $role !== $existingRole) {
                    throw ValidationException::withMessages([
                        'role' => 'Tài khoản Trưởng kho tổng chỉ được phép tạo hoặc phân quyền nhân sự với vai trò Nhân viên Kho Tổng.',
                    ]);
                }
            } elseif ($user->isBranchManager()) {
                if (! in_array($role, self::BRANCH_MANAGER_STAFF_ROLES, true) && $role !== $existingRole) {
                    throw ValidationException::withMessages([
                        'role' => 'Tài khoản quản lý chỉ được tạo hoặc phân quyền cho Thu ngân, Nhân viên Order và Nhân viên Bếp.',
                    ]);
                }
            }
        }
    }
}
