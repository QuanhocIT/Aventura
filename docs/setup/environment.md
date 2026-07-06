# 🌍 Cấu Hình Biến Môi Trường (.env)

> Giải thích chi tiết từng biến trong file `.env` của Aventura.  
> Sao chép `.env.example` thành `.env` trước khi chỉnh sửa.

---

## 🔑 Ứng Dụng (App)

```env
APP_NAME=Aventura          # Tên hiển thị
APP_ENV=local              # local | staging | production
APP_DEBUG=true             # false khi production
APP_URL=http://aventura.test
APP_KEY=                   # Tự sinh bằng: php artisan key:generate
```

---

## 🗄️ Database

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aventura
DB_USERNAME=root
DB_PASSWORD=               # Để trống với Laragon mặc định
```

---

## 🔴 Redis (Queue & Cache)

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

> ⚠️ Redis bắt buộc phải chạy trước khi khởi động ứng dụng.

---

## 🔍 Meilisearch (Tìm Kiếm)

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=           # Master key của Meilisearch server
```

---

## 📡 Reverb (WebSocket Realtime)

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local-app
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

---

## 🪣 MinIO / S3 (Lưu Trữ File)

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=aventura
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_ENDPOINT=http://127.0.0.1:9000
```

---

## 🤖 Microservices AI (Python)

```env
CHATBOT_SERVICE_URL=http://localhost:8002
EMAIL_SERVICE_URL=http://localhost:8001
UPSELL_SERVICE_URL=http://localhost:8003
```

---

## 🐞 Sentry (Giám Sát Lỗi Production)

```env
SENTRY_LARAVEL_DSN=        # Lấy từ sentry.io
SENTRY_TRACES_SAMPLE_RATE=0.2
```

---

## 🔗 Xem Thêm

- [Hướng dẫn cài đặt](installation.md)
- [Quay lại mục lục](../README.md)
