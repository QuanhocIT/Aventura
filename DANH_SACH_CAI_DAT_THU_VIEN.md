# Danh sach cai dat va thu vien cho BepsoViet

Tai lieu nay tong hop tu noi dung bao cao [bao_cao_quan_ly_nha_hang](bao_cao_quan_ly_nha_hang), doi chieu voi [composer.json](composer.json), [package.json](package.json) va mau bien moi truong trong [.env.example](.env.example).

## 1) Tong quan: cai gi la bat buoc, cai gi la nang cao

### Bat buoc de chay du an hien tai

- PHP 8.3+
- Composer
- Node.js 20+ (khuyen nghi 20/22 LTS)
- npm hoac pnpm
- MySQL 8+ (hoac sqlite de test nhanh)
- Redis (neu dung queue/cache theo production)

### Nang cao theo huong bao cao do an

- Spatie Permission (RBAC)
- Horizon + Pulse (queue/monitoring)
- Scout + Meilisearch (full-text search)
- Reverb (realtime websocket)
- Python microservice (FastAPI + Pandas + Scikit-learn)
- Sentry + MinIO/S3/R2

## 2) Thu vien hien trang trong project

### Backend (da co)

- laravel/framework (^13.7)
- inertiajs/inertia-laravel (^3.0)
- laravel/fortify (^1.34)
- laravel/tinker (^3.0)
- laravel/wayfinder (^0.1.14)

### Frontend (da co)

- vue (^3)
- vite (^8)
- @inertiajs/vue3
- tailwindcss (^4)
- @vitejs/plugin-vue

## 3) Thu vien de cai them theo bao cao

### Backend

```bash
composer require spatie/laravel-permission
composer require laravel/horizon
composer require laravel/pulse
composer require laravel/scout meilisearch/meilisearch-php
composer require laravel/reverb
```

### Frontend

```bash
npm install pinia
```

### Python service

```bash
pip install fastapi uvicorn pandas scikit-learn numpy pydantic python-dotenv httpx
```

## 4) Cai dat nhanh de chay local (Windows/Laragon)

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

Neu ban dung script composer san co:

```bash
composer run setup
composer run dev
```

## 5) Cau hinh .env toi thieu (MySQL + Redis)

Cap nhat file `.env` theo huong sau (gia tri mau):

```env
APP_NAME=BepsoViet
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bepso_viet
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
AWS_BUCKET=bepso-viet
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_ENDPOINT=http://127.0.0.1:9000
```

### Sentry

```env
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.2
```

## 7) Dich vu he thong can chay kem

- MySQL service
- Redis service
- Meilisearch service (neu da bat Scout)
- Reverb server (neu dung realtime)
- Python FastAPI service

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

## 10) Khoang trong so voi hien trang

Nhung thanh phan bao cao yeu cau nhung chua thay trong dependency hien tai:

- pinia
- spatie/laravel-permission
- laravel/horizon
- laravel/pulse
- laravel/scout
- meilisearch/meilisearch-php
- laravel/reverb
- bo thu vien python cho microservice
