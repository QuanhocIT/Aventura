<div style='font-family:sans-serif;max-width:480px;margin:auto;padding:32px;background:#fff;border-radius:12px'>
    <h2 style='color:#4f46e5'>🔐 Mã xác thực 2 lớp</h2>
    <p>Xin chào <strong>{{ $recipient_name ?? $name ?? $guest_name ?? 'Bạn' }}</strong>,</p>
    <p>Mã OTP đăng nhập của bạn là:</p>
    <div style='font-size:36px;font-weight:bold;letter-spacing:8px;color:#4f46e5;text-align:center;padding:16px;background:#f0f0ff;border-radius:8px'>
        {{ $code }}
    </div>
    <p style='color:#888;font-size:13px;margin-top:16px'>Mã có hiệu lực trong <strong>{{ $expires_in_minutes }} phút</strong>. Không chia sẻ mã này với ai.</p>
</div>
