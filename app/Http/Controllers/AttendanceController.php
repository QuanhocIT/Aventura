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

        // 1. Geolocation (GPS) Validation
        if ($restaurant && $restaurant->latitude && $restaurant->longitude) {
            $clientLat = $request->input('latitude');
            $clientLng = $request->input('longitude');

            if (is_null($clientLat) || is_null($clientLng)) {
                return back()->withErrors(['email' => 'Check-in thất bại: Vui lòng bật vị trí (GPS) và cấp quyền truy cập để chấm công.']);
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
            if ($restaurant->qr_checkin_expires_at && now()->greaterThan($restaurant->qr_checkin_expires_at)) {
                return back()->withErrors(['email' => 'Check-in thất bại: Mã QR chấm công trong ngày đã hết hạn. Hãy yêu cầu quản lý tạo mã QR mới.']);
            }

            $clientQR = $request->input('qr_code');
            if (empty($clientQR) || $clientQR !== $restaurant->qr_checkin_code) {
                return back()->withErrors(['email' => 'Check-in thất bại: Mã QR chấm công không hợp lệ hoặc không khớp.']);
            }
        }

        // Tìm ca được xếp có hiệu lực hiện tại
        $sa = null;
        $now = now();

        $scheduledAssignments = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('status', 'scheduled')
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

        if (!$sa) {
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

        $sa->update([
            'check_in_at' => now(),
            'status' => 'checked_in',
            'check_in_photo_path' => $photoPath,
        ]);

        return back()->with('success', 'Bạn đã CHECK-IN thành công ca trực "' . $sa->shift->name . '". Chúc bạn một ca làm việc vui vẻ!');
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

        $sa = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('status', 'checked_in')
            ->first();

        if (!$sa) {
            return back()->withErrors(['email' => 'Không tìm thấy ca trực nào đang hoạt động để check-out.']);
        }

        $sa->update([
            'check_out_at' => now(),
            'status' => 'completed',
        ]);

        return back()->with('success', 'Bạn đã CHECK-OUT thành công. Cảm ơn bạn vì sự đóng góp tuyệt vời ngày hôm nay!');
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

}
