# ✅ Checklist Trước Khi Go-Live — Aventura Production

> Tất cả thay đổi code đã được thực hiện tự động.
> Danh sách dưới đây là những việc **bạn phải làm thủ công** vì chúng liên quan đến tài khoản bên ngoài.

---

## 🔴 NGAY HÔM NAY (Bảo mật khẩn cấp)

### 1. Revoke Credentials Đã Lộ

- [ ] **Gmail App Password** — Vào [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords) → Thu hồi app password cũ → Tạo mới
- [ ] **Google OAuth Secret** — Vào [console.cloud.google.com](https://console.cloud.google.com) → APIs & Services → Credentials → Tìm OAuth 2.0 Client → Tạo mới → Cập nhật `GOOGLE_CLIENT_ID` và `GOOGLE_CLIENT_SECRET` vào `.env` production
- [ ] **Brevo API Key** — Vào [app.brevo.com](https://app.brevo.com) → SMTP & API → API Keys → Xóa key cũ `xkeysib-fe293e...` → Tạo mới → Cập nhật `BREVO_API_KEY`
- [ ] **SePay Webhook Secret** — Vào dashboard SePay → Webhooks → Xóa secret cũ → Sinh mới → Cập nhật `BILLING_WEBHOOK_SECRET`
- [ ] **Reverb App Key/Secret** — Chạy: `php artisan reverb:generate` → Cập nhật `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_APP_ID`

---

## 📋 Điền Vào `.env` Production (dùng `.env.production.example` làm template)

Sao chép template:
```bash
cp .env.production.example .env
```

Sau đó điền các giá trị sau:

| Biến | Giá Trị Cần Điền | Lấy Ở Đâu |
|---|---|---|
| `APP_KEY` | Sinh mới bằng `php artisan key:generate --show` | Terminal |
| `APP_URL` | `https://your-domain.com` | Domain của bạn |
| `DB_PASSWORD` | Mật khẩu mạnh (≥16 ký tự) | Tự đặt |
| `DB_ROOT_PASSWORD` | Mật khẩu root MySQL | Tự đặt |
| `REDIS_PASSWORD` | Mật khẩu mạnh cho Redis | Tự đặt |
| `MEILISEARCH_KEY` | Mật khẩu mạnh cho Meilisearch | Tự đặt |
| `INTERNAL_API_KEY` | Chạy: `openssl rand -hex 32` | Terminal |
| `GOOGLE_CLIENT_ID` | credentials.json từ Google Console | Bước 1 |
| `GOOGLE_CLIENT_SECRET` | credentials.json từ Google Console | Bước 1 |
| `BREVO_API_KEY` | API key mới từ Brevo | Bước 1 |
| `BILLING_WEBHOOK_SECRET` | Secret mới từ SePay | Bước 1 |
| `REVERB_APP_KEY/SECRET` | Sau khi chạy `reverb:generate` | Bước 1 |
| `SENTRY_LARAVEL_DSN` | [sentry.io](https://sentry.io) → New Project (Laravel) | Bước 2 |
| `TELEGRAM_WEBHOOK_URL` | Bot Token + Chat ID từ @BotFather | Bước 3 |
| `OPENWEATHER_API_KEY` | [openweathermap.org/api](https://openweathermap.org/api) — miễn phí | Tự đăng ký |
| `AWS_*` (Backblaze B2) | [backblaze.com](https://www.backblaze.com) → B2 Cloud Storage | Tự đăng ký |
| `FCM_SERVER_KEY` | Firebase Console → Project Settings → Cloud Messaging | Tự đăng ký |

---

## 🌐 Cấu Hình Domain Trong Caddyfile

Mở [`docker/caddy/Caddyfile`](docker/caddy/Caddyfile) và thay `your-domain.com` bằng domain thật:

```
# Thay dòng này:
your-domain.com {
# Thành:
aventura.yourdomain.com {
```

---

## 🚀 Deploy Lần Đầu

```bash
# 1. Đảm bảo đã điền đủ .env
# 2. Chạy deploy script
bash docker/deploy.sh

# 3. Kiểm tra tất cả containers đang chạy
docker compose ps

# 4. Kiểm tra health
curl -f https://your-domain.com/api/health

# 5. Đăng nhập Super Admin lần đầu
# URL: https://your-domain.com/super-admin/login
# Email: superadmin@aventura.local
# Pass: Aventura@2026!
# → ĐỔI MẬT KHẨU NGAY!
```

---

## 🔍 Kiểm Tra Sau Deploy

```bash
# Xem log tất cả services
docker compose logs -f

# Kiểm tra queue worker
docker compose exec app php artisan horizon:status

# Kiểm tra Python services
curl http://localhost:8001/health
curl http://localhost:8002/health
curl http://localhost:8003/

# Chạy PHP tests
docker compose exec app php artisan test --parallel

# Chạy Python tests (từ host, sau khi pip install)
cd services/analytics_service && pytest test_main.py -v
cd services/chatbot_service && pytest test_main.py -v
cd services/email_service && pytest test_main.py -v
```

---

## 📅 Lịch Tháng Đầu

| Tuần | Việc Cần Làm |
|---|---|
| Tuần 1 | Deploy staging → UAT nội bộ với 1-2 nhà hàng thử nghiệm |
| Tuần 2 | Fix bugs từ UAT → Go live production |
| Tuần 3 | Monitor chặt: Horizon queue, Sentry errors, Redis memory |
| Tuần 4 | Đánh giá performance → điều chỉnh số Queue Worker nếu cần |
| Tháng 2 | Cấu hình VNPay/MoMo thật → Load test → Kết nối E-Invoice thật |
