<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\ViolationReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Nghiệp vụ chấm công (check-in/check-out GPS+QR của nhân viên, check-in/out/báo
 * vắng hộ của quản lý, tự động lập biên bản vi phạm kèm theo) — tách khỏi
 * ScheduleController để controller chỉ còn lo HTTP, theo đúng khuôn đã áp dụng
 * cho PlatformMetricsService/SupplierSlaService.
 *
 * Mỗi hàm trả về mảng ['success' => bool, 'message' => string, ...] thay vì
 * RedirectResponse — controller tự dịch sang back()->with()/withErrors() để
 * service không phụ thuộc tầng HTTP.
 */
class TimeClockService
{
    /**
     * Nhân viên tự Check-in — xác thực GPS (nếu nhà hàng cấu hình toạ độ) và mã
     * QR (tĩnh hoặc động, cửa sổ 20 giây/2 chu kỳ) trước khi ghi nhận.
     */
    public function checkIn(Employee $employee, array $input): array
    {
        $restaurant = $employee->restaurant;

        // 1. Geolocation (GPS) Validation
        if ($restaurant && $restaurant->latitude && $restaurant->longitude) {
            $clientLat = $input['latitude'] ?? null;
            $clientLng = $input['longitude'] ?? null;

            if (is_null($clientLat) || is_null($clientLng)) {
                return ['success' => false, 'message' => 'Check-in thất bại: Vui lòng bật vị trí (GPS) và cấp quyền truy cập để chấm công.'];
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
                return ['success' => false, 'message' => 'Check-in thất bại: Bạn đang ở cách xa nhà hàng ('.round($distance).'m). Khoảng cách cho phép là dưới '.$allowedRadius.'m.'];
            }
        }

        // 2. QR Code Validation
        if ($restaurant && $restaurant->qr_checkin_code) {
            $clientQR = $input['qr_code'] ?? null;

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
                    return ['success' => false, 'message' => 'Check-in thất bại: Mã QR chấm công trong ngày đã hết hạn. Hãy yêu cầu quản lý tạo mã QR mới.'];
                }

                if (empty($clientQR) || $clientQR !== $restaurant->qr_checkin_code) {
                    return ['success' => false, 'message' => 'Check-in thất bại: Mã QR chấm công không hợp lệ hoặc không khớp.'];
                }
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
            return ['success' => false, 'message' => 'Hiện tại bạn không có ca trực nào được xếp hoặc chưa đến giờ check-in cho phép.'];
        }

        $photo = $input['check_in_photo'] ?? null;
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

        $sa->update([
            'check_in_at' => now(),
            'status' => 'checked_in',
            'check_in_photo_path' => $photoPath,
        ]);

        // Flush cached shift-access so middleware reflects the new status immediately
        $employee->flushShiftAccessCache();

        return ['success' => true, 'message' => 'Bạn đã CHECK-IN thành công ca trực "'.$sa->shift->name.'". Chúc bạn một ca làm việc vui vẻ!'];
    }

    /**
     * Nhân viên tự Check-out khỏi ca.
     */
    public function checkOut(Employee $employee): array
    {
        $sa = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('status', 'checked_in')
            ->first();

        if (! $sa) {
            return ['success' => false, 'message' => 'Không tìm thấy ca trực nào đang hoạt động để check-out.'];
        }

        $sa->update([
            'check_out_at' => now(),
            'status' => 'completed',
        ]);

        // Flush cached shift-access so middleware reflects the checkout immediately
        $employee->flushShiftAccessCache();

        return ['success' => true, 'message' => 'Bạn đã CHECK-OUT thành công. Cảm ơn bạn vì sự đóng góp tuyệt vời ngày hôm nay!'];
    }

    /**
     * Quản lý/Owner Check-in hộ nhân viên.
     */
    public function checkInEmployee(User $actingUser, array $data): array
    {
        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);

        $sa->update([
            'check_in_at' => now(),
            'status' => 'checked_in',
            'approved_by' => $actingUser->id,
            'notes' => $data['notes'] ?? 'Check-in hộ bởi Quản lý/Chủ nhà hàng',
        ]);

        // Flush cached shift-access for the affected employee
        $sa->employee?->flushShiftAccessCache();

        if (! empty($data['apply_violation'])) {
            $this->createAutoViolation($actingUser, $sa, 'Đi trễ / Vấn đề vào ca', $data['violation_notes'] ?? $data['notes'] ?? 'Check-in hộ kèm vi phạm vào ca', (float) ($data['penalty_amount'] ?? 0));
        }

        return ['success' => true, 'message' => 'Đã ghi nhận Check-in hộ thành công cho nhân viên.'];
    }

    /**
     * Quản lý/Owner Check-out hộ nhân viên.
     */
    public function checkOutEmployee(User $actingUser, array $data): array
    {
        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);

        $sa->update([
            'check_out_at' => now(),
            'status' => 'completed',
            'approved_by' => $actingUser->id,
            'notes' => $data['notes'] ?? 'Check-out hộ bởi Quản lý/Chủ nhà hàng',
        ]);

        // Flush cached shift-access for the affected employee
        $sa->employee?->flushShiftAccessCache();

        if (! empty($data['apply_violation'])) {
            $this->createAutoViolation($actingUser, $sa, 'Về sớm / Vấn đề ra ca', $data['violation_notes'] ?? $data['notes'] ?? 'Check-out hộ kèm vi phạm ra ca', (float) ($data['penalty_amount'] ?? 0));
        }

        return ['success' => true, 'message' => 'Đã ghi nhận Check-out hộ thành công cho nhân viên.'];
    }

    /**
     * Quản lý/Owner Báo vắng (Absent) nhân viên.
     */
    public function markAbsentEmployee(User $actingUser, array $data): array
    {
        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);

        $sa->update([
            'status' => 'absent',
            'approved_by' => $actingUser->id,
            'notes' => $data['notes'] ?? 'Vắng mặt không lý do',
        ]);

        // Flush cached shift-access for the affected employee
        $sa->employee?->flushShiftAccessCache();

        if (! empty($data['apply_violation'])) {
            $this->createAutoViolation($actingUser, $sa, 'Vắng mặt', $data['violation_notes'] ?? $data['notes'] ?? 'Báo vắng trực không lý do', (float) ($data['penalty_amount'] ?? 0));
        }

        return ['success' => true, 'message' => 'Đã ghi nhận báo vắng thành công cho nhân viên.'];
    }

    /**
     * Tự động tạo Biên bản vi phạm kỷ luật khi có tuỳ chọn kèm theo.
     */
    private function createAutoViolation(User $actingUser, ScheduleAssignment $sa, string $violationType, string $description, float $penaltyAmount): void
    {
        ViolationReport::create([
            'restaurant_id' => $sa->restaurant_id,
            'branch_id' => $sa->branch_id,
            'employee_id' => $sa->employee_id,
            'reported_by' => $actingUser->id,
            'violation_type' => $violationType,
            'severity' => 'low',
            'description' => $description,
            'penalty_amount' => $penaltyAmount,
            'occurred_at' => $sa->scheduled_date ? Carbon::parse($sa->scheduled_date)->toDateString().' '.now()->format('H:i:s') : now(),
            'status' => 'resolved', // Đã phê duyệt và áp dụng trực tiếp lên bảng lương nháp
            'is_anonymous' => false,
        ]);
    }
}
