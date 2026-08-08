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

    /**
     * Trang Nhân sự & Lịch biểu (Dành cho Day 3).
     */
    public function employeesPage(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeEmployeeManagement($user);
        $tenantContext = app(TenantContext::class);
        $branchId = $tenantContext->activeBranchId();

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
            ->when($tenantContext->isBranchScoped(), fn ($q) => $q->where('branch_id', $branchId))
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
                'rating_star' => (float) ($e->rating_star ?? 5.0),
                'rating_count' => (int) ($e->rating_count ?? 0),
                'branch_id' => $e->branch_id,
                'branch_name' => $e->branch?->name,
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
            'branches' => $user->isOwner()
                ? $restaurant->branches()->where('status', 'active')->get(['id', 'name'])
                : $restaurant->branches()->whereKey($branchId)->get(['id', 'name']),
            'activeBranchId' => $branchId,
            'branchScope' => $tenantContext->scope(),
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
            'role' => ['required', 'string', 'in:cashier,kitchen,manager,waiter'],
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
        ]);

        // $user->branch_id (owner/manager) chỉ có giá trị nếu đã từng bấm nút
        // chuyển chi nhánh trong phiên hiện tại (session active_branch_id) —
        // nếu chưa, rơi về chi nhánh đầu tiên của nhà hàng làm mặc định hợp lý.
        $tenantContext = app(TenantContext::class);
        $branchId = (int) ($data['branch_id'] ?? $tenantContext->activeBranchId() ?? $user->assignedBranchId() ?? 0);
        $data['branch_id'] = $branchId;
        if (! $user->canAccessBranch($branchId)) {
            throw ValidationException::withMessages(['branch_id' => 'Bạn không có quyền gán nhân viên vào chi nhánh này.']);
        }
        if ($tenantContext->isBranchScoped() && $branchId !== $tenantContext->activeBranchId()) {
            throw ValidationException::withMessages(['branch_id' => 'Chi nhánh nhân viên phải trùng chi nhánh hiện tại.']);
        }
        if ($data['role'] === 'manager') {
            $this->assertManagerSlotAvailable($user->restaurant_id, $branchId);
        }

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
            [$newUser, $newEmployee] = DB::transaction(function () use ($data, $user, $branchId, $frontUrl, $backUrl): array {
                // Mật khẩu mặc định là 'password', tài khoản kích hoạt ngay để đăng nhập.
                $newUser = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => bcrypt('password'),
                    'phone' => $data['phone'],
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $branchId ?: null,
                    'status' => 'active',
                    'email_verified_at' => now(),
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
        $this->authorizeEmployeeManagement($user);
        abort_if($employee->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->isOwner() || $user->isSuperAdmin() || $user->canAccessBranch((int) $employee->branch_id), 403);

        $newStatus = $employee->status === 'active' ? 'inactive' : 'active';
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
        $this->authorizeEmployeeManagement($user);
        abort_if($employee->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $employee->branch_id), 403);

        $tenantContext = app(TenantContext::class);
        $oldBranchId = $employee->branch_id ? (int) $employee->branch_id : null;
        $oldRole = $employee->user?->roles?->first()?->name;

        $data = $request->validate([
            'status' => ['sometimes', 'in:active,inactive'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'job_title' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', 'string', 'in:cashier,kitchen,manager,waiter'],
            'compensation_type' => ['sometimes', 'string', 'in:fixed,hourly,shift'],
            'pay_rate' => ['sometimes', 'numeric', 'min:0'],
            'base_salary' => ['sometimes', 'numeric', 'min:0'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'citizen_id_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'citizen_id_front' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'citizen_id_back' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'branch_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('restaurant_branches', 'id')->where('restaurant_id', $user->restaurant_id),
            ],
        ]);

        $newBranchId = $oldBranchId;
        if (array_key_exists('branch_id', $data)) {
            $newBranchId = $data['branch_id'] !== null ? (int) $data['branch_id'] : null;
            if ($newBranchId === null || ! $user->canAccessBranch($newBranchId)) {
                throw ValidationException::withMessages(['branch_id' => 'Bạn không có quyền gán nhân viên vào chi nhánh này.']);
            }
            if ($tenantContext->isBranchScoped() && $newBranchId !== $tenantContext->activeBranchId()) {
                throw ValidationException::withMessages(['branch_id' => 'Chi nhánh nhân viên phải trùng chi nhánh hiện tại.']);
            }
        }

        if ($employee->user) {
            $employee->user->update(['branch_id' => $newBranchId]);
        }

        if ($request->hasFile('citizen_id_front')) {
            $employee->citizen_id_front_url = $request->file('citizen_id_front')->store('citizen_ids', 'local');
        }

        if ($request->hasFile('citizen_id_back')) {
            $employee->citizen_id_back_url = $request->file('citizen_id_back')->store('citizen_ids', 'local');
        }

        // Sync associated User Spatie roles and update role_id in employees
        if ($employee->user && isset($data['role'])) {
            $role = Role::firstOrCreate([
                'name' => $data['role'],
                'guard_name' => 'web',
            ]);
            $employee->user->syncRoles([$role]);
            $employee->update(['role_id' => $role->id]);
        }

        // Sync full_name to User name
        if ($employee->user && isset($data['full_name'])) {
            $employee->user->update(['name' => $data['full_name']]);
        }

        $employeeData = array_filter($data, fn ($v) => $v !== null || isset($v));
        unset($employeeData['role']);
        unset($employeeData['citizen_id_front']);
        unset($employeeData['citizen_id_back']);
        if (array_key_exists('branch_id', $data)) {
            $employeeData['branch_id'] = $newBranchId;
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
                $shift->update([
                    'branch_id' => $branchId,
                    'name' => $s['name'],
                    'start_time' => $s['start'],
                    'end_time' => $s['end'],
                    'status' => 'active',
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
        abort_unless($user->isSuperAdmin() || $user->can('manage_employees'), 403, 'Bạn không có quyền quản lý nhân viên.');
    }
}
