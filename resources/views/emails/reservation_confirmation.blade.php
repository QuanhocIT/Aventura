<div style='font-family:sans-serif;max-width:560px;margin:auto;padding:0'>
    <div style='background:linear-gradient(135deg,#10b981,#059669);padding:32px;border-radius:12px 12px 0 0;text-align:center'>
        <div style='font-size:48px'>✅</div>
        <h1 style='color:#fff;margin:8px 0;font-size:24px'>Đặt bàn đã được xác nhận!</h1>
    </div>
    <div style='background:#fff;padding:32px;border-radius:0 0 12px 12px;border:1px solid #d1fae5'>
        <p>Xin chào <strong>{{ $recipient_name ?? $name ?? $guest_name ?? 'Bạn' }}</strong>,</p>
        <p>Nhà hàng <strong>{{ $restaurant_name ?? 'Nhà hàng' }}</strong> đã xác nhận đặt bàn của bạn:</p>
        <div style='background:#f0fdf4;border-radius:8px;padding:20px;margin:20px 0;border-left:4px solid #10b981'>
            <div style='color:#065f46;font-size:14px'>📅 {{ $reservation_date ?? '' }} lúc {{ $reservation_time ?? '' }}</div>
            <div style='color:#065f46;font-size:14px;margin-top:8px'>👥 {{ $party_size ?? '' }} khách</div>
            @if(!empty($table_name))
                <div style='color:#065f46;font-size:14px;margin-top:8px'>🪑 Bàn: {{ $table_name }}</div>
            @endif
        </div>
        @if(!empty($internal_notes))
            <p style='color:#6b7280;font-size:13px;font-style:italic'>📝 Ghi chú: {{ $internal_notes }}</p>
        @endif
        <p style='color:#9ca3af;font-size:12px;text-align:center;margin-top:24px'>Hẹn gặp bạn tại {{ $restaurant_name ?? 'Nhà hàng' }} 🍽️</p>
    </div>
</div>
