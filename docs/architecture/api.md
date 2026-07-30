# 🔌 Tài Liệu REST API

> Tài liệu mô tả các API endpoint chính của hệ thống Aventura.  
> Base URL: `https://yourdomain.com/api`  
> Authentication: **Bearer Token** (Laravel Sanctum)

---

## 🔐 Authentication

### Đăng nhập

```http
POST /login
Content-Type: application/json

{
  "email": "owner@restaurant.com",
  "password": "password"
}
```

**Response:**
```json
{
  "token": "1|abc123...",
  "user": { "id": 1, "name": "Nguyen Van A", "role": "owner" }
}
```

### Xác thực 2FA

```http
POST /two-factor-challenge
Content-Type: application/json

{
  "code": "123456"
}
```

---

## 🍽️ Menu & Món Ăn

| Method | Endpoint | Mô tả | Quyền |
|---|---|---|---|
| `GET` | `/menu-items` | Danh sách món ăn | Public |
| `GET` | `/menu-items/{id}` | Chi tiết món ăn | Public |
| `POST` | `/menu-items` | Thêm món ăn | owner, manager |
| `PUT` | `/menu-items/{id}` | Cập nhật món | owner, manager |
| `DELETE` | `/menu-items/{id}` | Xóa món ăn | owner |
| `PATCH` | `/menu-items/{id}/toggle-pause` | Bếp tạm dừng món | kitchen, manager |

---

## 📋 Đơn Hàng (Orders)

| Method | Endpoint | Mô tả | Quyền |
|---|---|---|---|
| `GET` | `/orders` | Danh sách đơn hàng | cashier+ |
| `POST` | `/orders` | Tạo đơn hàng | cashier+ |
| `GET` | `/orders/{id}` | Chi tiết đơn | cashier+ |
| `PATCH` | `/orders/{id}/status` | Cập nhật trạng thái | kitchen, cashier |
| `POST` | `/orders/{id}/split` | Tách đơn (cần owner bypass) | cashier |
| `POST` | `/temporary-orders` | Tạo đơn tạm từ QR | Guest |
| `POST` | `/temporary-orders/{id}/confirm` | Duyệt đơn tạm | cashier, waiter |

---

## ⭐ Feedback

| Method | Endpoint | Mô tả | Quyền |
|---|---|---|---|
| `POST` | `/feedback` | Gửi đánh giá | Guest (ẩn danh) |
| `GET` | `/feedback` | Danh sách đánh giá | manager+ |
| `GET` | `/feedback/nps` | Chỉ số NPS | manager+ |
| `GET` | `/feedback/summary` | Tổng hợp theo kỳ | manager+ |

**Request gửi feedback:**
```json
POST /feedback
{
  "order_id": 123,
  "rating": 4,
  "comment": "Món ăn ngon, phục vụ nhiệt tình",
  "staff_id": 5,
  "menu_item_id": 12,
  "is_anonymous": true
}
```

---

## 👨‍💼 Nhân Sự

| Method | Endpoint | Mô tả | Quyền |
|---|---|---|---|
| `GET` | `/schedules` | Xem lịch ca | all staff |
| `POST` | `/schedules` | Tạo ca làm việc | manager+ |
| `POST` | `/attendance/check-in` | Chấm công vào | all staff |
| `POST` | `/attendance/check-out` | Chấm công ra | all staff |
| `GET` | `/leave-requests` | Danh sách đơn nghỉ | manager+ |
| `POST` | `/leave-requests` | Gửi đơn xin nghỉ | all staff |
| `PATCH` | `/leave-requests/{id}/approve` | Duyệt đơn nghỉ | manager+ |

---

## 🔒 Mã Lỗi Phổ Biến

| HTTP Code | Ý nghĩa | Ví dụ |
|---|---|---|
| `200` | Thành công | |
| `201` | Tạo mới thành công | |
| `400` | Dữ liệu đầu vào lỗi | Validation error |
| `401` | Chưa đăng nhập | Token hết hạn |
| `403` | Không có quyền | Self-approval, tenant cross-access |
| `404` | Không tìm thấy | Tenant isolation trả về 404 |
| `402` | Gói cước hết hạn | Subscription expired |
| `422` | Logic nghiệp vụ thất bại | Tách đơn không có bypass |
| `429` | Quá nhiều request | Rate limiting |
| `503` | Hệ thống bảo trì | Maintenance mode |

---

## 🔗 Xem Thêm

- [Tổng quan kiến trúc](overview.md)
- [Sơ đồ Database](database.md)
- [Quay lại mục lục](../README.md)
