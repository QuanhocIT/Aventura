# ⭐ Module Đánh Giá & Phản Hồi Khách Hàng (Feedback)

> Cho phép khách hàng gửi đánh giá món ăn và nhân viên sau khi dùng bữa.  
> Phân tích sentiment tự động và cảnh báo realtime khi có đánh giá xấu.

---

## 📋 Tổng Quan

| Thuộc tính | Chi tiết |
|---|---|
| **Test case** | TC-FDB-19 |
| **Bảng DB chính** | `customer_feedback` |
| **Controller** | `App\Http\Controllers\FeedbackController` |
| **Event realtime** | `FeedbackSubmitted` (Laravel Reverb) |
| **Quyền truy cập** | Khách ẩn danh gửi / Manager+ xem |

---

## ✨ Tính Năng Chính

- ✅ Khách gửi đánh giá **ẩn danh** (không cần đăng nhập)
- ✅ Đánh giá **sao** (1–5) cho món ăn và nhân viên phục vụ
- ✅ **Chống spam** — một thiết bị chỉ gửi được 1 lần / bàn / phiên
- ✅ **Cảnh báo realtime** khi điểm < 3 sao (gửi lên màn hình quản lý)
- ✅ **Sentiment Analysis** bằng AI (phân loại tích cực / tiêu cực)
- ✅ **Dashboard báo cáo** — NPS, điểm trung bình, xu hướng theo tuần

---

## 🔄 Luồng Hoạt Động

```
Khách quét QR (cuối bữa)
    ↓
Hiển thị form đánh giá (Vue component: FeedbackForm.vue)
    ↓
Submit → POST /api/feedback
    ↓
FeedbackController@store
    → Validate (chống spam IP/session)
    → Lưu vào customer_feedback
    → Dispatch FeedbackSubmitted event
    ↓
Reverb broadcast → Manager dashboard cập nhật realtime
    ↓
Queue Job → Gọi Python AI Service phân tích sentiment
```

---

## 🗃️ Cấu Trúc Bảng `customer_feedback`

```sql
id                  bigint PK
restaurant_id       bigint FK  -- tenant isolation
order_id            bigint FK  -- liên kết đơn hàng (nullable)
staff_id            bigint FK  -- nhân viên được đánh giá (nullable)
menu_item_id        bigint FK  -- món ăn được đánh giá (nullable)
rating              tinyint    -- 1 đến 5 sao
comment             text       -- nội dung bình luận
sentiment           enum       -- positive | neutral | negative
is_anonymous        boolean    -- ẩn danh hay không
session_hash        varchar    -- hash chống spam
created_at          timestamp
```

---

## 🖥️ Giao Diện

| Trang | Đường dẫn Vue | Mô tả |
|---|---|---|
| Form gửi đánh giá | `pages/feedback/Submit.vue` | Khách hàng gửi |
| Dashboard quản lý | `pages/super-admin/feedback/Index.vue` | Xem toàn bộ feedback |
| Báo cáo NPS | `pages/admin/reports/NPS.vue` | Biểu đồ NPS theo thời gian |

---

## 🔗 Xem Thêm

- [Tính năng NPS](../testing/test-cases.md#TC-NPS-35)
- [Kiến trúc tổng quan](../architecture/overview.md)
- [Quay lại mục lục](../README.md)
