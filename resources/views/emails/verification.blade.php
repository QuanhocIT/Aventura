<div style='font-family:sans-serif;max-width:480px;margin:auto;padding:32px'>
    <h2 style='color:#4f46e5'>✉️ Xác thực email</h2>
    <p>Xin chào <strong>{{ $recipient_name ?? $name ?? $guest_name ?? 'Bạn' }}</strong>, mã xác thực của bạn là:</p>
    <div style='font-size:32px;font-weight:bold;text-align:center;color:#4f46e5;padding:16px;background:#f0f0ff;border-radius:8px'>
        {{ $code }}
    </div>
</div>
