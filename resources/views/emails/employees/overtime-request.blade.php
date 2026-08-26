<!doctype html>
<html lang="vi">
<head><meta charset="utf-8"><title>Yêu cầu tăng ca</title></head>
<body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.6">
    <h2>Yêu cầu tăng ca đột xuất</h2>
    <p>Xin chào {{ $overtime->employee?->full_name ?? 'bạn' }},</p>
    <p>Quản lý <strong>{{ $overtime->requester?->name ?? 'nhà hàng' }}</strong> đề nghị bạn tăng ca với thông tin:</p>
    <ul>
        <li>Ngày: <strong>{{ $overtime->scheduled_date?->format('d/m/Y') }}</strong></li>
        <li>Khung giờ: <strong>{{ $overtime->scheduled_start_at?->format('H:i') }} - {{ $overtime->scheduled_end_at?->format('H:i') }}</strong></li>
        <li>Loại OT: <strong>{{ match ($overtime->overtime_type) { 'night' => 'Ban đêm', 'rest_day' => 'Ngày nghỉ hằng tuần', 'holiday' => 'Ngày lễ / đặc biệt', default => 'Ngày thường' } }}</strong></li>
        <li>Số giờ dự kiến: <strong>{{ number_format((float) $overtime->hours_requested, 2) }} giờ</strong></li>
        <li>Mức dự kiến: <strong>{{ number_format((float) $overtime->hourly_rate, 0, ',', '.') }} đ/giờ × {{ number_format((float) $overtime->overtime_multiplier, 2) }} = {{ number_format((float) $overtime->estimated_amount, 0, ',', '.') }} đ</strong></li>
        <li>Lý do: {{ $overtime->reason ?: 'Theo nhu cầu vận hành đột xuất' }}</li>
    </ul>
    <p>Vui lòng đăng nhập cổng nhân viên để chấp nhận hoặc từ chối yêu cầu. Sau khi bạn chấp nhận, quản lý vẫn cần duyệt lần cuối. Chỉ giờ OT được duyệt và có chấm công thực tế mới được tính vào lương.</p>
</body>
</html>
