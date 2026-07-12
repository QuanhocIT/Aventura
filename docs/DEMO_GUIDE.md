# Hướng dẫn Demo & Cấu hình Aventura

Tài liệu này phục vụ trình diễn/bảo vệ đồ án và triển khai thực tế. Gồm 3 phần:
1. Nạp dữ liệu demo bằng 1 lệnh
2. Kịch bản demo 10 phút theo luồng nghiệp vụ
3. Hướng dẫn điền API key thật cho các tích hợp

---

## 1. Nạp dữ liệu demo (1 lệnh)

Sinh dữ liệu để **mọi báo cáo hiển thị số liệu đẹp** (P&L, BI Dashboard, Màn hình Bếp, xu hướng 6 tháng).

**Nhà hàng đã có sẵn menu (khuyến nghị)** — chỉ bổ sung dữ liệu báo cáo, nhanh (~2 phút):

```bash
php artisan demo:full --email=owner@bepso.test --reports-only
```

**Nhà hàng mới hoàn toàn** — seed cả menu + nhân viên rồi mới bổ sung báo cáo:

```bash
php artisan demo:full --email=owner@bepso.test --template=bbq
```

Tham số:
- `--email=` : email chủ nhà hàng (bỏ trống = nhà hàng đầu tiên có owner)
- `--template=` : `bbq` (mặc định) | `cafe` | `bubble_tea`
- `--reports-only` : chỉ bổ sung 6 tháng đơn + lương + chi phí (không đụng menu/nhân viên)

> Phần `--reports-only` tự cân bằng kinh tế: chi phí vận hành ≈ 15% và quỹ lương ≈ 22% doanh thu thực từng tháng, nên P&L luôn hiển thị biên lợi nhuận dương hợp lý (~15% tháng đủ, ~30% tháng hiện tại dở dang).

Lệnh này tạo:
- Thực đơn + định lượng BOM (giá vốn từng món)
- Nhân viên, ca làm, lịch phân ca
- **~300 đơn hàng hoàn thành trải 6 tháng** (doanh thu tăng dần)
- **Bảng lương đã duyệt** 3 tháng gần nhất
- **Chi phí vận hành** 6 tháng (thuê mặt bằng, điện nước, marketing…)
- Bản ghi trình diễn: 1 đặt bàn đã cọc, 1 đơn từ GrabFood

Chạy lại lệnh an toàn (idempotent) — dữ liệu báo cáo cũ được xóa trước khi seed lại.

---

## 2. Kịch bản demo 10 phút

Đăng nhập bằng tài khoản chủ nhà hàng đã seed. Đi theo luồng một ngày vận hành:

| Phút | Màn hình | Thao tác trình diễn |
|---|---|---|
| 0–1 | **Landing** (`/`) | Cuộn tới section Tích hợp — 2 hàng logo chạy marquee. Nhấn mạnh 12 tích hợp thật |
| 1–2 | **Dashboard** | Số liệu KPI đếm tăng dần (count-up), điểm sức khỏe kinh doanh, biểu đồ doanh thu |
| 2–3 | **Đặt bàn** (`/reservations`) | Chỉ đặt bàn "Khách VIP demo" có badge 💰 *Cọc 200.000đ — đã trả*. Giải thích: khách đặt online trả cọc qua cổng thanh toán → bàn tự xác nhận |
| 3–4 | **Cửa hàng Online** (`/order/{slug}`) | Mở storefront, thêm món (badge giỏ nảy), đặt thử → confetti 🎉. Nếu tắt mạng: banner offline, đơn tự lưu |
| 4–5 | **Tích hợp** (`/settings/integrations`) | Mục GrabFood → **"Đơn thử nghiệm"** → đơn hiện ngay trong Quản lý đơn hàng |
| 5–6 | **Màn hình Bếp** (`/kitchen`) | KDS: món quá giờ đổi màu vàng→đỏ theo SLA riêng, dải thống kê tốc độ bếp, chuông báo món mới |
| 6–7 | **Quản lý đơn** (`/orders`) | Chọn đơn nhiều món → **Tách bill**; đơn đã thanh toán → nút **HĐĐT** tải XML hóa đơn điện tử; **Chuyển bàn** |
| 7–8 | **Báo cáo Lãi/Lỗ** (`/reports/profit-loss`) | Điểm nhấn: doanh thu → COGS → lương → chi phí → **lợi nhuận ròng**, biểu đồ xu hướng 6 tháng, so sánh tháng trước |
| 8–9 | **Tích hợp → MISA** | Tải CSV chứng từ bán hàng; API công khai → tạo API key, chỉ docs 3 endpoint |
| 9–10 | **Super Admin** (nếu có) | Giám sát dịch vụ, sức khỏe nhà hàng, billing — cho thấy quy mô SaaS |

**Câu chốt:** "Aventura không chỉ quản lý đơn hàng — nó là hệ điều hành kinh doanh cho nhà hàng: từ đặt bàn, bếp, thanh toán đến kế toán và phân tích lợi nhuận, tất cả tích hợp sẵn."

---

## 3. Điền API key thật cho tích hợp

Vào **Cài đặt → Tích hợp**, bấm **Cấu hình** trên từng card. Mỗi dialog có khung 💡 hướng dẫn. Dưới đây là cách lấy key (đa số miễn phí):

### Google Analytics 4 (miễn phí, ~10 phút)
1. Vào [analytics.google.com](https://analytics.google.com) → tạo tài khoản + luồng dữ liệu web
2. Quản trị → Luồng dữ liệu → copy **Measurement ID** (`G-XXXXXXX`)
3. Mục "Measurement Protocol API secrets" → tạo secret (cho tracking server-side)

### Facebook Pixel (miễn phí, ~10 phút)
1. Vào [business.facebook.com](https://business.facebook.com) → Trình quản lý sự kiện
2. Tạo Pixel → copy **Pixel ID**
3. (Tùy chọn) Cài đặt Pixel → tạo **Conversions API Token**

### Zalo OA (miễn phí)
1. Tạo Official Account tại [oa.zalo.me](https://oa.zalo.me)
2. Vào [developers.zalo.me](https://developers.zalo.me) → tạo ứng dụng, liên kết OA → lấy **Access Token**
3. Sau khi lưu, bấm nút ✈ trên card để kiểm tra kết nối

### VNPay / MoMo / ZaloPay (cần đăng ký merchant)
- Các cổng này cấu hình ở cấp hệ thống qua file `.env` (không nhập trên UI):
  - VNPay: `VNPAY_TMNCODE`, `VNPAY_HASHSECRET` — đăng ký sandbox tại [sandbox.vnpayment.vn](https://sandbox.vnpayment.vn)
  - MoMo: `MOMO_PARTNER_CODE`, `MOMO_ACCESS_KEY`, `MOMO_SECRET_KEY`
  - ZaloPay: `ZALOPAY_APP_ID`, `ZALOPAY_KEY1`, `ZALOPAY_KEY2`

### GrabFood / ShopeeFood (qua kênh đối tác merchant)
1. Đăng ký đối tác (GrabMerchant / ShopeeFood Merchant), xin quyền Open API
2. Nhận **Partner ID / Store ID** + **Webhook Secret**
3. Dán vào Cấu hình, rồi copy URL webhook (mục "Nhận đơn Grab/Shopee") vào hệ thống đối tác

### MISA
- **Không cần key** — nút "Tải CSV chứng từ" ở cuối trang dùng được ngay, nhập trực tiếp vào MISA SME.
- App ID / Access Key chỉ cần khi đồng bộ tự động qua AMIS Platform ([actapp.misa.vn](https://actapp.misa.vn))

> **Chưa có key thật?** Bấm **"Dùng thử demo"** trên mỗi card — hệ thống điền credentials sandbox và bật ngay để trình diễn luồng, thay bằng key thật khi triển khai.

---

## 4. Vận hành production

- **Scheduler**: đảm bảo cron chạy `php artisan schedule:run` mỗi phút (đã có sẵn backup DB 02:00, nhắc đặt bàn, dọn dữ liệu…). Trên Linux: thêm vào crontab; trên Windows: Task Scheduler.
- **Queue**: chạy `php artisan queue:work` (hoặc Horizon) để xử lý webhook, email, job nền.
- **Realtime**: chạy `php artisan reverb:start` cho WebSocket (Màn hình Bếp, thông báo).
- **CI**: GitHub Actions (`.github/workflows/ci.yml`) tự chạy Pint + ESLint + vue-tsc + Pest (coverage ≥60%) + build mỗi lần push lên `develop`/`main`.
