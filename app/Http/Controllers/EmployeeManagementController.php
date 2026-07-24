<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleRegistration;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeManagementController extends Controller
{    /**
     * Trang Nhân sự & Lịch biểu (Dành cho Day 3).
     */
    public function employeesPage(Request $request): Response
    {
        $user = $request->user();

        $restaurant = $user->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'hr_timekeeping')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'hr_timekeeping',
                'feature_label' => 'Quản lý Nhân sự',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $employees = Employee::where('restaurant_id', $user->restaurant_id)
            ->with(['user.roles'])
            ->get()
            ->map(fn ($e) => [
                'id'                   => $e->id,
                'employee_code'        => $e->employee_code,
                'full_name'            => $e->full_name,
                'email'                => $e->email,
                'phone'                => $e->phone,
                'job_title'            => $e->job_title,
                'status'               => $e->status,
                'role'                 => $e->user && $e->user->roles->isNotEmpty() ? $e->user->roles->first()->name : 'Staff',
                'date_of_birth'        => $e->date_of_birth ? $e->date_of_birth->toDateString() : '',
                'address'              => $e->address,
                'citizen_id_number'    => $e->citizen_id_number,
                'citizen_id_front_url' => $e->citizen_id_front_url,
                'citizen_id_back_url'  => $e->citizen_id_back_url,
                'hire_date'            => $e->hire_date ? $e->hire_date->toDateString() : '',
                'compensation_type'    => $e->compensation_type ?? 'fixed',
                'pay_rate'             => (float) ($e->pay_rate ?? 0),
                'base_salary'          => (float) ($e->base_salary ?? 0),
                'rating_star'          => (float) ($e->rating_star ?? 5.0),
                'rating_count'         => (int) ($e->rating_count ?? 0),
            ]);

        // Query or seed shifts dynamically
        $shiftsQuery = $user->restaurant_id
            ? WorkShift::where('restaurant_id', $user->restaurant_id)->get()
            : collect();
        if ($shiftsQuery->isEmpty() && $user->restaurant_id) {
            $defaultShifts = [
                ['name' => 'Ca Sáng (06:00 - 14:00)', 'code' => 'CA_SANG', 'start_time' => '06:00', 'end_time' => '14:00'],
                ['name' => 'Ca Chiều (14:00 - 22:00)', 'code' => 'CA_CHIEU', 'start_time' => '14:00', 'end_time' => '22:00'],
                ['name' => 'Ca Tối (18:00 - 23:00)', 'code' => 'CA_TOI', 'start_time' => '18:00', 'end_time' => '23:00'],
            ];
            foreach ($defaultShifts as $ds) {
                WorkShift::create(array_merge($ds, ['restaurant_id' => $user->restaurant_id]));
            }
            $shiftsQuery = WorkShift::where('restaurant_id', $user->restaurant_id)->get();
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

        $assignmentsQuery = ScheduleAssignment::where('restaurant_id', $user->restaurant_id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->with(['employee', 'shift'])
            ->get();

        $schedules = $assignmentsQuery->map(fn ($a) => [
            'day' => Carbon::parse($a->scheduled_date)->format('l'), // 'Monday', 'Tuesday', etc.
            'employee_name' => $a->employee?->full_name ?? 'Không rõ',
            'shift_name' => $a->shift?->name ? explode(' (', $a->shift->name)[0] : 'Ca Mới',
        ]);

        $leaveRequests = LeaveRequest::where('restaurant_id', $user->restaurant_id)
            ->with(['employee'])
            ->latest()
            ->get()
            ->map(fn ($lr) => [
                'id'            => $lr->id,
                'employee_id'   => $lr->employee_id,
                'employee_name' => $lr->employee?->full_name ?? 'Không rõ',
                'leave_type'    => $lr->leave_type,
                'start_date'    => $lr->start_date->toDateString(),
                'end_date'      => $lr->end_date->toDateString(),
                'reason'        => $lr->reason,
                'status'        => $lr->status,
                'created_at'    => $lr->created_at->format('H:i d/m/Y'),
            ]);

        $registrations = ScheduleRegistration::where('restaurant_id', $user->restaurant_id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->with(['employee:id,full_name', 'shift:id,name'])
            ->get()
            ->map(fn ($r) => [
                'employee_name' => $r->employee?->full_name ?? 'Không rõ',
                'shift_name' => $r->shift?->name ?? '—',
                'day' => Carbon::parse($r->scheduled_date)->format('l'),
            ]);

        $pendingSwaps = ShiftSwap::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'accepted')
            ->with([
                'requesterAssignment.employee',
                'requesterAssignment.shift',
                'receiverAssignment.employee',
                'receiverAssignment.shift'
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

        $isSqlite = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite';
        $hourExpr = $isSqlite ? "strftime('%H', completed_at)" : "HOUR(completed_at)";

        $peakHours = \Illuminate\Support\Facades\DB::table('orders_unified')
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(30))
            ->selectRaw("{$hourExpr} as hour, SUM(total_amount) as revenue")
            ->groupBy(\Illuminate\Support\Facades\DB::raw($hourExpr))
            ->get()
            ->map(function ($r) {
                $r->hour = (int) $r->hour;
                return $r;
            });
        $totalRevenuePeak = $peakHours->sum('revenue');

        $shiftWeights = [];
        foreach ($shiftsQuery as $s) {
            $startH = (int) substr($s->start_time, 0, 2);
            $endH   = (int) substr($s->end_time,   0, 2);
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
            'employees'      => $employees,
            'shifts'         => $shifts,
            'schedules'      => $schedules,
            'registrations'  => $registrations,
            'leaveRequests'  => $leaveRequests,
            'pendingSwaps'   => $pendingSwaps,
            'autoSchedule'   => (bool) $user->restaurant->auto_schedule,
            'dailyForecasts' => $dailyForecasts,
        ]);
    }

    /**
     * Thêm nhân viên mới & phân quyền.
     */
    public function storeEmployee(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', \Illuminate\Validation\Rule::unique('users')->where('restaurant_id', $user->restaurant_id)],
            'phone'             => ['required', 'string', 'max:20'],
            'citizen_id_number' => ['required', 'string', 'max:20'],
            'address'           => ['required', 'string', 'max:500'],
            'date_of_birth'     => ['required', 'date', 'before:today'],
            'citizen_id_front'  => ['required', 'image', 'max:2048'],
            'citizen_id_back'   => ['required', 'image', 'max:2048'],
            'hire_date'         => ['required', 'date'],
            'compensation_type' => ['sometimes', 'string', 'in:fixed,hourly,shift'],
            'pay_rate'          => ['nullable', 'numeric', 'min:0'],
            'base_salary'       => ['required', 'numeric', 'min:0'],
            'role'              => ['required', 'string', 'in:cashier,kitchen,manager,waiter'],
            'job_title'         => ['required', 'string', 'max:100'],
        ]);

        $frontUrl = null;
        if ($request->hasFile('citizen_id_front')) {
            $path = $request->file('citizen_id_front')->store('citizen_ids', 'public');
            $frontUrl = '/storage/' . $path;
        }

        $backUrl = null;
        if ($request->hasFile('citizen_id_back')) {
            $path = $request->file('citizen_id_back')->store('citizen_ids', 'public');
            $backUrl = '/storage/' . $path;
        }

        // Tạo User mới cho nhân viên ở trạng thái Chờ xác nhận
        $tempPassword = Str::random(10);
        $newUser = User::create([
            'name'               => $data['name'],
            'email'              => $data['email'],
            'password'           => bcrypt($tempPassword),
            'phone'              => $data['phone'],
            'restaurant_id'      => $user->restaurant_id,
            'status'             => 'inactive',
            'email_verified_at'  => null,
        ]);

        // Gán Role qua Spatie
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name'       => $data['role'],
            'guard_name' => 'web',
        ]);
        $newUser->assignRole($role);

        // Tạo hồ sơ nhân viên Employee ở trạng thái Chờ xác nhận
        $newEmployee = Employee::create([
            'restaurant_id'        => $user->restaurant_id,
            'user_id'              => $newUser->id,
            'employee_code'        => 'EMP-' . Str::upper(Str::random(5)),
            'full_name'            => $data['name'],
            'phone'                => $data['phone'],
            'email'                => $data['email'],
            'date_of_birth'        => $data['date_of_birth'],
            'citizen_id_number'    => $data['citizen_id_number'],
            'citizen_id_front_url' => $frontUrl,
            'citizen_id_back_url'  => $backUrl,
            'address'              => $data['address'],
            'hire_date'            => $data['hire_date'],
            'compensation_type'    => $data['compensation_type'] ?? 'fixed',
            'pay_rate'             => $data['pay_rate'] ?? 0,
            'base_salary'          => $data['base_salary'] ?? 0,
            'job_title'            => $data['job_title'],
            'employment_type'      => 'full_time',
            'status'               => 'inactive',
            'role_id'              => $role->id,
        ]);

        // Tạo signed URL hạn dùng 3 ngày để xác nhận lời mời nhận việc
        $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'employees.verify',
            now()->addDays(3),
            ['user' => $newUser->id]
        );

        // Gửi email mời nhận việc & xác thực
        try {
            \Illuminate\Support\Facades\Mail::to($data['email'])->send(
                new \App\Mail\EmployeeInvitationMail(
                    $data['name'],
                    $user->restaurant->name ?? 'Aventura Restaurant',
                    $data['job_title'],
                    $verificationUrl
                )
            );
        } catch (\Exception $e) {
            // Log error or silently fallback, but don't crash
            logger()->error('Failed to send employee invitation email: ' . $e->getMessage());
        }

        return back()
            ->with('success', "Đã gửi email xác thực lời mời nhận việc đến hộp thư {$data['email']}. Nhân viên cần bấm xác nhận qua Gmail để kích hoạt tài khoản đăng nhập.")
            ->with('temp_password', "Mật khẩu tạm thời: {$tempPassword} — Mật khẩu này sẽ có hiệu lực ngay khi nhân viên hoàn tất xác nhận.");
    }

    /**
     * Xác thực và kích hoạt tài khoản nhân viên từ link Gmail.
     */
    public function verifyEmployee(Request $request, User $user): RedirectResponse
    {
        if ($user->status === 'active') {
            return redirect()->route('login')->with('success', 'Tài khoản của bạn đã được kích hoạt trước đó. Vui lòng đăng nhập.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
            $user->update([
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
        abort_unless($request->user()->can('manage_employees'), 403);
        abort_if($employee->restaurant_id !== $request->user()->restaurant_id, 403);

        $newStatus = $employee->status === 'active' ? 'inactive' : 'active';
        $employee->update(['status' => $newStatus]);

        if ($employee->user) {
            $employee->user->update(['status' => $newStatus]);
        }

        $msg = $newStatus === 'active' ? 'Đã kích hoạt tài khoản nhân viên.' : 'Đã vô hiệu hóa tài khoản nhân viên.';
        return back()->with('success', $msg);
    }

    /**
     * Xuất hồ sơ pháp lý & lý lịch trích ngang nhân sự.
     */
    public function exportEmployeeProfile(Request $request, Employee $employee): \Illuminate\Http\Response
    {
        $user = $request->user();
        abort_if($employee->restaurant_id !== $user->restaurant_id, 403, 'Không có quyền truy cập hồ sơ này.');

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
        $baseSalary = number_format($employee->base_salary) . ' VND';
        $status = $employee->status === 'active' ? 'Đang hoạt động' : ($employee->status === 'inactive' ? 'Tạm ngưng' : 'Đã chấm dứt');
        $roleName = e($employee->user ? ($employee->user->roles()->pluck('name')->first() ?? 'Staff') : 'Staff');

        $frontUrl = $employee->citizen_id_front_url ? asset($employee->citizen_id_front_url) : null;
        $backUrl = $employee->citizen_id_back_url ? asset($employee->citizen_id_back_url) : null;

        $frontImg = $frontUrl ? "<img src='{$frontUrl}' alt='Mặt trước CCCD' />" : "<div class='no-image'>Chưa tải ảnh mặt trước CCCD</div>";
        $backImg = $backUrl ? "<img src='{$backUrl}' alt='Mặt sau CCCD' />" : "<div class='no-image'>Chưa tải ảnh mặt sau CCCD</div>";

        $html = "
<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <title>Hồ sơ nhân viên - {$name}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap');
        body {
            font-family: 'Inter', sans-serif;
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
            Hồ sơ được trích xuất tự động từ hệ thống Aventura lúc " . now()->format('d/m/Y H:i:s') . ".<br/>
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

        $data = $request->validate([
            'status'            => ['sometimes', 'in:active,inactive'],
            'full_name'         => ['sometimes', 'string', 'max:255'],
            'phone'             => ['sometimes', 'nullable', 'string', 'max:20'],
            'job_title'         => ['sometimes', 'string', 'max:100'],
            'role'              => ['sometimes', 'string', 'in:cashier,kitchen,manager,waiter'],
            'compensation_type' => ['sometimes', 'string', 'in:fixed,hourly,shift'],
            'pay_rate'          => ['sometimes', 'numeric', 'min:0'],
            'base_salary'       => ['sometimes', 'numeric', 'min:0'],
            'date_of_birth'     => ['sometimes', 'nullable', 'date', 'before:today'],
            'address'           => ['sometimes', 'nullable', 'string', 'max:500'],
            'citizen_id_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'citizen_id_front'  => ['sometimes', 'nullable', 'image', 'max:2048'],
            'citizen_id_back'   => ['sometimes', 'nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('citizen_id_front')) {
            $path = $request->file('citizen_id_front')->store('citizen_ids', 'public');
            $employee->citizen_id_front_url = '/storage/' . $path;
        }

        if ($request->hasFile('citizen_id_back')) {
            $path = $request->file('citizen_id_back')->store('citizen_ids', 'public');
            $employee->citizen_id_back_url = '/storage/' . $path;
        }

        // Sync associated User Spatie roles and update role_id in employees
        if ($employee->user && isset($data['role'])) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate([
                'name' => $data['role'],
                'guard_name' => 'web'
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
        $employee->update($employeeData);
        $employee->save();

        return back()->with('success', 'Đã cập nhật thông tin nhân viên.');
    }

    /**
     * Đồng bộ hóa danh sách ca làm việc của nhà hàng.
     */
    public function syncShifts(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'shifts' => ['required', 'array'],
            'shifts.*.name' => ['required', 'string', 'max:100'],
            'shifts.*.start' => ['required', 'string'],
            'shifts.*.end' => ['required', 'string'],
        ]);

        $existingIds = [];
        $shiftIds = collect($data['shifts'])->pluck('id')->filter()->toArray();
        $existingShifts = WorkShift::where('restaurant_id', $user->restaurant_id)
            ->whereIn('id', $shiftIds)
            ->get()
            ->keyBy('id');

        foreach ($data['shifts'] as $index => $s) {
            $code = 'SHIFT_' . Str::upper(Str::slug($s['name'], '_')) . '_' . ($index + 1);

            $shift = null;
            if (isset($s['id']) && is_numeric($s['id']) && $s['id'] < 1000000) {
                $shift = $existingShifts->get($s['id']);
            }

            if ($shift) {
                $shift->update([
                    'name' => $s['name'],
                    'start_time' => $s['start'],
                    'end_time' => $s['end'],
                ]);
            } else {
                $shift = WorkShift::create([
                    'restaurant_id' => $user->restaurant_id,
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
            ->whereNotIn('id', $existingIds)
            ->delete();

        return back()->with('success', 'Đã lưu cấu hình ca làm việc mới.');
    }

}

