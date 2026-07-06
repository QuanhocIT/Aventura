# 🗄️ Sơ Đồ Database & Quan Hệ Bảng

> Tài liệu mô tả cấu trúc các bảng chính và mối quan hệ trong hệ thống Aventura.  
> Tất cả bảng nghiệp vụ đều có cột `restaurant_id` để thực hiện **Tenant Isolation**.

---

## 🏢 Nhóm: Multi-Tenant & Auth

```
restaurants ──< restaurant_users >── users
     │                                  │
     │                               roles_has_permissions
     │                               (Spatie Permission)
     ├── subscription_plans
     └── restaurant_settings
```

| Bảng | Mô tả |
|---|---|
| `restaurants` | Thông tin nhà hàng (tenant) |
| `users` | Tài khoản người dùng |
| `restaurant_users` | Pivot: user thuộc nhà hàng nào, vai trò gì |
| `subscription_plans` | Các gói cước SaaS (Basic, Premium...) |
| `audit_logs` | Nhật ký mọi thay đổi nhạy cảm |

---

## 🍽️ Nhóm: Menu & Đặt Món

```
menu_categories ──< menu_items ──< order_items >── orders
                         │                              │
                    menu_item_bom                  temporary_orders
                    (nguyên liệu)                  (đặt qua QR)
```

| Bảng | Mô tả |
|---|---|
| `menu_categories` | Danh mục món ăn |
| `menu_items` | Món ăn với giá & trạng thái |
| `menu_item_bom` | Định lượng nguyên liệu (Bill of Materials) |
| `orders` | Đơn hàng chính |
| `order_items` | Chi tiết món trong đơn |
| `temporary_orders` | Đơn tạm từ QR (chờ nhân viên duyệt) |

---

## 🏪 Nhóm: Kho & Nguyên Liệu

```
suppliers ──< purchase_orders ──< po_items >── ingredients
                                                    │
                                            inventory_transactions
                                            inventory_wastes
```

| Bảng | Mô tả |
|---|---|
| `ingredients` | Nguyên liệu kho |
| `suppliers` | Nhà cung cấp |
| `purchase_orders` | Đơn mua hàng (RFP) |
| `inventory_transactions` | Lịch sử nhập/xuất kho |
| `inventory_wastes` | Báo cáo hao hụt & hủy món |

---

## 👨‍💼 Nhóm: Nhân Sự & Lương

```
users ──< schedules ──< schedule_assignments
  │              
  ├── attendance_logs (GPS + Webcam)
  ├── leave_requests
  ├── salary_advances
  └── payroll_records ──< payroll_items
```

| Bảng | Mô tả |
|---|---|
| `schedules` | Ca làm việc định nghĩa |
| `schedule_assignments` | Phân ca cho nhân viên |
| `attendance_logs` | Chấm công (GPS + ảnh webcam) |
| `leave_requests` | Đơn xin nghỉ phép |
| `salary_advances` | Tạm ứng lương |
| `payroll_records` | Bảng lương tháng |

---

## 💳 Nhóm: Thanh Toán & Loyalty

```
orders ──< payments ──< vouchers
  │
  └── customer_loyalty_points
         └── loyalty_tiers (Bạc / Vàng / Kim Cương)
```

| Bảng | Mô tả |
|---|---|
| `payments` | Giao dịch thanh toán |
| `vouchers` | Mã giảm giá |
| `voucher_usages` | Lịch sử dùng voucher (chống gian lận) |
| `customer_loyalty_points` | Điểm tích lũy khách hàng |
| `loyalty_tiers` | Cấu hình hạng thành viên |

---

## ⭐ Nhóm: Feedback & CDP

```
orders ──< customer_feedback (rating + sentiment)
users  ──< cdp_behaviors     (hành vi xem/click)
```

| Bảng | Mô tả |
|---|---|
| `customer_feedback` | Đánh giá & phản hồi khách |
| `cdp_behaviors` | Log hành vi khách (CDP) |

---

## 🔗 Các Quan Hệ Quan Trọng

```sql
-- Tenant Isolation: mọi query tự filter theo restaurant_id
SELECT * FROM orders WHERE restaurant_id = {current_tenant_id};

-- Order với items và món ăn
orders
  └── order_items (order_id, menu_item_id, quantity, price)
        └── menu_items (name, description, image)
              └── menu_item_bom (ingredient_id, quantity_used)
                    └── ingredients (name, unit, current_stock)

-- Phân ca với chấm công
schedule_assignments (user_id, schedule_id, date)
  └── attendance_logs (user_id, check_in_at, check_out_at, photo_path, lat, lng)
```

---

## 🔗 Xem Thêm

- [Tổng quan kiến trúc](overview.md)
- [REST API](api.md)
- [Quay lại mục lục](../README.md)
