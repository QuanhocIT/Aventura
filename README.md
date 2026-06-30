# 🍽️ Aventura — Hệ Thống Quản Lý Nhà Hàng

> Nền tảng SaaS quản lý nhà hàng toàn diện: đặt món QR, POS, kho, nhân sự, lương, AI & realtime.

[![Laravel](https://img.shields.io/badge/Laravel-13.x-red?logo=laravel)](https://laravel.com)
[![Vue](https://img.shields.io/badge/Vue-3.x-green?logo=vue.js)](https://vuejs.org)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

---

## ⚡ Cài Đặt Nhanh

```bash
composer install && npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
```

> Xem hướng dẫn đầy đủ tại [docs/setup/installation.md](docs/setup/installation.md)

---

## 📚 Tài Liệu

| Chủ đề | Đường dẫn |
|---|---|
| Cài đặt & môi trường | [docs/setup/](docs/setup/) |
| Kiến trúc hệ thống | [docs/architecture/](docs/architecture/) |
| Tài liệu tính năng | [docs/features/](docs/features/) |
| Kiểm thử | [docs/testing/](docs/testing/) |
| Lịch sử thay đổi | [docs/changelog/CHANGELOG.md](docs/changelog/CHANGELOG.md) |

---

## 🛠️ Tech Stack

- **Backend:** Laravel 13, PHP 8.3, Inertia.js, Spatie Permission, Horizon, Scout
- **Frontend:** Vue 3, Vite, Tailwind CSS 4, Pinia
- **Database:** MySQL 8, Redis
- **Search:** Meilisearch
- **Realtime:** Laravel Reverb (WebSocket)
- **AI/Microservices:** FastAPI (Python), TF-IDF Chatbot, Sentiment Analysis

---

## 📬 Liên Hệ & Đóng Góp

Xem [docs/contributing.md](docs/contributing.md) để biết cách đóng góp vào dự án.
