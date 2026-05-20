# Aventura – Hệ thống quản lý nhà hàng SaaS

## Yêu cầu hệ thống

| Công cụ | Phiên bản |
|---|---|
| PHP | 8.3+ |
| Composer | 2.x |
| Node.js | 18+ |
| MySQL | 8.0+ |
| Laragon / XAMPP | Bất kỳ |

---

## Cài đặt (chỉ cần làm 1 lần)

### Bước 1 — Clone dự án

```bash
git clone https://github.com/QuanhocIT/Aventura.git
cd Aventura
```

### Bước 2 — Cài dependencies

```bash
composer install
npm install
```

### Bước 3 — Tạo file môi trường

```bash
cp .env.example .env
php artisan key:generate
```

### Bước 4 — Tạo database

Mở **phpMyAdmin** hoặc MySQL client, tạo database tên **`aventura`**:

```sql
CREATE DATABASE aventura CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> Nếu mật khẩu MySQL của bạn khác rỗng, mở file `.env` và sửa `DB_PASSWORD=your_password`

### Bước 5 — Chạy migration + seed data

```bash
php artisan migrate --seed
```

Lệnh này tự động tạo toàn bộ bảng và dữ liệu mẫu.

### Bước 6 — Build frontend

```bash
npm run build
```

### Bước 7 — Chạy server

```bash
php artisan serve
```

Mở trình duyệt vào: **http://localhost:8000**

---

## Tài khoản demo có sẵn

| Vai trò | Email | Mật khẩu |
|---|---|---|
| **Super Admin** | superadmin@aventura.local | `Avenrura@2026!` |
| **Owner (Chủ nhà hàng)** | owner@bepso.test | `password` |
| **Manager** | manager@bepso.test | `password` |
| **Cashier (Thu ngân)** | cashier@bepso.test | `password` |
| **Kitchen (Bếp)** | kitchen@bepso.test | `password` |
| **Inventory (Kho)** | inventory@bepso.test | `password` |

---

## Chạy nhanh (tất cả trong 1 lệnh)

Nếu dùng **Laragon** (Windows), sau khi tạo database `aventura`:

```bash
composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed && npm install && npm run build && php artisan serve
```

---

## Cấu trúc chính

```
Aventura/
├── app/
│   ├── Http/Controllers/SuperAdmin/   # Quản lý hệ thống
│   ├── Models/                        # Eloquent models
│   └── Services/                      # Business logic
├── database/
│   ├── migrations/                    # Cấu trúc bảng
│   └── seeders/                       # Dữ liệu mẫu
├── resources/js/
│   ├── pages/                         # Vue pages (Inertia)
│   └── components/                    # UI components
└── routes/
    ├── web.php                        # Routes chính
    └── super-admin.php                # Routes Super Admin
```

---

## Lỗi thường gặp

**`These credentials do not match`** khi đăng nhập Super Admin
→ Chưa seed database. Chạy: `php artisan db:seed --class=SuperAdminSeeder`

**Trang trắng / CSS không load**
→ Chưa build frontend. Chạy: `npm run build`

**`SQLSTATE: Connection refused`**
→ MySQL chưa chạy. Bật Laragon → Start All

**`php artisan` không nhận lệnh**
→ Chưa copy `.env`. Chạy: `cp .env.example .env && php artisan key:generate`
