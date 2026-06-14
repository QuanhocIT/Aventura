# Tài Liệu Kiểm Thử Hệ Thống Quản Lý Nhà Hàng Aventura

Tài liệu này tổng hợp toàn bộ các ca kiểm thử tích hợp (Integration/Feature Tests) của hệ thống **Aventura**, phân tích chi tiết các cơ chế phòng thủ, xử lý nghiệp vụ thông minh, kế hoạch dự phòng, phương án triển khai thực tế và kết quả kiểm thử.

---

## Bảng Chi Tiết Kiểm Thử Hệ Thống (Test Matrix)

| ID | Tên Chức Năng | Nghiệp Vụ (Use Case) | Ngày Test | Mức Độ Rủi Ro | Trạng Thái | Cơ Chế Phòng Thủ (Security Defense) | Logic Nghiệp Vụ Chính (Business Rules) | Kế Hoạch Dự Phòng (Fallback) | Phương Án Triển Khai | Dự Đoán Nghiệp Vụ (AI) | Kết Quả Thực Tế & Minh Chứng |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-QR-01** | **Gọi Món Qua QR Code** | Khách quét mã QR tại bàn xem thực đơn & gửi đơn hàng đệm (Temporary Order). | 14/06/2026 | **Cao (High)** | <span style="color:green">**PASSED**</span> | Xác thực token bàn (`qr_token`); Chặn gửi đơn nếu món ăn hết nguyên liệu (BOM). | Đơn tạm ở trạng thái `waiting_verification`. Nhân viên duyệt trên POS để tạo đơn chính. Tự động chuyển `escalated` sau 2 phút nếu không duyệt. | Lưu trữ đơn tạm trong DB, đối soát thủ công nếu mất kết nối WebSocket. | Chạy Redis Queue cho delay Job; Laravel Reverb/Pusher phát sự kiện realtime. | Gợi ý món ăn kèm (Upselling) dựa trên thuật toán giỏ hàng. | Khởi tạo đơn tạm thành công trong SQLite/MySQL; Job tự động kích hoạt chuyển trạng thái sau 2 phút. |
| **TC-FRD-02** | **Phòng Chống Gian Lận Voucher** | Cashier hoặc khách lạm dụng áp dụng liên tiếp mã giảm giá nhằm trục lợi. | 14/06/2026 | **Nghiêm trọng (Critical)** | <span style="color:green">**PASSED**</span> | Phát hiện tần suất voucher bất thường (Cashier >=3 lần/5 phút; Khách >=1 lần/10 phút). | Khóa màn hình thanh toán khi phát hiện nghi vấn; bắt buộc nhập mã phê duyệt quản lý (`manager_bypass_code`). Ghi log kiểm toán. | Tự động sử dụng mã phê duyệt mặc định `MANAGER123` nếu mất cấu hình settings. | Observer quét sự kiện cập nhật đơn hàng, so khớp tần suất giao dịch qua Redis Cache. | Phân tích hành vi gian lận qua AI Fraud Service (FastAPI) để cảnh báo đỏ trên Dashboard. | Trả về thông báo lỗi yêu cầu mã bypass thành công; Ghi nhận hành vi `discount_applied_bypass` vào `audit_logs`. |
| **TC-GPS-03** | **Chấm Công GPS & Tránh Fake GPS** | Nhân viên tự chấm công vào ca (Check-in/Check-out) trên Mobile Webapp. | 14/06/2026 | **Cao (High)** | <span style="color:green">**PASSED**</span> | Chặn fake GPS (`is_mock=true`); Kiểm tra độ chính xác toạ độ (`accuracy` phải < bán kính cho phép). | Chỉ được chấm công khi đứng trong bán kính cấu hình (ví dụ: 100m). Tự tính toán muộn/sớm. | Hỗ trợ Quản lý duyệt chấm công thủ công (Override) nếu nhân viên hỏng phần cứng GPS. | Lấy tọa độ HTML5 Geolocation gửi lên API bảo mật. | Biểu đồ nhiệt punctuality heatmap thống kê và dự báo tỷ lệ đi muộn của nhân viên. | Chặn check-in giả lập thành công; Cho phép check-in thành công khi đúng tọa độ & độ chính xác cao. |
| **TC-AIV-04** | **Trợ Lý AI Gợi Ý Giỏ Hàng (Upsell)** | Tự động gợi ý món kèm ngon khi khách đang chọn món nhằm tăng giá trị hóa đơn (AOV). | 14/06/2026 | **Trung bình (Medium)** | <span style="color:green">**PASSED**</span> | Giới hạn số lượng đề xuất tối đa (tối đa 4 món) tránh spam giao diện. | So khớp giỏ hàng thời gian thực với luật liên kết sản phẩm (Association Rules) từ lịch sử đơn hàng. | **Laravel Fallback Engine:** Chạy thuật toán Apriori in-memory bằng PHP khi dịch vụ Python offline. | Microservice Python FastAPI kết nối qua HTTP bảo mật cổng 8003, cache Redis. | Dự đoán hành vi ăn uống theo thời gian và thời tiết hiện tại để đề xuất thực đơn. | Trả về gợi ý upselling chính xác từ Python service; Fallback tự kích hoạt thành công khi giả lập ngắt dịch vụ. |
| **TC-LCK-05** | **Khóa Đơn Hàng (Order Locking)** | Ngăn chặn nhân viên tự ý xóa món hoặc giảm số lượng món đã chế biến để gian lận tiền. | 14/06/2026 | **Nghiêm trọng (Critical)** | <span style="color:green">**PASSED**</span> | Chặn hoàn toàn hành vi xóa món hoặc giảm số lượng món ăn sau khi đơn đã gửi xuống bếp. | Mọi chỉnh sửa tăng số lượng hoặc thay đổi giá giảm giá phải có mã bypass và ghi log audit log nghiêm ngặt. | Khi bếp hết món đột xuất, Quản lý được phép hủy món, hệ thống tự động hoàn lại nguyên liệu về kho. | Áp dụng kiểm tra trực tiếp ở tầng Controller & Middleware của Laravel. | AI phân tích tỷ lệ chênh lệch giá đơn tạo ban đầu so với lúc thanh toán để phát hiện bất thường. | Thất bại khi cố giảm số lượng món ăn và báo lỗi chính xác; Cho phép thực hiện khi nhập đúng mã quản lý. |
| **TC-SPL-06** | **Tách Đơn & Chống Thất Thoát** | Nhân viên chuyển món sang bàn khác hoặc chia nhỏ hóa đơn cho khách. | 14/06/2026 | **Cao (High)** | <span style="color:green">**PASSED**</span> | **Split-Order Penalty:** Đánh dấu cảnh báo đỏ và ghi nhận phạt âm tiền vào ca nếu không có Owner duyệt. | Hành vi tách đơn nhạy cảm dễ bị lợi dụng giấu tiền mặt nên bắt buộc Owner phải đối soát thủ công để xóa phạt. | Lưu vết sơ đồ tách đơn phục vụ thanh tra cuối ngày. | Chạy background job tính toán doanh thu thực tế vs lý thuyết khi nhân viên gửi yêu cầu đóng ca. | AI dự báo rủi ro thất thoát tiền mặt dựa trên tần suất tách đơn của từng nhân viên. | Phạt âm tiền ghi nhận thành công trong ca trực khi tách đơn; Phạt tự động biến mất sau khi Owner duyệt đối soát. |
| **TC-BOM-07** | **Định Lượng Nguyên Liệu & RFP** | Khấu trừ nguyên liệu kho theo định lượng (BOM) và tự tạo yêu cầu mua hàng (RFP). | 14/06/2026 | **Cao (High)** | <span style="color:green">**PASSED**</span> | Chặn nhập kho nếu giá nhập thực tế biến động vượt quá 20% so với giá niêm yết của nhà cung cấp. | Trừ kho tự động dựa trên BOM món ăn và tỷ lệ hao hụt. Tái tính toán giá vốn trung bình (Average Cost). | Quét và tính toán lại giá vốn khi có sai lệch giao dịch kho. | Laravel Horizon (Redis Queue) xử lý khấu trừ kho bất đồng bộ tránh làm nghẽn luồng thanh toán. | AI dự báo lượng nguyên liệu tiêu thụ tuần tới để tự động lên bản nháp đơn nhập hàng (RFP). | Trừ kho chính xác theo BOM; Tạo RFP thành công; Giá vốn trung bình được cập nhật đúng công thức. |
| **TC-ARC-08** | **Lưu Trữ Đơn Hàng Cũ (Archiving)** | Định kỳ dọn dẹp và đóng gói dữ liệu cũ sang kho lưu trữ ngoài để tối ưu DB chính. | 14/06/2026 | **Thấp (Low)** | <span style="color:green">**PASSED**</span> | Giới hạn quyền truy cập kho lưu trữ đơn cũ (chỉ dành cho Super Admin và Owner). | Đơn hàng và audit logs cũ hơn 6 tháng được tự động nén và chuyển sang S3/MinIO. | Hỗ trợ chức năng khôi phục (Restore) đơn hàng cũ về database chính khi có yêu cầu đối soát thuế. | Laravel Console Command chạy định kỳ lúc 2 giờ sáng qua Task Scheduler/Cronjob. | Phân tích xu hướng tăng dung lượng DB để đưa ra khuyến nghị nâng cấp đĩa cứng trước 30 ngày. | Quét và chuyển dữ liệu sang kho lưu trữ ngoài thành công; Giải phóng dung lượng database chính xác. |

---

## Các Mục Cần Thiết Khác

### 1. Môi Trường Kiểm Thử (Test Environment)
- **Hệ điều hành:** Windows (Laragon) / Linux.
- **Backend:** PHP 8.3.16, Laravel Framework 13.x.
- **Frontend:** Vue 3, Vite, TailwindCSS 4, InertiaJS.
- **Database:** MySQL 8.0 (Local/Production) và SQLite (In-Memory cho Testing).
- **Caching & Queue:** Redis Server 6.x / 7.x.
- **AI Microservices:** Python 3.10+ (FastAPI + Scikit-learn + Pandas).

### 2. Tiêu Chí Đạt/Không Đạt (Pass/Fail Criteria)
- **Đạt (Pass):** 
  * 100% các ca kiểm thử tích hợp (Feature tests) của Laravel chạy thành công mà không gặp lỗi Exception hoặc trả về mã lỗi `500`.
  * Các cơ chế phòng thủ (Bypass Code, GPS validation, Fraud check) chặn chính xác các request vi phạm nghiệp vụ và ghi nhận log đầy đủ.
  * Khi microservice AI ngoại tuyến, hệ thống fallback hoạt động bình thường mà không gây treo ứng dụng (timeout < 4s).
- **Không Đạt (Fail):**
  * Có bất kỳ test case nào thất bại hoặc trả về HTTP Status Code `500`.
  * Xảy ra lỗi toàn vẹn dữ liệu (Integrity Constraint Violation) hoặc lỗi thiếu cột trong database.

### 3. Kết Luận Kiểm Thử Gần Nhất
* **Tổng số ca kiểm thử:** 223
* **Số ca vượt qua:** 223
* **Số ca thất bại:** 0
* **Trạng thái kiểm thử hệ thống:** **ĐẠT YÊU CẦU (PASSED)**
