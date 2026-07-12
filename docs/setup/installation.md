# ⚙️ Hướng Dẫn Cài Đặt Aventura

> Tài liệu này hướng dẫn cài đặt dự án từ đầu trên môi trường local (Windows + Laragon).

---

## 📋 Yêu Cầu Hệ Thống

| Công cụ | Phiên bản tối thiểu | Ghi chú |
|---|---|---|
| PHP | 8.3+ | Bật extension: pdo, redis, gd, zip |
| Composer | 2.x | |
| Node.js | 20+ LTS | Khuyên dùng v20 hoặc v22 |
| npm / pnpm | npm 10+ | |
| MySQL | 8.0+ | |
| Redis | 7.x | Bắt buộc cho queue & cache |

### Tùy Chọn (Theo Tính Năng)

| Dịch vụ | Cổng | Mục đích |
|---|---|---|
| Meilisearch | 7700 | Full-text search |
| Laravel Reverb | 8080 | WebSocket realtime |
| MinIO | 9000 | Object storage (ảnh, file) |
| Python Email Service | 8001 | Gửi email qua microservice |
| Python Chatbot | 8002 | AI chatbot |

---

## 🚀 Cài Đặt Nhanh (5 bước)

```bash
# 1. Clone repository
git clone https://github.com/your-org/aventura.git
cd aventura

# 2. Cài dependencies
composer install
npm install

# 3. Cấu hình môi trường
copy .env.example .env
php artisan key:generate

# 4. Khởi tạo database
php artisan migrate --seed

# 5. Chạy dev server
npm run dev
# Mở terminal mới:
php artisan serve
```

Hoặc dùng script shortcut:

```bash
composer run setup   # = bước 2-4
composer run dev     # = bước 5
```

---

## 🔧 Cài Đặt Nâng Cao

### Python Microservices

```bash
# Email Service (port 8001)
cd services/email_service
pip install -r requirements.txt
uvicorn main:app --port 8001

# Chatbot AI (port 8002)
cd services/chatbot_service
pip install -r requirements.txt
uvicorn main:app --port 8002
```

### Meilisearch

```bash
# Tải về và chạy
./meilisearch --master-key=your_key

# Đồng bộ dữ liệu
php artisan scout:import "App\Models\MenuItem"
```

---

## ✅ Kiểm Tra Sau Cài Đặt

```bash
php -v                      # PHP >= 8.3
php artisan about           # Tóm tắt cấu hình Laravel
php artisan migrate:status  # Tất cả migration đã chạy
php artisan test            # Toàn bộ test suite PASS
npm run types:check         # Không có lỗi TypeScript
npm run lint:check          # Không có lỗi ESLint
```

---

## 🔗 Xem Thêm

- [Cấu hình biến môi trường](environment.md)
- [Hướng dẫn triển khai](deployment.md)
- [Quay lại mục lục](../README.md)
