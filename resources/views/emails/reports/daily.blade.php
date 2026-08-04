<div style="font-family:'Times New Roman',Times,serif;max-width:600px;margin:auto;padding:0">
    <div style='background:linear-gradient(135deg,#0f766e,#0891b2);padding:32px;border-radius:12px 12px 0 0;text-align:center'>
        <div style='font-size:40px'>📊</div>
        <h1 style='color:#fff;margin:8px 0;font-size:22px'>Báo cáo doanh thu ngày {{ $report_date ?? '' }}</h1>
        <p style='color:#a5f3fc;font-size:14px'>{{ $restaurant_name ?? 'Nhà hàng' }}</p>
    </div>
    <div style='background:#fff;padding:32px;border-radius:0 0 12px 12px;border:1px solid #e0e7ff'>
        <div style='display:grid;gap:12px;margin-bottom:24px'>
            <div style='background:#f0fdfa;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                <span style='color:#6b7280'>💰 Doanh thu gộp</span>
                <strong style='color:#0f766e'>{{ number_format($gross_revenue ?? 0, 0, ',', '.') }}đ</strong>
            </div>
            <div style='background:#fef2f2;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                <span style='color:#6b7280'>🏷️ Giảm giá</span>
                <strong style='color:#dc2626'>-{{ number_format($discount_total ?? 0, 0, ',', '.') }}đ</strong>
            </div>
            <div style='background:#f0fdf4;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                <span style='color:#6b7280'>✅ Doanh thu thuần</span>
                <strong style='color:#059669'>{{ number_format($net_revenue ?? 0, 0, ',', '.') }}đ</strong>
            </div>
            <div style='background:#f5f3ff;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                <span style='color:#6b7280'>📦 Đơn hoàn thành / tổng đơn</span>
                <strong style='color:#4f46e5'>{{ $completed_count ?? 0 }} / {{ $order_count ?? 0 }} ({{ $cancelled_count ?? 0 }} hủy)</strong>
            </div>
            <div style='background:#fff7ed;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                <span style='color:#6b7280'>🧾 Giá trị trung bình/đơn</span>
                <strong style='color:#ea580c'>{{ number_format($average_order_value ?? 0, 0, ',', '.') }}đ</strong>
            </div>
        </div>

        <h3 style='color:#374151;font-size:14px;margin-bottom:12px'>💳 Theo phương thức thanh toán</h3>
        <table style='width:100%;border-collapse:collapse;margin-bottom:24px'>
            <tbody>
                <tr>
                    <td style='padding:6px 0;color:#6b7280;font-size:13px'>Tiền mặt</td>
                    <td style='padding:6px 0;text-align:right;font-size:13px'>{{ number_format($cash_revenue ?? 0, 0, ',', '.') }}đ</td>
                </tr>
                <tr>
                    <td style='padding:6px 0;color:#6b7280;font-size:13px'>Chuyển khoản</td>
                    <td style='padding:6px 0;text-align:right;font-size:13px'>{{ number_format($bank_transfer_revenue ?? 0, 0, ',', '.') }}đ</td>
                </tr>
                <tr>
                    <td style='padding:6px 0;color:#6b7280;font-size:13px'>Thẻ ngân hàng</td>
                    <td style='padding:6px 0;text-align:right;font-size:13px'>{{ number_format($card_revenue ?? 0, 0, ',', '.') }}đ</td>
                </tr>
                <tr>
                    <td style='padding:6px 0;color:#6b7280;font-size:13px'>Ví điện tử</td>
                    <td style='padding:6px 0;text-align:right;font-size:13px'>{{ number_format($ewallet_revenue ?? 0, 0, ',', '.') }}đ</td>
                </tr>
            </tbody>
        </table>

        @if(!empty($top_products))
        <h3 style='color:#374151;font-size:14px;margin-bottom:12px'>🏆 Top món bán chạy</h3>
        <ol style='padding-left:16px;color:#4b5563;font-size:14px;margin-bottom:24px'>
            @foreach(array_slice($top_products, 0, 5) as $p)
                <li style='margin-bottom:4px'>{{ $p['name'] ?? '' }} — {{ $p['qty'] ?? 0 }} phần <span style='color:#9ca3af'>({{ number_format($p['revenue'] ?? 0, 0, ',', '.') }}đ)</span></li>
            @endforeach
        </ol>
        @endif

        @if(!empty($peak_hour))
        <p style='background:#eff6ff;padding:12px 16px;border-radius:8px;color:#1e40af;font-size:13px;margin-bottom:24px'>
            ⏰ Giờ cao điểm: <strong>{{ $peak_hour['label'] ?? '' }}</strong> ({{ $peak_hour['order_count'] ?? 0 }} đơn, {{ number_format($peak_hour['revenue'] ?? 0, 0, ',', '.') }}đ)
        </p>
        @endif

        @if(!empty($comparison))
        <h3 style='color:#374151;font-size:14px;margin-bottom:12px'>📈 So với hôm qua ({{ $comparison['yesterday_date'] ?? '' }})</h3>
        <p style='color:{{ ($comparison['revenue_delta'] ?? 0) >= 0 ? "#059669" : "#dc2626" }};font-size:14px;margin-bottom:8px'>
            Doanh thu: {{ ($comparison['revenue_delta'] ?? 0) >= 0 ? '▲' : '▼' }} {{ number_format(abs($comparison['revenue_delta'] ?? 0), 0, ',', '.') }}đ
            ({{ ($comparison['revenue_delta_pct'] ?? 0) >= 0 ? '+' : '' }}{{ $comparison['revenue_delta_pct'] ?? 0 }}%)
        </p>
        <p style='color:#6b7280;font-size:13px;margin-bottom:24px'>
            Đơn hàng: {{ ($comparison['order_delta'] ?? 0) >= 0 ? '+' : '' }}{{ $comparison['order_delta'] ?? 0 }} đơn so với hôm qua
        </p>
        @endif

        @if(!empty($shift_summary))
        <h3 style='color:#374151;font-size:14px;margin-bottom:12px'>🔒 Chốt ca trong ngày</h3>
        <ul style='padding-left:16px;color:#4b5563;font-size:13px;margin-bottom:16px'>
            @foreach($shift_summary as $s)
                <li style='margin-bottom:4px'>
                    {{ $s['shift_name'] ?? '—' }}:
                    {{ match($s['status'] ?? '') { 'confirmed' => '✓ Đã xác nhận', 'disputed' => '⚠ Tranh chấp', 'submitted' => '⏳ Chờ duyệt', default => '○ Bản nháp' } }}
                    · Chênh lệch: <span style='color:{{ ($s['cash_difference'] ?? 0) >= 0 ? "#059669" : "#dc2626" }}'>{{ ($s['cash_difference'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($s['cash_difference'] ?? 0, 0, ',', '.') }}đ</span>
                </li>
            @endforeach
        </ul>
        @endif

        @if($has_unconfirmed_shifts ?? false)
        <p style='background:#fffbeb;padding:12px 16px;border-radius:8px;color:#92400e;font-size:13px'>⚠️ Còn ca chưa được xác nhận — vui lòng kiểm tra tại trang Chốt Ca.</p>
        @else
        <p style='color:#059669;font-size:13px'>✓ Tất cả ca trong ngày đã được xác nhận.</p>
        @endif

        <p style='color:#9ca3af;font-size:12px;text-align:center;margin-top:24px'>Báo cáo tự động hằng ngày lúc 23:59 · Aventura</p>
    </div>
</div>
