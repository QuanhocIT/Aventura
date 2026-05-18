# Danh sach cai dat va thu vien cho Aventura

Tai lieu nay tong hop tu noi dung bao cao [bao_cao_quan_ly_nha_hang](bao_cao_quan_ly_nha_hang), doi chieu voi [composer.json](composer.json), [package.json](package.json) va mau bien moi truong trong [.env.example](.env.example).

Để chạy dự án Aventura, bạn cần cài đặt một số thư viện và công cụ hỗ trợ.

Để test: mở terminal rồi chạy php artisan serve, sau đó truy cập http:// mà nó trả về


## 1) Tổng quan: Yêu cầu hệ thống

### Bắt buộc để chạy dự án

- PHP 8.3+
- Composer
- Node.js 20+ (khuyên dùng 20/22 LTS)
- npm hoặc pnpm
- MySQL 8+ (hoặc sqlite để test nhanh)
- Redis (nếu dùng queue/cache hoặc production)

**Lưu ý:** Tất cả các package PHP/backend và frontend đã được khai báo trong composer.json và package.json. KHÔNG cần tự chạy các lệnh `composer require ...` hay `npm install ...` cho các package này.

### Nâng cao (tùy chọn theo tính năng)

- Meilisearch (full-text search)
- Reverb (realtime websocket)
- MinIO/S3/R2 (object storage)
- Sentry (giám sát lỗi)
- Python microservice (FastAPI + Pandas + Scikit-learn)


## 2) Thư viện đã có sẵn trong dự án

### Backend (composer.json)

- laravel/framework (^13.7)
- inertiajs/inertia-laravel (^3.0)
- laravel/fortify (^1.34)
- laravel/tinker (^3.0)
- laravel/wayfinder (^0.1.14)
- spatie/laravel-permission (^7.4)
- laravel/horizon (^1.7)
- laravel/pulse (^1.7)
- laravel/scout (^11.2)
- meilisearch/meilisearch-php (^1.16)
- laravel/reverb (^1.10)

### Frontend (package.json)

- vue (^3)
- vite (^8)
- @inertiajs/vue3
- tailwindcss (^4)
- pinia (^3)
- @vitejs/plugin-vue


## 3) Thư viện cần cài thêm (nếu phát triển Python service)

### Python service (tùy chọn)

Nếu muốn chạy AI/microservice, cần cài Python 3.10+ và các package:

```bash
pip install fastapi uvicorn pandas scikit-learn numpy pydantic python-dotenv httpx
```


## 4) Cài đặt nhanh để chạy local (Windows/Laragon)

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

Hoặc dùng script composer có sẵn:

```bash
composer run setup
composer run dev
```

**Lưu ý:** KHÔNG cần tự chạy các lệnh require/install cho các package đã có trong composer.json và package.json.

## 5) Cau hinh .env toi thieu (MySQL + Redis)

Cap nhat file `.env` theo huong sau (gia tri mau):

```env
APP_NAME=Aventura
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aventura
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## 6) Cau hinh bo sung cho cac thanh phan nang cao

### Meilisearch

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=
```

### Reverb

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local-app
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

### MinIO/S3

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=aventura
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_ENDPOINT=http://127.0.0.1:9000
```

### Sentry

```env
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.2
```


## 7) Dịch vụ hệ thống cần chạy kèm

- MySQL service
- Redis service (bắt buộc nếu dùng queue/cache)
- Meilisearch service (nếu bật Scout)
- Reverb server (nếu dùng realtime)
- Python FastAPI service (nếu dùng AI/microservice)

Lenh thuong dung:

```bash
php artisan queue:work
php artisan horizon
php artisan pulse:check
php artisan reverb:start
```

## 8) Kiem tra sau cai dat (smoke test)

```bash
php -v
composer -V
node -v
npm -v
php artisan about
php artisan migrate:status
php artisan test
npm run types:check
npm run lint:check
```

## 9) Checklist trien khai theo pha

1. Phase 1 (chay duoc): composer install, npm install, .env, migrate, dev server.
2. Phase 2 (phan quyen + queue): cai spatie permission + redis + horizon.
3. Phase 3 (tim kiem + realtime): meilisearch/scout + reverb.
4. Phase 4 (phan tich AI): Python microservice + ket noi API/queue.
5. Phase 5 (van hanh): sentry + object storage + monitor.


## 10) Ghi chú bổ sung

Tất cả các package backend/frontend chính đã có trong composer.json và package.json. Nếu bạn cần phát triển AI/microservice, hãy cài Python và các package như hướng dẫn ở mục 3.

Nếu gặp lỗi thiếu Redis khi migrate, hãy chắc chắn đã bật Redis service trên máy local (Laragon có thể bật Redis qua menu Services).

Nếu gặp lỗi "table already exists" khi migrate, hãy kiểm tra lại database hoặc chạy lại lệnh migrate:fresh.
