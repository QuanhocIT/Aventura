<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ScheduleAssignment;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    /**
     * Nhân viên tự Check-in vào ca.
     */
    public function checkIn(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        $restaurant = $employee->restaurant;

        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'hr_timekeeping')) {
            return back()->withErrors(['feature' => 'Gói dịch vụ hiện tại không hỗ trợ tính năng Chấm công. Vui lòng nâng cấp gói.']);
        }

        // 1. Geolocation (GPS) Validation
        if ($restaurant && $restaurant->latitude && $restaurant->longitude) {
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

            if (!is_null($accuracy) && floatval($accuracy) > 100) {
                return back()->withErrors(['email' => 'Check-in thất bại: Độ chính xác GPS quá thấp (' . round($accuracy) . 'm). Yêu cầu độ chính xác dưới 100m.']);
            }

            $earthRadius = 6371000; // in meters
            $latFrom = deg2rad($restaurant->latitude);
            $lonFrom = deg2rad($restaurant->longitude);
            $latTo = deg2rad(floatval($clientLat));
            $lonTo = deg2rad(floatval($clientLng));

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
            $distance = $angle * $earthRadius;
            
            $allowedRadius = $restaurant->checkin_radius_meters ?? 100;
            if ($distance > $allowedRadius) {
                return back()->withErrors(['email' => 'Check-in thất bại: Bạn đang ở cách xa nhà hàng (' . round($distance) . 'm). Khoảng cách cho phép là dưới ' . $allowedRadius . 'm.']);
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
                    $expectedDyn = 'DYN_' . substr(hash_hmac('sha256', (string)$chunk, (string)$restaurant->id . '_checkin_secret_key_123'), 0, 8);
                    if (hash_equals(strtoupper($expectedDyn), strtoupper($clientQR))) {
                        $isDynamicValid = true;
                        break;
                    }
                }
            }

            if (!$isDynamicValid) {
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

        \Illuminate\Support\Facades\DB::transaction(function () use (&$sa, $employee, $now) {
            $scheduledAssignments = ScheduleAssignment::where('employee_id', $employee->id)
                ->where('status', 'scheduled')
                ->lockForUpdate()
                ->with('shift')
                ->get();

            foreach ($scheduledAssignments as $saCandidate) {
                $shift = $saCandidate->shift;
                if (!$shift || $shift->status !== 'active') {
                    continue;
                }

                $dateStr = $saCandidate->scheduled_date instanceof Carbon ? $saCandidate->scheduled_date->toDateString() : Carbon::parse($saCandidate->scheduled_date)->toDateString();
                $start = Carbon::parse($dateStr . ' ' . $shift->start_time);
                
                if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                    $end = Carbon::parse($dateStr . ' ' . $shift->end_time)->addDay();
                } else {
                    $end = Carbon::parse($dateStr . ' ' . $shift->end_time);
                }

                $allowedStart = $start->copy()->subMinutes(30);
                $allowedEnd = $end;

                if ($now->between($allowedStart, $allowedEnd)) {
                    $sa = $saCandidate;
                    break;
                }
            }
        });

        if (!$sa) {
            // Idempotent guard: nếu nhân viên đã check-in ca đang active trong cùng thời điểm
            $alreadyCheckedIn = ScheduleAssignment::where('employee_id', $employee->id)
                ->where('status', 'checked_in')
                ->where('scheduled_date', now()->toDateString())
                ->exists();

            if ($alreadyCheckedIn) {
                return back()->with('success', 'Bạn đã CHECK-IN thành công trước đó.');
            }

            return back()->withErrors(['email' => 'Hiện tại bạn không có ca trực nào được xếp hoặc chưa đến giờ check-in cho phép.']);
        }

        $photo = $request->input('check_in_photo');
        $photoPath = null;
        if ($photo && preg_match('/^data:image\/(\w+);base64,/', $photo, $matches)) {
            $type = strtolower($matches[1]);
            $data = substr($photo, strpos($photo, ',') + 1);
            $data = base64_decode($data);
            if ($data !== false) {
                $filename = 'checkin_' . $employee->id . '_' . time() . '_' . Str::random(5) . '.' . $type;
                $photoPath = 'checkins/' . $filename;
                Storage::disk('public')->put($photoPath, $data);
            }
        }

        $now = now();
        $shift = $sa->shift;
        $dateStr = $sa->scheduled_date instanceof Carbon ? $sa->scheduled_date->toDateString() : Carbon::parse($sa->scheduled_date)->toDateString();
        $start = Carbon::parse($dateStr . ' ' . $shift->start_time);
        
        $isLate = $now->greaterThan($start);
        $lateMinutes = $isLate ? $now->diffInMinutes($start) : 0;
        $gracePeriod = $restaurant->grace_period_minutes ?? 0;
        $isLateAndViolating = $isLate && $lateMinutes > $gracePeriod;

        if ($isLateAndViolating) {
            \App\Models\ViolationReport::create([
                'restaurant_id'  => $sa->restaurant_id,
                'branch_id'      => $sa->branch_id,
                'employee_id'    => $sa->employee_id,
                'reported_by'    => $employee->id,
                'violation_type' => 'Đi trễ / Vấn đề vào ca',
                'severity'       => 'low',
                'description'    => "Đi trễ tự động: Check-in lúc " . $now->format('H:i') . " (Trễ " . $lateMinutes . " phút, ca bắt đầu lúc " . $start->format('H:i') . ", thời gian ân hạn " . $gracePeriod . " phút)",
                'penalty_amount' => 0,
                'occurred_at'    => $now,
                'status'         => 'open',
                'is_anonymous'   => false,
            ]);
        }

        if ($sa->check_in_at !== null || $sa->status === 'checked_in') {
            return back()->with('success', 'Bạn đã CHECK-IN thành công trước đó.');
        }

        $sa->update([
            'check_in_at' => $now,
            'status' => 'checked_in',
            'check_in_photo_path' => $photoPath,
        ]);

        // Flush cached shift-access so middleware reflects the new status immediately
        $employee->flushShiftAccessCache();
        // Flush cached dashboard so the updated schedule status shows immediately
        \Illuminate\Support\Facades\Cache::forget("employee_dashboard:{$employee->id}:" . now()->format('Y-m'));

        $successMsg = 'Bạn đã CHECK-IN thành công ca trực "' . $sa->shift->name . '". Chúc bạn một ca làm việc vui vẻ!';
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
        if (!$employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        $restaurant = $employee->restaurant;

        // Geolocation (GPS) Validation
        if ($restaurant && $restaurant->latitude && $restaurant->longitude) {
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

            if (!is_null($accuracy) && floatval($accuracy) > 100) {
                return back()->withErrors(['email' => 'Check-out thất bại: Độ chính xác GPS quá thấp (' . round($accuracy) . 'm). Yêu cầu độ chính xác dưới 100m.']);
            }

            $earthRadius = 6371000; // in meters
            $latFrom = deg2rad($restaurant->latitude);
            $lonFrom = deg2rad($restaurant->longitude);
            $latTo = deg2rad(floatval($clientLat));
            $lonTo = deg2rad(floatval($clientLng));

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
            $distance = $angle * $earthRadius;
            
            $allowedRadius = $restaurant->checkin_radius_meters ?? 100;
            if ($distance > $allowedRadius) {
                return back()->withErrors(['email' => 'Check-out thất bại: Bạn đang ở cách xa nhà hàng (' . round($distance) . 'm). Khoảng cách cho phép là dưới ' . $allowedRadius . 'm.']);
            }
        }

        $sa = null;
        \Illuminate\Support\Facades\DB::transaction(function () use (&$sa, $employee) {
            $sa = ScheduleAssignment::where('employee_id', $employee->id)
                ->where('status', 'checked_in')
                ->lockForUpdate()
                ->with('shift')
                ->first();
        });

        if (!$sa) {
            return back()->withErrors(['email' => 'Không tìm thấy ca trực nào đang hoạt động để check-out.']);
        }

        $now = now();
        $shift = $sa->shift;
        $isEarlyAndViolating = false;
        $earlyMinutes = 0;
        
        if ($shift) {
            $dateStr = $sa->scheduled_date instanceof Carbon ? $sa->scheduled_date->toDateString() : Carbon::parse($sa->scheduled_date)->toDateString();
            
            if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                $end = Carbon::parse($dateStr . ' ' . $shift->end_time)->addDay();
            } else {
                $end = Carbon::parse($dateStr . ' ' . $shift->end_time);
            }

            $isEarly = $now->lessThan($end);
            $earlyMinutes = $isEarly ? $now->diffInMinutes($end) : 0;
            // Cho phép về sớm tối đa 5 phút không bị phạt
            $isEarlyAndViolating = $isEarly && $earlyMinutes > 5;
            
            if ($isEarlyAndViolating) {
                \App\Models\ViolationReport::create([
                    'restaurant_id'  => $sa->restaurant_id,
                    'branch_id'      => $sa->branch_id,
                    'employee_id'    => $sa->employee_id,
                    'reported_by'    => $employee->id,
                    'violation_type' => 'Về sớm / Vấn đề ra ca',
                    'severity'       => 'low',
                    'description'    => "Về sớm tự động: Check-out lúc " . $now->format('H:i') . " (Về sớm " . $earlyMinutes . " phút, ca kết thúc lúc " . $end->format('H:i') . ")",
                    'penalty_amount' => 0,
                    'occurred_at'    => $now,
                    'status'         => 'open',
                    'is_anonymous'   => false,
                ]);
            }
        }

        if ($sa->check_out_at !== null || $sa->status === 'completed') {
            return back()->with('success', 'Bạn đã CHECK-OUT thành công trước đó.');
        }

        $sa->update([
            'check_out_at' => $now,
            'status' => 'completed',
        ]);

        // Flush cached shift-access so middleware reflects the checkout immediately
        $employee->flushShiftAccessCache();
        // Flush cached dashboard so hours_worked count updates immediately
        \Illuminate\Support\Facades\Cache::forget("employee_dashboard:{$employee->id}:" . now()->format('Y-m'));

        $successMsg = 'Bạn đã CHECK-OUT thành công. Cảm ơn bạn vì sự đóng góp tuyệt vời ngày hôm nay!';
        if ($isEarlyAndViolating) {
            $successMsg .= " Tuy nhiên, hệ thống ghi nhận bạn đã về sớm {$earlyMinutes} phút và đã lập biên bản lỗi.";
        }

        return back()->with($isEarlyAndViolating ? 'info' : 'success', $successMsg);
    }

    /**
     * Quản lý/Owner Check-in hộ nhân viên.
     */
    public function checkInEmployee(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'exists:schedule_assignments,id'],
            'notes' => ['nullable', 'string', 'max:250'],
            'apply_violation' => ['nullable', 'boolean'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'violation_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);
        
        $sa->update([
            'check_in_at' => now(),
            'status' => 'checked_in',
            'approved_by' => $request->user()->id,
            'notes' => $data['notes'] ?? 'Check-in hộ bởi Quản lý/Chủ nhà hàng',
        ]);

        if ($request->boolean('apply_violation')) {
            $this->createAutoViolation($request, $sa, 'Đi trễ / Vấn đề vào ca', $data['violation_notes'] ?? $data['notes'] ?? 'Check-in hộ kèm vi phạm vào ca');
        }

        return back()->with('success', 'Đã ghi nhận Check-in hộ thành công cho nhân viên.');
    }

    /**
     * Quản lý/Owner Check-out hộ nhân viên.
     */
    public function checkOutEmployee(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'exists:schedule_assignments,id'],
            'notes' => ['nullable', 'string', 'max:250'],
            'apply_violation' => ['nullable', 'boolean'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'violation_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);
        
        $sa->update([
            'check_out_at' => now(),
            'status' => 'completed',
            'approved_by' => $request->user()->id,
            'notes' => $data['notes'] ?? 'Check-out hộ bởi Quản lý/Chủ nhà hàng',
        ]);

        if ($request->boolean('apply_violation')) {
            $this->createAutoViolation($request, $sa, 'Về sớm / Vấn đề ra ca', $data['violation_notes'] ?? $data['notes'] ?? 'Check-out hộ kèm vi phạm ra ca');
        }

        return back()->with('success', 'Đã ghi nhận Check-out hộ thành công cho nhân viên.');
    }

    /**
     * Quản lý/Owner Báo vắng (Absent) nhân viên.
     */
    public function markAbsentEmployee(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'exists:schedule_assignments,id'],
            'notes' => ['nullable', 'string', 'max:250'],
            'apply_violation' => ['nullable', 'boolean'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'violation_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);
        
        $sa->update([
            'status' => 'absent',
            'approved_by' => $request->user()->id,
            'notes' => $data['notes'] ?? 'Vắng mặt không lý do',
        ]);

        if ($request->boolean('apply_violation')) {
            $this->createAutoViolation($request, $sa, 'Vắng mặt', $data['violation_notes'] ?? $data['notes'] ?? 'Báo vắng trực không lý do');
        }

        return back()->with('success', 'Đã ghi nhận báo vắng thành công cho nhân viên.');
    }

    /**
     * Tự động tạo Biên bản vi phạm kỷ luật khi có tuỳ chọn kèm theo.
     */
    private function createAutoViolation(Request $request, ScheduleAssignment $sa, string $violationType, string $description): void
    {
        $user = $request->user();
        $penaltyAmount = (float) ($request->input('penalty_amount') ?? 0);

        \App\Models\ViolationReport::create([
            'restaurant_id'  => $sa->restaurant_id,
            'branch_id'      => $sa->branch_id,
            'employee_id'    => $sa->employee_id,
            'reported_by'    => $user->id,
            'violation_type' => $violationType,
            'severity'       => 'low',
            'description'    => $description,
            'penalty_amount' => $penaltyAmount,
            'occurred_at'    => $sa->scheduled_date ? Carbon::parse($sa->scheduled_date)->toDateString() . ' ' . now()->format('H:i:s') : now(),
            'status'         => 'resolved', // Đã phê duyệt và áp dụng trực tiếp lên bảng lương nháp
            'is_anonymous'   => false,
        ]);
    }

}
