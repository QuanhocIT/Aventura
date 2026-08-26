<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OvertimeAttendanceService
{
    public function settings(Employee $employee, string $date): array
    {
        $policy = app(OvertimePolicyService::class)->policyFor($employee, $date);

        return [
            'require_gps' => (bool) ($policy['require_gps'] ?? true),
            'require_qr' => (bool) ($policy['require_qr'] ?? false),
            'require_photo' => (bool) ($policy['require_photo'] ?? false),
        ];
    }

    /** @return array{latitude: float|null, longitude: float|null, distance: float|null, method: string, photo_path: string|null} */
    public function verify(Employee $employee, OvertimeRequest $overtime, array $input, string $stage): array
    {
        $settings = app(OvertimePolicyService::class)->policyFor($employee, $overtime->scheduled_date);
        $restaurant = $employee->restaurant;
        $targetLat = $employee->branch?->latitude ?? $restaurant?->latitude;
        $targetLng = $employee->branch?->longitude ?? $restaurant?->longitude;
        $distance = null;
        $methods = [];

        if (($settings['require_gps'] ?? true) && $targetLat !== null && $targetLng !== null) {
            $clientLat = $input['latitude'] ?? null;
            $clientLng = $input['longitude'] ?? null;
            if ($clientLat === null || $clientLng === null) {
                abort(422, 'Vui lòng bật GPS và cấp quyền vị trí để chấm công OT.');
            }
            if (! empty($input['is_mock'])) {
                abort(422, 'Phát hiện vị trí giả lập, chấm công OT bị từ chối.');
            }
            if (isset($input['accuracy']) && (float) $input['accuracy'] > 100) {
                abort(422, 'Độ chính xác GPS quá thấp, yêu cầu dưới 100m.');
            }

            $distance = $this->distance((float) $targetLat, (float) $targetLng, (float) $clientLat, (float) $clientLng);
            $radius = $employee->branch?->checkin_radius_meters ?? ($restaurant?->checkin_radius_meters ?? 100);
            if ($distance > $radius) {
                abort(422, 'Bạn đang ở ngoài bán kính chấm công cho phép ('.round($distance).'m / '.$radius.'m).');
            }
            $methods[] = 'gps';
        }

        if ($settings['require_qr'] ?? false) {
            $qr = (string) ($input['qr_code'] ?? '');
            if (! $this->validQr($restaurant, $qr)) {
                abort(422, 'Mã QR chấm công OT không hợp lệ hoặc đã hết hạn.');
            }
            $methods[] = 'qr';
        }

        $photoKey = $stage === 'check-in' ? 'check_in_photo' : 'check_out_photo';
        $photoPath = null;
        if ($settings['require_photo'] ?? false) {
            $photoPath = $this->storePhoto($input[$photoKey] ?? null, $employee->id, $stage);
            if (! $photoPath) {
                abort(422, 'Vui lòng chụp ảnh xác nhận khi chấm công OT.');
            }
            $methods[] = 'photo';
        } elseif (! empty($input[$photoKey])) {
            $photoPath = $this->storePhoto($input[$photoKey], $employee->id, $stage);
            if ($photoPath) {
                $methods[] = 'photo';
            }
        }

        return [
            'latitude' => isset($input['latitude']) ? (float) $input['latitude'] : null,
            'longitude' => isset($input['longitude']) ? (float) $input['longitude'] : null,
            'distance' => $distance,
            'method' => implode('+', $methods) ?: 'manual',
            'photo_path' => $photoPath,
        ];
    }

    private function validQr($restaurant, string $clientQr): bool
    {
        if (! $restaurant || ! $restaurant->qr_checkin_code || $clientQr === '') {
            return false;
        }

        if (str_starts_with($clientQr, 'DYN_')) {
            $now = now()->timestamp;
            for ($i = 0; $i <= 1; $i++) {
                $chunk = floor(($now - ($i * 20)) / 20);
                $expected = 'DYN_'.substr(hash_hmac('sha256', (string) $chunk, (string) $restaurant->id.config('app.key', 'aventura_secret_salt')), 0, 8);
                if (hash_equals(strtoupper($expected), strtoupper($clientQr))) {
                    return true;
                }
            }

            return false;
        }

        return (! $restaurant->qr_checkin_expires_at || now()->lessThanOrEqualTo($restaurant->qr_checkin_expires_at))
            && hash_equals((string) $restaurant->qr_checkin_code, $clientQr);
    }

    private function storePhoto(?string $photo, int $employeeId, string $stage): ?string
    {
        if (! $photo || ! preg_match('/^data:image\/(\w+);base64,/', $photo, $matches)) {
            return null;
        }
        $data = base64_decode(substr($photo, strpos($photo, ',') + 1), true);
        if ($data === false) {
            return null;
        }
        $path = 'overtime/'.$stage.'_'.$employeeId.'_'.time().'_'.Str::random(5).'.'.strtolower($matches[1]);
        Storage::disk('public')->put($path, $data);

        return $path;
    }

    private function distance(float $latFrom, float $lngFrom, float $latTo, float $lngTo): float
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($latFrom);
        $lngFrom = deg2rad($lngFrom);
        $latTo = deg2rad($latTo);
        $lngTo = deg2rad($lngTo);
        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)));

        return $angle * $earthRadius;
    }
}
