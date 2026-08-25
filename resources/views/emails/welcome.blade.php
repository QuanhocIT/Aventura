<div style="font-family:'Times New Roman',Times,serif;max-width:480px;margin:auto;padding:32px;line-height:1.6;color:#333">
    <h2 style='color:#4f46e5'>👋 Chào mừng đến với Aventura!</h2>
    <p>Xin chào <strong>{{ $recipient_name ?? $name ?? 'Bạn' }}</strong>,</p>
    <p>Cảm ơn bạn đã đăng ký tài khoản Aventura cho nhà hàng/cửa hàng <strong>{{ $restaurant_name }}</strong>.</p>
    <p>Thời gian dùng thử miễn phí của bạn là: <strong>{{ $trial_days }} ngày</strong>.</p>
    <p>Bạn có thể đăng nhập vào hệ thống tại: <a href="{{ $login_url }}" style="color:#4f46e5;font-weight:bold;text-decoration:none">{{ $login_url }}</a></p>
    <p>Nếu bạn cần bất kỳ sự hỗ trợ nào, hãy liên hệ với chúng tôi qua cổng hỗ trợ.</p>
    <p>Trân trọng,<br>Đội ngũ Aventura</p>
</div>
