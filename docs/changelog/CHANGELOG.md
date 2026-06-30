# 📋 Lịch Sử Thay Đổi (Changelog)

> Theo chuẩn [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)  
> và [Semantic Versioning](https://semver.org/lang/vi/)

---

## [Unreleased]

### ✨ Added
- Module Feedback Dashboard cho Super Admin (`TC-FDB-19`)
- Biểu đồ NPS theo tuần trên trang báo cáo

### 🐛 Fixed
- Sửa lỗi hiển thị sai điểm rating khi filter theo ngày

---

## [1.4.0] — 2026-06-29

### ✨ Added
- Nâng cấp UI toàn bộ trang Super Admin (Restaurant, Billing, Feedback)
- Thêm Meilisearch Console với giao diện quản lý index
- Biểu đồ doanh thu theo thời gian thực (Reverb + Chart.js)

### 🔄 Changed
- Chuyển từ Vuetify sang Tailwind CSS 4 cho toàn bộ UI
- Cải thiện tốc độ truy vấn báo cáo doanh thu (-60% query time)

### 🐛 Fixed
- Sửa lỗi tenant scope bị bỏ qua khi query qua relationship
- Sửa lỗi 2FA không hoạt động với Google Authenticator trên iOS

---

## [1.3.0] — 2026-06-14

### ✨ Added
- 50 test cases tích hợp (TC-001 → TC-050) — tất cả PASSED
- Module AI Upsell (Python FastAPI port 8003)
- Chức năng Impersonation cho Super Admin (`TC-IMP-23`)
- B2B Escrow thanh toán nhà cung cấp (`TC-ESC-13`)

### 🔒 Security
- Thêm Anti Self-Approval Middleware (`TC-APP-12`)
- Tăng cường Tenant Isolation với Global Scope (`TC-TEN-14`)

---

## [1.2.0] — 2026-05-20

### ✨ Added
- Module chấm công GPS + chống Fake GPS (`TC-GPS-03`)
- Module chấm công Webcam (`TC-WBC-24`)
- Tích hợp Sepay Webhook thanh toán (`TC-PAY-25`)
- Hệ thống Loyalty Points & hạng thành viên (`TC-LYT-37`)

---

## [1.1.0] — 2026-04-15

### ✨ Added
- QR Order Flow với Temporary Order (`TC-QR-01`)
- Order Locking chống gian lận nhân viên (`TC-LCK-05`)
- BOM khấu trừ kho tự động (`TC-BOM-07`)
- Phân quyền RBAC với Spatie Permission

---

## [1.0.0] — 2026-03-01

### 🎉 Initial Release
- Khởi tạo dự án Laravel 13 + Vue 3 + Inertia.js
- Cấu trúc Multi-tenant cơ bản
- Module đăng nhập / đăng ký + 2FA
- CRUD nhà hàng, menu, bàn
