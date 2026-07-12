# 🏗️ Tổng Quan Kiến Trúc Hệ Thống Aventura

> Aventura là nền tảng SaaS **multi-tenant** — nhiều nhà hàng độc lập chia sẻ một codebase,  
> nhưng dữ liệu hoàn toàn **cách ly** theo `restaurant_id`.

---

## 🗺️ Sơ Đồ Kiến Trúc Tổng Thể

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                          │
│  Browser/Mobile  →  Vue 3 (Inertia.js)  →  Tailwind CSS 4   │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS
┌────────────────────────▼────────────────────────────────────┐
│                      LARAVEL (Core)                          │
│  Routes → Middleware → Controller → Service → Model          │
│                                                              │
│  • Inertia SSR         • Spatie Permissions                  │
│  • Laravel Fortify     • Laravel Scout (Meilisearch)         │
│  • Laravel Horizon     • Laravel Pulse (Monitor)             │
│  • Laravel Reverb      • Laravel Wayfinder                   │
└──────┬──────────────────────────────────────┬───────────────┘
       │                                      │
┌──────▼───────┐                    ┌─────────▼──────────────┐
│   MySQL 8    │                    │   Redis 7               │
│  (Primary DB)│                    │  Cache / Queue / Session │
└──────────────┘                    └────────────────────────┘
       │
┌──────▼──────────────────────────────────────────────────────┐
│                   MICROSERVICES (Python)                     │
│  Port 8001: Email Service (FastAPI + SMTP)                   │
│  Port 8002: Chatbot AI (TF-IDF + MySQL)                      │
│  Port 8003: Upsell AI (Apriori + Scikit-learn)               │
└─────────────────────────────────────────────────────────────┘
```

---

## 🏢 Multi-Tenant Architecture

Mỗi request được tự động **gắn `restaurant_id`** thông qua:

- **Eloquent Global Scope** — trait `BelongsToRestaurant` trên tất cả model nghiệp vụ
- **Middleware** `CheckTenantSubscription` — kiểm tra hạn dùng gói cước
- **Policy** — giới hạn quyền truy cập theo vai trò trong từng nhà hàng

```
Request → Auth Middleware → Tenant Scope → Controller → Data (filtered by restaurant_id)
```

---

## 👥 Phân Cấp Vai Trò (RBAC)

| Vai trò | Phạm vi | Quyền |
|---|---|---|
| `super-admin` | Toàn hệ thống | Quản lý tất cả tenant, plans, billing |
| `owner` | 1 nhà hàng | Toàn quyền nhà hàng đó |
| `manager` | 1 chi nhánh | Quản lý nhân sự, kho, báo cáo |
| `cashier` | POS | Thanh toán, đặt món |
| `kitchen` | Bếp | Xem & cập nhật trạng thái món |
| `waiter` | Phục vụ | Gọi món, QR |

---

## 🔄 Luồng Xử Lý Đơn Hàng (Order Flow)

```
Khách quét QR
    ↓
Tạo Temporary Order (status: waiting_verification)
    ↓
Nhân viên duyệt trên POS (trong 2 phút)
    ↓ (quá hạn → tự động escalated)
Order chính được tạo → gửi xuống Kitchen Display
    ↓
Bếp cập nhật trạng thái → Realtime qua Reverb
    ↓
Khấu trừ kho (BOM) qua Horizon Queue
    ↓
Thanh toán → Loyalty Points → Audit Log → Ca kết
```

---

## 🔗 Xem Thêm

- [Sơ đồ Database](database.md)
- [REST API](api.md)
- [Quay lại mục lục](../README.md)
