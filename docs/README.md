# 📚 Tài Liệu Aventura — Mục Lục Tổng Hợp

> Tất cả tài liệu kỹ thuật của dự án được tổ chức tại đây.  
> Cập nhật lần cuối: 2026-06-29

---

## 🗂️ Cấu Trúc Thư Mục `docs/`

```
docs/
├── README.md                     ← File này (mục lục tổng hợp)
│
├── setup/                        ← Hướng dẫn cài đặt & cấu hình
│   ├── installation.md           ← Cài đặt từ đầu (composer, npm, migrate...)
│   ├── environment.md            ← Giải thích từng biến .env
│   └── deployment.md             ← Deploy lên server thực tế (Nginx, Supervisor...)
│
├── architecture/                 ← Kiến trúc & thiết kế hệ thống
│   ├── overview.md               ← Tổng quan kiến trúc, multi-tenant, RBAC
│   ├── database.md               ← Sơ đồ database & quan hệ giữa các bảng
│   └── api.md                    ← REST API (endpoints, payload, response, error codes)
│
├── features/                     ← Mô tả chi tiết từng tính năng
│   ├── feedback.md               ← Module đánh giá & phản hồi khách hàng
│   ├── restaurant.md             ← Module quản lý nhà hàng / chi nhánh / bàn
│   └── billing.md                ← Module thanh toán & gói cước SaaS
│
├── testing/                      ← Tài liệu kiểm thử
│   ├── test-cases.md             ← Ma trận 50 test cases (TC-001 → TC-050)
│   └── how-to-test.md            ← Lệnh chạy test, coverage, cấu trúc files
│
└── changelog/
    └── CHANGELOG.md              ← Lịch sử thay đổi theo phiên bản
```

---

## 🔗 Liên Kết Nhanh

### ⚙️ Cài Đặt
- [Hướng dẫn cài đặt đầy đủ](setup/installation.md)
- [Cấu hình biến môi trường](setup/environment.md)
- [Hướng dẫn triển khai production](setup/deployment.md)

### 🏗️ Kiến Trúc
- [Tổng quan hệ thống & Multi-Tenant](architecture/overview.md)
- [Sơ đồ Database](architecture/database.md)
- [REST API Reference](architecture/api.md)

### ✨ Tính Năng
- [Feedback & Đánh giá](features/feedback.md)
- [Quản lý nhà hàng](features/restaurant.md)
- [Thanh toán & Gói cước](features/billing.md)

### 🧪 Kiểm Thử
- [Ma trận kiểm thử (50 TCs — tất cả PASSED)](testing/test-cases.md)
- [Cách chạy test suite](testing/how-to-test.md)

### 📋 Lịch Sử
- [CHANGELOG](changelog/CHANGELOG.md)

---

## 📌 Quy Ước Đặt Tên File Markdown

| Loại | Quy tắc | Ví dụ |
|---|---|---|
| File thường | `chữ-thường-gạch-ngang.md` | `test-cases.md`, `how-to-test.md` |
| File quan trọng | `UPPER_CASE.md` | `README.md`, `CHANGELOG.md` |
| Thư mục | `chữ-thường/` | `features/`, `setup/`, `architecture/` |
