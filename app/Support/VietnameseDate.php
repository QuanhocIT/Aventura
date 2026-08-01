<?php

namespace App\Support;

/**
 * Tên thứ trong tuần tiếng Việt — dùng chung giữa ScheduleController::index()
 * và ShiftSwapService::suggestSwaps() (trước đây là 1 hàm private trùng lặp
 * khi hai chỗ dùng nằm ở hai class khác nhau sau khi tách "chia để trị").
 */
class VietnameseDate
{
    private const DAYS = [
        'Monday' => 'Thứ Hai',
        'Tuesday' => 'Thứ Ba',
        'Wednesday' => 'Thứ Tư',
        'Thursday' => 'Thứ Năm',
        'Friday' => 'Thứ Sáu',
        'Saturday' => 'Thứ Bảy',
        'Sunday' => 'Chủ Nhật',
    ];

    public static function dayName(string $englishDay): string
    {
        return self::DAYS[$englishDay] ?? $englishDay;
    }
}
