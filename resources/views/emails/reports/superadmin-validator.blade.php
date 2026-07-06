BÁO CÁO TRÌNH KIỂM ĐỊNH HOẠT ĐỘNG DOANH NGHIỆP - {{ $checkedDate }}
==================================================================

TỔNG QUAN HỆ THỐNG:
- Số cửa hàng được kiểm tra: {{ $summary['total_checked'] }}
- Số cửa hàng có hoạt động hôm nay: {{ $summary['active_today'] }}
- Số cửa hàng không hoạt động hôm nay: {{ $summary['inactive_today'] }}
- Số cửa hàng MỚI bị gắn cờ cảnh báo: {{ count($newlyFlagged) }}
- Tổng số cửa hàng đang bị gắn cờ cảnh báo: {{ $summary['total_flagged'] }}

------------------------------------------------------------------
🚩 DANH SÁCH CỬA HÀNG MỚI BỊ GẮN CỜ (Không hoạt động >= {{ $inactiveDaysLimit }} ngày dù gói còn hạn):
@forelse($newlyFlagged as $index => $r)
{{ $index + 1 }}. {{ $r['name'] }} (Mã: {{ $r['code'] }})
   - Gói dịch vụ: {{ $r['plan'] }} (Hạn dùng: {{ $r['ends_at'] }})
   - Hoạt động cuối: {{ $r['last_active_at'] ?? 'Chưa từng hoạt động' }}
   - Số ngày không hoạt động: {{ $r['inactive_days'] }} ngày
   - Email liên hệ: {{ $r['email'] }}
   - Điện thoại: {{ $r['phone'] }}
@empty
(Không có cửa hàng mới nào bị gắn cờ hôm nay)
@endforelse

------------------------------------------------------------------
🟢 DANH SÁCH CỬA HÀNG CÓ HOẠT ĐỘNG HÔM NAY:
@forelse($activeTodayDetails as $index => $r)
{{ $index + 1 }}. {{ $r['name'] }} (Mã: {{ $r['code'] }})
   - Gói dịch vụ: {{ $r['plan'] }}
   - Số đơn hàng mới: {{ $r['orders_count'] }}
   - Số món ăn ra bếp: {{ $r['dishes_count'] }}
   - Doanh thu hôm nay: {{ number_format($r['revenue'], 0, ',', '.') }} {{ $r['currency'] }}
@empty
(Không có cửa hàng nào có hoạt động hôm nay)
@endforelse

------------------------------------------------------------------
⚪ DANH SÁCH CỬA HÀNG KHÔNG HOẠT ĐỘNG HÔM NAY:
@forelse($inactiveTodayDetails as $index => $r)
{{ $index + 1 }}. {{ $r['name'] }} (Mã: {{ $r['code'] }})
   - Gói dịch vụ: {{ $r['plan'] }}
   - Hoạt động cuối: {{ $r['last_active_at'] ?? 'Chưa từng hoạt động' }}
   - Trạng thái gắn cờ: {{ $r['is_flagged'] ? '🚩 ĐANG BỊ GẮN CỜ' : 'Bình thường' }}
@empty
(Không có cửa hàng nào không hoạt động hôm nay)
@endforelse

---
Báo cáo tự động từ hệ thống Aventura lúc {{ now()->format('H:i d/m/Y') }}.
Chi tiết quản lý tại: {{ $dashboardUrl }}
