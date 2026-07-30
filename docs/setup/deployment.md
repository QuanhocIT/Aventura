# 🚀 Hướng Dẫn Triển Khai (Deployment)

> Hướng dẫn deploy Aventura lên môi trường production (VPS/Server Linux).

---

## 📋 Yêu Cầu Server

| Thành phần | Tối thiểu | Khuyến nghị |
|---|---|---|
| CPU | 2 vCPU | 4 vCPU |
| RAM | 4 GB | 8 GB |
| Disk | 40 GB SSD | 100 GB SSD |
| OS | Ubuntu 22.04 LTS | Ubuntu 22.04 LTS |
| PHP | 8.3 + FPM | 8.3 + FPM |
| Web Server | Nginx | Nginx |
| Database | MySQL 8.0 | MySQL 8.0 (managed) |
| Cache | Redis 7 | Redis 7 (managed) |

---

## 🔧 Các Bước Triển Khai

### 1. Clone & Cài Dependencies

```bash
cd /var/www
git clone https://github.com/your-org/aventura.git
cd aventura

composer install --no-dev --optimize-autoloader
npm install && npm run build
```

### 2. Cấu Hình Môi Trường

```bash
cp .env.example .env
php artisan key:generate

# Chỉnh sửa .env theo môi trường production
nano .env
```

> ⚠️ Bắt buộc set `APP_ENV=production` và `APP_DEBUG=false`

### 3. Database & Storage

```bash
php artisan migrate --force
php artisan storage:link
php artisan scout:import "App\Models\MenuItem"
```

### 4. Tối Ưu Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache
```

### 5. Cấu Hình Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/aventura/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 6. Queue Worker (Supervisor)

```ini
# /etc/supervisor/conf.d/aventura-worker.conf
[program:aventura-worker]
command=php /var/www/aventura/artisan queue:work redis --sleep=3 --tries=3
directory=/var/www/aventura
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/aventura/storage/logs/worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start aventura-worker:*
```

### 7. Cronjobs (Task Scheduler)

```bash
# Thêm vào crontab của server
crontab -e

# Thêm dòng sau:
* * * * * cd /var/www/aventura && php artisan schedule:run >> /dev/null 2>&1
```

### 8. SSL (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

---

## 🔄 Quy Trình Deploy Cập Nhật

```bash
# Script deploy nhanh (chạy khi có code mới)
cd /var/www/aventura

git pull origin main
composer install --no-dev --optimize-autoloader
npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo supervisorctl restart aventura-worker:*
```

---

## ✅ Kiểm Tra Sau Deploy

```bash
php artisan about
php artisan migrate:status
curl -I https://yourdomain.com    # HTTP 200
php artisan queue:monitor         # Queue đang chạy
```

---

## 🔗 Xem Thêm

- [Cài đặt local](installation.md)
- [Cấu hình .env](environment.md)
- [Quay lại mục lục](../README.md)
