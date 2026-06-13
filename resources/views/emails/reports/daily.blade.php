BÁO CÁO DOANH THU NGÀY {{ $report_date_vn }}
Nhà hàng: {{ $restaurant_name }}
=========================================

TỔNG QUAN
  Doanh thu gộp  : {{ number_format($gross_revenue, 0, ',', '.') }}đ
  Giảm giá       : -{{ number_format($discount_total, 0, ',', '.') }}đ
  Doanh thu thuần: {{ number_format($net_revenue, 0, ',', '.') }}đ
  Đơn hoàn thành : {{ $completed_count }} / {{ $order_count }} đơn
  Đơn hủy        : {{ $cancelled_count }} đơn
  Giá trị TB/đơn : {{ number_format($average_order_value, 0, ',', '.') }}đ

THANH TOÁN
  Tiền mặt      : {{ number_format($cash_revenue, 0, ',', '.') }}đ
  Chuyển khoản  : {{ number_format($bank_transfer_revenue, 0, ',', '.') }}đ
  Thẻ ngân hàng : {{ number_format($card_revenue, 0, ',', '.') }}đ
  Ví điện tử    : {{ number_format($ewallet_revenue, 0, ',', '.') }}đ

@if(count($top_products) > 0)
TOP SẢN PHẨM BÁN CHẠY
@foreach($top_products as $i => $p)
  {{ $i + 1 }}. {{ $p['name'] }} — {{ $p['qty'] }} món — {{ number_format($p['revenue'], 0, ',', '.') }}đ
@endforeach
@endif

@if($peak_hour)
GIỜ CAO ĐIỂM: {{ $peak_hour['label'] }} ({{ $peak_hour['order_count'] }} đơn, {{ number_format($peak_hour['revenue'], 0, ',', '.') }}đ)
@endif

SO SÁNH HÔM QUA ({{ $comparison['yesterday_date'] }})
  Doanh thu : {{ $comparison['revenue_delta'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($comparison['revenue_delta']), 0, ',', '.') }}đ ({{ $comparison['revenue_delta_pct'] >= 0 ? '+' : '' }}{{ $comparison['revenue_delta_pct'] }}%)
  Đơn hàng  : {{ $comparison['order_delta'] >= 0 ? '+' : '' }}{{ $comparison['order_delta'] }} đơn

@if(count($shift_summary) > 0)
CHỐT CA
@foreach($shift_summary as $s)
  {{ $s['shift_name'] }}: {{ match($s['status']) { 'confirmed' => '✓ Đã xác nhận', 'disputed' => '⚠ Tranh chấp', 'submitted' => '⏳ Chờ duyệt', default => '○ Bản nháp' } }} | Chênh lệch: {{ $s['cash_difference'] >= 0 ? '+' : '' }}{{ number_format($s['cash_difference'], 0, ',', '.') }}đ
@endforeach
@endif

@if($has_unconfirmed_shifts)
⚠️ CÒN CA CHƯA ĐƯỢC XÁC NHẬN — Vui lòng kiểm tra tại trang Chốt Ca.
@else
✓ Tất cả ca trong ngày đã được xác nhận.
@endif

---
Báo cáo tự động từ hệ thống Aventura lúc {{ now()->format('H:i d/m/Y') }}.
