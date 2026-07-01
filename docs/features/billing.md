# 💳 Module Thanh Toán & Gói Cước SaaS (Billing)

> Quản lý gói cước dịch vụ, thanh toán tự động qua Sepay, xuất hóa đơn PDF.

---

## 📋 Tổng Quan

| Thuộc tính | Chi tiết |
|---|---|
| **Test cases** | TC-PAY-25, TC-MID-26, TC-PLN-27 |
| **Model chính** | `SubscriptionPlan`, `RestaurantSubscription`, `BillingInvoice` |
| **Controller** | `BillingController`, `SepayWebhookController` |
| **Cổng thanh toán** | Sepay (chuyển khoản ngân hàng VN) |
| **Quyền truy cập** | owner (thanh toán), super-admin (quản lý plan) |

---

## ✨ Tính Năng Chính

- ✅ **Gói cước linh hoạt** — Basic, Premium, Custom với giới hạn tài nguyên riêng
- ✅ **Sepay Webhook** — tự động gia hạn khi nhận chuyển khoản (`TC-PAY-25`)
- ✅ **Subscription Middleware** — chặn truy cập khi hết hạn (`TC-MID-26`)
- ✅ **Plan Builder** — Super Admin tùy chỉnh giới hạn từng gói (`TC-PLN-27`)
- ✅ **Xuất hóa đơn PDF** — tự động sau mỗi lần thanh toán
- ✅ **Churn Detection** — AI dự báo nguy cơ hủy gói (`TC-CHN-36`)

---

## 💰 Cấu Trúc Gói Cước

| Gói | Bàn | Nhân viên | Lưu trữ | Giá/tháng |
|---|---|---|---|---|
| **Basic** | 10 bàn | 5 tài khoản | 1 GB | Cơ bản |
| **Premium** | 50 bàn | 30 tài khoản | 20 GB | Nâng cao |
| **Enterprise** | Không giới hạn | Không giới hạn | 100 GB | Tùy chỉnh |
| **Custom** | Do Admin cấu hình | Do Admin cấu hình | Do Admin cấu hình | Tùy chỉnh |

---

## 🔄 Luồng Thanh Toán Sepay

```
Owner chuyển khoản ngân hàng
    ↓ (nội dung CK: AVENTURA-{restaurant_id})
Sepay nhận giao dịch → Gọi Webhook
    ↓
POST /webhook/sepay
    → Xác thực chữ ký HMAC
    → So khớp số tiền & nội dung
    → Tìm restaurant theo mã chuyển khoản
    → Gia hạn subscription_ends_at
    → Xuất hóa đơn PDF → Gửi email
    ↓
Owner nhận email xác nhận gia hạn
```

---

## 🔒 Subscription Middleware

Mọi request từ tenant đều qua `CheckTenantSubscription`:

```
Request → CheckTenantSubscription
    ├── subscription_ends_at >= now()  → OK, tiếp tục
    └── subscription_ends_at < now()  → HTTP 402
              ↓
        Redirect sang trang gia hạn
        (vẫn cho phép xem báo cáo & trang thanh toán)
```

---

## 🗃️ Cấu Trúc Bảng Chính

```
subscription_plans
├── id, name (Basic/Premium/Enterprise)
├── max_tables, max_staff, max_storage_gb
├── price_monthly, price_yearly
└── features (JSON)

restaurant_subscriptions
├── restaurant_id
├── plan_id
├── subscription_ends_at
└── status (active | expired | trial)

billing_invoices
├── restaurant_id, plan_id
├── amount, currency
├── paid_at, pdf_path
└── sepay_transaction_id
```

---

## 🖥️ Giao Diện

| Trang | Đường dẫn Vue | Mô tả |
|---|---|---|
| Trang thanh toán | `pages/billing/Index.vue` | Owner xem & gia hạn |
| Quản lý gói | `pages/super-admin/plans/Index.vue` | Super Admin tạo/sửa plan |
| Lịch sử hóa đơn | `pages/billing/Invoices.vue` | Danh sách hóa đơn |

---

## 🔗 Xem Thêm

- [Module Nhà Hàng](restaurant.md)
- [Hạn ngạch lưu trữ - TC-QTA-15](../testing/test-cases.md#mức-trung-bình-medium)
- [Quay lại mục lục](../README.md)
