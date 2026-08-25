<div style="font-family:'Times New Roman',Times,serif;max-width:540px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:2px solid #fde68a">
    <h2 style='color:#d97706'>⚠️ Cảnh báo vắng mặt không phép</h2>
    <p>Nhà hàng <strong>{{ $restaurant_name ?? 'Nhà hàng' }}</strong></p>
    <p>Các nhân viên sau <strong>chưa check-in</strong> sau {{ $minutes_late ?? 15 }} phút kể từ giờ bắt đầu ca:</p>
    <ul style='background:#fffbeb;padding:16px 24px;border-radius:8px;border-left:4px solid #f59e0b'>
        @foreach($employees ?? [] as $e)
            <li style='margin-bottom:6px;color:#92400e'><strong>{{ $e['name'] ?? '' }}</strong> — Ca {{ $e['shift'] ?? '' }}</li>
        @endforeach
    </ul>
    <p style='color:#374151;margin-top:16px'>Vui lòng liên hệ nhân viên ngay hoặc phân công người thay thế.</p>
    <p style='color:#9ca3af;font-size:12px;margin-top:16px'>Cảnh báo tự động · {{ now()->format('d/m/Y H:i') }}</p>
</div>
