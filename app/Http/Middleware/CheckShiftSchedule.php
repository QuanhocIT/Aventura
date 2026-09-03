<?php

namespace App\Http\Middleware;

use App\Models\ScheduleAssignment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckShiftSchedule
 *
 * Hạn chế truy cập API của nhân viên vận hành (thu ngân, phục vụ) theo
 * ca làm việc thực tế trong bảng schedule_assignments.
 *
 * Logic:
 *  - Owner / Manager / Super Admin → luôn được phép.
 *  - Nhân viên (staff / cashier / waiter) → chỉ được truy cập trong giờ ca
 *    + GRACE_MINUTES phút sau khi ca kết thúc (để xử lý nốt đơn cuối cùng).
 *  - Nếu nhà hàng không bật tính năng này → bỏ qua kiểm tra.
 *
 * Grace period mặc định: 15 phút sau khi ca kết thúc.
 * Timezone: lấy từ config('app.timezone') — đã set = Asia/Ho_Chi_Minh.
 */
class CheckShiftSchedule
{
    /** Số phút grace period sau khi ca kết thúc */
    private const GRACE_MINUTES = 15;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Chưa xác thực → để auth middleware xử lý
        if (! $user) {
            return $next($request);
        }

        // Shift-independent roles (including Trưởng kho Tổng) are always allowed.
        if ($user->isExemptFromShiftLock()) {
            return $next($request);
        }

        $restaurant = $user->restaurant;

        // Nhà hàng không tồn tại hoặc chưa bật tính năng kiểm tra ca → bỏ qua
        if (! $restaurant) {
            return $next($request);
        }

        // Kiểm tra feature flag (nếu có trong plan/settings)
        // Nếu nhà hàng chưa bật shift_access_control thì bỏ qua
        if (! $this->isShiftCheckEnabled($restaurant)) {
            return $next($request);
        }

        $restaurantId = $user->restaurant_id;
        $now = now(); // Đã đúng timezone vì config/app.php = Asia/Ho_Chi_Minh

        $isInShift = ScheduleAssignment::where('restaurant_id', $restaurantId)
            ->where('employee_id', function ($query) use ($user) {
                // Lấy employee_id từ bảng employees qua user_id
                $query->select('id')
                    ->from('employees')
                    ->where('user_id', $user->id)
                    ->limit(1);
            })
            ->whereDate('scheduled_date', $now->toDateString())
            ->whereIn('status', ['scheduled', 'checked_in'])
            ->where(function ($q) use ($now) {
                // Đang trong giờ ca (start_time <= now <= end_time)
                $q->where(function ($inner) use ($now) {
                    $inner->where('start_time', '<=', $now->format('H:i:s'))
                        ->where('end_time', '>=', $now->format('H:i:s'));
                })
                // HOẶC trong grace period: ca đã kết thúc nhưng chưa quá GRACE_MINUTES
                    ->orWhere(function ($inner) use ($now) {
                        $graceTime = $now->copy()->subMinutes(self::GRACE_MINUTES);
                        $inner->where('end_time', '>=', $graceTime->format('H:i:s'))
                            ->where('end_time', '<', $now->format('H:i:s'));
                    });
            })
            ->exists();

        if (! $isInShift) {
            // Inertia request → redirect với thông báo
            if ($request->header('X-Inertia')) {
                return redirect()->back()->with([
                    'error' => 'Bạn không trong ca làm việc. Vui lòng liên hệ quản lý.',
                ]);
            }

            // JSON / API request → 403 JSON
            return response()->json([
                'message' => 'Truy cập bị từ chối: Ngoài giờ ca làm việc.',
                'grace_period' => self::GRACE_MINUTES.' phút sau khi ca kết thúc',
                'current_time' => $now->format('H:i:s d/m/Y'),
            ], 403);
        }

        return $next($request);
    }

    /**
     * Kiểm tra nhà hàng có bật tính năng kiểm tra ca không.
     *
     * Quy tắc:
     *  - warehouse_staff → LUÔN áp dụng kiểm tra ca (không cần opt-in theo nhà hàng).
     *  - Các role khác → đọc flag `shift_access_control` trong settings nhà hàng (opt-in).
     */
    private function isShiftCheckEnabled(mixed $restaurant): bool
    {
        $user = request()->user();

        // Nhân viên kho luôn bị kiểm tra ca — không cần bật thêm tính năng.
        if ($user && $user->hasRole('warehouse_staff')) {
            return true;
        }

        // Các role khác: đọc settings nhà hàng (opt-in)
        // Ví dụ: return (bool) ($restaurant->settings['shift_access_control'] ?? false);
        return false;
    }
}
