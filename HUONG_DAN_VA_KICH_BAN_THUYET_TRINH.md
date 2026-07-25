# KỊCH BẢN VÀ HƯỚNG DẪN THUYET TRINH DỰ ÁN HỆ THỐNG QUẢN LÝ NHÀ HÀNG AVENTURA (SaaS)

> **Dự án**: Aventura - Hệ thống quản lý nhà hàng dạng SaaS tích hợp AI & Microservice  
> **Thời lượng khuyến nghị**: 12 - 15 phút trình bày + 10 - 15 phút Q&A  
> **Phong thái**: Tự tin, chuyên nghiệp, làm nổi bật giá trị nghiệp vụ F&B và sức mạnh công nghệ  

---

## I. TỔNG QUAN VỀ CHIẾN LƯỢC THUYẾT TRINH

Để thuyết phục Hội đồng / Ban giám khảo / Khách hàng, bài thuyết trình cần làm nổi bật **3 trụ cột cốt lõi**:

1. **Giá trị Nghiệp vụ F&B**: Tối ưu vận hành, giảm thất thoát, minh bạch hóa dòng tiền và nhân sự.
2. **Kiến trúc Công nghệ Hiện đại & Chịu lỗi cao**: Laravel 12 + Vue 3 SPA + Realtime WebSockets + Circuit Breaker + Microservice Python.
3. **Trí tuệ nhân tạo (AI & Data Analytics)**: Dự báo tồn kho nguyên liệu, phát hiện gian lận (Fraud Detection), gợi ý bán hàng (Upsell/Cross-sell).

---

## II. KỊCH BẢN CHI TIẾT THEO TỪNG SLIDE & LỜI THOẠI (WORD-BY-WORD SCRIPT)

### SLIDE 1: PHẦN MỞ ĐẦU & NỖI ĐAU CỦA NGÀNH F&B (1.5 PHÚT)

* **Nội dung hiển thị trên Slide**:
  * Tên dự án: **AVENTURA - SaaS Restaurant Management System**
  * Thách thức ngành F&B: Thất thoát nguyên liệu, nghẽn đơn giờ cao điểm, gian lận ngân quỹ, thiếu dự báo chính xác.
  * Tên người thuyết trình & Vai trò.

* **Lời thoại thuyết trình**:
  > *"Kính chào Hội đồng và toàn thể các bạn!  
  > Trong kinh doanh F&B, việc điều hành một nhà hàng chưa bao giờ là bài toán dễ dàng. Các chủ nhà hàng luôn phải đối mặt với những 'cơn đau đầu' kéo dài: **thất thoát kho không rõ nguyên nhân, nghẽn order giữa thu ngân và bếp vào giờ cao điểm, nhân viên gian lận bằng cách hủy đơn sau khi thu tiền**, và quan trọng nhất là **thiếu các số liệu dự báo thông minh để ra quyết định kinh doanh**.  
  >
  > Chính vì lý do đó, đội ngũ chúng tôi đã phát triển **AVENTURA** – Hệ thống quản lý nhà hàng đa doanh nghiệp (SaaS) tích hợp Trí tuệ Nhân tạo, giúp tối ưu hóa vận hành, minh bạch dòng tiền và gia tăng lợi nhuận bền vững."*

---

### SLIDE 2: TỔNG QUAN HỆ THỐNG & MÔ HÌNH SAAS MULTI-TENANT (1.5 PHÚT)

* **Nội dung hiển thị trên Slide**:
  * Mô hình **Multi-tenant**: Cô lập dữ liệu tuyệt đối giữa các nhà hàng (Tenant).
  * Quy trình vận hành khép kín: Đặt bàn -> Order (POS) -> Bếp (KDS) -> Thu ngân -> Quản lý kho -> AI Báo cáo.
  * Sơ đồ phân quyền chi tiết (RBAC).

* **Lời thoại thuyết trình**:
  > *"Aventura được thiết kế theo mô hình **SaaS Multi-tenant**, cho phép hàng ngàn nhà hàng cùng vận hành trên một hạ tầng nhưng dữ liệu được phân tách hoàn toàn riêng biệt và bảo mật.  
  >
  > Hệ thống bao phủ toàn bộ vòng đời vận hành của nhà hàng:  
  > 1. **Phục vụ**: Đặt bàn, gọi món trực quan trên thiết bị di động/máy POS.  
  > 2. **Nhà bếp (KDS)**: Nhận đơn tức thì theo thời gian thực.  
  > 3. **Thu ngân**: Thanh toán nhanh chóng, xuất hóa đơn chuẩn xác.  
  > 4. **Quản trị viên**: Phân quyền chặt chẽ theo vai trò (Chủ nhà hàng, Quản lý, Thu ngân, Bếp, Phục vụ) thông qua cơ chế Spatie RBAC."*

---

### SLIDE 3: KIẾN TRÚC CÔNG NGHỆ CỐT LÕI (2 PHÚT)

* **Nội dung hiển thị trên Slide**:
  * **Backend**: Laravel 12 (PHP) + Redis + Meilisearch + Laravel Reverb (WebSockets).
  * **Frontend**: Vue.js 3 (SPA) + Pinia + Tailwind CSS + Vite.
  * **Microservice**: Python FastAPI + Pandas + Scikit-learn.
  * Sơ đồ luồng giao tiếp dữ liệu giữa Frontend, Backend và Microservice.

* **Lời thoại thuyết trình**:
  > *"Về kiến trúc công nghệ, chúng tôi lựa chọn bộ giải pháp thế hệ mới để đạt hiệu năng tối đa:  
  > * **Backend chính**: Sử dụng **Laravel 12** đóng vai trò RESTful API trung tâm xử lý logic nghiệp vụ và quản lý Tenant.  
  > * **Frontend**: Xây dựng dưới dạng ứng dụng đơn trang **Vue 3 SPA** kết hợp với **Pinia**, giúp giao diện mượt mà, thời gian phản hồi dưới 2 giây và không gây gián đoạn tải trang.  
  > * **Thời gian thực (Realtime)**: Tích hợp **Laravel Reverb (WebSockets)** giúp đồng bộ tức thì giữa phục vụ, bếp và thu ngân.  
  > * **Tìm kiếm & Caching**: Tận dụng **Redis** lưu tạm và **Meilisearch** để truy vấn hóa đơn, món ăn 'nhanh như chớp' thay vì SQL truyền thống.  
  > * **Hệ thống AI**: Đón đầu xu hướng bằng **Microservice Python FastAPI**, chuyên trách xử lý dữ liệu lớn và thuật toán học máy."*

---

### SLIDE 4: ĐIỂM SÁNG AI & PHÂN TÍCH THÔNG MINH (2.5 PHÚT)

* **Nội dung hiển thị trên Slide**:
  * **1. Dự báo tồn kho**: So sánh tồn kho lý thuyết vs thực tế, tính toán hao hụt.
  * **2. Phát hiện gian lận (Fraud Detection)**: Cảnh báo âm két, hủy món/sửa đơn bất thường.
  * **3. Gợi ý kinh doanh (Upsell)**: Đề xuất combo món đi kèm dựa trên lịch sử mua hàng.
  * **4. Dự báo doanh thu**: Phân tích khung giờ cao điểm và xu hướng tiêu dùng.

* **Lời thoại thuyết trình**:
  > *"Điểm biệt lập và ăn điểm nhất của Aventura nằm ở **Phân hệ AI Microservice**:  
  >
  > Thứ nhất, **Dự báo kho & Chốt kho vật lý**: Hệ thống không chỉ trừ kho tự động theo công thức món ăn, mà còn so sánh giữa *Tồn kho lý thuyết* và *Tồn kho thực tế* để chỉ ra sai số hao hụt. AI tự động đưa ra gợi ý lượng nguyên liệu cần nhập cho tuần tới dựa trên lịch sử bán.  
  >
  > Thứ hai, **Chống gian lận (Fraud Detection)**: Thất thoát trong nhà hàng đa phần đến từ con người. Python Service phân tích nhật ký thao tác để tự động phát hiện và cảnh báo các hành vi nghi vấn: nhân viên hủy đơn sau khi khách đã trả tiền, sửa bill liên tục hoặc âm két tiền mặt bất thường.  
  >
  > Thứ ba, **Gợi ý Upsell thông minh**: Sử dụng thuật toán phân tích kết hợp (Apriori), khi nhân viên lên đơn, hệ thống sẽ đề xuất ngay món kèm hoặc combo có xác suất mua cao nhất, giúp gia tăng giá trị trung bình trên mỗi hóa đơn."*

---

### SLIDE 5: HẠ TẦNG BỀN BỈ & CƠ CHẾ CHỊU LỖI CIRCUIT BREAKER (2 PHÚT)

* **Nội dung hiển thị trên Slide**:
  * **KPI Uptime > 99%**: Giám sát thời gian thực với Laravel Horizon, Pulse & Sentry.
  * **Circuit Breaker Pattern**: 3 trạng thái **CLOSED -> OPEN -> HALF-OPEN**.
  * **Fallback Engine**: Tự động chuyển đổi sang PHP Fallback Engine (Upsell) & SMTP Mail Fallback khi Python/External Service gián đoạn.

* **Lời thoại thuyết trình**:
  > *"Đối với hệ thống SaaS kinh doanh liên tục, thời gian chết (downtime) là tổn thất lớn. Do đó, chúng tôi thiết kế hạ tầng có **Khả năng chịu lỗi vượt trội (Fault Tolerance)**.  
  >
  > Chúng tôi triển khai cơ chế **Circuit Breaker 3 trạng thái**:  
  > * Trong điều kiện bình thường (CLOSED), Laravel gọi API sang Python Service.  
  > * Nếu Python Service gặp sự cố ngắt kết nối quá 3 lần, ngắt mạch sẽ tự động bật (OPEN). Ngay lập tức, **Laravel Fallback Engine** viết bằng PHP sẽ tự động tiếp quản để chạy thuật toán gợi ý món ăn trực tiếp trên memory, đảm bảo việc bán hàng của nhà hàng **không bao giờ bị gián đoạn**.  
  > * Khi Python khôi phục, mạch chuyển sang HALF-OPEN thăm dò và tự động khôi phục CLOSED.  
  >
  > Đồng thời, hệ thống được theo dõi 24/7 bởi Laravel Horizon, Pulse và tự động bắn cảnh báo sự cố qua Sentry."*

---

### SLIDE 6: KỊCH BẢN LIVE DEMO (3 PHÚT)

Lưu ý: Mở sẵn 2 cửa sổ trình duyệt: 1 bên là Giao diện Phục vụ/Thu ngân, 1 bên là Màn hình Bếp/KDS và Dashboard Quản lý.

* **Nội dung hành động Demo**:
  1. **Bước 1**: Đăng nhập với vai trò Phục vụ -> Chọn Bàn 05 -> Thực hiện Order 2 món (Ví dụ: Lẩu Thái + Trà Đào).
  2. **Bước 2**: Chỉ cho Hội đồng thấy Màn hình Bếp tự động nhảy thông tin order ngay lập tức (Realtime Reverb) mà không cần nhấn F5. Bếp nhấn "Đang chế biến" -> "Hoàn thành".
  3. **Bước 3**: Chuyển sang Giao diện Thu ngân -> Khách yêu cầu thanh toán -> Xuất hóa đơn -> Tồn kho nguyên liệu (thịt, rau, trà) tự động bị trừ tương ứng.
  4. **Bước 4**: Mở Dashboard AI -> Xem biểu đồ dự báo doanh thu, cảnh báo phát hiện gian lận (nếu có đơn bị hủy bất thường) và Báo cáo chênh lệch tồn kho thực tế vs lý thuyết.

* **Lời thoại khi thực hiện Demo**:
  > *"Bây giờ, tôi xin phép trình diễn Kịch bản vận hành thực tế của Aventura:  
  >
  > Đầu tiên, nhân viên phục vụ mở giao diện POS trên máy tính bảng, chọn Bàn 05 và order món. Nhờ Vue 3 và Realtime WebSocket, ngay khi bấm gửi, màn hình Bếp ở phía bên phải lập tức nhận đơn mà không có độ trễ.  
  >
  > Bếp bắt đầu chế biến và chuyển trạng thái 'Hoàn thành'. Ngay lập tức, thu ngân thấy trạng thái sẵn sàng, thực hiện in hóa đơn thanh toán. Ngay sau khi thanh toán thành công, hệ thống tự động trừ kho nguyên liệu chính xác theo định lượng món ăn.  
  >
  > Cuối cùng, tại Dashboard Quản lý, chủ nhà hàng có cái nhìn toàn cảnh: từ biểu đồ doanh thu Realtime, gợi ý món bán kèm do AI đề xuất, cho đến các cảnh báo rủi ro gian lận được cập nhật liên tục."*

---

### SLIDE 7: KẾT LUẬN & HƯỚNG PHÁT TRUYỂN TƯƠNG LAI (1 PHÚT)

* **Nội dung hiển thị trên Slide**:
  * **Tóm tắt giá trị**: Vận hành nhanh - Chống thất thoát - Tích hợp AI - Hạ tầng chịu lỗi.
  * **Hướng phát triển**: Tích hợp thanh toán QR tự động (VNPay/VietQR), Kiosk tự order, AI Voice Assistant cho nhà bếp.
  * Lời cảm ơn & Mời đặt câu hỏi.

* **Lời thoại thuyết trình**:
  > *"Tóm lại, Aventura không chỉ là một phần mềm quản lý nhà hàng thông thường, mà là một **Hệ sinh thái vận hành thông minh**, giúp các doanh nghiệp F&B tối ưu chi phí và tăng trưởng doanh thu.  
  >
  > Trong tương lai, chúng tôi sẽ mở rộng tích hợp cổng thanh toán VietQR/VNPay tự động và phát triển Kiosk tự order cho khách hàng.  
  >
  > Cảm ơn Quý Hội đồng và các bạn đã chú ý lắng nghe! Sau đây, tôi rất mong nhận được những góp ý và câu hỏi từ Hội đồng."*

---

## III. CHUẨN BỊ BỘ CÂU HỎI HÓC BÚA TỪ HỘI ĐỒNG & CÂU TRẢ LỜI CHUẨN (Q&A STRATEGY)

### ❓ Câu hỏi 1: Hệ thống của bạn làm Multi-tenant như thế nào? Liệu dữ liệu của Nhà hàng A có nguy cơ bị lộ sang Nhà hàng B không?

* **Cách trả lời**:
  > *"Dạ thưa Hội đồng, hệ thống của chúng em áp dụng kiến trúc Multi-tenancy phân tách ở mức logic dữ liệu (Shared Database, Separate Schema/Tenant ID) kết hợp với **Global Scope & Middleware trong Laravel**.  
  > Tất cả các câu truy vấn cơ sở dữ liệu đều bắt buộc đi qua Tenant Middleware để gắn điều kiện `tenant_id` tự động. Do đó, bất kỳ truy vấn nào cũng chỉ tiếp cận được đúng phạm vi dữ liệu của nhà hàng đó, loại bỏ hoàn toàn rủi ro rò rỉ dữ liệu chéo giữa các tenant."*

---

### ❓ Câu hỏi 2: Tại sao lại tách Python Microservice? Tại sao không dùng PHP viết luôn cho tiện?

* **Cách trả lời**:
  > *"Dạ, việc tách Python Microservice được cân nhắc dựa trên điểm mạnh của từng ngôn ngữ:  
  > * **Laravel (PHP)** rất mạnh về xử lý nghiệp vụ web, quản lý database quan hệ, authentication và realtime event.  
  > * **Python** là 'vua' trong lĩnh vực xử lý dữ liệu và Machine Learning với các thư viện tối ưu như Pandas, Scikit-learn.  
  > Nếu xử lý các bài toán AI phức tạp trên PHP sẽ dễ gây nghẽn tiến trình (blocking I/O) và làm chậm API chính. Tách thành Microservice với **FastAPI (Asynchronous)** giúp hệ thống hoạt động độc lập, tận dụng tối đa tài nguyên và dễ dàng mở rộng (scale) sau này."*

---

### ❓ Câu hỏi 3: Nếu dịch vụ Python FastAPI bị sập hoặc nghẽn mạng thì hệ thống có bị ngừng trệ không?

* **Cách trả lời**:
  > *"Dạ hoàn toàn không ạ. Chúng em đã thiết kế mẫu kiến trúc **Circuit Breaker (Ngắt mạch 3 trạng thái)**.  
  > Khi phát hiện Python Service bị lỗi quá 3 lần, Circuit Breaker chuyển sang trạng thái OPEN và ngắt kết nối tạm thời. Ngay lập tức, hệ thống tự động kích hoạt **Laravel Fallback Engine** viết bằng PHP thuần để đảm bảo các tính năng chính như gợi ý món (Upsell) vẫn hoạt động ổn định in-memory. Trải nghiệm người dùng tại nhà hàng hoàn toàn không bị ảnh hưởng."*

---

### ❓ Câu hỏi 4: Thuật toán phát hiện gian lận (Fraud Detection) phát hiện bằng cách nào?

* **Cách trả lời**:
  > *"Dạ, phân hệ Fraud Detection sử dụng các quy tắc phân tích bất thường dựa trên nhật ký hệ thống (Audit Log):  
  > 1. Tần suất hủy/sửa đơn sau khi đã in tạm tính hoặc thu tiền.  
  > 2. Chênh lệch giữa số tiền mặt thực tế trong két và tổng tiền trên hóa đơn (âm két bất thường).  
  > 3. Tỷ lệ hao hụt kho thực tế so với định lượng tiêu chuẩn vượt ngưỡng cho phép.  
  > Khi phát hiện các chỉ số này vượt ngưỡng cảnh báo (threshold), Python Microservice sẽ flag đơn hàng/nhân viên đó và đẩy cảnh báo trực tiếp về Dashboard của Quản lý."*

---

### ❓ Câu hỏi 5: Hệ thống giải quyết bài toán Realtime giữa Bếp và Phục vụ như thế nào khi lượng truy vấn lớn?

* **Cách trả lời**:
  > *"Dạ, chúng em sử dụng **Laravel Reverb** (WebSocket server thuần PHP hiệu năng cao) kết hợp với **Redis Pub/Sub**.  
  > Thay vì để Frontend liên tục gửi request thăm dò (HTTP Polling) gây quá tải server, Reverb tạo kết nối hai chiều duy nhất (persistent WebSocket connection). Khi phục vụ tạo order, một Event sẽ được bắn vào Redis Queue và Reverb đẩy ngay thông điệp tới giao diện Bếp trong vài miligiây, giúp tiết kiệm tối đa băng thông và tài nguyên server."*

---

## IV. BẢNG CHECKLIST CHUẨN BỊ TRƯỚC KHI THUYẾT TRINH

| STT | Công việc cần chuẩn bị | Trạng thái |
| --- | --- | --- |
| 1 | Kiểm tra server Laravel & FastAPI Python đã start (`php artisan serve`, `uvicorn`, `redis-server`) | 🔲 |
| 2 | Mở sẵn 2 trình duyệt riêng biệt (Chrome / Firefox Incognito) cho 2 vai trò: Phục vụ & Bếp | 🔲 |
| 3 | Thêm sẵn dữ liệu mẫu (Menu món ăn, Đơn hàng, Lịch sử kho) đẹp mắt để demo | 🔲 |
| 4 | Kiểm tra kết nối Internet và Laravel Reverb WebSocket | 🔲 |
| 5 | Chuẩn bị remote chuyển slide & đồng hồ đếm ngược (timer 12 phút) | 🔲 |

---
*Chúc bạn có một buổi thuyết trình thành công rực rỡ và đạt điểm số tối đa!*
