# Báo cáo kiểm tra & nâng cấp dự án Aventura

**Ngày:** 30/07/2026
**Phạm vi:** Rà soát toàn dự án, vá lỗi, bổ sung test, dọn nợ kỹ thuật
**Kết luận:** Dự án ở trạng thái ổn định. Toàn bộ kiểm tra tự động đều xanh.

---

## 1. Kết quả kiểm tra tổng thể

| Hạng mục | Lệnh | Kết quả |
|---|---|---|
| Test tự động | `php artisan test` | **561 passed / 0 failed** (2.629 assertion, 3 skip có điều kiện) |
| Kiểu TypeScript | `npm run types:check` | **Sạch, 0 lỗi** |
| Build production | `npx vite build` | **Thành công** (~22s, bundle chính 277 KB → 63 KB gzip) |
| Chuẩn code PHP | `pint --test` (file đã sửa) | **Sạch** |
| Migration | `php artisan migrate:status` | **0 migration còn treo** |
| Route | `php artisan route:list` | **633 route** |
| Tác vụ định kỳ | `php artisan schedule:list` | **41 tác vụ**, không có tác vụ chết |
| 3 AI service Python | curl `/health`, `/chat` | **Cả 3 phản hồi đúng** |

**So với đầu phiên:** test tăng từ 487 → 561 (**+74 test mới**), tất cả nhắm vào các luồng trước đây có **0% coverage**.

---

## 2. Lỗi nghiêm trọng đã phát hiện & vá

### 2.1. Nhóm "rollback ngầm" — nghiêm trọng nhất

Bảng `audit_logs` dùng cột `subject_type` / `subject_id` (**không phải** `auditable_*`) và cột `event` là ENUM bắt buộc. Ba chỗ trong code ghi sai tên cột và thiếu `event`, khiến câu lệnh INSERT ném lỗi SQL **ngay bên trong `DB::transaction`** → **rollback toàn bộ nghiệp vụ**, nhưng lỗi bị `catch` nuốt nên người dùng vẫn thấy thông báo "thành công".

| Chức năng | Hậu quả thực tế |
|---|---|
| Xác nhận đặt bàn | **Không bao giờ lưu** — nhân viên bấm xác nhận, hệ thống báo thành công, đặt bàn vẫn ở trạng thái chờ |
| Đánh dấu khách không đến (no-show) | Không lưu, bàn không được giải phóng |
| **Hoàn tiền đơn hàng** | **Không lưu** — báo hoàn tiền thành công nhưng đơn vẫn ở trạng thái đã thanh toán |
| Cảnh báo thất thoát kho | Không ghi được cảnh báo, hỏng cả lần kiểm kê |

**Cách vá:** thay `AuditLog::create([...])` bằng `AuditLog::log($action, $event, $subject, $old, $new)` ở cả 4 chỗ.

**Ghi chú quan trọng:** loại lỗi này **không thể phát hiện bằng đọc code** — code trông hoàn toàn đúng, exception bị nuốt. Chỉ lộ ra khi viết test cho luồng chưa có coverage.

### 2.2. Lỗ hổng cướp điểm tích luỹ của khách

API `claim-points` trước đây chỉ cần `order_id`. Kẻ tấn công quét ID tuần tự (1, 2, 3...) là chiếm được điểm của hoá đơn người khác, và claim lặp vô hạn để nhân điểm.

**Đã vá:** thêm token HMAC phát ra từ màn hình xác nhận thanh toán + chặn hoá đơn chưa thanh toán + chặn claim lần thứ hai.

### 2.3. Ảnh CCCD nhân viên lộ công khai

Ảnh căn cước nhân viên lưu ở `storage/public` → truy cập được qua URL `/storage/citizen_ids/...` **không cần đăng nhập**, chỉ cần đoán tên file.

**Đã vá:** chuyển sang disk riêng tư (`storage/app/private`) + route `employees.citizen-id` kiểm tra quyền Owner/Manager và đúng nhà hàng.

> ⚠️ **Khi deploy production bắt buộc chạy:** `php artisan employees:secure-citizen-ids`
> (có `--dry-run` để xem trước). Lệnh này di chuyển ảnh cũ sang thư mục riêng tư.

### 2.4. Validation không giới hạn theo nhà hàng

59 chỗ dùng `'exists:table,id'` — kiểm tra ID tồn tại **trên toàn hệ thống**, không giới hạn trong nhà hàng của người dùng. Kẻ tấn công đoán ID của nhà hàng khác vẫn vượt qua được validation.

**Đã vá:** tạo `App\Support\TenantRule::exists()` và thay toàn bộ 59 chỗ. Luồng khách (không đăng nhập) truyền `restaurant_id` từ ngữ cảnh.

### 2.5. Webhook thanh toán có thể cộng tiền 2 lần

Hai webhook trùng đến đồng thời (cổng thanh toán retry) đều vượt qua bước kiểm tra rồi cùng ghi → gia hạn gói 2 lần / cộng tiền 2 lần.

**Đã vá:** thêm unique index `payment_webhooks(provider, transaction_code, signature)`; `payOrder()` trả về `bool` để nơi gọi biết đây là webhook trùng và không bắn lại thông báo.

### 2.6. Gói cước riêng lộ giá công khai

Trang đăng nhập lọc thiếu điều kiện `is_custom = false` (trang chủ vốn có). Gói riêng do SuperAdmin dựng cho **một khách hàng cụ thể** sẽ hiện nguyên giá đàm phán trên bảng giá công khai.

**Đã vá** + 3 test khoá lại hành vi.

### 2.7. Định tuyến giao hàng làm rơi đơn

Khi shipper **chưa bật GPS**, thuật toán lấy điểm đầu tiên làm mốc xuất phát nhưng quên đưa nó vào lộ trình → **mất trắng 1 đơn** khỏi danh sách giao.

### 2.8. Giá vốn món ăn treo giá cũ

Giá vốn (`cost_price`) chỉ được tính lại khi **nhập hàng** làm đổi giá nguyên liệu. **Sửa hoặc xoá công thức định lượng không kích hoạt gì** → giá vốn giữ nguyên giá cũ.

Hậu quả: toàn bộ Menu Engineering, ma trận BCG, cảnh báo biên lợi nhuận thấp đều **chạy trên số liệu sai**. Ví dụ thêm tôm hùm vào một món mà giá vốn không đổi → hệ thống vẫn báo món đó lãi tốt trong khi thực tế đang bán lỗ.

**Đã vá:** tách `ProductCostService` dùng chung cho cả 3 nơi (nhập hàng, sửa công thức, xoá công thức).

### 2.9. Ghi ref trong computed (Vue)

`salaries/Index.vue` gán `currentPage.value = 1` bên trong một `computed` — Vue cấm việc này (getter phải thuần), dễ gây reset trang sai thời điểm. Đã chuyển sang `watch`.

---

## 3. Cải thiện hiệu năng

| Việc | Chi tiết |
|---|---|
| `whereDate` → `whereBetween` | `whereDate(cột, ...)` sinh ra `DATE(cột) = ?` — hàm bọc quanh cột khiến MySQL **bỏ qua index và quét toàn bảng**. Đã sửa 2 truy vấn nóng nhất (thống kê module Giao hàng bị poll liên tục, trang chi tiết nhà hàng SuperAdmin) |
| Sửa N+1 | `SalaryController` điều chỉnh lương hàng loạt: eager-load nhân viên + gộp 2 truy vấn `sum()` trong vòng lặp thành 2 truy vấn tổng |
| 4 index mới | `delivery_details(restaurant_id, delivery_status, delivered_at)`, `customers(restaurant_id, last_order_at)`, `customers(restaurant_id, total_spent)`, `salaries(restaurant_id, pay_period_start)` |
| CDP Dashboard | Chuyển sang phân trang phía server (20/trang). Trước đây tải **toàn bộ** danh sách khách hàng kèm dữ liệu RFM về trình duyệt rồi mới lọc |

---

## 4. Dọn nợ kỹ thuật & giao diện

- **Gộp phân trang:** nâng cấp `Pagination.vue` hỗ trợ 2 kiểu điều hướng (Inertia Link / nút bấm giữ tab), rồi gộp **10 trang** vốn tự viết lại markup riêng. Cố ý chừa `News.vue` vì là trang marketing công khai, khác hệ thiết kế.
- **Dọn dữ liệu rác:** xoá 9 gói cước và 8 nhà hàng do `factory()` sinh ra lẫn vào DB dev (tên faker, giá ngẫu nhiên) — chúng hiện lên bảng giá công khai vì có cùng trạng thái với gói thật. Bảng giá nay đúng **4 gói: Miễn Phí · Cơ Bản · Chuyên Nghiệp · Doanh Nghiệp**.
- **Khởi động AI 1 lệnh:** `services/start-all.ps1` + `composer dev:ai` — trước đây phải chạy tay 3 lệnh `uvicorn`, quên 1 cái là chatbot báo "tạm thời không khả dụng".

---

## 5. Lệnh mới bổ sung

| Lệnh | Công dụng |
|---|---|
| `php artisan employees:secure-citizen-ids` | Di trú ảnh CCCD từ thư mục công khai sang riêng tư (**bắt buộc chạy khi deploy**) |
| `php artisan db:purge-factory-data` | Dọn gói cước/nhà hàng do factory sinh ra lẫn trong DB dev. Mặc định dry-run, cấm chạy trên production |
| `composer dev:ai` | Khởi động cả 3 AI service Python bằng 1 lệnh |

---

## 6. Test mới bổ sung (+74)

Các module dưới đây trước đây có **0 test**:

| Module | Số test | Ghi chú |
|---|---|---|
| Giao hàng thông minh (TSP + cân tải shipper) | 13 | Module phức tạp nhất, trước đây hoàn toàn không có test |
| Đặt bàn (công khai + vòng đời xác nhận) | 13 | Phát hiện bug xác nhận không lưu |
| Đặt hàng online (checkout) | 9 | Luồng chạm tiền của khách |
| Quản lý nhân sự + bảo mật ảnh CCCD | 9 | |
| Giá vốn món theo công thức | 7 | Phát hiện bug giá vốn treo |
| CDP Dashboard | 7 | Khoá lại việc phân trang phía server |
| Hoàn tiền đơn hàng | 6 | Phát hiện bug hoàn tiền không lưu |
| Validation theo nhà hàng + claim điểm | 6 | |
| Bảng giá công khai | 3 | Chặn lộ gói riêng |

---

## 7. Việc còn lại (không chặn vận hành)

### 7.1. Chỉ anh/chị làm được (không phải việc code)

- Thuê VPS + tên miền + chứng chỉ SSL
- Sinh mới (xoay vòng) các secret đã lộ trong môi trường dev: Google, Gmail, Brevo, webhook
- Cấu hình sao lưu DB ra ngoài VPS (S3 / Backblaze B2)
- Đăng ký tài khoản merchant thật: VNPay, MoMo, ZaloPay, Grab, Shopee
- Ký hợp đồng nhà cung cấp hoá đơn điện tử để bật chữ ký số (hiện đang xuất XML đúng chuẩn TT78 rồi upload tay)
- Nghiệm thu một giao dịch SePay thật

### 7.2. Nợ kỹ thuật đã biết, độ ưu tiên thấp

- **~70 chỗ `whereDate` còn lại** trên các bảng nhỏ hơn — cùng vấn đề mất index nhưng ít ảnh hưởng
- **444 cảnh báo ESLint `no-unused-vars`** — biến/icon import thừa rải rác, nợ có sẵn từ trước, không ảnh hưởng chạy
- **Pint drift toàn dự án** — khác biệt định dạng có sẵn; các file sửa trong đợt này đều đã sạch
- **Materialized View báo STALE trên máy dev** — do máy dev không chạy scheduler, không phải lỗi

### 7.3. Tính năng lớn cần quyết định hướng trước khi làm

- **Đa ngôn ngữ cho menu QR khách hàng** — hiện toàn bộ chỉ có tiếng Việt; đáng làm nếu nhắm khách du lịch/nước ngoài
- **POS hoạt động khi mất mạng thật sự** — cần Service Worker + IndexedDB + kiểm thử trên thiết bị thật; làm nông sẽ gây rối cho thu ngân giờ cao điểm

---

## 8. Nhắc lại trước khi deploy

```bash
# 1. Di trú ảnh CCCD sang thư mục riêng tư (xem trước bằng --dry-run)
php artisan employees:secure-citizen-ids --dry-run
php artisan employees:secure-citizen-ids

# 2. Kiểm tra cấu hình trước khi mở bán
php artisan launch:check

# 3. Chạy migration (2 migration mới trong đợt này)
php artisan migrate --force
```

Trên máy dev, `launch:check` hiện báo 2 lỗi chặn — cả hai đều là **cấu hình môi trường dev**, không phải lỗi code:
- `APP_DEBUG=true` (production phải là `false`)
- `APP_URL` đang là địa chỉ ngrok (production phải là tên miền thật)
