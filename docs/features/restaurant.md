# 🏪 Module Quản Lý Nhà Hàng (Restaurant)

> Quản lý thông tin nhà hàng, chi nhánh, bàn, thực đơn và toàn bộ vận hành hàng ngày.

---

## 📋 Tổng Quan

| Thuộc tính | Chi tiết |
|---|---|
| **Model chính** | `Restaurant`, `Branch`, `Table`, `MenuItem` |
| **Controller** | `RestaurantController`, `TableController` |
| **Quyền truy cập** | owner (toàn quyền), manager (chi nhánh), super-admin (xem tất cả) |
| **Tenant Isolation** | Mọi query tự filter theo `restaurant_id` |

---

## ✨ Tính Năng Chính

- ✅ **Đa chi nhánh** — 1 nhà hàng có nhiều chi nhánh
- ✅ **Quản lý bàn** — tạo, đặt layout, QR code từng bàn
- ✅ **Thực đơn phân cấp** — danh mục → món ăn → combo
- ✅ **Gói cước SaaS** — giới hạn số bàn, nhân viên theo plan
- ✅ **Impersonation** — Super Admin hỗ trợ owner trực tiếp (`TC-IMP-23`)
- ✅ **Maintenance Mode** — bật/tắt bảo trì từng nhà hàng (`TC-MNT-16`)
- ✅ **Chống Self-Approval** — mọi phê duyệt phải có người khác duyệt (`TC-APP-12`)

---

## 🔄 Luồng Khởi Tạo Nhà Hàng Mới

```
Super Admin tạo Restaurant (tenant mới)
    ↓
Gán gói cước (subscription_plan_id)
    ↓
Owner đăng ký tài khoản → liên kết với restaurant
    ↓
Owner tạo Chi nhánh → Tạo Bàn → In QR code
    ↓
Owner tạo Danh mục → Thêm Món ăn → Cài BOM nguyên liệu
    ↓
Nhà hàng sẵn sàng vận hành
```

---

## 🗃️ Cấu Trúc Bảng Chính

```
restaurants
├── id, name, logo, address, lat, lng
├── subscription_plan_id
├── subscription_ends_at
└── settings (JSON: tax_rate, currency, timezone...)

branches
├── id, restaurant_id, name, address
└── manager_id

tables
├── id, branch_id, restaurant_id
├── name (e.g. "Bàn 01")
├── qr_token (unique, dùng để xác thực QR)
├── capacity
└── status (available | occupied | reserved)
```

---

## 🖥️ Giao Diện

| Trang | Đường dẫn Vue | Mô tả |
|---|---|---|
| Danh sách nhà hàng | `pages/super-admin/restaurants/Index.vue` | Super Admin xem tất cả tenant |
| Chi tiết nhà hàng | `pages/super-admin/restaurants/Show.vue` | Thông tin + gói cước |
| Quản lý bàn | `pages/admin/tables/Index.vue` | Layout sơ đồ bàn |
| Thực đơn | `pages/admin/menu/Index.vue` | Danh mục & món ăn |

---

## 🔗 Xem Thêm

- [Module Thanh Toán](billing.md)
- [Module Nhân Sự](hr.md)
- [Sơ đồ Database](../architecture/database.md)
- [Quay lại mục lục](../README.md)
