@php($period = $frequency === 'monthly' ? 'tháng' : 'tuần')
Báo cáo tổng quan hệ thống Aventura — {{ $generatedAt->format('d/m/Y H:i') }}
Tần suất: định kỳ mỗi {{ $period }}

CHỈ SỐ TỔNG QUAN
- Tổng số nhà hàng: {{ number_format($snapshot['stats']['total_restaurants']) }} (đang hoạt động: {{ number_format($snapshot['stats']['active']) }}, tạm ngưng: {{ number_format($snapshot['stats']['suspended']) }}, hết hạn: {{ number_format($snapshot['stats']['expired']) }})
- Tổng số người dùng: {{ number_format($snapshot['stats']['total_users']) }}
- Số nhà hàng gói Pro: {{ number_format($snapshot['stats']['pro_plan']) }}

CHỈ SỐ SAAS
- MRR: {{ number_format($snapshot['saasMetrics']['mrr']) }} VNĐ — ARR: {{ number_format($snapshot['saasMetrics']['arr']) }} VNĐ
- Tỷ lệ rời bỏ tháng này: {{ $snapshot['saasMetrics']['churn_rate'] }}%
- Số thuê bao đang hoạt động: {{ number_format($snapshot['saasMetrics']['active_subscriptions']) }}
- Số tenant trả phí: {{ number_format($snapshot['saasMetrics']['paid_tenants']) }}

GIỮ CHÂN DOANH THU ({{ $snapshot['revenueRetention']['period_label'] }} so với {{ $snapshot['revenueRetention']['previous_label'] }})
- NRR: {{ $snapshot['revenueRetention']['nrr'] !== null ? $snapshot['revenueRetention']['nrr'].'%' : 'Chưa đủ dữ liệu' }}
- GRR: {{ $snapshot['revenueRetention']['grr'] !== null ? $snapshot['revenueRetention']['grr'].'%' : 'Chưa đủ dữ liệu' }}

@if (count($snapshot['alerts']))
CẢNH BÁO ĐANG MỞ
@foreach ($snapshot['alerts'] as $alert)
- [{{ $alert['severity'] === 'critical' ? 'Nghiêm trọng' : 'Cảnh báo' }}] {{ $alert['title'] }}: {{ $alert['message'] }}
@endforeach

@endif
Xem dashboard đầy đủ: {{ $dashboardUrl }}
Xem & in báo cáo chi tiết: {{ $reportUrl }}

--
Đây là email tự động từ hệ thống Aventura. Bạn có thể tắt báo cáo định kỳ này trong trang Dashboard Super Admin.
