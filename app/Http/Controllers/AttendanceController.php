<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ScheduleAssignment;
use App\Models\ViolationReport;
use App\Notifications\CheckInConfirmedNotification;
use App\Services\ApprovalService;
use App\Services\AttendanceCancellationService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    /**
     * Nhân viên tự Check-in vào ca.
     */
    public function checkIn(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        $restaurant = $employee->restaurant;

        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'hr_timekeeping')) {
            return back()->withErrors(['feature' => 'Gói dịch vụ hiện tại không hỗ trợ tính năng Chấm công. Vui lòng nâng cấp gói.']);
        }

        // 1. Geolocation (GPS) Validation - Ưu tiên tọa độ của chi nhánh nhân viên làm việc
        $targetLat = $employee->branch?->latitude ?? $restaurant?->latitude;
        $targetLng = $employee->branch?->longitude ?? $restaurant?->longitude;
        $allowedRadius = $employee->branch?->checkin_radius_meters ?? ($restaurant?->checkin_radius_meters ?? 100);

        if ($targetLat && $targetLng) {
            $clientLat = $request->input('latitude');
            $clientLng = $request->input('longitude');
            $isMock = $request->input('is_mock');
            $accuracy = $request->input('accuracy');

            if (is_null($clientLat) || is_null($clientLng)) {
                return back()->withErrors(['email' => 'Check-in thất bại: Vui lòng bật vị trí (GPS) và cấp quyền truy cập để chấm công.']);
            }

            if ($isMock) {
                return back()->withErrors(['email' => 'Check-in thất bại: Phát hiện sử dụng vị trí giả lập (Mock Location). Chấm công bị từ chối.']);
            }

            if (! is_null($accuracy) && floatval($accuracy) > 100) {
                return back()->withErrors(['email' => 'Check-in thất bại: Độ chính xác GPS quá thấp ('.round($accuracy).'m). Yêu cầu độ chính xác dưới 100m.']);
            }

            $earthRadius = 6371000; // in meters
            $latFrom = deg2rad($targetLat);
            $lonFrom = deg2rad($targetLng);
            $latTo = deg2rad(floatval($clientLat));
            $lonTo = deg2rad(floatval($clientLng));

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
            $distance = $angle * $earthRadius;

            if ($distance > $allowedRadius) {
                return back()->withErrors(['email' => 'Check-in thất bại: Bạn đang ở cách xa địa điểm làm việc ('.round($distance).'m). Khoảng cách cho phép là dưới '.$allowedRadius.'m.']);
            }
        }

        // 2. QR Code Validation
        if ($restaurant && $restaurant->qr_checkin_code) {
            $clientQR = $request->input('qr_code');

            // Hỗ trợ mã QR động bên cạnh mã QR tĩnh truyền thống
            $isDynamicValid = false;
            if ($clientQR && str_starts_with($clientQR, 'DYN_')) {
                $nowTs = now()->timestamp;
                for ($i = 0; $i <= 1; $i++) {
                    $ts = $nowTs - ($i * 20);
                    $chunk = floor($ts / 20);
                    $secretSalt = config('app.key', 'aventura_secret_salt');
                    $expectedDyn = 'DYN_'.substr(hash_hmac('sha256', (string) $chunk, (string) $restaurant->id.$secretSalt), 0, 8);
                    if (hash_equals(strtoupper($expectedDyn), strtoupper($clientQR))) {
                        $isDynamicValid = true;
                        break;
                    }
                }
            }

            if (! $isDynamicValid) {
                // Nếu không khớp mã động, kiểm tra mã tĩnh
                if ($restaurant->qr_checkin_expires_at && now()->greaterThan($restaurant->qr_checkin_expires_at)) {
                    return back()->withErrors(['email' => 'Check-in thất bại: Mã QR chấm công trong ngày đã hết hạn. Hãy yêu cầu quản lý tạo mã QR mới.']);
                }

                if (empty($clientQR) || $clientQR !== $restaurant->qr_checkin_code) {
                    return back()->withErrors(['email' => 'Check-in thất bại: Mã QR chấm công không hợp lệ hoặc không khớp.']);
                }
            }
        }

        // Tìm ca được xếp có hiệu lực hiện tại
        $sa = null;
        $now = now();
        $isLateAndViolating = false;
        $lateMinutes = 0;
        $alreadyCheckedIn = false;

        $photo = $request->input('check_in_photo');
        $photoPath = null;
        if ($photo && preg_match('/^data:image\/(\w+);base64,/', $photo, $matches)) {
            $type = strtolower($matches[1]);
            $data = substr($photo, strpos($photo, ',') + 1);
            $data = base64_decode($data);
            if ($data !== false) {
                $filename = 'checkin_'.$employee->id.'_'.time().'_'.Str::random(5).'.'.$type;
                $photoPath = 'checkins/'.$filename;
                Storage::disk('public')->put($photoPath, $data);
            }
        }

        $exceededMaxLate = false;
        $exceededMaxMinutes = 0;

        DB::transaction(function () use (&$sa, &$alreadyCheckedIn, &$exceededMaxLate, &$exceededMaxMinutes, &$isLateAndViolating, &$lateMinutes, $employee, $now, $restaurant, $photoPath) {
            $scheduledAssignments = ScheduleAssignment::where('employee_id', $employee->id)
                ->where('status', 'scheduled')
                ->lockForUpdate()
                ->with('shift')
                ->get();

            foreach ($scheduledAssignments as $saCandidate) {
                $shift = $saCandidate->shift;
                if (! $shift || $shift->status !== 'active') {
                    continue;
                }

                $dateStr = $saCandidate->scheduled_date instanceof Carbon ? $saCandidate->scheduled_date->toDateString() : Carbon::parse($saCandidate->scheduled_date)->toDateString();
                $start = Carbon::parse($dateStr.' '.$shift->start_time);

                if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                    $end = Carbon::parse($dateStr.' '.$shift->end_time)->addDay();
                } else {
                    $end = Carbon::parse($dateStr.' '.$shift->end_time);
                }

                $allowedStart = $start->copy()->subMinutes(30);
                $allowedEnd = $end;

                if ($now->between($allowedStart, $allowedEnd)) {
                    $sa = $saCandidate;
                    break;
                }
            }

            if (! $sa) {
                $alreadyCheckedIn = ScheduleAssignment::where('employee_id', $employee->id)
                    ->where('status', 'checked_in')
                    ->where('scheduled_date', now()->toDateString())
                    ->exists();

                return;
            }

            if ($sa->check_in_at !== null || $sa->status === 'checked_in') {
                $alreadyCheckedIn = true;

                return;
            }

            $shift = $sa->shift;
            $dateStr = $sa->scheduled_date instanceof Carbon ? $sa->scheduled_date->toDateString() : Carbon::parse($sa->scheduled_date)->toDateString();
            $start = Carbon::parse($dateStr.' '.$shift->start_time);

            $isLate = $now->greaterThan($start);
            $lateMinutes = $isLate ? $now->diffInMinutes($start) : 0;
            $maxLateMinutes = $restaurant->max_late_checkin_minutes ?? null;

            if ($maxLateMinutes !== null && $maxLateMinutes > 0 && $lateMinutes > $maxLateMinutes) {
                $exceededMaxLate = true;
                $exceededMaxMinutes = $lateMinutes;

                return;
            }

            $gracePeriod = $restaurant->grace_period_minutes ?? 0;
            $isLateAndViolating = $isLate && $lateMinutes > $gracePeriod;

            if ($isLateAndViolating) {
                $excessMinutes = max(0, $lateMinutes - $gracePeriod);
                $penaltyType = $restaurant->late_penalty_type ?? 'none';
                $penaltyConfigAmount = (float) ($restaurant->late_penalty_amount ?? 0);
                $penaltyAmount = 0;

                if ($penaltyType === 'per_minute') {
                    $penaltyAmount = round($excessMinutes * $penaltyConfigAmount, 2);
                } elseif ($penaltyType === 'fixed_per_occurrence') {
                    $penaltyAmount = round($penaltyConfigAmount, 2);
                } elseif ($penaltyType === 'deduct_minute_salary') {
                    $hourlyRate = (float) ($employee->pay_rate ?? 0);
                    $penaltyAmount = round(($hourlyRate / 60) * $excessMinutes, 2);
                }

                ViolationReport::create([
                    'restaurant_id' => $sa->restaurant_id,
                    'branch_id' => $sa->branch_id,
                    'employee_id' => $sa->employee_id,
                    'reported_by' => $employee->id,
                    'violation_type' => 'Đi trễ / Vấn đề vào ca',
                    'severity' => 'low',
                    'description' => 'Đi trễ tự động: Check-in lúc '.$now->format('H:i').' (Trễ '.$lateMinutes.' phút, ca bắt đầu lúc '.$start->format('H:i').', thời gian ân hạn '.$gracePeriod.' phút)',
                    'penalty_amount' => $penaltyAmount,
                    'occurred_at' => $now,
                    'status' => 'open',
                    'is_anonymous' => false,
                ]);
            }

            $sa->update([
                'check_in_at' => $now,
                'status' => 'checked_in',
                'check_in_photo_path' => $photoPath,
            ]);
        });

        if ($exceededMaxLate) {
            return back()->withErrors(['email' => "Check-in thất bại: Bạn đã đi muộn {$exceededMaxMinutes} phút, vượt quá thời gian cho phép check-in ({$restaurant->max_late_checkin_minutes} phút). Vui lòng liên hệ Quản lý."]);
        }

        if ($alreadyCheckedIn) {
            return back()->with('success', 'Bạn đã CHECK-IN thành công trước đó.');
        }

        if (! $sa) {
            return back()->withErrors(['email' => 'Hiện tại bạn không có ca trực nào được xếp hoặc chưa đến giờ check-in cho phép.']);
        }

        // Flush cached shift-access so middleware reflects the new status immediately
        $employee->flushShiftAccessCache();
        // Flush cached dashboard so the updated schedule status shows immediately
        Cache::forget("employee_dashboard:{$employee->id}:".now()->format('Y-m'));

        $successMsg = 'Bạn đã CHECK-IN thành công ca trực "'.$sa->shift->name.'". Chúc bạn một ca làm việc vui vẻ!';
        if ($isLateAndViolating) {
            $successMsg .= " Tuy nhiên, hệ thống ghi nhận bạn đi trễ {$lateMinutes} phút và đã tự động lập biên bản lỗi.";
        }

        return back()->with($isLateAndViolating ? 'info' : 'success', $successMsg);
    }

    /**
     * Nhân viên tự Check-out khỏi ca.
     */
    public function checkOut(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        $restaurant = $employee->restaurant;

        // Geolocation (GPS) Validation - Ưu tiên tọa độ của chi nhánh nhân viên làm việc
        $targetLat = $employee->branch?->latitude ?? $restaurant?->latitude;
        $targetLng = $employee->branch?->longitude ?? $restaurant?->longitude;
        $allowedRadius = $employee->branch?->checkin_radius_meters ?? ($restaurant?->checkin_radius_meters ?? 100);

        if ($targetLat && $targetLng) {
            $clientLat = $request->input('latitude');
            $clientLng = $request->input('longitude');
            $isMock = $request->input('is_mock');
            $accuracy = $request->input('accuracy');

            if (is_null($clientLat) || is_null($clientLng)) {
                return back()->withErrors(['email' => 'Check-out thất bại: Vui lòng bật vị trí (GPS) và cấp quyền truy cập để chấm công.']);
            }

            if ($isMock) {
                return back()->withErrors(['email' => 'Check-out thất bại: Phát hiện sử dụng vị trí giả lập (Mock Location). Chấm công bị từ chối.']);
            }

            if (! is_null($accuracy) && floatval($accuracy) > 100) {
                return back()->withErrors(['email' => 'Check-out thất bại: Độ chính xác GPS quá thấp ('.round($accuracy).'m). Yêu cầu độ chính xác dưới 100m.']);
            }

            $earthRadius = 6371000; // in meters
            $latFrom = deg2rad($targetLat);
            $lonFrom = deg2rad($targetLng);
            $latTo = deg2rad(floatval($clientLat));
            $lonTo = deg2rad(floatval($clientLng));

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
            $distance = $angle * $earthRadius;

            if ($distance > $allowedRadius) {
                return back()->withErrors(['email' => 'Check-out thất bại: Bạn đang ở cách xa địa điểm làm việc ('.round($distance).'m). Khoảng cách cho phép là dưới '.$allowedRadius.'m.']);
            }
        }

        $sa = null;
        $now = now();
        $isEarlyAndViolating = false;
        $earlyMinutes = 0;
        $alreadyCheckedOut = false;

        $exceededMaxEarly = false;
        $exceededEarlyMinutes = 0;

        DB::transaction(function () use (&$sa, &$alreadyCheckedOut, &$exceededMaxEarly, &$exceededEarlyMinutes, &$isEarlyAndViolating, &$earlyMinutes, $employee, $now, $restaurant) {
            $sa = ScheduleAssignment::where('employee_id', $employee->id)
                ->where('status', 'checked_in')
                ->whereDate('scheduled_date', '<=', today())
                ->orderByDesc('scheduled_date')
                ->lockForUpdate()
                ->with('shift')
                ->first();

            if (! $sa) {
                // Check if already checked out today
                $alreadyCheckedOut = ScheduleAssignment::where('employee_id', $employee->id)
                    ->where('status', 'completed')
                    ->where('scheduled_date', now()->toDateString())
                    ->exists();

                return;
            }

            if ($sa->check_out_at !== null || $sa->status === 'completed') {
                $alreadyCheckedOut = true;

                return;
            }

            $shift = $sa->shift;
            if ($shift) {
                $dateStr = $sa->scheduled_date instanceof Carbon ? $sa->scheduled_date->toDateString() : Carbon::parse($sa->scheduled_date)->toDateString();

                if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                    $end = Carbon::parse($dateStr.' '.$shift->end_time)->addDay();
                } else {
                    $end = Carbon::parse($dateStr.' '.$shift->end_time);
                }

                $isEarly = $now->lessThan($end);
                $earlyMinutes = $isEarly ? $now->diffInMinutes($end) : 0;
                $maxEarlyMinutes = $restaurant->max_early_checkout_minutes ?? null;

                if ($maxEarlyMinutes !== null && $maxEarlyMinutes > 0 && $earlyMinutes > $maxEarlyMinutes) {
                    $exceededMaxEarly = true;
                    $exceededEarlyMinutes = $earlyMinutes;

                    return;
                }

                $gracePeriod = $restaurant->early_checkout_grace_minutes ?? 5;
                $isEarlyAndViolating = $isEarly && $earlyMinutes > $gracePeriod;

                if ($isEarlyAndViolating) {
                    $excessMinutes = max(0, $earlyMinutes - $gracePeriod);
                    $penaltyType = $restaurant->early_checkout_penalty_type ?? 'none';
                    $penaltyConfigAmount = (float) ($restaurant->early_checkout_penalty_amount ?? 0);
                    $penaltyAmount = 0;

                    if ($penaltyType === 'per_minute') {
                        $penaltyAmount = round($excessMinutes * $penaltyConfigAmount, 2);
                    } elseif ($penaltyType === 'fixed_per_occurrence') {
                        $penaltyAmount = round($penaltyConfigAmount, 2);
                    } elseif ($penaltyType === 'deduct_minute_salary') {
                        $hourlyRate = (float) ($employee->pay_rate ?? 0);
                        $penaltyAmount = round(($hourlyRate / 60) * $excessMinutes, 2);
                    }

                    ViolationReport::create([
                        'restaurant_id' => $sa->restaurant_id,
                        'branch_id' => $sa->branch_id,
                        'employee_id' => $sa->employee_id,
                        'reported_by' => $employee->id,
                        'violation_type' => 'Về sớm / Vấn đề ra ca',
                        'severity' => 'low',
                        'description' => 'Về sớm tự động: Check-out lúc '.$now->format('H:i').' (Về sớm '.$earlyMinutes.' phút, ca kết thúc lúc '.$end->format('H:i').', thời gian ân hạn '.$gracePeriod.' phút)',
                        'penalty_amount' => $penaltyAmount,
                        'occurred_at' => $now,
                        'status' => 'open',
                        'is_anonymous' => false,
                    ]);
                }
            }

            $sa->update([
                'check_out_at' => $now,
                'status' => 'completed',
            ]);
        });

        if ($exceededMaxEarly) {
            return back()->withErrors(['email' => "Check-out thất bại: Bạn đang xin ra ca sớm {$exceededEarlyMinutes} phút, vượt quá thời gian cho phép về sớm ({$restaurant->max_early_checkout_minutes} phút). Vui lòng liên hệ Quản lý để được duyệt ra ca."]);
        }

        if ($alreadyCheckedOut) {
            return back()->with('success', 'Bạn đã CHECK-OUT thành công trước đó.');
        }

        if (! $sa) {
            return back()->withErrors(['email' => 'Không tìm thấy ca trực nào đang hoạt động để check-out.']);
        }

        // Flush cached shift-access so middleware reflects the checkout immediately
        $employee->flushShiftAccessCache();
        // Flush cached dashboard so hours_worked count updates immediately
        Cache::forget("employee_dashboard:{$employee->id}:".now()->format('Y-m'));

        $successMsg = 'Bạn đã CHECK-OUT thành công. Cảm ơn bạn vì sự đóng góp tuyệt vời ngày hôm nay!';
        if ($isEarlyAndViolating) {
            $successMsg .= " Tuy nhiên, hệ thống ghi nhận bạn đã về sớm {$earlyMinutes} phút và đã lập biên bản lỗi.";
        }

        return back()->with($isEarlyAndViolating ? 'info' : 'success', $successMsg);
    }

    /**
     * Quản lý/Owner/Trưởng kho Check-in hộ nhân viên.
     */
    public function checkInEmployee(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager', 'super_admin']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', TenantRule::exists('schedule_assignments')],
            'is_on_time' => ['nullable', 'boolean'],
            'actual_check_in_time' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:250'],
            'apply_violation' => ['nullable', 'boolean'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'violation_notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $request, $user) {
            $sa = ScheduleAssignment::lockForUpdate()->with(['shift', 'employee.user'])->findOrFail($data['assignment_id']);
            abort_unless($user->canAccessBranch($sa->branch_id), 403, 'Bạn không có quyền thao tác chấm công cho chi nhánh này.');
            $shift = $sa->shift;
            $dateStr = $sa->scheduled_date instanceof Carbon ? $sa->scheduled_date->toDateString() : Carbon::parse($sa->scheduled_date)->toDateString();

            $isOnTime = $request->boolean('is_on_time', true);
            $checkInAt = now();

            if ($isOnTime && $shift) {
                // Nếu đúng giờ: Ghi nhận giờ bắt đầu ca chuẩn (không tính trễ)
                $checkInAt = Carbon::parse($dateStr.' '.$shift->start_time);
            } elseif (! empty($data['actual_check_in_time'])) {
                if (preg_match('/^\d{2}:\d{2}$/', $data['actual_check_in_time'])) {
                    $checkInAt = Carbon::parse($dateStr.' '.$data['actual_check_in_time']);
                } else {
                    $checkInAt = Carbon::parse($data['actual_check_in_time']);
                }
            }

            $sa->update([
                'check_in_at' => $checkInAt,
                'status' => 'checked_in',
                'approved_by' => $user->id,
                'notes' => $data['notes'] ?? ($isOnTime ? 'Quản lý/Chủ quán xác nhận nhân viên vào ca đúng giờ' : 'Xác nhận vào ca thời gian thực tế'),
            ]);

            if ($request->boolean('apply_violation')) {
                $this->createAutoViolation($request, $sa, 'Đi trễ / Vấn đề vào ca', $data['violation_notes'] ?? $data['notes'] ?? 'Xác nhận check-in kèm vi phạm vào ca');
            }

            // Gửi thông báo tới tài khoản Nhân viên
            $employeeUser = $sa->employee?->user;
            if ($employeeUser) {
                $checkInFormatted = $checkInAt->format('H:i d/m/Y');
                $shiftName = $shift?->name ?? 'ca trực';
                $employeeUser->notify(new CheckInConfirmedNotification(
                    "Quản lý đã xác nhận ca trực \"{$shiftName}\". Giờ vào ca được ghi nhận: {$checkInFormatted}.",
                    $dateStr
                ));
            }
        });

        return back()->with('success', 'Đã xác nhận Check-in thành công cho nhân viên.');
    }

    /**
     * Quản lý/Owner/Trưởng kho Check-out hộ nhân viên.
     */
    public function checkOutEmployee(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager', 'super_admin']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', TenantRule::exists('schedule_assignments')],
            'notes' => ['nullable', 'string', 'max:250'],
            'apply_violation' => ['nullable', 'boolean'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'violation_notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $request, $user) {
            $sa = ScheduleAssignment::lockForUpdate()->findOrFail($data['assignment_id']);
            abort_unless($user->canAccessBranch($sa->branch_id), 403, 'Bạn không có quyền thao tác chấm công cho chi nhánh này.');

            $sa->update([
                'check_out_at' => now(),
                'status' => 'completed',
                'approved_by' => $user->id,
                'notes' => $data['notes'] ?? 'Check-out hộ bởi Quản lý/Chủ nhà hàng',
            ]);

            if ($request->boolean('apply_violation')) {
                $this->createAutoViolation($request, $sa, 'Về sớm / Vấn đề ra ca', $data['violation_notes'] ?? $data['notes'] ?? 'Check-out hộ kèm vi phạm ra ca');
            }
        });

        return back()->with('success', 'Đã ghi nhận Check-out hộ thành công cho nhân viên.');
    }

    /**
     * Quản lý/Owner/Trưởng kho Báo vắng (Absent) nhân viên.
     */
    public function markAbsentEmployee(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager', 'super_admin']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', TenantRule::exists('schedule_assignments')],
            'notes' => ['nullable', 'string', 'max:250'],
            'apply_violation' => ['nullable', 'boolean'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'violation_notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $request, $user) {
            $sa = ScheduleAssignment::lockForUpdate()->findOrFail($data['assignment_id']);
            abort_unless($user->canAccessBranch($sa->branch_id), 403, 'Bạn không có quyền thao tác chấm công cho chi nhánh này.');

            $sa->update([
                'status' => 'absent',
                'approved_by' => $user->id,
                'notes' => $data['notes'] ?? 'Vắng mặt không lý do',
            ]);

            if ($request->boolean('apply_violation')) {
                $this->createAutoViolation($request, $sa, 'Vắng mặt', $data['violation_notes'] ?? $data['notes'] ?? 'Báo vắng trực không lý do');
            }
        });

        return back()->with('success', 'Đã ghi nhận báo vắng thành công cho nhân viên.');
    }

    /**
     * Tự động tạo Biên bản vi phạm kỷ luật khi có tuỳ chọn kèm theo.
     */
    private function createAutoViolation(Request $request, ScheduleAssignment $sa, string $violationType, string $description): void
    {
        $user = $request->user();
        $penaltyAmount = (float) ($request->input('penalty_amount') ?? 0);

        ViolationReport::create([
            'restaurant_id' => $sa->restaurant_id,
            'branch_id' => $sa->branch_id,
            'employee_id' => $sa->employee_id,
            'reported_by' => $user->id,
            'violation_type' => $violationType,
            'severity' => 'low',
            'description' => $description,
            'penalty_amount' => $penaltyAmount,
            'occurred_at' => $sa->scheduled_date ? Carbon::parse($sa->scheduled_date)->toDateString().' '.now()->format('H:i:s') : now(),
            'status' => $request->user()->isOwner() || $request->user()->isSuperAdmin()
                ? 'resolved'
                : 'open',
            'is_anonymous' => false,
        ]);
    }

    /**
     * Nhân viên gửi yêu cầu xác nhận vào ca tới Quản lý chi nhánh / Chủ doanh nghiệp.
     */
    public function requestCheckIn(Request $request): RedirectResponse
    {
        app(AttendanceCancellationService::class)->cancelExpiredAttendanceRequests();

        $user = $request->user();
        if ($user->hasAnyRole(['owner', 'manager'])) {
            return back()->withErrors(['email' => 'Tài khoản Chủ quán / Quản lý không cần gửi yêu cầu xác nhận vào ca.']);
        }

        $employee = $user->employee;
        if (! $employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        // Tìm ca trực hôm nay của nhân viên
        $todayStr = today()->toDateString();
        $assignment = ScheduleAssignment::where('employee_id', $employee->id)
            ->whereIn('status', ['scheduled', 'pending_checkin'])
            ->whereDate('scheduled_date', $todayStr)
            ->with('shift')
            ->first();

        if (! $assignment) {
            return back()->withErrors(['email' => 'Hôm nay bạn không có ca trực nào được xếp để gửi yêu cầu vào ca.']);
        }

        if ($assignment->status === 'checked_in') {
            return back()->with('success', 'Bạn đã được xác nhận vào ca trực này trước đó.');
        }

        $now = now();

        // Gửi yêu cầu phê duyệt tới Quản lý chi nhánh và Chủ doanh nghiệp
        app(ApprovalService::class)->submitRequest('shift_checkin', [
            'assignment_id' => $assignment->id,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id ?? $assignment->branch_id,
            'shift_name' => $assignment->shift?->name ?? 'Ca trực',
            'requested_at' => $now->toIso8601String(),
            'notes' => 'Yêu cầu xác nhận vào ca từ nhân viên '.$user->name.' ('.($employee->job_title ?? 'Nhân viên').')',
        ], $user);

        // Tạm thời ghi nhận chấm công thời điểm gửi yêu cầu (nếu sau 24h không duyệt sẽ tự động hủy)
        $assignment->update([
            'status' => 'pending_checkin',
            'check_in_at' => $assignment->check_in_at ?? $now,
        ]);

        return back()->with('success', '🚀 Đã gửi yêu cầu xác nhận vào ca tới Quản lý chi nhánh & Chủ doanh nghiệp! Hệ thống tạm thời ghi nhận chấm công lúc '.$now->format('H:i').' (Sẽ tự động hủy sau 24h nếu không được xác nhận).');
    }

    /**
     * Nhân viên gửi yêu cầu xác nhận hết ca tới Quản lý chi nhánh / Chủ doanh nghiệp.
     */
    public function requestCheckOut(Request $request): RedirectResponse
    {
        app(AttendanceCancellationService::class)->cancelExpiredAttendanceRequests();

        $user = $request->user();
        if ($user->hasAnyRole(['owner', 'manager'])) {
            return back()->withErrors(['email' => 'Tài khoản Chủ quán / Quản lý không cần gửi yêu cầu xác nhận hết ca.']);
        }

        $employee = $user->employee;
        if (! $employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        $assignment = ScheduleAssignment::where('employee_id', $employee->id)
            ->whereIn('status', ['checked_in', 'pending_checkout'])
            ->with('shift')
            ->first();

        if (! $assignment) {
            return back()->withErrors(['email' => 'Không tìm thấy ca trực nào đang hoạt động để gửi yêu cầu xác nhận hết ca.']);
        }

        if ($assignment->status === 'pending_checkout') {
            return back()->with('info', 'Yêu cầu xác nhận hết ca của bạn đang chờ Quản lý chi nhánh / Chủ quán phê duyệt.');
        }

        if ($assignment->status === 'completed') {
            return back()->with('success', 'Bạn đã hoàn thành ca trực này trước đó.');
        }

        $now = now();

        // Gửi yêu cầu phê duyệt tới Quản lý chi nhánh và Chủ doanh nghiệp
        app(ApprovalService::class)->submitRequest('shift_checkout', [
            'assignment_id' => $assignment->id,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id ?? $assignment->branch_id,
            'shift_name' => $assignment->shift?->name ?? 'Ca trực',
            'requested_at' => $now->toIso8601String(),
            'notes' => 'Yêu cầu xác nhận hết ca từ nhân viên '.$user->name.' ('.($employee->job_title ?? 'Nhân viên').')',
        ], $user);

        // Tạm thời ghi nhận thời gian hết ca tại thời điểm gửi yêu cầu
        $assignment->update([
            'status' => 'pending_checkout',
            'check_out_at' => $assignment->check_out_at ?? $now,
        ]);

        return back()->with('success', '🚀 Đã gửi yêu cầu xác nhận hết ca tới Quản lý chi nhánh & Chủ doanh nghiệp! Vui lòng chờ xác nhận.');
    }

    /**
     * Duyệt chấm công nhanh các ca trực không có bất thường/vi phạm.
     */
    public function batchApproveNormal(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can('manage_attendance') || $user->hasAnyRole(['manager', 'owner', 'super_admin']), 403);

        $restaurantId = $user->restaurant_id;
        $branchId = app(TenantContext::class)->activeBranchId();

        $assignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', ['checked_in', 'completed'])
            ->whereNull('approved_by')
            ->whereNotNull('check_in_at')
            ->get();

        if ($assignments->isEmpty()) {
            $msg = 'Không có bản ghi chấm công nào chờ duyệt.';

            return $request->wantsJson() ? response()->json(['success' => false, 'message' => $msg], 422) : back()->withErrors(['attendance' => $msg]);
        }

        $approvedCount = 0;
        foreach ($assignments as $sa) {
            $hasViolation = ViolationReport::where('restaurant_id', $restaurantId)
                ->where('employee_id', $sa->employee_id)
                ->whereDate('occurred_at', $sa->scheduled_date)
                ->where('status', 'open')
                ->exists();

            if ($hasViolation) {
                continue;
            }

            $sa->update([
                'approved_by' => $user->id,
            ]);
            $approvedCount++;
        }

        if ($approvedCount > 0) {
            AuditLog::log(
                'attendance_batch_approved',
                'updated',
                $assignments->first(),
                null,
                ['approved_count' => $approvedCount]
            );
        }

        $msg = "Đã duyệt chấm công nhanh cho {$approvedCount} ca trực bình thường.";

        return $request->wantsJson()
            ? response()->json(['success' => true, 'message' => $msg, 'approved_count' => $approvedCount])
            : back()->with('success', $msg);
    }
}
