<div style="font-family:'Times New Roman',Times,serif;max-width:560px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:1px solid #fecaca">
    @if(!empty($status_url))
        <p style='text-align:center;margin:0 0 20px'><a href="{{ $status_url }}" style='display:inline-block;background:#dc2626;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:bold'>Xem trạng thái đặt bàn</a></p>
    @endif
    <h2 style='color:#dc2626'>❌ Đặt bàn đã bị hủy</h2>
    <p>Xin chào <strong>{{ $recipient_name ?? $name ?? $guest_name ?? 'Bạn' }}</strong>,</p>
    <p>Rất tiếc, đặt bàn của bạn tại <strong>{{ $restaurant_name ?? 'Nhà hàng' }}</strong> vào ngày <strong>{{ $reservation_date ?? '' }}</strong> lúc <strong>{{ $reservation_time ?? '' }}</strong> đã bị hủy.</p>
    @if(!empty($reason))
        <div style='background:#fef2f2;padding:16px;border-radius:8px;border-left:4px solid #dc2626;margin:16px 0'><p style='color:#991b1b;margin:0'>Lý do: {{ $reason }}</p></div>
    @endif
    <p style='color:#6b7280;margin-top:16px'>Để đặt bàn lại, vui lòng liên hệ nhà hàng hoặc quét mã QR trên bàn.</p>
    <p style='color:#9ca3af;font-size:12px;margin-top:24px'>Xin lỗi về sự bất tiện này. Chúng tôi hy vọng được phục vụ bạn lần tới.</p>
</div>
