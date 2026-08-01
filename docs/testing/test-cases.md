# 🧪 Ma Trận Kiểm Thử Hệ Thống Aventura (Test Cases)

> Tài liệu này tổng hợp toàn bộ ca kiểm thử tích hợp của hệ thống.  
> Nguồn gốc: migrate từ `TAI_LIEU_KIEM_THU.md` ở thư mục gốc.  
> Xem hướng dẫn chạy test tại: [how-to-test.md](how-to-test.md)

---

## 📊 Tổng Quan

| Tổng TC | Passed | Failed | Pending |
|---|---|---|---|
| 50 | ✅ 50 | ❌ 0 | ⏳ 0 |

---

## 🔴 Mức Nghiêm Trọng (Critical)

| ID | Tên | Trạng Thái | Mô Tả |
|---|---|---|---|
| TC-FRD-02 | Phòng Chống Gian Lận Voucher | ✅ PASSED | Phát hiện tần suất dùng voucher bất thường, yêu cầu mã bypass quản lý |
| TC-LCK-05 | Khóa Đơn Hàng | ✅ PASSED | Chặn xóa/giảm món đã gửi bếp |
| TC-APP-12 | Ngăn Tự Phê Duyệt | ✅ PASSED | `approver_id` ≠ `requester_id` |
| TC-ESC-13 | Thanh Toán Escrow B2B | ✅ PASSED | Khóa tiền khi hóa đơn sai > 20% |
| TC-TEN-14 | Phân Tách Dữ Liệu Tenant | ✅ PASSED | Global Scope lọc theo `restaurant_id` |
| TC-EMP-22 | Khóa Tài Khoản Brute Force | ✅ PASSED | Khóa 15 phút sau 5 lần sai |
| TC-IMP-23 | Giả Danh Hỗ Trợ | ✅ PASSED | Super Admin impersonate Owner |
| TC-PAY-25 | Webhook Sepay | ✅ PASSED | Xác thực chữ ký + gia hạn thuê bao |
| TC-2FA-28 | Bảo Mật 2FA | ✅ PASSED | Google Authenticator + Recovery Codes |

---

## 🟠 Mức Cao (High)

| ID | Tên | Trạng Thái | Mô Tả |
|---|---|---|---|
| TC-QR-01 | Gọi Món Qua QR | ✅ PASSED | Đặt món tạm, nhân viên duyệt, tự escalate sau 2 phút |
| TC-GPS-03 | Chấm Công GPS | ✅ PASSED | Chặn fake GPS, kiểm tra tọa độ trong bán kính |
| TC-SPL-06 | Tách Đơn | ✅ PASSED | Penalty âm tiền khi tách đơn không có owner duyệt |
| TC-BOM-07 | Định Lượng Kho (BOM) | ✅ PASSED | Khấu trừ nguyên liệu + tạo RFP tự động |
| TC-SCH-09 | Quy Tắc Nghỉ 11 Giờ | ✅ PASSED | Chặn phân ca không đủ 11h nghỉ giữa 2 ca |
| TC-ADV-11 | Giới Hạn Tạm Ứng Lương | ✅ PASSED | Không tạm ứng quá 50% lương thực làm |
| TC-QTA-15 | Hạn Ngạch Lưu Trữ | ✅ PASSED | Chặn upload khi vượt quota gói cước |
| TC-MNT-16 | Chế Độ Bảo Trì | ✅ PASSED | Chỉ Admin vào được khi maintenance mode |
| TC-PAY-17 | Tính Lương Tự Động | ✅ PASSED | Tính lương theo GPS chấm công thực tế |
| TC-INV-20 | Đồng Thời Hóa Kho | ✅ PASSED | Pessimistic Lock chống Race Condition |
| TC-WST-21 | Báo Cáo Hao Hụt | ✅ PASSED | Ghi nhận hao hụt, trừ kho chính xác |
| TC-WBC-24 | Chấm Công Webcam | ✅ PASSED | Chụp ảnh realtime, chống ảnh tĩnh giả |
| TC-MID-26 | Middleware Thuê Bao | ✅ PASSED | Redirect khi gói cước hết hạn |
| TC-PWD-29 | Xác Nhận Mật Khẩu | ✅ PASSED | Re-confirm trước thao tác nhạy cảm |
| TC-BKP-33 | Sao Lưu Tự Động | ✅ PASSED | Backup DB + upload S3/MinIO |
| TC-SLP-39 | Đóng Ca Cuối Ngày | ✅ PASSED | Tự động đóng ca lúc 23:59 |
| TC-KIT-42 | Bếp Báo Hết Món | ✅ PASSED | Ẩn món realtime trên QR menu |
| TC-LOG-47 | Audit Log Toàn Diện | ✅ PASSED | Không ai được xóa/sửa log |
| TC-SCH-09 | Vi Phạm Nghỉ Ngơi | ✅ PASSED | Cảnh báo vi phạm lao động |

---

## 🟡 Mức Trung Bình (Medium)

| ID | Tên | Trạng Thái | Mô Tả |
|---|---|---|---|
| TC-AIV-04 | AI Upsell Gợi Ý Giỏ Hàng | ✅ PASSED | Gợi ý kèm món theo Association Rules |
| TC-LVE-10 | Hạn Ngạch Xin Nghỉ | ✅ PASSED | Không cho nghỉ quá 30% cùng vai trò |
| TC-ADV-18 | Cố Vấn AI | ✅ PASSED | Chat AI phân tích kinh doanh |
| TC-FDB-19 | Đánh Giá Khách Hàng | ✅ PASSED | Ẩn danh, chống spam, cảnh báo < 3 sao |
| TC-PLN-27 | Gói Dịch Vụ | ✅ PASSED | Plan Builder với giới hạn tài nguyên |
| TC-SLA-32 | Escalation Ticket | ✅ PASSED | Tự escalate vé quá hạn SLA |
| TC-NPS-35 | Chỉ Số NPS | ✅ PASSED | Tính NPS chuẩn, lọc spam |
| TC-CHN-36 | Dự Báo Churn | ✅ PASSED | AI dự báo nguy cơ hủy gói |
| TC-LYT-37 | Hạng Thành Viên | ✅ PASSED | Bạc/Vàng/Kim Cương theo chi tiêu |
| TC-EML-40 | Email Dự Phòng | ✅ PASSED | Fallback SMTP khi Python service lỗi |
| TC-KIT-41 | Bếp Tạm Dừng Món | ✅ PASSED | Disable nút đặt món realtime |
| TC-SCH-43 | AI Gợi Ý Ca Trực | ✅ PASSED | Phân lịch tự động không trùng |
| TC-COP-45 | Sao Chép Lịch Tuần | ✅ PASSED | Clone lịch tuần trước, bỏ ngày nghỉ |
| TC-SLA-46 | Hạn SLA Ticket | ✅ PASSED | Tính thời hạn theo gói cước |

---

## 🟢 Mức Thấp (Low)

| ID | Tên | Trạng Thái | Mô Tả |
|---|---|---|---|
| TC-ARC-08 | Lưu Trữ Đơn Cũ | ✅ PASSED | Chuyển đơn > 6 tháng sang S3 |
| TC-WTH-30 | Dự Báo Thời Tiết | ✅ PASSED | Menu thay đổi theo thời tiết |
| TC-CDP-31 | Hành Vi Khách (CDP) | ✅ PASSED | Log click/view ẩn danh |
| TC-FAC-38 | Demo Seeders | ✅ PASSED | Tạo 12 tháng dữ liệu mẫu |
| TC-CLN-34 | Dọn File Tạm | ✅ PASSED | Xóa session, log cũ định kỳ |
| TC-SCH-44 | Đề Xuất Đổi Ca | ✅ PASSED | Tìm nhân viên phù hợp đổi ca |

---

## 🔗 Xem Thêm

- [Hướng dẫn chạy test](how-to-test.md)
- [Quay lại mục lục](../README.md)
