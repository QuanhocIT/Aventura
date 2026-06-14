# Tài Liệu Kiểm Thử Hệ Thống Quản Lý Nhà Hàng Aventura

Tài liệu này tổng hợp toàn bộ các ca kiểm thử tích hợp (Integration/Feature Tests) của hệ thống **Aventura**, phân tích chi tiết các cơ chế phòng thủ, xử lý nghiệp vụ thông minh, kế hoạch dự phòng và phương án triển khai thực tế.

---

## Bảng Chi Tiết Kiểm Thử Hệ Thống (Test Matrix)

| STT | Tên Chức Năng | Nghiệp Vụ (Use Case) | Ngày Test | Cơ Chế Phòng Thủ (Fraud/Security Defense) | Logic Nghiệp Vụ Chính (Business Rules) | Kế Hoạch Dự Phòng (Fallback / Fail-safe) | Phương Án Triển Khai (Deployment) | Dự Đoán Nghiệp Vụ (AI Projections) |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **01** | **Gọi Món Tại Bàn Qua QR Code** | Khách hàng quét mã QR tại bàn để xem thực đơn và gửi đơn hàng đệm (Temporary Order). | 14/06/2026 | - Xác thực token của bàn (`qr_token`).<br>- Không cho phép gửi đơn nếu món ăn hết nguyên liệu chế biến (tính toán BOM theo thời gian thực). | - Khách gửi đơn ở trạng thái `waiting_verification`. Nhận viên sẽ xác nhận trên POS để tạo đơn chính thức.<br>- Tự động kích hoạt Job kiểm tra trễ 2 phút. Nếu quá 2 phút không ai duyệt, đơn sẽ tự động chuyển sang `escalated` (Cảnh báo đỏ). | Đơn hàng không bị mất, hệ thống lưu trữ dưới dạng đơn tạm thời để nhân viên đối soát thủ công nếu kết nối WebSocket bị đứt. | - Chạy Redis Queue để xử lý delay Job.<br>- Sử dụng Pusher/Laravel Reverb phát sự kiện thời gian thực tới màn hình POS của nhân viên. | Gợi ý món ăn đi kèm (Upselling) dựa trên thuật toán phân tích giỏ hàng của khách. |
| **02** | **Phòng Chống Gian Lận Voucher** | Cashier hoặc khách hàng lạm dụng áp dụng liên tiếp mã giảm giá nhằm trục lợi. | 14/06/2026 | **Real-Time Fraud Prevention:**<br>- Phát hiện tần suất áp dụng voucher bất thường (Cashier áp dụng >= 3 lần/5 phút; Khách hàng áp dụng >= 1 lần/10 phút). | - Khi phát hiện gian lận, hệ thống khóa màn hình thanh toán và yêu cầu nhập mã phê duyệt của quản lý (`manager_bypass_code`).<br>- Ghi nhận chi tiết lịch sử vào `audit_logs` để hậu kiểm. | Nếu mất cấu hình hệ thống, hệ thống sẽ sử dụng mã phê duyệt mặc định là `MANAGER123` để tránh làm gián đoạn vận hành của quán. | Tích hợp Observer vào sự kiện cập nhật đơn hàng để tự động quét tần suất giao dịch thông qua Redis Cache. | Phân tích hành vi gian lận qua AI Fraud Detection Service (FastAPI) để tự động đưa ra cảnh báo đỏ trên Dashboard của Owner. |
| **03** | **Chấm Công GPS & Tránh Fake GPS** | Nhân viên tự chấm công vào ca (Check-in/Check-out) trên thiết bị di động của họ. | 14/06/2026 | **Anti-Spoofing & Mock GPS:**<br>- Chặn hoàn toàn các ứng dụng giả lập tọa độ (`is_mock = true`).<br>- Kiểm tra độ chính xác sai số GPS (`accuracy` phải nhỏ hơn bán kính cho phép). | - Nhân viên chỉ có thể chấm công thành công khi đứng trong bán kính cấu hình của nhà hàng (ví dụ: 100m).<br>- Tự động ghi nhận ca trực và thời gian đi muộn/về sớm. | Hỗ trợ cơ chế cho phép Quản lý duyệt chấm công thủ công (Override) trong trường hợp thiết bị nhân viên lỗi GPS hoặc lỗi phần cứng. | Triển khai trên Mobile Webapp, lấy tọa độ HTML5 Geolocation API của trình duyệt và gửi lên qua API bảo mật. | AI phân tích biểu đồ nhiệt chấm công (Punctuality Heatmap) để dự báo tỷ lệ đi muộn của từng nhân viên theo từng ca. |
| **04** | **Trợ Lý AI Gợi Ý Giỏ Hàng (Upselling)** | Tự động gợi ý các món ăn kèm ngon khi khách đang chọn món nhằm tăng giá trị hóa đơn (AOV). | 14/06/2026 | Giới hạn số lượng gợi ý đề xuất tối đa (ví dụ: tối đa 4 món) để tránh spam giao diện người dùng. | - Dựa trên lịch sử đơn hàng hoàn thành để tìm ra luật liên kết sản phẩm (ví dụ: Khách ăn Lẩu thường uống thêm Coca). | **Laravel Fallback Engine:** Nếu dịch vụ Python (FastAPI port 8003) bị offline, hệ thống tự động kích hoạt thuật toán Apriori in-memory bằng PHP để tính toán luật liên kết giỏ hàng. | Microservice Python chạy bằng FastAPI + Pandas kết nối qua API HTTP bảo mật, cache kết quả bằng Redis. | AI dự đoán hành vi ăn uống của khách dựa trên thời gian quét mã QR và thời tiết hiện tại để hiển thị danh mục món ăn phù hợp. |
| **05** | **Khóa Đơn Hàng Nghiêm Ngặt (Order Locking)** | Ngăn chặn hành vi nhân viên xóa món hoặc giảm số lượng món đã làm xong để gian lận tiền. | 14/06/2026 | **Strict Modification Lock:**<br>- Không cho phép giảm số lượng món ăn sau khi đơn đã được gửi xuống nhà bếp.<br>- Không cho phép xóa món khỏi đơn hàng. | - Mọi thay đổi tăng số lượng hoặc thay đổi giá (giảm giá) đều được ghi nhận lịch sử kiểm toán nghiêm ngặt.<br>- Giảm giá trực tiếp dưới giá niêm yết bắt buộc phải có mã quản lý bypass. | Khi nhà bếp không thể phục vụ món ăn (hết nguyên liệu đột xuất), quản lý có quyền hủy món và hệ thống sẽ tự động cập nhật lại kho. | Áp dụng trực tiếp tại tầng Controller và Middleware của Laravel để chặn request sửa đổi không hợp lệ từ client. | AI phân tích tỷ lệ chênh lệch giữa giá trị đơn hàng lúc tạo và lúc thanh toán để cảnh báo các ca làm việc có dấu hiệu bất thường. |
| **06** | **Tách Đơn & Chống Thất Thoát Tiền** | Nhân viên chuyển bớt món ăn sang một bàn khác hoặc tách hóa đơn cho khách. | 14/06/2026 | **Split-Order Penalty:**<br>- Tự động đánh dấu đỏ cảnh báo giao dịch tách đơn.<br>- Ghi nhận một khoản phạt âm tiền vào ca làm việc của nhân viên thực hiện nếu không được Owner phê duyệt. | - Giao dịch tách đơn là hành vi nhạy cảm dễ bị lợi dụng để giấu tiền mặt.<br>- Khoản phạt âm tiền chỉ được vô hiệu hóa khi Chủ nhà hàng (Owner) bấm duyệt đối soát thủ công. | Cho phép lưu vết đầy đủ sơ đồ tách đơn để phục vụ thanh tra cuối ngày nếu có tranh chấp tiền mặt trong ca. | Chạy background job để đối soát doanh thu thực tế và doanh thu lý thuyết trong ca trực khi thực hiện đóng ca. | AI dự báo rủi ro thất thoát tiền mặt dựa trên tần suất tách đơn của từng nhân viên. |
| **07** | **Định Lượng Nguyên Liệu & Tự Động RFP** | Tự động trừ kho nguyên liệu theo định lượng món ăn (BOM) và tạo yêu cầu nhập hàng. | 14/06/2026 | Khóa không cho phép nhập kho nguyên liệu nếu giá nhập thực tế biến động vượt quá 20% so với giá niêm yết của nhà cung cấp. | - Mỗi món ăn được cấu hình một công thức (BOM) gồm các nguyên liệu thành phần kèm tỷ lệ hao hụt.<br>- Khi đơn hàng hoàn thành, kho vật lý của chi nhánh tự động bị khấu trừ. | Cho phép chạy tiến trình quét và tái tính toán lại giá vốn trung bình (Average Cost) theo mô hình FIFO hoặc bình quân gia quyền khi có sai lệch. | Triển khai qua Laravel Horizon (Redis Queue) để xử lý việc khấu trừ kho bất đồng bộ, tránh nghẽn luồng thanh toán tại quầy. | AI tự động dự báo lượng nguyên liệu tiêu thụ trong tuần tới (dựa trên dữ liệu lịch sử và dự báo thời tiết) để tự động lên bản nháp đơn mua hàng (RFP). |
| **08** | **Tự Động Trích Xuất & Lưu Trữ Đơn Hàng Cũ** | Dọn dẹp cơ sở dữ liệu để đảm bảo tốc độ truy vấn luôn nhanh. | 14/06/2026 | Giới hạn quyền truy cập kho lưu trữ đơn hàng cũ (chỉ dành cho Super Admin và Owner). | - Các đơn hàng và log kiểm toán cũ hơn 6 tháng sẽ tự động được đóng gói và chuyển sang đĩa lưu trữ ngoài (S3/MinIO).<br>- Giải phóng không gian đĩa cho database chính. | Hỗ trợ tính năng khôi phục (Restore) đơn hàng cũ từ file lưu trữ ngoài về database chính khi có yêu cầu đối soát thuế. | Sử dụng Laravel Console Command kết hợp với Windows Task Scheduler hoặc Linux Cronjob chạy định kỳ vào 2 giờ sáng hàng ngày. | Phân tích xu hướng tăng trưởng dung lượng database để đưa ra khuyến nghị nâng cấp gói dung lượng trước 30 ngày. |

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
