# Hồi sinh 10 tác vụ định kỳ từng bị "chết" (routes/schedule.php)

## Bối cảnh

`app/Console/Kernel.php` từng khai báo 14 tác vụ định kỳ nhưng **không bao giờ chạy**:
Laravel 11+ (`bootstrap/app.php` dùng `Application::configure()`) bind thẳng
`Illuminate\Foundation\Console\Kernel`, không gọi tới `App\Console\Kernel::schedule()`.
Xác nhận bằng `php artisan schedule:list` trước khi sửa: chỉ in ra 23 dòng từ
`routes/console.php` cũ, không có dòng nào từ Kernel.php.

Đã gộp toàn bộ lịch về **`routes/schedule.php`** (nguồn duy nhất, đăng ký qua
`bootstrap/app.php` -> `withSchedule()`), xoá `app/Console/Kernel.php`.

10 trong số 14 tác vụ cũ (đã loại `kpis:recalculate` trùng `kpis:calculate`;
3 tác vụ P0 partition/archive/intraday-summary được bật thẳng vì rủi ro thấp và
cần thiết ngay) được bọc qua cờ môi trường riêng, **mặc định TẮT**
(`->skip(...)` khi cờ chưa bật) vì đây là 14 tác vụ chưa từng chạy thật trong hệ
thống — vài cái mutate dữ liệu hoặc gửi email cho khách hàng thật. Không bật
đồng loạt.

## Thứ tự bật khuyến nghị

Bật từng cờ một, cách nhau vài ngày, kiểm tra log + dữ liệu ảnh hưởng sau mỗi lần bật.

| # | Cờ env | Lệnh | Rủi ro | Vì sao |
|---|---|---|---|---|
| 1 | `SCHEDULER_RESTORE_TICKETS_SLA` | `tickets:check-sla` | Thấp | Chỉ đọc + tạo cảnh báo nội bộ |
| 2 | `SCHEDULER_RESTORE_KITCHEN_ALERT_OVERDUE` | `kitchen:alert-overdue-orders` | Thấp | Chỉ đọc + cảnh báo nội bộ |
| 3 | `SCHEDULER_RESTORE_RESERVATIONS_NO_SHOWS` | `reservations:mark-no-shows` | Trung bình | Đổi trạng thái đặt bàn — chạy thử `--dry-run` nếu lệnh hỗ trợ, kiểm tra số bản ghi bị đổi |
| 4 | `SCHEDULER_RESTORE_RESERVATIONS_REMINDERS` | `reservations:send-reminders` | Trung bình | Gửi thông báo (không phải email hàng loạt) |
| 5 | `SCHEDULER_RESTORE_PROMOTIONS_EXPIRE` | `promotions:expire-outdated` | Trung bình | Đổi trạng thái khuyến mãi đang chạy |
| 6 | `SCHEDULER_RESTORE_SHIFTS_AUTO_CLOSE` | `shifts:auto-close-expired` | Trung bình | Tự đóng ca làm — kiểm tra không đóng nhầm ca đang mở hợp lệ |
| 7 | `SCHEDULER_RESTORE_RESTAURANTS_VALIDATE` | `restaurants:validate-activity` | Trung bình | Đổi trạng thái nhà hàng (active/inactive) |
| 8 | `SCHEDULER_RESTORE_RESTAURANTS_HEALTH` | `restaurants:calculate-health` | Thấp | Chỉ tính điểm sức khoẻ, không đổi trạng thái nghiệp vụ |
| 9 | `SCHEDULER_RESTORE_ONBOARDING_SYNC` | `onboarding:sync` | Trung bình | Đổi trạng thái onboarding user |
| 10 | `SCHEDULER_RESTORE_TRIAL_ONBOARDING_EMAILS` | `trial:onboarding-emails` | **Cao nhất** | Gửi email cho TOÀN BỘ khách đang trial cùng lúc lần đầu — chạy tay 1 lần trong maintenance window trước, xác nhận nội dung/danh sách người nhận đúng, rồi mới bật cờ |

## Cách bật

Thêm vào `.env` (production), ví dụ bật mục #1:

```env
SCHEDULER_RESTORE_TICKETS_SLA=true
```

Sau khi bật, xác nhận:

```bash
php artisan config:clear
php artisan schedule:list | grep tickets:check-sla   # không còn bị Skipped
php artisan schedule:test                            # chọn tác vụ vừa bật, chạy thử tay 1 lần
```

## Việc đã bật thẳng (không cần cờ)

3 tác vụ nhóm "P0" trong `routes/schedule.php` được bật ngay vì cần thiết cho
hiệu năng và rủi ro thấp (đã có sẵn `--dry-run`/`withoutOverlapping`/`onOneServer`):
- `orders:archive-old --months=3` (hàng ngày 02:30) — bắt đầu 3 tháng, xiết dần
  xuống 1 tháng sau khi xác nhận ổn định vài ngày.
- `db:manage-partitions --forward=6` (hàng tháng + hàng tuần).
- `orders:purge --months=36 --confirm` (hàng tháng — mặc định lệnh là dry-run,
  đã thêm `--confirm` nên sẽ DROP partition thật; kiểm tra kỹ trước khi deploy).
- `promotions:calculate-analytics` (hàng ngày 00:20) — trước đây mồ côi hoàn toàn.
- `kpis:calculate` (hàng ngày 01:00) — đã xoá `kpis:recalculate` trùng lặp.
