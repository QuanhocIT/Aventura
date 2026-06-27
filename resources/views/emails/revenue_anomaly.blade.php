<div style='font-family:sans-serif;max-width:540px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:2px solid #fca5a5'>
    <h2 style='color:#dc2626'>📉 Cảnh báo doanh thu bất thường</h2>
    <p>Nhà hàng <strong>{{ $restaurant_name ?? 'Nhà hàng' }}</strong></p>
    <div style='background:#fef2f2;padding:20px;border-radius:8px;margin:16px 0'>
        <p style='color:#991b1b;margin:0'>Doanh thu hiện tại: <strong>{{ number_format($current_revenue ?? 0, 0, ',', '.') }}đ</strong></p>
        <p style='color:#991b1b;margin:8px 0 0'>Trung bình cùng ngày tuần trước: <strong>{{ number_format($expected_revenue ?? 0, 0, ',', '.') }}đ</strong></p>
        <p style='color:#dc2626;font-size:18px;font-weight:bold;margin:8px 0 0'>Chênh lệch: ↓ {{ $drop_percent ?? 0 }}%</p>
    </div>
    <p style='color:#374151'>Đây có thể do lý do đặc biệt (ngày lễ, thời tiết...) hoặc cần kiểm tra vận hành.</p>
    <p style='color:#9ca3af;font-size:12px;margin-top:16px'>Cảnh báo tự động lúc 15:00 · Aventura</p>
</div>
