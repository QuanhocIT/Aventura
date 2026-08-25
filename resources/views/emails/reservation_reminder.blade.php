<div style="font-family:'Times New Roman',Times,serif;max-width:560px;margin:auto;padding:0">
    <div style='background:linear-gradient(135deg,#f59e0b,#d97706);padding:32px;border-radius:12px 12px 0 0;text-align:center'>
        <div style='font-size:48px'>🍽️</div>
        <h1 style='color:#fff;margin:8px 0;font-size:24px'>Nhắc nhở đặt bàn</h1>
    </div>
    <div style='background:#fff;padding:32px;border-radius:0 0 12px 12px;border:1px solid #fde68a'>
        <p style='color:#374151'>Xin chào <strong>{{ $recipient_name ?? $name ?? $guest_name ?? 'Bạn' }}</strong>,</p>
        <p style='color:#374151'>Chúng tôi nhắc bạn rằng bạn có đặt bàn tại <strong>{{ $restaurant_name ?? 'Nhà hàng' }}</strong> sắp đến:</p>
        <div style='background:#fef3c7;border-radius:8px;padding:20px;margin:20px 0;border-left:4px solid #f59e0b'>
            <div style='color:#92400e;font-size:14px'>📅 Ngày: <strong>{{ $reservation_date ?? '' }}</strong></div>
            <div style='color:#92400e;font-size:14px;margin-top:8px'>⏰ Giờ: <strong>{{ $reservation_time ?? '' }}</strong></div>
            <div style='color:#92400e;font-size:14px;margin-top:8px'>👥 Số người: <strong>{{ $party_size ?? '' }} khách</strong></div>
            @if(!empty($table_name))
                <div style='color:#92400e;font-size:14px;margin-top:8px'>🪑 Bàn: <strong>{{ $table_name }}</strong></div>
            @endif
        </div>
        @if(!empty($special_requests))
            <p style='color:#6b7280;font-size:13px'>✨ Yêu cầu đặc biệt: {{ $special_requests }}</p>
        @endif
        <p style='color:#374151;margin-top:16px'>Nếu bạn cần thay đổi hoặc hủy bàn, vui lòng liên hệ nhà hàng trước ít nhất 1 giờ.</p>
        <hr style='border:none;border-top:1px solid #e5e7eb;margin:24px 0'>
        <p style='color:#9ca3af;font-size:12px;text-align:center'>Email tự động từ hệ thống Aventura · {{ $restaurant_name ?? 'Nhà hàng' }}</p>
    </div>
</div>
