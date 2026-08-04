<div style="font-family:'Times New Roman',Times,serif;max-width:560px;margin:auto;padding:0">
    <div style='background:linear-gradient(135deg,#ec4899,#8b5cf6);padding:40px;border-radius:12px 12px 0 0;text-align:center'>
        <div style='font-size:56px'>🎂</div>
        <h1 style='color:#fff;margin:12px 0;font-size:26px'>Chúc mừng sinh nhật!</h1>
        <p style='color:#fce7f3;font-size:16px'>Chúng tôi yêu quý bạn ❤️</p>
    </div>
    <div style='background:#fff;padding:32px;border-radius:0 0 12px 12px;border:1px solid #f9a8d4'>
        <p style='color:#374151;font-size:16px'>Xin chào <strong>{{ $recipient_name ?? $name ?? $guest_name ?? 'Bạn' }}</strong>,</p>
        <p style='color:#374151'>Nhân dịp sinh nhật của bạn, <strong>{{ $restaurant_name ?? 'Nhà hàng' }}</strong> xin tặng bạn một món quà đặc biệt:</p>
        <div style='background:linear-gradient(135deg,#fdf2f8,#f5f3ff);border:2px dashed #ec4899;border-radius:12px;padding:24px;margin:24px 0;text-align:center'>
            <div style='font-size:14px;color:#9ca3af;letter-spacing:2px;text-transform:uppercase'>Voucher sinh nhật</div>
            <div style='font-size:48px;font-weight:900;color:#ec4899;margin:8px 0'>{{ $discount ?? '20%' }}</div>
            <div style='font-size:14px;color:#6b7280'>GIẢM GIÁ cho lần ghé thăm tiếp theo</div>
            <div style='margin-top:16px;padding:12px 24px;background:#ec4899;color:#fff;border-radius:8px;font-weight:bold;font-size:18px;letter-spacing:4px;display:inline-block'>{{ $voucher_code ?? 'BIRTHDAY' }}</div>
            <div style='font-size:12px;color:#9ca3af;margin-top:8px'>Hiệu lực đến: {{ $expires_at ?? '' }}</div>
        </div>
        <p style='color:#9ca3af;font-size:12px;text-align:center'>Chúc bạn một ngày sinh nhật thật vui vẻ và hạnh phúc! 🎉</p>
    </div>
</div>
