# Triển khai Aventura lên Production

Hướng dẫn đưa Aventura từ môi trường dev (Laragon/ngrok) lên một VPS chạy thật.
Yêu cầu tối thiểu: **VPS Linux ~4GB RAM, 2 vCPU**, Docker + Docker Compose, một **domain** trỏ về VPS.

Stack chạy trong `docker-compose.yml`: container `app` (nginx + php-fpm + 2 queue worker + scheduler + **Reverb**) + `mysql` + `redis` + `meilisearch`.

> 💡 **Rẻ nhất để bắt đầu:** có thể chạy trên **Oracle Cloud Always Free** (4 CPU ARM + 24GB RAM, miễn phí vĩnh viễn — image `php:8.3-fpm-alpine`, mysql/redis/meilisearch đều có bản arm64). Domain .com ~250k/năm + Cloudflare (SSL/CDN free). Tổng ~0–150k/tháng.

---

## 0. QUICKSTART (copy-paste)

```bash
# Trên VPS đã cài Docker + Docker Compose, sau khi git clone và điền .env:
cp .env.production.example .env      # rồi điền giá trị THẬT (xem mục 3 — xoay secret)
bash docker/deploy.sh                # build + migrate + seed quyền + optimize + launch:check
docker compose exec app php artisan demo:showcase   # (tuỳ chọn) tạo 1 nhà hàng mẫu để demo
```

Các lệnh trợ giúp go-live (chạy trong container: `docker compose exec app php artisan ...`):
- **`launch:check`** — soi cấu hình production, báo đỏ chỗ chưa sẵn sàng (APP_DEBUG, secret, mail, SePay, SSL…).
- **`demo:showcase`** — tạo nhanh 1 nhà hàng mẫu (menu + storefront) để demo/pilot.
- **`menu:import file.csv --email=chuquan@quan.vn`** — nhập cả thực đơn từ CSV (dùng `menu:import --sample` để lấy file mẫu).
- **`demo:cleanup --keep=chuquan@quan.vn`** — ẩn các nhà hàng demo (dry-run; thêm `--force` để thực thi).

---

## 1. Chuẩn bị

```bash
git clone <repo> && cd Aventura
cp .env.production.example .env
# Điền các giá trị THẬT trong .env (xem mục 3 — checklist xoay secrets)
php artisan key:generate          # chỉ khi deploy DB sạch (xem cảnh báo APP_KEY)
```

**⚠️ APP_KEY:** nếu bạn mang DB dev sang (có cột mã hóa `citizen_id_number`,
`manager_bypass_code`…), **phải giữ nguyên APP_KEY cũ**, đổi key sẽ khiến dữ liệu
mã hóa không giải mã được. Deploy DB sạch mới thì `key:generate`.

## 2. Khởi chạy

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=PermissionsSeeder --force
docker compose exec app php artisan scout:import "App\Models\Product"   # nếu dùng search
```

Kiểm tra sức khỏe:
```bash
curl -f https://your-domain.com/api/health      # kỳ vọng 200, database/cache = true
```

Đặt một **reverse proxy có SSL** (Caddy/Traefik/nginx) trước container `app`:
- `:443` → `app:80` (HTTP app)
- `wss://your-domain.com/app` → `app:8080` (Reverb websocket) — cần cho KDS & thông báo realtime.

## 3. 🔴 Checklist xoay secrets TRƯỚC KHI mở cho khách

Các secret dưới đây **đã lộ** trong quá trình dev (chat/file), **BẮT BUỘC tạo mới**:

| Secret | Nơi xoay | Ghi chú |
|---|---|---|
| `GOOGLE_CLIENT_SECRET` | Google Cloud Console → Credentials | Tạo secret mới, thu hồi secret cũ |
| `MAIL_PASSWORD` (Gmail app pw) | Ngừng dùng Gmail cá nhân | Chuyển sang Brevo SMTP transactional |
| `BREVO_API_KEY` | Brevo dashboard → SMTP & API | Revoke key cũ |
| `BILLING_WEBHOOK_SECRET` | Sinh chuỗi ngẫu nhiên mới | Cấu hình trùng trong dashboard SePay |
| `REVERB_APP_KEY/SECRET/ID` | Tự sinh bộ mới | Không tái dùng bộ dev |
| `DB_PASSWORD`, `MEILISEARCH_KEY` | Đặt mật khẩu mạnh mới | |

Đồng thời đảm bảo: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`.

## 4. Dịch vụ nền (đã tự chạy trong container `app`)

Supervisord đã bật sẵn — xác minh:
```bash
docker compose exec app supervisorctl status
# php-fpm, nginx, queue-worker_00/01, scheduler, reverb → RUNNING
```
- **Queue worker**: khử BOM tồn kho, email, thông báo, billing async. Không chạy → các việc này treo.
- **Scheduler**: billing sync/nhắc hạn, báo cáo ngày, backup 02:00, dọn partition, hết hạn điểm… (xem `routes/console.php`).
- **Reverb**: realtime KDS/thông báo. Không chạy → UI vẫn hoạt động nhưng không cập nhật tức thời.

## 5. Backup & giám sát

- **DB backup**: lệnh `db:backup` chạy 02:00 hằng ngày (đã lên lịch). Cấu hình `AWS_*` (Backblaze B2/Wasabi/S3) để đẩy backup ra ngoài VPS. **Kiểm tra restore hằng tháng.**
- **Error tracking**: điền `SENTRY_LARAVEL_DSN` → tự động báo lỗi production.
- **Uptime**: trỏ UptimeRobot/healthcheck vào `/api/health`.

## 6. Kích hoạt kinh doanh

1. **Thu tiền tenant (SePay)**: trỏ webhook SePay về `https://your-domain.com/webhooks/payments`, secret = `BILLING_WEBHOOK_SECRET`. Nghiệm thu 1 giao dịch thật: checkout → chuyển khoản → webhook kích hoạt gói.
2. **Thanh toán khách**: launch với **VietQR/chuyển khoản** (chạy ngay, không cần key ngoài). VNPay/MoMo/ZaloPay: điền key khi có merchant → cổng tự hiện trên storefront (`isConfigured()`).
3. **Online Store cho từng tenant**: mỗi nhà hàng phải bật Online Store (tạo `slug`) trong `online-store` để có link storefront `order/{slug}` — nếu không, link storefront trả 404.
4. **Hóa đơn điện tử**: hiện xuất XML TT78 (`orders/{order}/e-invoice.xml`), chưa ký số — quy trình launch: tải XML rồi upload lên cổng nhà cung cấp (MISA/VNPT). Tích hợp API ký số là hạng mục sau.

## 7. Cập nhật phiên bản

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize   # cache config/route/view
```

## 8. Kiểm thử hồi quy (chạy trên bản clone, KHÔNG chạy trên DB production)

Xem `tests/Feature/Smoke/` + `phpunit.mysql.xml`: clone DB → `php artisan test -c phpunit.mysql.xml`
để bắt lỗi 500/schema-drift trước mỗi lần deploy lớn. CI cũng có job `mysql-drift` chạy bộ test trên MySQL 8.
