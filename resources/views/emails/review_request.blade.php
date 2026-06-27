<div style='font-family:sans-serif;max-width:560px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:1px solid #e5e7eb'>
    <div style='text-align:center;margin-bottom:24px'>
        <div style='font-size:48px'>⭐</div>
        <h2 style='color:#1f2937;margin:8px 0'>Bạn thấy trải nghiệm hôm nay thế nào?</h2>
        <p style='color:#6b7280'>Ý kiến của bạn giúp chúng tôi ngày càng hoàn thiện hơn</p>
    </div>
    <p style='color:#374151'>Xin chào <strong>{{ $recipient_name ?? $name ?? $guest_name ?? 'Bạn' }}</strong>,</p>
    <p style='color:#374151'>Cảm ơn bạn đã ghé thăm <strong>{{ $restaurant_name ?? 'Nhà hàng' }}</strong> vào hôm nay. Chúng tôi hy vọng bạn có một trải nghiệm tuyệt vời!</p>
    <div style='text-align:center;margin:24px 0'>
        <a href='{{ $review_url ?? '#' }}' style='display:inline-block;background:#4f46e5;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:16px'>
            Đánh giá ngay →
        </a>
    </div>
    <p style='color:#9ca3af;font-size:12px;text-align:center'>Chỉ mất 30 giây · Giúp ích rất nhiều cho chúng tôi</p>
</div>
