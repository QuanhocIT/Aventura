<div style="font-family:'Times New Roman',Times,serif;max-width:600px;margin:auto;padding:0">
    <div style='background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:32px;border-radius:12px 12px 0 0;text-align:center'>
        <div style='font-size:40px'>📊</div>
        <h1 style='color:#fff;margin:8px 0;font-size:22px'>Báo cáo tuần</h1>
        <p style='color:#c4b5fd;font-size:14px'>{{ $week_label ?? '' }} · {{ $restaurant_name ?? 'Nhà hàng' }}</p>
    </div>
    <div style='background:#fff;padding:32px;border-radius:0 0 12px 12px;border:1px solid #e0e7ff'>
        <div style='display:grid;gap:12px;margin-bottom:24px'>
            <div style='background:#f5f3ff;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                <span style='color:#6b7280'>💰 Doanh thu</span>
                <strong style='color:#4f46e5'>{{ number_format($revenue ?? 0, 0, ',', '.') }}đ</strong>
            </div>
            <div style='background:#f0fdf4;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                <span style='color:#6b7280'>📦 Tổng đơn</span>
                <strong style='color:#059669'>{{ $order_count ?? 0 }} đơn</strong>
            </div>
            <div style='background:#fff7ed;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                <span style='color:#6b7280'>⭐ NPS trung bình</span>
                <strong style='color:#ea580c'>{{ $avg_rating ?? '—' }}/5.0</strong>
            </div>
            <div style='background:#fdf2f8;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                <span style='color:#6b7280'>📈 So tuần trước</span>
                <strong style='color:{{ ($revenue_trend ?? 0) >= 0 ? "#059669" : "#dc2626" }}'>{{ ($revenue_trend ?? 0) >= 0 ? '↑' : '↓' }} {{ abs($revenue_trend ?? 0) }}%</strong>
            </div>
        </div>
        @if(!empty($top_products))
        <h3 style='color:#374151;font-size:14px;margin-bottom:12px'>🏆 Top món bán chạy tuần này</h3>
        <ol style='padding-left:16px;color:#4b5563;font-size:14px'>
            @foreach(array_slice($top_products ?? [], 0, 5) as $p)
                <li style='margin-bottom:4px'>{{ $p['name'] ?? '' }} <span style='color:#9ca3af'>({{ $p['quantity'] ?? 0 }} phần)</span></li>
            @endforeach
        </ol>
        @endif
        <p style='color:#9ca3af;font-size:12px;text-align:center;margin-top:24px'>Báo cáo tự động mỗi thứ Hai · Aventura</p>
    </div>
</div>
