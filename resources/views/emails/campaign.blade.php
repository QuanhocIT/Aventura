<div style='font-family:sans-serif;max-width:600px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:1px solid #e2e8f0'>
    <h2 style='color:#4f46e5;margin-bottom:20px;font-size:20px;font-weight:bold;text-align:center'>📢 {{ $title ?? 'Thông báo hệ thống' }}</h2>
    <p>Xin chào <strong>{{ $recipient_name ?? $name ?? $guest_name ?? 'Bạn' }}</strong>,</p>
    <div style='font-size:16px;line-height:1.6;color:#334155;margin-top:16px;white-space:pre-wrap'>{!! nl2br(e($content ?? '')) !!}</div>
    <hr style='border:none;border-top:1px solid #e2e8f0;margin:24px 0' />
    <p style='color:#64748b;font-size:12px;text-align:center'>Email này được gửi từ ban quản trị hệ thống SaaS Aventura.</p>
</div>
