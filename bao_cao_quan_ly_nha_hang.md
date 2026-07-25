# BÁO CÁO DỰ ÁN: HỆ THỐNG QUẢN LÝ NHÀ HÀNG DẠNG SAAS

Tên đề tài: Hệ thông quản lý nhà hàng Aventura

Chủ trương

Đề cao khả năng quản lý toàn phần, tính minh bạch trong quản lý, giảm sự phụ thuộc và tính trung thực của nhân viên; tối ưu các chức năng chuyên môn cốt lõi cho doanh nghiệp; hỗ trợ xử lý kịp thời các vấn đề ảnh hưởng đến uy tín và sự vận hành của nhà hàng, tích hợp AI vào hệ thống để tăng khả năng phán đoán để đưa ra các quyết định sáng suốt nâng cao lợi nhuận của doanh nghiệp.

________________________________________

1. Giới thiệu

Dự án xây dựng hệ thống quản lý nhà hàng theo mô hình SaaS tương tự KiotViet, cho phép nhiều doanh nghiệp sử dụng chung một nền tảng nhưng dữ liệu được tách biệt theo từng nhà hàng (tenant).

Hệ thống hỗ trợ quản lý đơn hàng, nhân sự, kho nguyên liệu, doanh thu, khách hàng và báo cáo kinh doanh. Ngoài ra, hệ thống tích hợp Python để phân tích dữ liệu, tự động hóa xử lý nghiệp vụ và hỗ trợ dự đoán, giúp chủ nhà hàng đưa ra quyết định chính xác hơn.

________________________________________

1. Công nghệ sử dụng

Để đáp ứng mục tiêu xây dựng một nền tảng SaaS mạnh mẽ, đảm bảo hiệu năng cao (phản hồi < 2s) và khả năng mở rộng linh hoạt, dự án Hệ thông quản lý nhà hàng Aventura được triển khai dựa trên hệ sinh thái công nghệ hiện đại sau:

2.1 Backend & Hệ sinh thái xử lý trung tâm

•    Laravel 12 (PHP): Đóng vai trò nền tảng chính để xây dựng RESTful API, quản lý logic nghiệp vụ và cấu trúc đa người thuê (Multi-tenant).

o    Laravel Horizon: Công cụ bắt buộc đi kèm với Redis để quản lý và giám sát hệ thống hàng đợi (Queue), giúp theo dõi tỷ lệ lỗi và thời gian xử lý các tác vụ nền.

o    Laravel Pulse: Hệ thống giám sát sức khỏe server thời gian thực, giúp phát hiện sớm các truy vấn chậm (slow queries) hoặc các điểm nghẽn tài nguyên API.

o    Spatie Laravel Permission: Thư viện tiêu chuẩn công nghiệp được tích hợp để quản lý vai trò (Roles) và quyền hạn (Permissions) một cách chặt chẽ, bảo mật.

•    Python Microservice: Chuyên trách xử lý dữ liệu chuyên sâu và AI.

o    FastAPI: Framework hiệu năng cao, hỗ trợ bất đồng bộ (Asynchronous) dùng để xây dựng các API cầu nối, giúp Laravel và Python giao tiếp cực nhanh.

o    Pandas & Scikit-learn: Các thư viện chuyên dụng để phân tích bảng biểu doanh thu, xây dựng mô hình dự báo tồn kho và phát hiện gian lận (Fraud Detection).

2.2 Frontend & Trải nghiệm người dùng

•    Vue.js (SPA): Xây dựng ứng dụng đơn trang giúp giao diện hoạt động mượt mà, giảm thiểu thời gian chờ giữa các thao tác của nhân viên nhà hàng.

o    Pinia: Thư viện quản lý trạng thái (State Management) thế hệ mới, đảm bảo dữ liệu đơn hàng và trạng thái bàn được đồng bộ liên tục trên toàcungxn hệ thống mà không cần tải lại trang.

o    Tailwind CSS: Framework CSS tối ưu để thiết kế giao diện tùy chỉnh nhanh chóng, đảm bảo tính hiển thị tốt trên nhiều thiết bị như máy POS, máy tính bảng và điện thoại.

o    Vite: Công cụ hỗ trợ build mã nguồn tốc độ cao, giúp tối ưu hóa việc chia nhỏ mã nguồn (code splitting) để ứng dụng vận hành nhẹ nhàng hơn.

2.3 Database & Cache (Tầng dữ liệu)

•    MySQL: Hệ quản trị cơ sở dữ liệu quan hệ chính, lưu trữ toàn bộ thông tin nhà hàng, thực đơn và giao dịch.

•    Redis: Hệ thống lưu trữ dữ liệu trên bộ nhớ tạm (In-memory), đóng vai trò Caching, quản lý Session và làm trình điều phối hàng đợi (Queue) để giảm tải tối đa cho Database chính.

•    Meilisearch: Giải pháp tìm kiếm chuyên sâu (Full-text search), hỗ trợ tìm kiếm món ăn và lịch sử hóa đơn "nhanh như chớp" thay vì sử dụng các câu lệnh SQL truyền thống dễ gây chậm hệ thống.

2.4 Hạ tầng, Realtime & Giám sát (DevOps)

•    Laravel Reverb / WebSockets: Đảm bảo truyền tải thông tin thời gian thực giữa các bộ phận (ví dụ: nhân viên phục vụ order thì màn hình bếp nhận thông tin ngay lập tức).

•    Docker: Đóng gói toàn bộ môi trường ứng dụng, giúp việc triển khai từ môi trường phát triển lên Production luôn ổn định và đồng nhất.

•    MinIO: Giải pháp lưu trữ đối tượng (Object Storage) mã nguồn mở, dùng để quản lý hình ảnh món ăn và tệp hóa đơn chuyên nghiệp, tách biệt khỏi mã nguồn dự án.

•    Sentry: Hệ thống theo dõi lỗi tập trung, tự động cảnh báo các sự cố phát sinh ở cả Frontend và Backend để đội ngũ kỹ thuật xử lý kịp thời, đảm bảo KPI Uptime > 99%.

•    Circuit Breaker: Cơ chế ngăn chặn lỗi lan truyền (Cascading Failure), ngắt tạm thời các cuộc gọi API từ Laravel đến các microservices bên ngoài khi phát hiện sự cố quá tải hoặc mất kết nối.

2.5 Công cụ quản lý dự án

•    GitHub: Quản lý mã nguồn, kiểm soát phiên bản và cộng tác nhóm.

•    Trello: Điều phối công việc, quản lý quy trình triển khai theo mô hình Kanban để theo dõi tiến độ dự án.

•    Postman: Công cụ chủ đạo trong việc kiểm thử và xây dựng tài liệu hướng dẫn sử dụng API.

2.6 Lớp chịu lỗi Circuit Breaker & Dự phòng hạ tầng (Fault Tolerance)

Để đảm bảo hệ thống SaaS hoạt động bền bỉ (KPI Uptime > 99%) ngay cả khi các dịch vụ hỗ trợ (microservices) bên ngoài gặp sự cố, hệ thống triển khai máy trạng thái (State Machine) 3 cấp độ:

•    CLOSED (Trạng thái bình thường): Mọi yêu cầu API của Laravel đến microservices (như FastAPI Analytics, Email Service) đều được thực hiện trực tiếp.

•    OPEN (Trạng thái ngắt mạch): Khi số lần kết nối thất bại liên tiếp vượt quá 3 lần, mạch tự động chuyển sang OPEN. Laravel lập tức chặn đứng các cuộc gọi API tiếp theo để giải phóng băng thông và định tuyến trực tiếp các yêu cầu đến hàm xử lý dự phòng (Fallback Engine).

•    HALF-OPEN (Trạng thái thăm dò): Sau chu kỳ cooldown (thời gian hồi phục), mạch chuyển sang HALF-OPEN và gửi thử nghiệm một lượng nhỏ API request. Nếu thành công, mạch đóng lại (CLOSED); nếu tiếp tục lỗi, mạch duy trì OPEN.

•    Cơ chế dự phòng (Fallback Fallback):

  o    Đối với Dịch vụ Gợi ý Upsell: Nếu FastAPI Python offline, Laravel tự động kích hoạt *Laravel Fallback Engine* viết bằng PHP chạy thuật toán Apriori in-memory để phục vụ gợi ý món ăn kèm.

  o    Đối với Dịch vụ Email: Nếu FastAPI Email Service bị sập, Laravel tự động chuyển sang driver SMTP Brevo/Mailgun được cấu hình trực tiếp từ Laravel để gửi OTP, hóa đơn và thông báo khẩn cấp.

________________________________________

1. Vai trò của các công nghệ trong hệ thống

Hệ thống Aventura được xây dựng dựa trên sự kết hợp giữa Laravel, Vue.js và Python, trong đó mỗi công nghệ đảm nhiệm một vai trò riêng nhằm tối ưu hiệu suất, khả năng mở rộng và trải nghiệm người dùng. Việc tách rõ trách nhiệm giữa các công nghệ giúp hệ thống dễ bảo trì, nâng cấp và phù hợp với mô hình SaaS nhiều doanh nghiệp cùng sử dụng.

3.1 Vai trò của Laravel trong hệ thống

Laravel đóng vai trò là backend chính của hệ thống, chịu trách nhiệm xử lý toàn bộ logic nghiệp vụ cốt lõi, quản lý dữ liệu, phân quyền và cung cấp API cho frontend. Laravel giúp tổ chức mã nguồn theo mô hình MVC, hỗ trợ phát triển nhanh, bảo mật tốt và dễ mở rộng trong tương lai.

Laravel đảm nhiệm các chức năng như:

• Xây dựng REST API phục vụ frontend Vue.js.

• Xử lý logic nghiệp vụ như quản lý đơn hàng, menu, kho, nhân sự, chấm công và thanh toán.

• Quản lý xác thực người dùng (Authentication) bằng JWT hoặc Sanctum.

• Phân quyền hệ thống theo mô hình RBAC (Role-Based Access Control).

• Kết nối và thao tác dữ liệu với MySQL Database.

• Quản lý Queue Job để xử lý các tác vụ nền như gửi thông báo, tạo báo cáo hoặc đồng bộ dữ liệu.

• Hỗ trợ Realtime Event thông qua WebSocket hoặc Laravel Reverb.

• Kết nối với Python Service để gửi dữ liệu phân tích và nhận kết quả xử lý.

Ví dụ: Khi khách hàng tạo đơn món ăn, Laravel sẽ xử lý việc tạo order, lưu dữ liệu vào database, gửi trạng thái realtime cho bếp và cập nhật tồn kho sau thanh toán.

________________________________________

3.2 Vai trò của Vue.js trong hệ thống

Vue.js đóng vai trò là frontend của hệ thống, giúp xây dựng giao diện quản trị và giao diện sử dụng theo mô hình SPA (Single Page Application), giúp website hoạt động nhanh hơn, giảm tải refresh trang và nâng cao trải nghiệm người dùng.

Vue.js đảm nhiệm các chức năng như:

• Hiển thị giao diện quản lý cho chủ nhà hàng, nhân viên và khách hàng.

• Giao tiếp với Laravel thông qua REST API.

• Cập nhật dữ liệu Realtime như trạng thái món ăn, thông báo order, cập nhật doanh thu hoặc tồn kho.

• Xây dựng dashboard thống kê trực quan bằng biểu đồ và dữ liệu động.

• Quản lý trạng thái ứng dụng (State Management) để tối ưu hiệu suất.

• Tăng trải nghiệm người dùng bằng việc chuyển trang nhanh mà không cần tải lại website.

Ví dụ: Khi bếp chuyển trạng thái món từ “Đang làm” sang “Hoàn thành”, Vue.js sẽ tự động cập nhật giao diện realtime cho thu ngân hoặc khách hàng mà không cần tải lại trang.

________________________________________

3.3 Vai trò của Python trong hệ thống

Python không thay thế Laravel mà đóng vai trò microservice xử lý chuyên biệt, tập trung vào các bài toán phân tích dữ liệu, dự đoán và tự động hóa xử lý nghiệp vụ nâng cao mà backend truyền thống khó tối ưu.

3.3.1 Phân tích dữ liệu doanh thu

Python hỗ trợ:

• Phân tích doanh thu theo ngày, tuần, tháng.

• Phân tích khung giờ đông khách.

• Thống kê món bán chạy.

• Tính toán lợi nhuận thực tế.

Ví dụ: Chủ quán có thể biết món nào mang lợi nhuận cao nhưng bán chậm để điều chỉnh chiến lược kinh doanh.

3.3.2 Dự đoán tồn kho nguyên liệu

Python hỗ trợ:

• Dự đoán lượng nguyên liệu cần nhập.

• Cảnh báo thiếu nguyên liệu.

• Phân tích tốc độ tiêu thụ hàng hóa.

• Chốt kho vật lý ( 1 số hao hụt sẽ không thể tính toán máy móc được ) => Python nên thực hiện so sánh giữa Tồn kho lý thuyết (đã trừ theo công thức ) và Tồn kho thực tế để chỉ ra sai số

Ví dụ: Hệ thống có thể dự đoán tuần sau cần nhập thêm khoảng 20kg thịt bò dựa trên lịch sử bán hàng.

3.3.3 Phát hiện bất thường (Fraud Detection)

Python hỗ trợ phát hiện:

• Âm két bất thường.

• Nhân viên sửa hoặc xóa đơn nhiều lần.

• Hủy món bất thường.

• Thất thoát kho.

Ví dụ: Nếu thu ngân thường xuyên hủy đơn sau khi khách đã thanh toán, hệ thống sẽ ghi nhận hành vi và đưa ra cảnh báo cho quản lý.

3.3.4 Gợi ý kinh doanh

Python hỗ trợ:

• Phân tích món thường được mua cùng nhau.

• Đề xuất combo sản phẩm.

• Đề xuất thời điểm nên chạy khuyến mãi.

Ví dụ: Nếu khách mua trà đào thường gọi thêm bánh ngọt, hệ thống sẽ đề xuất combo giảm giá để tăng doanh thu.

3.3.5 Xử lý báo cáo nâng cao

Python hỗ trợ:

• Sinh biểu đồ thống kê nâng cao.

• Phân tích hiệu suất nhân viên.

• Tổng hợp báo cáo KPI.

• Dự báo doanh thu trong tương lai.

Ví dụ: Chủ nhà hàng có thể xem dự đoán doanh thu tháng tiếp theo để chuẩn bị ngân sách nhập hàng và nhân sự.

Sự kết hợp giữa Laravel, Vue.js và Python giúp hệ thống vừa đảm bảo khả năng xử lý nghiệp vụ ổn định, giao diện hiện đại realtime, vừa có khả năng phân tích dữ liệu thông minh nhằm hỗ trợ ra quyết định kinh doanh hiệu quả hơn.

________________________________________

1. Kiến trúc hệ thống

Hệ thống Aventura được thiết kế theo mô hình kiến trúc hiện đại, linh hoạt và có khả năng mở rộng cao, kết hợp giữa mô hình SaaS Đa người thuê (Multi-tenant), tách biệt Frontend-Backend, xử lý bất đồng bộ qua Queue, truyền tải dữ liệu thời gian thực (Realtime) và tích hợp Microservice Python chuyên biệt cho AI/Analytics.

4.1 Mô hình kiến trúc tổng thể (High-Level Architecture)

Hệ thống được cấu thành dựa trên 6 trụ cột kiến trúc chính:

• Mô hình SaaS Multi-tenant (Đa người thuê): Cho phép hàng trăm nhà hàng sử dụng chung một hạ tầng ứng dụng và cơ sở dữ liệu (Shared Database - Shared Schema), nhưng dữ liệu được cô lập logic tuyệt đối thông qua Tenant Key (`restaurant_id`) và bộ lọc tự động `BelongsToRestaurant`.

• Kiến trúc Tách biệt Frontend - Backend (Decoupled SPA & REST API):

- Frontend: Sử dụng Vue.js SPA đảm nhận trải nghiệm người dùng, tải giao diện nhanh chóng, quản lý trạng thái tập trung qua Pinia.
- Backend: Sử dụng Laravel 12 đóng vai trò RESTful API Server, cung cấp các endpoint bảo mật, xử lý toàn bộ logic nghiệp vụ cốt lõi và kiểm soát phân quyền.

• Kiến trúc Xử lý Thời gian thực (Realtime Architecture):

- Tích hợp Laravel Reverb / WebSockets giúp truyền dữ liệu hai chiều lập tức giữa các màn hình vận hành (Ví dụ: Thu ngân bấm tạo đơn -> Màn hình Bếp/KDS lập tức nhận thông báo chế biến mà không cần tải lại trang).

• Kiến trúc Xử lý Bất đồng bộ & Hàng đợi (Asynchronous Queue Architecture):

- Sử dụng Redis làm trình điều phối hàng đợi (Queue Broker) kết hợp với Laravel Horizon để xử lý các tác vụ tốn thời gian (như gửi email hóa đơn, tạo báo cáo Excel, đẩy dữ liệu Audit Log) ngoài luồng API chính, đảm bảo KPI phản hồi API < 2s.

• Kiến trúc Microservice Chuyên biệt (Python Analytics & AI Service):

- Tách biệt các bài toán nặng về phân tích dữ liệu (FastAPI, Pandas, Scikit-learn) thành một Microservice riêng biệt chạy song song với Laravel monolith. Điều này giúp tránh nghẽn CPU của server chính khi chạy các mô hình Machine Learning hoặc báo cáo tài chính phức tạp.

• Cơ chế Chịu lỗi & Dự phòng Hạ tầng (Fault Tolerance & Circuit Breaker):

- Áp dụng pattern Circuit Breaker (State Machine: CLOSED - OPEN - HALF-OPEN) kiểm soát kết nối giữa Laravel và các service bên ngoài. Nếu Python Microservice hoặc SMTP Email bị sự cố, hệ thống tự động ngắt kết nối và chuyển sang *Laravel Fallback Engine* (chạy thuật toán gợi ý in-memory bằng PHP) để đảm bảo hệ thống bán hàng luôn vận hành thông suốt (Uptime > 99%).

4.2 Kiến trúc Phân tầng Backend (Layered Software Architecture)

Mã nguồn Backend Laravel được tổ chức chặt chẽ theo mô hình 5 tầng (5-Layer Pattern) nhằm đảm bảo nguyên tắc Single Responsibility (SoC) và dễ dàng bảo trì:

1. Presentation / Routing Layer (Tầng Định tuyến & Middleware):
   - Tiếp nhận HTTP Request từ Client (Vue.js).
   - Kiểm tra an ninh qua lớp Middleware: Phân thực JWT/Sanctum, gán Tenant Scope (`restaurant_id`), kiểm tra quyền hạn (RBAC), kiểm tra ca làm việc (`CheckShiftSchedule`) và ngăn tự phê duyệt.

2. Controller Layer (Tầng Điều phối):
   - Tiếp nhận dữ liệu đầu vào, kiểm tra tính hợp lệ (Validation via FormRequest).
   - Điều phối cuộc gọi sang Tầng Service tương ứng và trả về JSON Response chuẩn hóa (dạng `code`, `message`, `data`).

3. Service Layer (Tầng Logic Nghiệp vụ):
   - Nơi chứa 100% logic kinh doanh của hệ thống (Ví dụ: Thuật toán tách/gộp bàn, tính toán khuyến mãi, áp dụng mã giảm giá, tính lương nhân viên).
   - Kích hoạt các Event, gửi Job vào Queue và gọi kết nối tới Python Microservice khi cần.

4. Repository Layer (Tầng Thao tác Dữ liệu):
   - Đóng vai trò là lớp trung gian trừu tượng hóa các câu lệnh truy vấn CSDL.
   - Thao tác trực tiếp với Eloquent ORM, áp dụng mặc định Global Scope `BelongsToRestaurant` để bảo mật dữ liệu tenant.

5. Infrastructure / Data Layer (Tầng Hạ tầng & Lưu trữ):
   - Quản lý lưu trữ CSDL MySQL, bộ nhớ đệm Redis, và đối tượng tệp tin trên Cloud Storage (MinIO/S3).

4.3 Luồng Tương tác & Giao tiếp giữa các Thành phần (System Intercommunication)

Sơ đồ luồng giao tiếp dữ liệu trong toàn hệ thống được thể hiện qua 2 luồng vận hành chính:

Luồng 1: Luồng Bán hàng & Đồng bộ Thời gian thực (Order & Realtime Flow)

Vue.js Frontend (Thu ngân / QR Order)
       │  (1) REST API Request (POST /api/v1/orders)
       ▼
Laravel API (Middleware Authorization & Validation)
       │  (2) Execute Service & Repository
       ▼
MySQL Database (DB::transaction bọc Order & Inventory)
       │  (3) Fire OrderCreated Event
       ▼
Laravel Reverb WebSocket Server ──(4) Push Notification──► Vue.js Kitchen Display (Màn hình Bếp)

Luồng 2: Luồng Phân tích Dữ liệu & AI (Python Microservice Integration Flow)

Hệ thống hỗ trợ 2 cơ chế giao tiếp linh hoạt với Python Microservice tùy thuộc vào tính chất nghiệp vụ:

• Cơ chế Đồng bộ (Synchronous REST API):

- Áp dụng cho các tính năng cần kết quả ngay lập tức (như Gợi ý món ăn kèm - Upsell khi chọn món).
- Luồng: `Laravel API` -> `HTTP POST (Timeout 1.5s)` -> `FastAPI Python` -> `Trả JSON Gợi ý` -> `Laravel API` -> `Vue.js`.
- Nếu FastAPI quá tải hoặc hỏng, Circuit Breaker ngắt mạch và gọi *Laravel PHP Fallback Engine* để sinh gợi ý dự phòng.

• Cơ chế Bất đồng bộ (Asynchronous Queue Processing):

- Áp dụng cho các bài toán nặng (Dự báo tồn kho 30 ngày, Phân tích gian lận Fraud Detection, Báo cáo ma trận BCG).
- Luồng:
    1. Laravel CronJob / Event đẩy một Job chứa `restaurant_id` và tham số vào Redis Queue.
    2. Python Worker (Background Process) ngắt đọc Job từ Redis Queue.
    3. Python xử lý tính toán chuyên sâu (Pandas / Scikit-Learn) rồi ghi trực tiếp kết quả báo cáo/cảnh báo vào MySQL Database.
    4. Laravel Horizon ghi nhận hoàn thành tác vụ và hiển thị báo cáo phân tích trực quan lên Dashboard của Chủ nhà hàng.

________________________________________

1. Thành phần hệ thống chi tiết

Hệ thống Aventura được thiết kế theo kiến trúc tách biệt giữa giao diện, nghiệp vụ và phân tích dữ liệu.

5.1 Các thành phần lõi

•    Frontend Vue.js: Xây dựng dưới dạng Single Page Application (SPA) để tối ưu trải nghiệm người dùng, giảm tải việc tải lại trang và giao tiếp hoàn toàn qua REST API.

•    Backend Laravel API: Đóng vai trò là trung tâm xử lý logic nghiệp vụ, quản lý xác thực (JWT/Sanctum), và kết nối các dịch vụ khác.

•    Python Analytics Service: Hoạt động như một microservice chuyên biệt nhằm xử lý các bài toán nặng về dữ liệu như dự báo tồn kho, phân tích doanh thu và phát hiện gian lận.

5.2 Tầng dữ liệu và Lưu trữ

•    MySQL Database: Lưu trữ dữ liệu chính theo mô hình Multi-tenant, sử dụng restaurant_id để phân tách logic dữ liệu giữa các nhà hàng.

•    Redis Cache & Queue: * Cache: Lưu trữ menu, cấu hình nhà hàng và dashboard để giảm tải cho MySQL.

o    Queue: Xử lý các tác vụ nền như gửi email, tạo báo cáo hoặc đẩy dữ liệu Audit Log để không làm chậm API.

•    Cloud Storage: Sử dụng S3, R2 hoặc MinIO để lưu trữ hình ảnh món ăn và hóa đơn, giúp hệ thống dễ dàng mở rộng dung lượng mà không phụ thuộc vào server vật lý.

5.3 Hạ tầng bổ trợ

•    WebSocket Server: Sử dụng Laravel Reverb hoặc Socket.IO để cập nhật trạng thái đơn hàng thời gian thực giữa Bếp, Thu ngân và Khách hàng.

•    Logging & Monitoring System: Ghi lại nhật ký truy cập, lỗi hệ thống và Audit Log chi tiết để truy vết hành vi người dùng và giám sát hiệu suất server (CPU/RAM).

________________________________________

1. Kiến trúc phân quyền (Authorization)

Hệ thống kết hợp giữa vai trò (Role) và quyền hạn chi tiết (Permission) để đảm bảo tính bảo mật và linh hoạt.

6.1 Mô hình RBAC (Role-Based Access Control)

Hệ thống định nghĩa các nhóm quyền mặc định dựa trên chức danh công việc trong nhà hàng:

Vai trò    Phạm vi quản lý và trách nhiệm

Super Admin    Quản lý toàn bộ nền tảng SaaS, gói dịch vụ, và hỗ trợ khách hàng cấp hệ thống.

Restaurant Owner    Có toàn quyền trong một nhà hàng cụ thể: quản lý nhân sự, menu, kho và báo cáo tài chính.

Manager    Điều hành hoạt động hàng ngày, duyệt đơn xin nghỉ, xử lý phản hồi khách hàng và lập bảng tính lương.

Cashier    Tạo đơn hàng, áp dụng mã giảm giá và thực hiện thanh toán cho khách.

Kitchen    Tiếp nhận đơn từ các tầng/bàn và cập nhật trạng thái chế biến món ăn.

Inventory Staff    Quản lý nhập/xuất nguyên liệu và thực hiện các giao dịch kho.

Customer    Truy cập menu qua mã QR tại bàn để xem thông tin món và đặt đồ trực tiếp.

6.2 Permission-based Access (Quyền dựa trên hành động)

Thay vì kiểm tra vai trò trực tiếp trong code, hệ thống kiểm tra các "Permission" cụ thể để dễ dàng tùy chỉnh quyền cho từng cá nhân khi cần thiết.

Ví dụ cấu hình quyền chi tiết:

•    Cashier (Thu ngân):

o    create_order: Tạo mới đơn hàng.

o    payment_order: Thực hiện thủ tục thanh toán.

o    view_order: Xem danh sách đơn hàng trong ca làm việc.

•    Kitchen (Bếp):

o    view_kitchen_order: Xem danh sách món cần chế biến.

o    update_food_status: Chuyển trạng thái từ "Đang làm" sang "Hoàn thành".

•    Manager (Quản lý):

o    manage_staff: Thêm, sửa hoặc quản lý lịch làm việc của nhân viên.

o    manage_salary: Lập bảng lương và tính toán các khoản phạt/thưởng.

o    view_report: Xem báo cáo doanh thu và hiệu suất theo ngày/tuần/tháng.

Lưu ý: Tất cả các hành động nhạy cảm như sửa giá, hủy đơn hoặc sửa dữ liệu kho đều được hệ thống tự động ghi lại vào Audit Log để phục vụ việc tra soát minh bạch sau này.

6.3 Cơ chế phòng thủ đặc biệt trong phân quyền

•    Ngăn chặn tự phê duyệt (Prevent Self-Approval): Để tránh rủi ro lạm quyền hoặc thông đồng nội bộ, hệ thống thiết lập bộ lọc nghiêm ngặt ở tầng Middleware. Nhân viên (kể cả Manager) tuyệt đối không thể tự phê duyệt đơn xin nghỉ phép của chính mình, yêu cầu đổi ca trực của mình, hoặc các đề xuất mua nguyên vật liệu PO/RFP do chính mình tạo ra. Lớp kiểm tra chéo sẽ so sánh ID người yêu cầu và ID người phê duyệt; nếu trùng khớp, hệ thống chặn đứng giao dịch và trả về mã lỗi HTTP 403 Forbidden.

•    Giới hạn truy cập theo ca làm việc (Shift-restricted Access): Để triệt tiêu nguy cơ nhân sự ở nhà vẫn đăng nhập vào hệ thống để tạo đơn khống hoặc xem lén thông tin kinh doanh, các tài khoản nhân sự vận hành (Thu ngân, phục vụ) bị khóa tự động ngoài giờ làm việc. Middleware CheckShiftSchedule sẽ liên tục đối chiếu thời điểm gửi request với lịch trực thực tế trong bảng schedule_assignments; nếu nằm ngoài khung giờ trực, quyền gọi API sẽ lập tức bị vô hiệu hóa.

________________________________________

1. KIẾN TRÚC DỮ LIỆU

Hệ thống Aventura được thiết kế với kiến trúc dữ liệu chú trọng vào khả năng cô lập dữ liệu người dùng, tối ưu hóa truy vấn và khả năng truy vết hành vi, đảm bảo tính bảo mật và minh bạch cao nhất cho mô hình SaaS.

7.1 Mô hình Multi-tenant (Kiến trúc đa người thuê)

Hệ thống triển khai kiến trúc Multi-tenancy theo giải pháp Shared Database - Shared Schema, trong đó toàn bộ các doanh nghiệp dùng chung cơ sở dữ liệu nhưng được cô lập hoàn toàn về mặt logic. Đây là mô hình cân bằng tốt nhất giữa chi phí vận hành hạ tầng và khả năng quản lý tập trung cho hệ thống Aventura.

•    Cơ chế phân tách và cô lập dữ liệu:

  o    Khóa định danh (Tenant Key): Mọi bảng dữ liệu liên quan đến vận hành bắt buộc chứa cột restaurant_id làm khóa ngoại để phân tách dữ liệu giữa các nhà hàng.

  o    Global Scope (Laravel): Hệ thống áp dụng cơ chế lọc tự động ở tầng Model thông qua Eloquent Trait `BelongsToRestaurant` được tích hợp trên toàn bộ các model nghiệp vụ của tenant. Điều này đảm bảo mọi câu lệnh truy vấn (SELECT, UPDATE, DELETE) luôn mặc định kèm theo điều kiện `WHERE restaurant_id = ?` của tài khoản đăng nhập hiện tại, ngăn chặn tuyệt đối nguy cơ rò rỉ dữ liệu giữa các nhà hàng. Nếu người dùng cố tình truy cập chéo ID tài nguyên của tenant khác, hệ thống tự động trả về lỗi 403 hoặc 404. Đối với Super Admin, hệ thống sẽ sử dụng phương thức `withoutGlobalScopes()` để vượt qua bộ lọc tự động này để quản lý vĩ mô.

•    Các thực thể áp dụng định danh:

  o    Quản lý Bán hàng & Sơ đồ bàn: Đơn hàng (orders), chi tiết đơn hàng (order_items), thanh toán (payments). Đặc biệt là hệ thống Khu vực (areas) và Bàn (tables) giúp quản lý trạng thái chỗ ngồi và phục vụ gọi món tại chỗ qua QR.

  o    Quản lý Kho: Nguyên liệu, sản phẩm (products), công thức định lượng (product_recipes) và nhật ký giao dịch kho (inventory_transactions).

  o    Quản lý Nhân sự: Danh sách nhân viên (employees), lịch làm việc (schedules), bảng lương (salaries) và báo cáo vi phạm (violation_reports).

•    Lợi ích và Tối ưu hóa:

  o    An toàn dữ liệu: Cô lập logic giúp các nhà hàng hoạt động như trên các môi trường riêng biệt dù cùng sử dụng một hệ thống backend chung.

  o    Hiệu năng truy vấn: Kết hợp đánh chỉ mục (Composite Index) giữa restaurant_id và các trường tìm kiếm thường xuyên (như status, created_at) giúp tăng tốc độ xử lý từ 40% đến 70% khi dữ liệu phình to.

  o    Quản lý tài nguyên: Dễ dàng kiểm soát hạn mức (Quota) theo gói dịch vụ, ví dụ: Gói Free chỉ được tạo tối đa 10 bàn, gói Pro không giới hạn số lượng khu vực và bàn.

7.2 Hệ thống Logging & Audit (Nhật ký kiểm soát)

Đề cao tính minh bạch và giảm sự phụ thuộc vào tính trung thực của nhân viên, hệ thống xây dựng một tầng giám sát chặt chẽ (`audit_logs`).

•    Đối tượng theo dõi trọng yếu: Hệ thống ghi lại toàn bộ các hành động nhạy cảm có nguy cơ gây thất thoát tài chính:

  o    Quản lý đơn hàng: Ghi vết chi tiết ai là người tạo đơn, ai thực hiện sửa đổi món hoặc hủy đơn hàng.

  o    Quản lý giá & Chiết khấu: Lưu vết các hành động sửa giá món ăn hoặc áp dụng voucher/giảm giá bất thường.

•    Thông tin định danh trong Log: Mỗi bản ghi Audit Log bao gồm đầy đủ các trường dữ liệu:

  o    Chủ thể & Thời gian: User ID, vai trò của người thực hiện và Timestamp chính xác.

  o    Dấu vết thiết bị: Địa chỉ IP của thiết bị thực hiện thao tác nhằm phát hiện các truy cập bất thường.

  o    Dữ liệu cũ & mới: So sánh giá trị trước (`old_values`) và sau (`new_values`) khi thay đổi dưới dạng JSON.

•    Tích hợp AI: Các dữ liệu Log này là nguồn đầu vào quan trọng cho Microservice Python để phân tích và phát hiện các hành vi gian lận (Fraud Detection) tự động.

•    Cơ chế trích xuất dữ liệu tự động (Audit Log Pipeline): Để không ảnh hưởng đến tốc độ phản hồi API bán hàng chính (KPI < 2s), hệ thống triển khai thông qua Laravel Model Observers kết hợp Redis Queue. Model Observer chớp lấy trạng thái cũ qua `$model->getOriginal()` và trạng thái mới qua `$model->getAttributes()`, sau đó đẩy bất đồng bộ vào hàng đợi Redis để xử lý ghi log nền.

7.3 Danh mục thực thể chính (Database Schema) & Quản lý trạng thái

Hệ thống quản lý bộ cơ sở dữ liệu được chuẩn hóa theo các nhóm thực thể chính:

•    Nhóm Hệ thống & Phân quyền: `users`, `roles`, `permissions`, `restaurants`, `restaurant_branches`.

•    Nhóm Vận hành Nhà hàng: `employees`, `products`, `product_recipes` (định lượng món ăn).

•    Nhóm Kho vận: `inventories`, `inventory_transactions`.

•    Nhóm Giao dịch: `orders`, `order_items`, `payments`.

•    Nhóm Nhân sự & Quản trị: `schedules` (lịch làm), `salaries` (lương), `customer_feedback`, `violation_reports` (báo cáo vi phạm), `audit_logs`.

•    Quản lý trạng thái dữ liệu (State Management):

  o    Đơn hàng: `pending` (chờ), `confirmed` (xác nhận), `preparing` (đang làm), `completed` (hoàn thành), `cancelled` (đã hủy).

  o    Tài khoản Doanh nghiệp (Tenant): `Active` (hoạt động), `Expired` (hết hạn), `Suspended` (khóa do vi phạm hoặc nợ cước).

7.4 Cơ chế nhất quán, an toàn & Xử lý đồng thời (Data Safety & Concurrency)

Để đáp ứng khối lượng giao dịch lớn trong giờ cao điểm mà vẫn giữ tính toàn vẹn dữ liệu:

•    Tính nguyên tử (Atomicity & DB Transactions): Sử dụng `DB::transaction` để bọc toàn bộ luồng thanh toán và kho bãi. Ví dụ: Khi tạo đơn hàng, các thao tác: Cập nhật đơn hàng `paid` -> Khấu trừ kho vật lý -> Ghi nhật ký giao dịch kho bắt buộc phải cùng thành công hoặc cùng thất bại (rollback) để tránh lệch số liệu dòng tiền và hàng hóa.

•    Xử lý tranh chấp đồng thời (Concurrency & Pessimistic Locking): Để ngăn chặn Race Condition khi hàng nghìn đơn hàng thanh toán cùng lúc, hệ thống áp dụng Khóa bi quan (`lockForUpdate` hoặc `sharedLock`) trên bảng `inventories`. Khi một tiến trình đang thực hiện trừ kho cho đơn hàng, bản ghi nguyên vật liệu tương ứng bị khóa cho đến khi transaction kết thúc, ngăn chặn tuyệt đối việc 2 nhân viên cùng trừ kho làm số lượng tồn bị âm hoặc sai lệch.

•    Quản lý Hạn ngạch & Dọn rác (Storage Quotas & Garbage Collector): Dung lượng tệp tin tải lên (ảnh món ăn, hóa đơn) được kiểm soát bởi `TenantStorageAndQuotaManager` theo gói thuê bao (gói Free tối đa 500MB, gói Pro tối đa 10GB). Background command chạy hàng giờ đóng vai trò Garbage Collector quét và xóa bỏ các tệp tin mồ côi.

•    Lưu trữ đơn hàng cũ & Sao lưu (Archiving & Backup): Định kỳ 2 giờ sáng hằng ngày, lệnh nén tự động `php artisan orders:archive` di chuyển toàn bộ đơn hàng và audit logs cũ hơn 6 tháng sang Cloud Storage (S3/MinIO), giải phóng dung lượng cho MySQL chính nhưng vẫn hỗ trợ khôi phục khi đối soát thuế. CSDL cũng được tự động sao lưu hàng ngày lên MinIO để phục vụ khôi phục thảm họa (Disaster Recovery).

7.5 Tối ưu hóa hiệu năng & Chiến lược lưu trữ đệm (Performance & Caching Strategy)

Để đạt KPI phản hồi API < 2s với lượng dữ liệu phình to:

•    Đánh Index chiến lược: Thực hiện đánh chỉ mục (Composite Index) cho các mục cốt yếu và cột thường xuyên dùng để lọc (`restaurant_id`, `status`, các khóa ngoại), giúp tăng tốc độ truy vấn từ 40% đến 70%.

•    Truy vấn thông minh & Tối ưu dữ liệu:

  o    Áp dụng Eager Loading (`Order::with('user')`) giải quyết triệt để vấn đề truy vấn N+1.

  o    Chỉ SELECT các cột cần thiết thay vì `SELECT *` để tiết kiệm băng thông và bộ nhớ.

  o    Áp dụng Soft Delete (đánh dấu ẩn dữ liệu thay vì xóa cứng ở các mắt xích quan trọng) để đảm bảo toàn vẹn dữ liệu lịch sử.

•    Tầng lưu trữ đệm (Redis Cache): Lưu trữ các dữ liệu ít thay đổi nhưng tần suất truy cập cao (thực đơn món ăn, cấu hình nhà hàng, dashboard doanh thu) trên RAM để giảm tải tối đa cho MySQL.

•    Phân tách lưu trữ file (Cloud Storage): Toàn bộ ảnh món ăn và hóa đơn vật lý được lưu trữ độc lập trên Cloud Storage (S3, R2, MinIO), giúp hệ thống dễ dàng mở rộng và giảm dung lượng sao lưu CSDL.

1. Các tác nhân

Hệ thống phân chia rõ rệt 5 tầng tác nhân để đảm bảo tính chuyên môn hóa:

•    Super Admin: Quản lý toàn bộ nền tảng SaaS, các gói dịch vụ (Free/Pro) và các doanh nghiệp (tenant).

•    Chủ nhà hàng (Owner): Có toàn quyền trong một nhà hàng, tập trung vào báo cáo lợi nhuận và chiến lược.

•    Quản lý (Manager): Điều hành hàng ngày, duyệt lịch làm việc, xử lý phản hồi và báo cáo sai phạm.

•    Nhân viên (Thu ngân, Bếp, Kho): Mỗi vị trí có giao diện và quyền hạn riêng để phối hợp nhịp nhàng theo Workflow.

•    Khách hàng: Có thể quét mã QR tại bàn để xem thực đơn và đặt món trực tiếp.

Các tác nhân chính trong hệ thống:

-Tầng 1: Super Admin (Chủ hệ thống SaaS)

 => Người quản lý toàn bộ nền tảng vĩ mô

 Quyền và chức năng vận hành nâng cao:

- Quản lý doanh nghiệp & Vòng đời thuê bao (Active, Expired, Suspended).

- Giả danh hỗ trợ kỹ thuật (Impersonation): Cho phép Super Admin truy cập giao diện dưới quyền Owner để hỗ trợ gỡ lỗi trực tiếp mà không cần mật khẩu, lưu log kiểm toán chặt chẽ để tránh lạm quyền.

- Thiết lập hạn mức tài nguyên (Plans & Quota Builder): Cấu hình giới hạn bàn, nhân sự, và dung lượng cloud storage cho từng gói (Free/Pro/Custom).

- Dự báo rời bỏ dịch vụ (AI Churn Forecast): Tích hợp Python Microservice phân tích rủi ro Churn và tự động gửi email chiến dịch tiếp thị chăm sóc khách hàng.

- Quản lý SLA & Tickets hỗ trợ: Tiếp nhận yêu cầu kỹ thuật, tự động leo thang (escalate) thẻ quá hạn xử lý của khách hàng Enterprise/VIP qua Artisan task.

- Quản lý sao lưu & Bảo trì (Backup & GC): Thực hiện sao lưu CSDL nén lên MinIO và dọn dẹp bộ nhớ đệm, tệp mồ côi (Garbage Collector).

- Quản lý bảo mật hệ thống: Quản lý WAF/Firewall (bật/tắt bảo trì, whitelist IP của máy POS hoặc block IP Brute Force).

- Tầng 2: Chủ nhà hàng (Restaurant Owner)

 => Khách hàng chính: tập trung phát triển các báo cáo lợi nhuận,cần tích hợp các tự động tính toán các dữ liệu của hệ thống để chủ nhà hàng có cơ sở kịp thời đưa ra các quyết định trọng yếu. Đề cao tính chính xác và tối ưu các thông tin chính

 Quyền và chức năng vận hành nâng cao:

- Quản lý cấu hình chuỗi chi nhánh và luân chuyển kho nội bộ.

- Quản lý phê duyệt chéo (Approvals): Duyệt lệnh mua PO lớn, chốt ca âm két, sửa ca trực của cấp dưới.

- Quản lý thiết bị POS đăng ký sử dụng trong quán.

- Xem báo cáo tài chính (Profit & Loss), báo cáo ma trận thực đơn BCG, phân tích không gian (Order Heatmap) và tính điểm NPS.

- Giải phóng hoặc mở tranh chấp tài khoản ký quỹ B2B Escrow với nhà cung cấp.

  - Tầng 3: Nhân sự trong nhà hàng & Shipper

 => Được phân tách chuyên môn hóa theo ca trực và quyền hạn:

- 1. Quản lý (Manager): cánh tay phải của chủ và là mắt xích chủ chốt của nhà hàng, đảm nhận việc xử lý các vấn đề nhỏ và trung trong nhà hàng,

 • Quản lý chấm công GPS & Webcam, duyệt đơn nghỉ phép của nhân viên.

 • Nhận báo cáo hao hụt, rác thải thực phẩm của Bếp.

 • Xếp lịch làm việc hàng tuần, lập bảng lương nháp.

 • Giải quyết các báo cáo vi phạm nội bộ và phản hồi tiêu cực của khách.

- 1. Nhân viên thu ngân / phục vụ / order:

 • Gọi món trực tiếp trên máy POS/Tablet (chặn thay đổi khi đã gửi xuống bếp).

 • Thu ngân thực hiện thanh toán hóa đơn, áp mã giảm giá (Voucher), tách hóa đơn ra bàn trống (bị kiểm soát phạt âm két và thông báo realtime cho Owner).

 • Thực hiện Check-in/Check-out GPS khớp Webcam.

 • Sử dụng Self-service Portal để đăng ký ca, xin đổi ca (Shift Swap) và khiếu nại ẩn danh.

- 1. Bếp (Kitchen):

 • Giao diện tối giản chia làm 2 màn hình (Chưa làm - Đã hoàn thành) tự động đếm thời gian thực từ lúc vào đơn.

 • Chuyển trạng thái đồ ăn (Đang làm -> Đã đi đồ).

 • Báo tạm dừng món ăn hoặc báo hết nguyên liệu (BOM) để tự động khóa nút đặt món trên thực đơn QR.

- 1. Nhân viên giao hàng (Shipper):

 • Sử dụng ứng dụng Shipper PWA trên điện thoại để cập nhật vị trí GPS thời gian thực (`updateLocation`).

 • Nhận tối ưu hóa tuyến đường giao hàng (Route Optimization) gom đơn theo lô (Batches) và xác nhận trạng thái đơn hàng khi giao thành công.

-Tầng 4: Nhà cung cấp (Suppliers)

 => Đối tác liên kết chuỗi cung ứng ngoài:

- Truy cập giao diện riêng (Supplier Portal) cấu hình danh mục và niêm yết bảng giá vật tư thời gian thực.

- Tiếp nhận đơn đặt hàng PO từ nhà hàng thông qua tín hiệu WebSocket của Laravel Reverb.

- Đấu thầu báo giá RFP (Request for Proposal) các gói nguyên liệu định kỳ của nhà hàng.

- Tải lên ảnh hóa đơn giao hàng để đối soát OCR tự động.

- Nhận thanh toán qua cổng ký quỹ B2B Escrow (đóng băng tiền nếu có sai lệch hóa đơn thực tế giao nhận > 20% và chờ Owner xác nhận giải phóng).

-Tầng5: Khách hàng của quán

 => Người dùng trực tiếp đặt món tại chỗ hoặc trực tuyến:

- Đặt món tại bàn: Quét mã QR động dán tại bàn để gửi yêu cầu giỏ hàng đệm (`waiting_verification`). Theo dõi tiến độ món ăn qua Laravel Echo và yêu cầu gọi nhân viên/thanh toán trực tuyến.

- Đặt món trực tuyến: Truy cập storefront đặt món, nhận phí vận chuyển tự động, thanh toán quét QR động (Sepay/VietQR/VNPay/Momo) và theo dõi trạng thái giao hàng.

- Cổng Loyalty Portal: Xem ví Coupon, hạng thẻ thành viên (Silver, Gold, Diamond) và tự đổi thưởng bằng điểm tích lũy.

- Gửi phản hồi (Feedback) và chấm điểm cảm xúc Lexicon tự động.

1. Bảo mật

9.1 Authentication (JWT / Sanctum)

Đây là bước xác thực để trả lời câu hỏi: "Bạn là ai?".

•    Sanctum: Là công cụ mặc định và rất nhẹ nhàng của Laravel. Nó cấp cho bạn một "tấm thẻ" (token) sau khi đăng nhập. Mỗi lần bạn muốn lấy dữ liệu (như xem danh sách bàn), bạn chỉ cần trình tấm thẻ này ra.

•    Tại sao dùng? Vì hệ thống của bạn tách rời Backend (Laravel) và Frontend (Vue.js), Sanctum giúp hai bên nhận diện nhau mà không cần dùng đến Session truyền thống vốn rất phức tạp cho API.

9.2 Authorization (RBAC)

Đây là bước phân quyền để trả lời  câu hỏi: "Bạn là ai, và bạn có quyền làm gì?".

•    RBAC (Role-Based Access Control): Bạn chia người dùng vào các nhóm như: Super Admin, Chủ quán, Thu ngân, Bếp.

•    Nguyên tắc thiết kế hệ thống: Aventura áp dụng mô hình phân quyền hướng hành động (Permission-based Access). Toàn bộ logic nghiệp vụ ở cả Backend (Laravel API) và Frontend (Vue.js SPA) đều được rào chắn dựa trên các Permission tĩnh (như create_order, payment_order, view_report). Hệ thống hoàn toàn không hard-code tên vai trò (Role) trong mã nguồn, cho phép Chủ nhà hàng tùy biến hoặc nâng cấp, tước quyền của bất kỳ nhân sự nào một cách linh hoạt mà không cần can thiệp vào mã nguồn.

•    Cách thức: Thay vì kiểm tra trực tiếp chức vụ, hệ thống kiểm tra "Quyền" (Permission).

o    Ví dụ: Cả Quản lý và Chủ quán đều có quyền view_report, nhưng chỉ Chủ quán mới có quyền delete_restaurant.

9.3 Chống SQL Injection

SQL Injection là khi kẻ xấu cố tình nhập các đoạn mã database vào ô tìm kiếm hoặc đăng nhập để "lừa" hệ thống thực thi lệnh ngoài ý muốn.

•    Cách Laravel bảo vệ bạn: Khi bạn dùng Eloquent ORM (ví dụ: User::where('id', $id)->first()), Laravel đã tự động "lọc" sạch dữ liệu trước khi gửi vào database. Bạn gần như không phải làm gì thêm trừ khi bạn viết các câu lệnh SQL thuần (Raw Query).

9.4 Chống XSS (Cross-Site Scripting)

XSS xảy ra khi ai đó nhập mã JavaScript vào một ô nhập liệu (ví dụ: tên món ăn), và đoạn mã đó được thực thi trên máy của người dùng khác khi họ xem món đó.

•    Cách bảo vệ: * Vue.js: Tự động mã hóa các dữ liệu bạn hiển thị ra màn hình.

o    Laravel: Khi bạn trả về JSON từ API, các ký tự đặc biệt đã được xử lý an toàn.

9.5 Chống CSRF (Cross-Site Request Forgery)

Đây là kiểu tấn công "mượn tay". Kẻ xấu lừa bạn bấm vào một link lạ khi bạn đang đăng nhập vào Aventura, và link đó tự động gửi một lệnh (như "Xóa nhân viên") đến server.

•    Lưu ý cho sinh viên: Vì bạn dùng API (Sanctum/JWT), bạn thường sẽ không cần dùng CSRF Token truyền thống giống như các trang web load lại trang (MPA). Laravel Sanctum có cơ chế bảo vệ riêng cho SPA rất an toàn.

9.6 Rate Limiting

Đây là cơ chế "giới hạn tốc độ".

•    Mục đích: Ngăn chặn kẻ xấu dùng phần mềm tự động để thử mật khẩu hàng nghìn lần/giây hoặc spam tạo đơn hàng làm sập server.

•    Ví dụ: Bạn có thể cài đặt: "Mỗi địa chỉ IP chỉ được thử đăng nhập tối đa 5 lần trong 1 phút".

9.7 Logging hành vi bất thường

Hệ thống ghi lại nhật ký (Log) để truy vết.

•    Audit Log: Tài liệu của bạn nhấn mạnh việc ghi lại ai đã sửa giá, ai đã hủy đơn, vào lúc nào, từ IP nào. Điều này giúp chủ quán kiểm soát tính trung thực của nhân viên mà không cần có mặt tại quán.

9.8 Mã hóa dữ liệu nhạy cảm

Dữ liệu nhạy cảm nhất chính là Mật khẩu.

•    Nguyên tắc: Tuyệt đối không bao giờ lưu mật khẩu dưới dạng chữ rõ (plain text) vào database.

•    Cách làm: Laravel tự động dùng thuật toán Bcrypt để băm (hash) mật khẩu. Nếu database có bị lộ, kẻ xấu cũng không thể biết mật khẩu thật là gì.

=>Vì đang làm mô hình Multi-tenant, bảo mật quan trọng nhất mà bạn cần thực hiện tốt trong code là Global Scope. Hãy đảm bảo rằng mọi câu lệnh SELECT luôn có điều kiện WHERE restaurant_id = ... để nhà hàng A không bao giờ thấy thực đơn hay doanh thu của nhà hàng B.

9.9 Bảo mật hai lớp (2FA Challenges)

Hệ thống tích hợp xác thực hai bước (2FA) sử dụng Google Authenticator cho tài khoản quản trị và chủ nhà hàng. Khóa bí mật (Secret Keys) được mã hóa an toàn trong CSDL. Khi bật 2FA, mọi phiên đăng nhập mới bắt buộc phải điền đúng mã OTP 6 số thời gian thực. Hệ thống cung cấp sẵn 8 mã khôi phục dự phòng (Recovery Codes) dùng một lần đề phòng mất thiết bị.

9.10 Xác nhận mật khẩu nhạy cảm (Confirm Password)

Để phòng chống tấn công chiếm quyền điều khiển phiên (Session Hijacking) khi người dùng rời máy, hệ thống áp dụng bộ lọc yêu cầu xác nhận mật khẩu. Các thao tác nhạy cảm (như thay đổi email, bật/tắt 2FA, xem recovery codes) bắt buộc phải nhập lại mật khẩu hiện tại thông qua middleware `password.confirm`.

9.11 Chống tấn công dò mật khẩu (Brute Force Protection)

Tích hợp cơ chế Account Locking trong luồng Login của Laravel Fortify. Tài khoản sẽ tự động bị khóa tạm thời trong vòng 15 phút nếu nhập sai mật khẩu liên tiếp 5 lần để ngăn bot dò quét mật khẩu.

9.12 Chống Fake GPS khi chấm công

Phân hệ chấm công GPS của nhân viên trên Mobile Webapp được bảo vệ bằng cơ chế chặn Mock GPS. Hệ thống kiểm tra trực tiếp cờ `is_mock=true` do thiết bị trả về và so sánh độ chính xác tọa độ (`accuracy`); nếu vi phạm, lệnh Check-in/Check-out sẽ bị từ chối.

9.13 Xác thực chấm công bằng hình ảnh Webcam

Nhân viên bắt buộc chụp hình selfie trực tiếp qua camera của quầy POS khi bấm chấm công. Tệp ảnh được chuyển đổi thành chuỗi Base64 và đẩy lên server để lưu vết kiểm toán, phòng chống triệt để tình trạng chấm công hộ.

9.14 Nhật ký hành vi CDP tuân thủ quyền riêng tư

Nhật ký hành vi khách hàng (CDP Logs) thu thập dữ liệu giỏ hàng, view món ăn qua QR Code được mã hóa ẩn danh hoàn toàn (GDPR Compliant), đồng thời chặn truy cập từ xa trái phép bằng cơ chế CORS.

 1. Chi tiết luồng nghiệp vụ hệ thống (Workflow)

Sơ đồ Workflow đặt món và xử lý đơn hàng

Plaintext

Khách/Thu ngân tạo đơn [cite: 306]

         ↓

[Kiểm tra & Giữ kho tạm thời] (Ghi bản ghi vào bảng đệm inventory_reservations) [cite: 308, 326]

         ↓

Bếp nhận order Realtime [cite: 310]

         ↓

Bếp hoàn thành món [cite: 312]

         ↓

Thu ngân bấm Thanh toán (Kích hoạt Database Transaction) [cite: 314]

         ↓

┌───────────────────────────────────────────┴───────────────────────────────────────────┐

▼ (Nhánh 1: Đồng bộ - Xử lý ngay lập tức)               ▼ (Nhánh 2: Bất đồng bộ - Đẩy vào Redis Queue) [cite: 317]

- Chuyển trạng thái bàn sang "Trống" [cite: 319]                    - Ghi lịch sử Audit Log chi tiết (Ai sửa, ai hủy) [cite: 318]

- Cắt/Trừ kho vật lý lõi (quantity_on_hand) [cite: 318]             - Tạo file hóa đơn PDF/Excel, tự động gửi Email [cite: 319]

- Ghi Nhật ký giao dịch kho (inventory_transactions)  - Đẩy dữ liệu qua FastAPI cho Python Analytics [cite: 321]

- Ghi nhận doanh thu tổng vào MySQL [cite: 320]                                         ↓

                                                                Python cập nhật Dashboard Realtime [cite: 323]

Hệ thống vận hành dựa trên kiến trúc hướng sự kiện (Event-Driven Architecture), phân tách nghiêm ngặt giữa luồng nghiệp vụ cốt lõi (Đồng bộ) và các tác vụ hậu cần nặng (Bất đồng bộ). Sự kết hợp này đáp ứng tiêu chí xử lý mượt mà trong giờ cao điểm với phản hồi API < 2s.

+1

________________________________________

Các bước xử lý chi tiết trong Luồng nghiệp vụ

1. Khách / Thu ngân tạo đơn và Giữ kho tạm thời

•    Thực hiện: Khách quét mã QR tại bàn để tự gọi món hoặc Thu ngân thao tác trực tiếp trên màn hình máy POS.

•    Kỹ thuật (Đồng bộ): Ứng dụng Vue.js gửi một HTTP Request (POST) chứa thông tin món ăn và số lượng lên Laravel API.

•    Xử lý giữ kho đệm: Laravel lập tức đối chiếu bảng công thức định lượng (product_recipes). Để tránh xung đột khóa và hiện tượng Deadlock trực tiếp trên bảng vật lý lõi, hệ thống kiểm tra và ghi nhận số lượng giữ chỗ vào bảng đệm inventory_reservations với trạng thái holding và thời gian hết hạn (TTL). Nếu lượng nguyên liệu trong kho không đủ đáp ứng, hệ thống lập tức chặn lại và bắn lỗi trả về giao diện Vue.js để nhân viên hoặc khách dừng phục vụ món đó, triệt tiêu hoàn toàn nguy cơ kho bị âm (Over-selling).

+1

•    Bảo mật: Laravel kiểm tra quyền hạn chi tiết (Permission) của tài khoản thực hiện thao tác và xác thực tính hợp lệ của dữ liệu đầu vào.

1. Bếp nhận order thời gian thực (Realtime)

•    Cơ chế: Ngay khi Laravel xác thực kho đủ và lưu đơn hàng thành công vào database MySQL với trạng thái pending, một Event hệ thống sẽ lập tức được kích hoạt.

•    Kỹ thuật: Thông qua hệ sinh thái Laravel Reverb (WebSocket Server bảo mật ở backend) và Laravel Echo ở frontend, tín hiệu được đẩy thẳng xuống giao diện của nhân viên Bếp.

•    Trải nghiệm: Màn hình Bếp tự động hiển thị món ăn mới với đầy đủ thông tin (tên món, số lượng, số bàn/tầng, thời gian vào đơn) thời gian thực mà không cần nhân viên phải nhấn F5 để tải lại trang.

1. Bếp chế biến và Hoàn thành món

•    Thao tác: Nhân viên bếp thao tác trên màn hình chuyên dụng, nhấn chuyển trạng thái món ăn từ "Chưa làm" sang "Hoàn thành".

•    Kỹ thuật: Trạng thái đơn hàng trong database được cập nhật từ preparing (đang làm) sang completed (hoàn thành).

•    Thông báo: Một tín hiệu realtime hướng sự kiện khác lại được gửi ngược về cho Thu ngân hoặc hiển thị trực tiếp trên thiết bị di động của Khách để thông báo món ăn đã sẵn sàng đi đồ.

1. Thu ngân thực hiện thủ tục thanh toán và Trừ kho lõi

•    Thao tác: Thu ngân kiểm tra lại hóa đơn, áp dụng mã giảm giá, voucher (nếu có) và nhấn nút "Thanh toán".

•    Kỹ thuật (Vòng đời Database Transaction): Laravel bắt buộc phải bọc toàn bộ luồng này trong DB::transaction để đảm bảo tính nguyên tử (Atomicity) và tính nhất quán tuyệt đối của dữ liệu:

o    Hóa đơn thanh toán được khởi tạo, trạng thái thanh toán chuyển thành paid.

o    Doanh thu thực tế được ghi nhận vào hệ thống và trạng thái bàn ăn tự động chuyển về lại trạng thái "Trống" (available).

o    Cập nhật giảm số lượng kho vật lý lõi (quantity_on_hand) tại bảng inventories, đồng thời chuyển trạng thái bản ghi tương ứng trong bảng đệm inventory_reservations từ holding sang committed.

o    Khởi tạo bản ghi Nhật ký giao dịch kho (inventory_transactions): Tiến trình này được thực hiện đồng bộ ngay tại bước thanh toán để đảm bảo mọi biến động kho vật lý luôn có lịch sử giao dịch đối ứng đi kèm, ngăn chặn rủi ro mất dấu dữ liệu kiểm toán.

o    Nếu xảy ra bất kỳ sự cố phát sinh nào (lỗi cổng thanh toán, mất mạng, lỗi DB), toàn bộ quá trình trên sẽ được Rollback để tránh sai lệch dòng tiền và số liệu.

1. Kích hoạt Pipeline xử lý nền bất đồng bộ (Redis Queue)

Sau khi bước 4 (Database Transaction) hoàn tất thành công, API bán hàng chính thức được giải phóng để tiếp tục phục vụ các đơn hàng khác. Laravel lập tức đẩy các Job tác vụ nặng vào hệ thống hàng đợi ngầm (Redis Queue) do Laravel Horizon giám sát để xử lý ngầm:

+2

•    Bước 5.1 - Tính toán giá vốn: Job ngầm tính toán lại giá vốn trung bình (average_cost) của nguyên liệu dựa trên các giao dịch kho vật lý vừa được ghi nhận.

•    Bước 5.2 - Trích xuất Audit Log an toàn: Model Observer kích hoạt đồng bộ ngay trước đó để bóc tách mảng dữ liệu thay đổi cũ ($model->getOriginal()) và dữ liệu mới ($model->getAttributes()) tại tầng API nay sẽ được parse thành chuỗi JSON và lưu vào bảng audit_logs thông qua Queue để không làm chậm luồng bán hàng chính.

•    Bước 5.3 - Hậu cần: Tự động tạo tệp hóa đơn định dạng PDF/Excel, kích hoạt gửi Email biên lai thanh toán cho khách hàng và chủ nhà hàng.

+1

1. Python Microservice phân tích dữ liệu và Cập nhật báo cáo nâng cao

•    Giao tiếp: Queue Worker của Laravel đóng gói toàn bộ dữ liệu đơn hàng và lịch sử biến động kho sạch, phát một HTTP Request bất đồng bộ sang Python Microservice thông qua cầu nối API tốc độ cao của FastAPI.

•    Xử lý: Python nhận dữ liệu, sử dụng các thư viện chuyên dụng (Pandas, Scikit-learn) để tính toán lại các chỉ số lợi nhuận, chạy mô hình dự báo lượng nguyên liệu cần nhập, so sánh sai số giữa "Tồn kho lý thuyết" và "Tồn kho thực tế", đồng thời kiểm tra thuật toán phát hiện gian lận (Fraud Detection) nếu có hành vi sửa/xóa đơn bất thường.

•    Kết quả: Sau khi phân tích, Python gửi kết quả trả về, các biểu đồ thống kê nâng cao trên giao diện Dashboard của Chủ nhà hàng (Owner) và Quản lý (Manager) sẽ tự động cập nhật số liệu mới nhất để phục vụ ra quyết định kinh doanh kịp thời từ xa.

________________________________________11. Database chính

Chiến lược tối ưu hóa bổ sung:

•    Eager Loading: Luôn sử dụng with() (ví dụ: Order::with('order_items')) để triệt tiêu lỗi Query N+1.

•    Chỉ chọn field cần thiết: Sử dụng SELECT id, name... thay vì SELECT * để tiết kiệm RAM và băng thông.

•    Caching: Sử dụng Redis để cache Menu món ăn và Dashboard báo cáo nhằm giảm tải tối đa cho MySQL.

•    Global Scope: Tự động áp dụng WHERE restaurant_id = ? cho mọi truy vấn để đảm bảo an toàn dữ liệu tuyệt đối giữa các nhà hàng

________________________________________

 1. Tính năng nâng cao

Để tạo sự khác biệt thương mại và tối ưu hóa vận hành toàn diện cho các nhà hàng, nền tảng Aventura triển khai bộ tính năng nâng cao dựa trên sự phối hợp giữa Laravel 12, Vue.js và Python Microservice:

•    · Realtime Order (Đặt món thời gian thực): Tích hợp hệ sinh thái Laravel Reverb (WebSocket) và Laravel Echo. Khi khách hàng quét mã QR tại bàn hoặc nhân viên phục vụ lên đơn, thông tin món ăn được đẩy thẳng xuống màn hình chuyên dụng của Bếp theo thời gian thực dưới dạng dòng chảy sự kiện (Event-Driven) với độ trễ < 500ms mà không cần tải lại trang. ·

•     Offline Mode (Chế độ vận hành ngoại tuyến): Ứng dụng Vue.js SPA kết hợp công nghệ Service Workers và IndexedDB ở Frontend để lưu trữ tạm thời dữ liệu thực đơn, sơ đồ bàn và các đơn hàng đang phục vụ. Khi nhà hàng gặp sự cố mất kết nối Internet, luồng gọi món và in bill tại quầy POS vẫn hoạt động bình thường; dữ liệu giao dịch sẽ tự động đồng bộ ngược lên MySQL trung tâm ngay khi có mạng trở lại. ·

•    Multi-branch (Quản lý đa chi nhánh): Cho phép chủ doanh nghiệp quản lý chuỗi nhiều cơ sở trên cùng một tài khoản Owner. Hệ thống phân tách mạch lạc báo cáo tài chính, danh mục kho bãi và nhân sự theo từng branch_id, đồng thời hỗ trợ luồng luân chuyển nguyên liệu nội bộ giữa các chi nhánh.

•    AI Analytics (Python) (Phân tích dữ liệu bằng AI): Sử dụng FastAPI làm cầu nối để đẩy bất đồng bộ dữ liệu hóa đơn, hành vi người dùng sang Python Microservice. Tại đây, các thư viện Pandas và Scikit-learn sẽ xử lý bảng biểu để bóc tách khung giờ cao điểm, tính toán tỷ lệ biên lợi nhuận thực tế và phân tích nhóm món ăn thường được mua cùng nhau để gợi ý tạo combo tăng doanh thu.

•     Fraud Detection (Phát hiện gian lận thu ngân): Thuật toán AI của Python liên tục quét tầng dữ liệu Audit Log (audit_logs) và lịch sử thanh toán. Hệ thống sẽ tự động gắn cờ cảnh báo (Flagged) và gửi thông báo trực tiếp đến quản lý nếu phát hiện các hành vi bất thường như: Thu ngân liên tục sửa giá món, áp dụng voucher chiết khấu sai quy trình, hoặc thực hiện hủy hóa đơn/tách đơn sau khi khách đã rời bàn.

•    Smart Inventory Prediction (Dự báo kho thông minh): Python phân tích dữ liệu tiêu thụ lịch sử, kết hợp các yếu tố mùa vụ (ngày lễ, cuối tuần, thời tiết) để chạy mô hình dự báo lượng nguyên liệu cần nhập cho tuần kế tiếp. Đồng thời, hệ thống thực hiện đối chiếu giữa "Tồn kho lý thuyết" (hệ thống tự trừ theo công thức định lượng product_recipes) và "Tồn kho thực tế" (nhân viên kiểm kho nhập máy) để chỉ ra sai số hao hụt vật lý do thất thoát.

•    Auto KPI Dashboard (Bảng điều khiển KPI tự động): Tự động tổng hợp và trực quan hóa hiệu suất làm việc của từng nhân sự (tốc độ đi đồ của phục vụ, thời gian hoàn thành món của bếp, số lượng đơn phục vụ thành công trong ca) lên sơ đồ trực quan của Quản lý và Chủ nhà hàng.

•     Auto Salary Calculation (Tự động tính lương): Cuối tháng, hệ thống tự động quét bảng dữ liệu lịch trực (schedule_assignments), chấm công (shift_closings) và tự động cấu hình bảng lương (salaries). Logic tính toán sẽ tự động cộng thưởng chuyên cần và áp dụng các khoản phạt lũy tiến đã được Admin phê duyệt (ví dụ: Thu ngân để âm két ca trực, đầu bếp để hao hụt kho vượt hạn mức cho phép, nhân viên bị báo cáo vi phạm).

•     Auto gửi mail (Python) (Hệ thống tự động gửi báo cáo): Cứ vào lúc 23h59 hằng ngày, một tác vụ lập lịch ngầm (Cronjob) sẽ kích hoạt Python Microservice đóng gói toàn bộ báo cáo tài chính trong ngày (tổng doanh thu, số tiền chuyển khoản chi tiết, chênh lệch khớp ca) và tự động gửi email báo cáo trực tiếp cho Chủ doanh nghiệp.

________________________________________

 1. KPI

Để đảm bảo nền tảng SaaS vận hành mượt mà theo mô hình nhiều nhà hàng cùng sử dụng chung hạ tầng (Shared Database - Shared Schema), hệ thống bắt buộc phải đạt được các chỉ số đo lường hiệu năng cứng sau:

13.1. Hiệu năng hệ thống

· API Response < 2s: 100% các request tải thực đơn, kiểm tra bàn trống, hoặc điều phối dữ liệu nghiệp vụ phải có thời gian phản hồi dưới 2 giây (Thời gian phản hồi lý tưởng trong điều kiện mạng tiêu chuẩn đạt < 1.5 giây nhờ ứng dụng triệt để Redis Caching ). · 1.000+ đơn/ngày: Hạ tầng backend chịu tải và xử lý trơn tru trên 1.000 giao dịch hóa đơn/đơn hàng phát sinh mỗi ngày cho mỗi nhà hàng mà không xảy ra hiện tượng nghẽn mạch dữ liệu hoặc lệch số liệu tài chính. · 100+ nhà hàng cùng hoạt động: Hệ thống đáp ứng năng lực phục vụ đồng thời cho hơn 100 doanh nghiệp (Tenants) vận hành cùng lúc, đảm bảo cơ chế Global Scope cô lập tuyệt đối luồng dữ liệu, nhà hàng A không bao giờ nhìn thấy hoặc can thiệp được vào dữ liệu nhà hàng B. · 10.000+ user đồng thời: Hệ thống xử lý mượt mà khi có từ 10.000 người dùng kết nối và tương tác đồng thời vào hệ thống trong các khung giờ cao điểm (bao gồm cả lượt quét QR gọi món của khách và thao tác bấm máy của nhân viên).

13.2. Độ ổn định

•    Uptime > 99%: Cam kết tỷ lệ hoạt động liên tục của máy chủ đạt trên 99%, giảm thiểu tối đa thời gian chết (Downtown) bằng cách triển khai hệ thống theo dõi lỗi tập trung Sentry để phát hiện và cảnh báo sớm các điểm nghẽn phần cứng.

•    Backup dữ liệu định kỳ: Cấu hình hệ thống tự động sao lưu sao chép (Automated Backup) toàn bộ cơ sở dữ liệu MySQL định kỳ hằng ngày và đồng bộ tệp tin hóa đơn, hình ảnh kho bãi lên Cloud Storage (MinIO/S3) để sẵn sàng kích hoạt phương án khôi phục thảm họa (Disaster Recovery) ngay khi server gặp sự cố vật lý.

 1. Các giao diện cần thiết

Hệ thống thiết kế một nền tảng Frontend Vue.js SPA đồng nhất nhưng áp dụng cơ chế phân quyền dựa trên hành động (Permission-based Access Control). Tùy thuộc vào Token danh tính và vai trò được cấp, người dùng sẽ được điều hướng vào các phân hệ giao diện chuyên biệt:

•    Giao diện 1: Super Admin (Quản trị hệ thống SaaS): Dành riêng cho chủ nền tảng Aventura để theo dõi tổng doanh thu bản quyền, quản lý vòng đời các Tenant, cấu hình hạn mức tài nguyên của các gói dịch vụ (Free/Pro) và tiếp nhận yêu cầu hỗ trợ kỹ thuật (Ticket System).

•    Giao diện 2: Khách của Super Admin (Landing Page & Portal Đăng ký): Hệ thống website mặt tiền giới thiệu tính năng, bảng giá, AI Chatbot tư vấn và form Onboarding tự động để các chủ nhà hàng mới tự đăng ký doanh nghiệp, tự động chạy seeder dữ liệu kho mẫu để dùng thử hệ thống.

•     Giao diện 3: Admin (Chủ doanh nghiệp) / Quản lý nhà hàng: Màn hình Dashboard quản trị tối cao cấp doanh nghiệp. Hiển thị báo cáo doanh thu, lợi nhuận chuyên sâu, module cấu hình menu/định lượng món, quản lý nhập/xuất kho bãi, duyệt đơn xin nghỉ và lập bảng tính lương nhân sự.

•     Giao diện 4: Order / Thu ngân / Bếp (Phân hệ Vận hành trực tiếp tại quán): Giao diện đặc thù có tính kết nối Realtime cực cao:

o    Nhân viên Phục vụ/Order: Sơ đồ bàn trực quan, chọn bàn trống để vào đơn, chọn món và gửi thông báo xuống bếp.

o    Nhân viên Thu ngân: Giao diện POS bán hàng chuyên dụng, hỗ trợ áp voucher, thực hiện thủ tục thanh toán, in hóa đơn và khớp ca chốt két.

o    Nhân viên Bếp: Giao diện tối giản chia làm 2 màn hình (Đơn chưa làm và đơn đã hoàn thành) kèm bộ đếm thời gian thực từ lúc vào đơn để tối ưu tốc độ chế biến.

•    Giao diện 5: Khách của nhà hàng: Giao diện SPA gọn nhẹ hiển thị trực tiếp trên điện thoại của khách hàng sau khi quét mã QR tại bàn. Hỗ trợ xem menu hình ảnh, xem mô tả nguyên liệu/hương vị món ăn, nắm bắt giá cả công khai và ấn đặt đồ trực tiếp không qua trung gian.

•    Giao diện 6: Nhà phân phối (Portal Chuỗi cung ứng): Giao diện dành cho các đối tác cung cấp nguyên vật liệu của nhà hàng để theo dõi các đơn đặt hàng nguyên liệu từ phía bộ phận Kho, cập nhật bảng giá nguyên kho và cập nhật trạng thái giao hàng.

! Các lưu ý kiểm soát bảo mật tầng giao diện:

•    Rào chắn quyền hạn nghiêm ngặt: Các vị trí công việc khác nhau tuy có thể truy cập vào cùng một ứng dụng Frontend, nhưng hệ thống sẽ dựa vào bảng quyền hạn (permissions) của Spatie để ẩn/hiện hoặc cấm quyền click vào các nút chức năng nhạy cảm. Ví dụ: Tài khoản nhân viên Order tuyệt đối không nhìn thấy mục Báo cáo doanh thu hoặc nút bấm "Thanh toán hóa đơn" của Thu ngân.

•     Khóa tài khoản order ngoài ca làm việc: Để triệt tiêu hoàn toàn nguy cơ nhân viên gian lận hoặc kẻ xấu cố tình đăng nhập vào đơn khống làm sai lệch doanh thu thực tế, toàn bộ các tài khoản của nhân viên ca dưới (Order, Thu ngân) sẽ được cấu hình khóa tự động. Hệ thống đối chiếu trực tiếp với bảng lịch làm việc (schedule_assignments); tài khoản chỉ hiển thị nút đăng nhập và khả dụng trong đúng khung giờ của ca trực được phân công, ngoài giờ làm việc hệ thống sẽ tự động vô hiệu hóa quyền truy cập API.

 1. Định hướng phát triển từng phần

15.1. Phát triển Super Admin (SaaS Management Layer)

Đây là tầng quản trị cao nhất của nền tảng Aventura, chịu trách nhiệm quản lý toàn bộ hệ thống và các doanh nghiệp sử dụng nền tảng. Mục tiêu của module này là đảm bảo hệ thống hoạt động ổn định, dễ mở rộng, tối ưu hóa hạ tầng và cô lập dữ liệu tuyệt đối giữa hàng trăm doanh nghiệp.

? Một hệ thống SaaS có nhiều nhà hàng sẽ được quản lý như thế nào? Giải pháp: Hệ thống áp dụng mô hình Multi-tenant (Shared Database - Shared Schema). Tất cả dữ liệu như nhân viên, menu, kho, bàn, đơn hàng và doanh thu sẽ được lưu trữ chung nhưng phân tách logic nghiêm ngặt thông qua định danh restaurant_id. Kết hợp với cơ chế Global Scope trong Laravel, hệ thống tự động lọc dữ liệu theo từng nhà hàng, ngăn chặn tuyệt đối rò rỉ thông tin giữa các bên. Đối với tài khoản Super Admin, hệ thống sẽ ứng dụng phương thức withoutGlobalScopes() để vượt qua bộ lọc tự động này, cho phép đứng từ tầng vĩ mô quản lý toàn bộ các Tenant.

________________________________________

Các phân hệ chức năng chính

1. Quản lý doanh nghiệp và Cấu hình Tài nguyên vĩ mô (Tenant & Quota Management)

Super Admin có quyền kiểm soát toàn diện vòng đời hoạt động của các doanh nghiệp và điều phối tài nguyên máy chủ một cách linh hoạt, ngăn chặn tình trạng một Tenant dùng quá tải làm ảnh hưởng đến các Tenant khác (Hiệu ứng Noisy Neighbor).

•    Khởi tạo và cấp phát không gian: Tạo nhà hàng/doanh nghiệp mới và cấu hình bộ dữ liệu mẫu (Seeder dữ liệu mẫu gồm thực đơn, nguyên liệu kho mẫu) phục vụ luồng Onboarding tự động khi có nhà hàng mới đăng ký dùng thử.

•    Quản lý thông tin pháp lý: Lưu trữ và quản lý thông tin pháp lý, mã số thuế và thông tin liên hệ của từng doanh nghiệp.

•    Điều phối trạng thái tài khoản: Kích hoạt, tạm ngưng hoặc khóa tài khoản doanh nghiệp tùy thuộc vào hành vi vận hành và tình trạng thanh toán gói dịch vụ.

•    Quản lý giới hạn Quota tầng sâu: Áp dụng cơ chế Subscription Plan (Gói dịch vụ) để phân chia hạn mức tài nguyên ngay tại tầng cơ sở dữ liệu kết hợp Rate Limiting:

o    Gói Miễn phí (Free): Giới hạn tối đa 5 nhân viên, 2 khu vực, 10 bàn hoạt động, dung lượng tối đa 500MB Cloud Storage (lưu ảnh món ăn) và không hỗ trợ các tính năng AI nâng cao.

o    Gói Cao cấp (Pro): Không giới hạn số lượng nhân viên/khu vực/bàn, mở khóa tối đa 10GB Cloud Storage, toàn bộ sức mạnh phân tích dữ liệu AI và kiến trúc truyền tải Realtime.

o    Tenant Rate Limiting Middleware: Giới hạn tần suất gửi API request/phút trên từng restaurant_id để bảo vệ tài nguyên MySQL/Redis dùng chung.

1. Quản lý tài khoản và Bảo mật (Identity & Access Management)

Đảm bảo tính toàn vẹn và an toàn thông tin tối cao cho toàn bộ hệ sinh thái quản trị:

•    Quản lý tài khoản tối cao: Giám sát và quản lý danh sách tài khoản của các chủ doanh nghiệp (Tenant Owners).

•    Kiểm soát xác thực: Hỗ trợ đặt lại (Reset) mật khẩu và kiểm soát chặt chẽ cơ chế xác thực hai lớp (2FA) để chống truy cập trái phép.

•    Truy vết SaaS Audit Logs: Ghi lại chi tiết hành vi của chính các tài khoản có quyền Super Admin (ai đã khóa Tenant nào, ai đã thay đổi tham số hệ thống, từ IP nào) nhằm đảm bảo tính minh bạch tối cao, bảo vệ uy tín nền tảng.

? Super Admin có thể can thiệp sâu đến mức nào vào dữ liệu doanh nghiệp? Giải pháp: Để đảm bảo tính minh bạch và bảo mật dữ liệu khách hàng tuyệt đối, hệ thống phân định rõ ranh giới quyền lực của Super Admin. Mặc dù về mặt database, vai trò super_admin nắm giữ quyền lực tối cao, nhưng hệ thống sẽ rào chắn bằng logic Backend và Frontend:

•    Được phép: Khóa các tenant vi phạm chính sách, thực hiện hỗ trợ kỹ thuật cấp cao, cấu hình các tham số hệ thống vĩ mô, cấu hình thời gian sống (TTL) của dữ liệu đệm, và bật/tắt tính năng bảo trì theo vùng (Feature Flagging).

•    Không được phép: Xem chi tiết đơn hàng, can thiệp vào bảng lương nhân sự hoặc xem báo cáo doanh thu chi tiết của từng hóa đơn nội bộ. Super Admin chỉ được phép xem các số liệu trích xuất tổng lượng vĩ mô (như tổng lượng request, tổng dung lượng lưu trữ) để điều phối hạ tầng.

1. Trung tâm xử lý Hóa đơn và Đối soát Tài chính hệ thống (Billing, Webhook & Invoicing)

Phân hệ chịu trách nhiệm tự động hóa toàn bộ quy trình tài chính và luồng tiền bản quyền phần mềm giữa nền tảng Aventura và các doanh nghiệp, đảm bảo dòng tiền minh bạch và giảm thiểu tác vụ thủ công.

•    Tích hợp Webhook cổng thanh toán tự động: Xây dựng endpoint tiếp nhận Webhook từ ngân hàng hoặc cổng thanh toán trực tuyến. Khi Tenant thanh toán nâng cấp/gia hạn, hệ thống tự động kiểm tra payload, khớp mã transaction_code để cập nhật lại trường status và gia hạn trường expired_at ngay trong cơ sở dữ liệu, mở khóa tài nguyên Realtime cho Tenant.

•    Tự động hóa hóa đơn bất đồng bộ (Invoicing): Tự động tạo tệp hóa đơn định dạng PDF/Excel gửi về email của chủ doanh nghiệp khi sắp đến hạn (trước 7 ngày) và ngay sau khi giao dịch thành công. Tác vụ sinh file PDF và gửi email được đẩy vào Redis Queue để xử lý ngầm, tránh gây tắc nghẽn hoặc timeout API Webhook của cổng thanh toán.

•    Hệ thống cảnh báo nợ/hết hạn: Tự động gửi thông báo qua Notification/Email nhắc nhở chủ nhà hàng về thời hạn thanh toán.

•    Xử lý gia hạn thủ công (Manual Override): Công cụ cho phép Super Admin tặng thêm ngày dùng thử (Free Trial), áp dụng mã giảm giá đặc biệt hoặc gia hạn thủ công cho các đối tác chiến lược mà không làm sai lệch logic hệ thống.

? Khi doanh nghiệp hết hạn gói dịch vụ, hệ thống sẽ xử lý như thế nào để vừa giữ chân khách hàng vừa đảm bảo tính thương mại? Giải pháp: Áp dụng bộ trạng thái tài khoản theo 3 mức độ tại trường status của bảng restaurants (đồng bộ với trường status của bảng restaurant_subscriptions để quản lý chu kỳ gói):

•    Active: Nhà hàng hoạt động bình thường, đầy đủ tính năng và tài nguyên theo hạn mức của gói dịch vụ đã mua.

•    Expired: Chuyển sang chế độ "Chỉ đọc" (Read-Only). Doanh nghiệp vẫn có quyền truy cập tra cứu dữ liệu cũ, xuất báo cáo kế toán cũ nhưng hệ thống sẽ chặn toàn bộ quyền tạo đơn mới, thêm bàn, thêm món ăn hoặc thêm nhân viên.

•    Suspended: Khóa toàn bộ quyền truy cập vào hệ thống nếu doanh nghiệp tiếp tục không gia hạn sau một khoảng thời gian quy định (ví dụ: 30 ngày).

Để hiện thực hóa chế độ "Chỉ đọc" (Expired) một cách triệt để và tự động, hệ thống sử dụng một lớp phần mềm trung gian (CheckTenantSubscription Middleware) trong Laravel để tạo rào chắn bộ lọc ở vòng ngoài của mọi request gửi lên API:

PHP

namespace App\Http\Middleware;

use Closure;

use Illuminate\Http\Request;

use App\Models\Restaurant;

class CheckTenantSubscription

{

    public function handle(Request $request, Closure $next)

    {

        // 1. Kiểm tra xem user có thuộc nhà hàng nào không (tránh lỗi cho Super Admin)

        if (!auth()->check() || !auth()->user()->restaurant_id) {

            return $next($request);

        }



        $restaurantId = auth()->user()->restaurant_id;

        $restaurant = Restaurant::find($restaurantId);



        if (!$restaurant) {

            return response()->json(['message' => 'Doanh nghiệp không tồn tại trên hệ thống.'], 404);

        }



        // 2. Nếu trạng thái bị khóa hoàn toàn (Suspended)

        if ($restaurant->status === 'suspended') {

            return response()->json(['message' => 'Tài khoản doanh nghiệp đã bị khóa. Vui lòng liên hệ bộ phận hỗ trợ.'], 403);

        }



        // 3. Nếu trạng thái hết hạn (Expired) -> Kích hoạt chế độ Read-Only

        // Chỉ cho phép các request đọc dữ liệu (GET), chặn tất cả các tác vụ thay đổi dữ liệu (POST, PUT, DELETE)

        if ($restaurant->status === 'expired' && !$request->isMethod('GET')) {

            return response()->json(['message' => 'Gói dịch vụ đã hết hạn. Vui lòng gia hạn để tiếp tục sử dụng tính năng này.'], 402);

        }



        return $next($request);

    }

}

1. Dashboard phân tích dữ liệu tổng quan nền tảng (SaaS Analytics & Insights)

Trung tâm dữ liệu vĩ mô của Super Admin, cung cấp cái nhìn toàn diện về sức khỏe tài chính, tốc độ tăng trưởng khách hàng và hiệu suất kinh doanh của toàn bộ nền tảng SaaS.

•    Thống kê chỉ số tài chính SaaS: Theo dõi trực quan các chỉ số sinh tồn của mô hình SaaS bao gồm: MRR (Doanh thu định kỳ hàng tháng), ARR (Doanh thu định kỳ hàng năm), và Churn Rate (Tỷ lệ khách hàng hủy dịch vụ).

•    Biểu đồ tăng trưởng Tenant: Hiển thị biểu đồ trực quan về số lượng nhà hàng đăng ký mới, tỷ lệ chuyển đổi từ gói Free sang gói Pro theo dòng thời gian.

•    Phân tích hành vi sử dụng tài nguyên vĩ mô: Thống kê các nhà hàng có lượng đơn hàng/request cao nhất hoặc tiêu tốn nhiều dung lượng lưu trữ Cloud để Super Admin có kế hoạch điều phối và nâng cấp hạ tầng server hợp lý.

1. Giám sát hạ tầng, Xử lý hàng đợi nghẽn và Hệ thống hỗ trợ (DevOps & Support Portal)

Module bảo đảm tính sẵn sàng cao (High Availability) của toàn hệ thống Aventura, giúp phát hiện sớm các điểm nghẽn kỹ thuật và tiếp nhận, xử lý khiếu nại từ phía nhà hàng một cách chuyên nghiệp.

•    Giám sát hiệu năng trực quan (Laravel Pulse & Horizon): Tích hợp giao diện giám sát Redis Queue thời gian thực và log lỗi tập trung (Bug tracking). Cho phép Super Admin phát hiện các Job xử lý ngầm bị lỗi hoặc bị chậm (như Job đồng bộ dữ liệu sang Python Microservice, Job gửi email biên lai) để thực hiện kích hoạt lại (retry queue) hoặc tăng thêm số lượng Worker thông qua Supervisor.

•    Hệ thống cảnh báo tự động: Khi phát hiện tỷ lệ lỗi API vượt ngưỡng 5%, xuất hiện các truy vấn chậm (Slow Queries), hoặc CPU/RAM server quá tải, hệ thống tự động gửi cảnh báo qua Telegram/Discord cho đội ngũ kỹ thuật xử lý ngay lập tức nhằm bảo đảm cam kết tốc độ phản hồi API < 2s.

•    Hệ thống quản lý yêu cầu (Ticket System): Tiếp nhận khiếu nại, báo cáo lỗi từ giao diện Admin của các nhà hàng. Hệ thống tự động phân loại ticket theo mức độ nghiêm trọng (ví dụ: Lỗi màn hình Bếp không nhận realtime = Nguy cấp; Lỗi chính tả menu = Thấp) để điều phối kỹ thuật viên xử lý kịp thời.

•    Hệ thống truyền thông đồng loạt (Broadcasting Portal): Cho phép Super Admin soạn và phát tín hiệu thông báo bảo trì hoặc cập nhật tính năng mới hiển thị dưới dạng popup realtime (qua Laravel Reverb) lên tất cả màn hình của Thu ngân, Chủ quán, và Bếp trên toàn nền tảng.

•    Trung tâm tư liệu tự phục vụ (Self-service Knowledge Base): Quản lý kho tài liệu hướng dẫn, video cấu hình hệ thống giúp các nhà hàng mới dễ dàng tiếp cận và tự giải quyết các vấn đề vận hành cơ bản, giảm tối đa áp lực nhân sự hỗ trợ cho Aventura.

________________________________________

Thứ tự ưu tiên phát triển module (Roadmap)

1. Xác thực & Phân quyền tầng quản trị tối cao (Authentication & Authorization).

2. Quản lý danh sách và vòng đời Doanh nghiệp (Tenant Management).

3. Định hình cấu hình Gói dịch vụ và hạn mức Quota (Subscription Plans & System Quota).

4. Áp dụng CheckTenantSubscription Middleware giới hạn tài nguyên và chặn quyền theo gói (Quota & Read-Only Management).

5. Xây dựng cổng quản lý thanh toán bất đồng bộ qua Redis Queue và hóa đơn hệ thống (Billing System & Webhook Processing).

6. Phát triển Dashboard phân tích dữ liệu vĩ mô và chỉ số SaaS (SaaS Analytics & Insights).

7. Tích hợp công cụ giám sát hạ tầng thời gian thực, điều phối Queue Worker và Ticket Support hệ thống (DevOps Monitoring & Customer Support).

________________________________________

15.2 Phát triển giao diện khách để tiếp đón doanh nghiệp

Đây là giao diện marketing và onboarding, đóng vai trò là "mặt tiền" của nền tảng SaaS Aventura. Giao diện này giúp các chủ doanh nghiệp F&B dễ dàng tiếp cận nền tảng, tìm hiểu các tính năng cốt lõi, trải nghiệm thử và đăng ký sử dụng hệ thống một cách nhanh chóng, tự động hóa hoàn toàn.

? Làm sao để doanh nghiệp hiểu được hệ thống có phù hợp với họ hay không?

Giải pháp: Thiết kế một Landing Page chuyên nghiệp, trực quan với cấu trúc phân tầng thông tin rõ ràng. Tích hợp đầy đủ các tài liệu giới thiệu chức năng nổi bật, lợi ích thực tế, video demo quy trình vận hành thực tế (phục vụ - bếp - thu ngân), hình ảnh mô phỏng giao diện đa thiết bị (máy POS, máy tính bảng, điện thoại) và một trợ lý AI Chatbot tư vấn trực tuyến.

Các chức năng chính

1. Trang giới thiệu hệ thống (Landing Page)

•    Bao gồm: * Giới thiệu tính năng cốt lõi: Quản lý menu, công thức định lượng nguyên vật liệu, quản lý kho thông minh, phân tích doanh thu bằng AI, chấm công và tính lương tự động.

o    Bảng so sánh gói dịch vụ: Minh bạch về tính năng và giới hạn tài nguyên giữa hai gói Free và Pro.

o    Trải nghiệm nhanh (Interactive Demo): Khu vực tương tác giả lập cho phép khách hàng click dùng thử nhanh một số tính năng cơ bản của màn hình POS bán hàng.

o    AI Chatbot hỗ trợ: Trợ lý ảo trả lời nhanh các thắc mắc thường gặp (FAQs) về giá cả, cách vận hành, hỗ trợ kỹ thuật và thiết bị tương thích.

o    Khách hàng thực tế (Social Proof): Các bài viết đánh giá, câu chuyện thành công từ các nhà hàng đang sử dụng Aventura.

•    Ví dụ: Doanh nghiệp truy cập trang chủ có thể ngay lập tức xem video demo luồng order tại bàn bằng mã QR, cách hệ thống tự động trừ kho nguyên liệu sau khi thanh toán, và hỏi AI Chatbot: "Hệ thống có hỗ trợ quản lý chuỗi nhiều chi nhánh không?" để nhận câu trả lời ngay lập tức.

•    Mục tiêu:

o    Giảm tối đa rào cản tiếp cận thông tin cho khách hàng mới.

o    Tăng mức độ tin tưởng và tính chuyên nghiệp của nền tảng SaaS.

o    Giảm thiểu 80% nhân sự trực tổng đài hỗ trợ 24/7 nhờ trợ lý AI Chatbot.

1. Trang đăng ký doanh nghiệp (Quy trình Onboarding tự động)

•    Bao gồm: Đăng ký tài khoản quản trị tối cao (Owner), khởi tạo thông tin nhà hàng đầu tiên và lựa chọn gói dịch vụ ban đầu.

•    ? Làm sao để doanh nghiệp đăng ký nhanh và sử dụng được ngay mà không cần sự can thiệp thủ công của kỹ thuật viên?

•    Giải pháp: Thiết kế quy trình Onboarding tự động hóa trên hạ tầng Shared Database - Shared Schema:

1. Khởi tạo Tenant định danh: Khi chủ quán nhấn đăng ký, hệ thống tự động tạo một bản ghi mới trong bảng restaurants để cấp một ID định danh duy nhất (restaurant_id). Toàn bộ hoạt động của nhà hàng này sau đó sẽ được đóng khung bởi ID này thông qua cơ chế Global Scope của Laravel.

2. Tự động cấp quyền quản trị mặc định (Owner Role): Hệ thống tạo tài khoản người dùng đầu tiên trong bảng users, liên kết trực tiếp với restaurant_id mới và tự động gán vai trò owner (Spatie Laravel Permission) để trao toàn quyền quản trị cho chủ quán.

3. Seeding dữ liệu mẫu (Sample Data Seeding): Để tránh việc chủ quán đối mặt với một hệ thống "trống trơn" gây bối rối, hệ thống tự động sinh một số dữ liệu cấu hình cơ bản đi kèm với restaurant_id vừa tạo:

    Các đơn vị tính mặc định (Mass: g, kg; Volume: ml, l; Count: cái, chai).

    Tạo sẵn 1 Khu vực mẫu (Area) và 3 Bàn mẫu (Tables) kèm mã QR code tự động sinh.

    Tạo sẵn 3 danh mục món ăn mẫu (Cơm, Mỳ, Đồ uống) kèm 1-2 món ăn demo để chủ quán hình dung luồng hoạt động. Đồng thời, để đảm bảo luồng nghiệp vụ trừ kho tự động không bị xung đột khóa ngoại hay lỗi dữ liệu rỗng (Null) khi chủ quán test thử đơn hàng, hệ thống seeder sẽ khởi tạo khép kín chuỗi cung ứng mẫu bao gồm:

    Tạo sẵn các Nguyên liệu mẫu (ingredients) tương ứng trong kho (như: Thịt bò, bánh phở, gạo, đá viên...) kết hợp liên kết nhà cung cấp mặc định. * Tự động thiết lập định mức trong bảng Công thức mẫu (product_recipes) nhằm liên kết trực tiếp các món ăn demo với các nguyên liệu mẫu vừa sinh ra. Cơ chế đồng bộ này đảm bảo các tiến trình ngầm (Queue Worker) tính toán hao hụt kho bãi chạy trơn tru ngay từ giây phút đầu tiên kích hoạt Tenant.

•    Ví dụ: Chủ nhà hàng chỉ cần nhập các thông tin cơ bản: Tên nhà hàng ("Phở Việt"), Email, Mật khẩu và Số điện thoại. Sau 3 giây, hệ thống hoàn tất thiết lập và chuyển thẳng họ vào trang quản trị đã có sẵn bàn mẫu, món ăn mẫu để thử nghiệm tính năng bán hàng ngay lập tức.

•    Mục tiêu:

o    Tự động hóa 100% quy trình thiết lập, giảm chi phí vận hành kỹ thuật xuống mức 0 đồng cho mỗi tenant mới.

o    Tối ưu khả năng mở rộng (Scalability) của hệ thống, cho phép hàng nghìn nhà hàng đăng ký cùng lúc mà không làm nghẽn hạ tầng.

o    Nâng cao trải nghiệm người dùng bằng cách giúp họ tiếp cận luồng bán hàng thực tế ngay trong 1 phút đầu tiên.

1. Chọn gói dịch vụ (Subscription Management)

•    ? Có nên cho doanh nghiệp dùng thử trước khi trả phí không?

•    Giải pháp: Hệ thống áp dụng cơ chế Free Trial (Dùng thử miễn phí) từ 7 đến 30 ngày đối với gói Pro để doanh nghiệp làm quen với tất cả các chức năng trước khi đưa ra quyết định thương mại.

•    Cơ chế giới hạn tài nguyên (Quota Enforcement):

o    Gói Free (Mặc định khi đăng ký): Hệ thống áp dụng giới hạn nghiêm ngặt ở tầng ứng dụng và cơ sở dữ liệu: Tối đa 1 chi nhánh, tối đa 10 bàn hoạt động, tối đa 5 tài khoản nhân viên, không mở khóa phân quyền tùy chỉnh và không hỗ trợ các báo cáo dự báo kho/gian lận bằng AI.

o    Gói Pro (Thử nghiệm hoặc Trả phí): Mở khóa không giới hạn số lượng bàn, chi nhánh, nhân viên và kích hoạt toàn bộ sức mạnh phân tích dữ liệu của Python Microservice.

•    Mục tiêu:

o    Tăng tỷ lệ chuyển đổi (Conversion Rate) từ người dùng miễn phí sang khách hàng trả phí thực tế bằng việc chứng minh giá trị của gói Pro.

o    Bảo vệ tài nguyên máy chủ bằng cách giới hạn hiệu năng của các tài khoản miễn phí.

1. Trang liên hệ, hỗ trợ và Hướng dẫn tương tác (Guided Tours)

•    Bao gồm: Cổng tiếp nhận yêu cầu hỗ trợ (Ticket System), đặt lịch hẹn demo trực tiếp với đội ngũ phát triển và hệ thống tài liệu hướng dẫn thông minh.

•    ? Làm sao để giữ chân doanh nghiệp, giảm tỷ lệ rời bỏ (Churn Rate) sau khi đăng ký?

•    Giải pháp: Thiết kế hệ thống hướng dẫn tương tác từng bước tự động (Interactive Guided Tours) ngay trên giao diện:

o    Ngày thứ 1 (Bước chân đầu tiên): Khi chủ quán đăng nhập lần đầu, một bong bóng chỉ dẫn (tooltip) sẽ xuất hiện, dẫn dắt họ tạo nhóm món ăn và thêm món ăn thực tế của quán mình vào thực đơn.

o    Ngày thứ 2 (Chuẩn hóa vận hành): Hệ thống gợi ý và hướng dẫn thiết lập công thức định lượng nguyên liệu (ví dụ: 1 bát phở cần 150g bánh phở, 80g thịt bò) để kích hoạt tính năng tự động trừ kho.

o    Ngày thứ 3 (Quản trị nhân sự): Gợi ý chủ quán thêm tài khoản nhân viên, phân quyền (Thu ngân, Bếp) và hướng dẫn cách xếp lịch làm việc hàng tuần.

•    Mục tiêu:

o    Giảm tỷ lệ khách hàng bỏ dùng sớm (Drop-off Rate) do giao diện quản lý F&B quá nhiều thông tin và khó tiếp cận.

o    Giúp khách hàng khai thác tối đa giá trị của phần mềm, biến Aventura thành công cụ không thể thiếu trong vận hành hàng ngày của họ.

o    Nâng cao tỷ lệ gia hạn dịch vụ dài hạn.

Thứ tự phát triển ưu tiên của module

1. Giai đoạn 1 (Core Marketing): Thiết kế Landing Page và trang giới thiệu tính năng tĩnh để kiểm tra mức độ thu hút khách hàng.

2. Giai đoạn 2 (Authentication & Tenant Seeder - Quan trọng nhất): Phát triển form đăng ký, tích hợp logic tự động tạo tenant mới trong database dùng chung, tự động gán quyền Owner và seeder dữ liệu mẫu ban đầu.

3. Giai đoạn 3 (Subscription & Quota Middleware): Xây dựng hệ thống phân chia gói dịch vụ (Free/Pro), viết các middleware kiểm tra giới hạn tài nguyên (ví dụ: Chặn không cho tạo bàn thứ 11 nếu đang ở gói Free).

4. Giai đoạn 4 (Retention & Support): Tích hợp thư viện Guided Tours (ví dụ: Intro.js hoặc Shepherd.js) trên giao diện quản trị, phát triển hệ thống Ticket và chatbot AI tư vấn.

15.3: Phát triển Quản lý nhà hàng/chủ nhà hàng

? Tài khoản cố định hay thay đổi: dựa vào việc phân quyền của Chủ doanh nghiệp, có thể nâng vai trò tài khoản bình thường thành tài khoản quản lý cũng có thể vô hiệu vai trò

? Giới hạn quyền lực: việc nhập nguyên liệu đầu vào hàng ngày, chốt doanh thu theo ngày, phân ca làm,... đều sẽ gửi thông báo tới Admin để duyệt trước khi các lệnh đó được thực thi

-Quản lý menu: Tạo - sửa – xóa – đánh dấu các món trong thực đơn, cần tạo cả công thức pha chế từng món, mô tả món. Lý do:  các nhân viên trong quán khi làm pha chế, bếp sẽ biết được công thức nhân viên phục vụ và order sẽ biết được đặc điểm từng món để mời chào khách. Rút ngắn thời gian đào tạo, quy hoạch trách nghiệm, tăng tính chuyên nghiệp của từng vai trò.

-Quản lý Kho nguyên vật liệu:  . Đề xuất áp dụng AI vào để đề đánh giá và đề xuất nhập hàng dự trên doanh thu của các ngày trước đó.

? Ai có thể truy cập kho: Chủ doanh nghiệp và quản lý

?Khi tạo cần theo thứ tự nào: Chọn loại nguyên liệu ở danh mục đã có ( nếu chưa có thì được tạo loại mới ) -> click tạo sản phẩm ( tên nguyên liệu, nhà cung cấp, trọng lượng/ cân nặng )

? Khi nào có thể tạo nguyên liệu mới: khi quán có món mới sử dụng loại nguyên liệu  mới

- Tạo danh mục các nguyên liệu dụng làm ra các sản phẩm ( loại, tên, số lượng, trọng lượng/cân nặng của mỗi đơn vị )

- Hằng ngày sẽ về nhập số lượng nguyên liệu mà mua về ( chỉ nhập số lượng vì các sản phẩm đó đã được tạo sẵn danh mục ), có mục để lưu hóa đơn nếu mua ngoài ( yêu cầu giữ hóa đơn cứng giấy để cuối tháng tính ( xác thực 2 lần để khi có vấn đề về nguyên liệu thì có thể nhanh chóng rà soát nguyên nhân rồi xử lý trước mắt và tăng độ chính xác khi tính toán nguyên liệu ), cuối tháng các mục đó sẽ làm tiền đề tính cost

- Quản lý nhân sự: tạo sửa và xóa nhân sự. Quản lý hoặc admin sẽ tạo tk và phát cho nhân viên trong quán, sẽ không có chức năng đăng ký tài khoản đối với nhân viên của quán, khi tạo tk cho nhân viên trong quán thì sẽ cần thông tin như: tên, ngày tháng năm sinh, tạm trú, ảnh cccd, sdt. Lý do: thông tin càng chi tiết thì sẽ càng đảm bảo khi xảy ra vấn đề gì

=>Lợi ích, sẽ quản lý chặt chẽ nhân viên hơn trong quá trình làm việc cũng như dễ bề khai báo với các cơ quan chức năng ( khi: đóng thuế, đăng ký an toàn thực phẩm, làm việc với cơ quan chức năng địa phương khi cần )

-Quản lý doanh thu, lợi nhuận: Quản lý hoặc admin có thể chọn thời gian chốt ca hằng ngày, sau mỗi ca hệ thống sẽ yêu cầu chốt ca để xem âm hay đủ tiền(ví dụ ngày chốt ca 2 lần ca sáng lúc 16h, ca tối 21h, thì cứ cuối ca sẽ gửi tổng doanh thu và tiền ck ghi nhận được trong thời gian ca đó. Yêu cầu xác nhận số tiền mặt còn lại có đủ với tính toán của hệ thống ko, nếu

có thu chi gì thì tài khoảng admin hoặc quản lý phải bổ sung vào phiếu đó rồi xác nhận. Thông tin người xác nhận sẽ lưu lại và gửi cho Admin vào cuối ngày ),hằng ngày sẽ gửi báo cáo doanh thu về email cho chủ doanh nghiệp vào lúc 23h59 ( doanh thu gồm: tổng doanh thu, Số chuyển khoản ghi nhận ( có link để xem chi tiết thông tin các chuyển khoản đó ), chốt ca theo từng ca trong ngày và thông tin người xác nhận, sau khi trừ các chi phí phát sinh thì số tiền mặt phải còn lại là... Nếu không đủ tiền mặt, hệ thống sẽ tự động ghi nhận giá trị chênh lệch âm này và gắn trách nhiệm trực tiếp vào tài khoản nhân sự phụ trách phiên khớp ca (hoặc tài khoản có quyền payment_order) trong ca làm việc đó. Đồng thời, hệ thống sẽ tự động gửi thông báo và biên bản chốt ca đến email của nhân sự phụ trách để họ xác nhận hoặc thực hiện quyền khiếu nại nếu có sai sót, đảm bảo tính minh bạch tối đa. Bổ sung: để tăng tính minh bạch thì mỗi khi chốt ca thì sẽ gửi gmail vào tài khoản thu ngân  ca đó để họ xác nhận và có chức năng khiếu nại nếu sai xót.

1. Cụm Tài chính & Báo cáo (Financial & Analytics Hub)

Chủ nhà hàng lúc này vừa xem được bề nổi (vận hành) vừa nắm được bề sâu (dòng tiền):

•    Chốt ca & Quản lý doanh thu gộp (Gross): Thực hiện chốt doanh thu theo ngày/ca, đối soát tiền mặt thực tế với hệ thống.

•    Phân tích Lợi nhuận ròng (Net Profit): Móc nối với Python Service để xem báo cáo lợi nhuận thực tế sau khi tự động trừ đi giá vốn hàng bán (COGS) và chi phí nhân sự.

•    Báo cáo Vận hành: Xem thống kê số lượng món được order nhiều nhất trong ngày/tuần/tháng để dự báo và chuẩn bị nguyên liệu.

Phân tích kỹ nội dung từng chức năng trong cụm:

1.1. Quản lý Doanh thu gộp (Gross) & Chốt ca thực chiến

Đây là chốt chặn đầu tiên để đảm bảo dòng tiền không bị thất thoát hằng ngày, dựa trên cơ chế minh bạch và quy trách nhiệm rõ ràng:

•    Thiết lập linh hoạt & Đối soát dòng tiền: Hệ thống xử lý việc chốt ca hằng ngày như thế nào để tránh thất thoát? Chủ nhà hàng/Quản lý cấu hình các mốc thời gian chốt ca cố định (ví dụ: ca sáng 16h, ca tối 21h). Cuối mỗi ca, hệ thống tự động tổng hợp tổng doanh thu và tiền chuyển khoản (có link chi tiết). Người phụ trách chốt ca phải đếm tiền mặt thực tế, đối soát với tính toán của hệ thống và khai báo các khoản thu/chi lặt vặt phát sinh vào phiếu .

•    Xử lý "Âm két" (Quy trách nhiệm): Nếu số tiền mặt thực tế bị thiếu thì hệ thống quy trách nhiệm ra sao? Nếu số tiền mặt thực tế thấp hơn hệ thống báo cáo, khoản chênh lệch âm này tự động được ghi nhận . Hệ thống lập tức gắn trách nhiệm trực tiếp vào tài khoản nhân sự phụ trách phiên khớp ca (hoặc người có quyền thanh toán payment_order lúc đó).

•    Cơ chế minh bạch & Khiếu nại: Làm sao để đảm bảo tính minh bạch cho nhân viên khi chốt ca, tránh việc bị quy trách nhiệm sai? Ngay khi chốt ca, một thông báo, biên bản chốt ca và email sẽ được gửi trực tiếp đến tài khoản của thu ngân ca đó . Họ bắt buộc phải xác nhận hoặc sử dụng chức năng khiếu nại nếu phát hiện sai sót, tránh việc đổ lỗi sau này.

1.2. Hệ thống Báo cáo Tự động (23:59 Daily Report)

Chủ nhà hàng không cần túc trực tại quán vẫn nắm bắt được dòng tiền qua báo cáo tự động:

•    Gửi báo cáo lúc 23h59: Chủ nhà hàng làm sao để theo dõi tổng thể dòng tiền mà không cần có mặt tại quán? Đúng 23h59 hằng ngày, hệ thống tự động tổng hợp toàn bộ dữ liệu trong ngày và gửi thẳng qua email Chủ doanh nghiệp.

•    Nội dung cốt lõi: Báo cáo liệt kê rõ tổng doanh thu, số lượng chuyển khoản (kèm link xem chi tiết), chi tiết thông tin người xác nhận chốt từng ca, và con số tiền mặt cuối cùng phải còn lại sau khi trừ các chi phí phát sinh .

1.3. Phân tích Lợi nhuận ròng (Net Profit) & Đối soát Chi phí

Để hệ thống (kết hợp Python Service) tự động tính ra được Lợi nhuận ròng, nó sẽ cấn trừ các luồng dữ liệu từ Kho và Nhân sự:

•    Quản lý Danh mục & Truy cập Kho: Ai là người có quyền truy cập và khi nào được tạo nguyên liệu mới? Chỉ có Chủ doanh nghiệp và Quản lý mới được phép truy cập vào kho. Việc tạo nguyên liệu mới (chọn loại $\rightarrow$ tạo sản phẩm điền tên, nhà cung cấp, trọng lượng) chỉ được thực hiện khi quán ra mắt món mới sử dụng loại nguyên liệu đó .

•    Trừ Chi phí nguyên liệu (COGS) & Nhập hàng: Việc nhập hàng hằng ngày diễn ra như thế nào để đảm bảo tính toán giá vốn (cost) chính xác vào cuối tháng? Nhân viên nhập số lượng nguyên liệu mua về hằng ngày dựa trên danh mục tạo sẵn. Hệ thống yêu cầu giữ lại hóa đơn cứng (nếu mua ngoài) để xác thực 2 lần khi cần rà soát sự cố. Dữ liệu này sau đó đối chiếu với công thức định lượng (từ menu) để tự động tính ra giá vốn chính xác (Cost) làm tiền đề chốt sổ cuối tháng.

•    Trừ Chi phí rủi ro/Nhân sự: Các khoản "âm két" của thu ngân, hoặc lỗi hao hụt nguyên liệu từ bếp sẽ được hệ thống tính toán làm cơ sở để trừ vào lương cuối tháng. Điều này giúp lợi nhuận ròng phản ánh đúng thực tế túi tiền của chủ quán.

1.4. Báo cáo Vận hành & Dự báo bằng AI

Dữ liệu tài chính không chỉ để nhìn lại, mà còn để dự báo tương lai:

•    Dự báo Kho bãi (AI): Làm thế nào để giải quyết bài toán nhập hàng dư thừa hoặc thiếu hụt? Hệ thống AI sẽ phân tích doanh thu và số lượng món ăn order nhiều nhất trong các ngày trước đó để tự động đánh giá và đề xuất số lượng nguyên liệu cần nhập cho ngày mai.

•    Tối ưu Menu: Thống kê món bán chạy giúp định hình lại mô tả món ăn và công thức pha chế . Từ đó, nhân viên order hiểu rõ đặc điểm từng món để dễ dàng mời chào khách, rút ngắn thời gian đào tạo và tăng hiệu suất bán hàng .

1.5. Cơ chế Kiểm duyệt chéo (Bảo vệ quyền lực Chủ nhà hàng)

Để đảm bảo các con số tài chính không bị thao túng, hệ thống áp dụng luật "Kiểm duyệt chéo":

•    Phê duyệt trước khi thực thi: Làm thế nào để giới hạn quyền lực của cấp dưới (Quản lý/Nhân viên) để tránh lạm quyền? Mọi hành động có khả năng làm thay đổi dòng tiền hoặc chi phí (như nhập nguyên liệu đầu vào hằng ngày, chốt doanh thu theo ngày, phân ca làm việc) đều không được thực thi ngay. Hệ thống bắt buộc gửi thông báo tới Admin để duyệt trước khi lệnh có hiệu lực.

•    Tùy biến & Quản lý nhân sự: Tài khoản trong hệ thống là cố định hay có thể thay đổi? Quyền hạn tài khoản hoàn toàn linh hoạt dựa trên sự phân quyền của Chủ doanh nghiệp. Chủ quán có quyền nâng cấp một tài khoản bình thường lên làm quản lý, hoặc vô hiệu hóa vai trò đó. Việc tạo mới nhân sự cũng bị kiểm soát khắt khe (yêu cầu CCCD, tạm trú, số điện thoại) để đảm bảo an toàn khi xảy ra sự cố và dễ dàng khai báo với cơ quan chức năng (thuế, an toàn thực phẩm) .

1. Cụm Vận hành Cốt lõi (Core Operations: Menu & Inventory)

Đây là khu vực thiết lập luật chơi của quán, Owner nắm toàn quyền cấu hình:

•    Quản lý Thực đơn & Định lượng (BOM): Tạo danh mục món ăn và thiết lập công thức định lượng khép kín (Ví dụ: 1 bát phở = 150g bánh phở + 80g thịt bò). Đây là cơ sở để hệ thống tự động trừ kho và tính cost.

•    Quản lý Kho bãi toàn diện: Tạo danh mục nguyên vật liệu, dụng cụ. Kiểm soát các phiếu nhập/xuất kho và xem báo cáo hao hụt (so sánh tồn kho lý thuyết và thực tế).

•    Kiểm toán Đơn hàng (Order Auditing): Tra soát mọi đơn hàng từ mọi ca làm việc, kiểm tra lịch sử chỉnh sửa, hủy món, tách bàn để phát hiện gian lận.

Phân tích kỹ nội dung từng chức năng trong cụm:

2.1. Quản lý Thực đơn & Công thức định lượng (BOM - Bill of Materials)

Đây không chỉ là danh sách món ăn để bán, mà là lõi vận hành liên kết trực tiếp với kho bãi và đào tạo nhân sự.

•    Chuẩn hóa chất lượng & Đào tạo: Làm sao để chuẩn hóa chất lượng món ăn và giúp nhân sự (bếp, phục vụ) phối hợp nhịp nhàng mà không mất nhiều thời gian đào tạo?

o    Giải pháp: Hệ thống bắt buộc Chủ quán/Quản lý khi tạo món (bảng products) phải viết mô tả đặc điểm món ăn . Nhờ đó, nhân viên Order phục vụ hiểu rõ hương vị để mời chào, tư vấn khách . Đồng thời, hệ thống yêu cầu thiết lập công thức định lượng khép kín (bảng product_recipes liên kết sản phẩm và nguyên liệu) . Điều này giúp Bếp và Pha chế nhìn vào là biết chính xác cách chế biến, tăng tính chuyên nghiệp .

•    Tiền đề tự động hóa: Công thức định lượng này chính là cơ sở dữ liệu cốt lõi để hệ thống tự động tính toán đối chiếu kho đệm và tự động trừ kho lõi (quantity_on_hand) ngay khi Thu ngân bấm thanh toán hóa đơn.

2.2. Quản lý Kho bãi toàn diện & Quy trình Nhập/Xuất

Kho bãi là luồng dễ thất thoát nhất. Hệ thống số hóa luồng này bằng quy trình chặt chẽ và lưu vết chứng từ cứng.

•    Quyền truy cập & Khởi tạo danh mục: Ai là người được quyền can thiệp vào kho và quy trình khởi tạo nguyên liệu mới diễn ra theo thứ tự nào để tránh rác dữ liệu?

o    Giải pháp: Chỉ có Chủ doanh nghiệp và Quản lý mới được phép truy cập kho. Việc tạo nguyên liệu mới (bảng ingredients) chỉ được thực hiện khi quán ra mắt món mới có sử dụng loại đó. Quy trình bắt buộc đi từ: Chọn/tạo danh mục (Loại) $\rightarrow$ Tạo sản phẩm nguyên liệu (Nhập tên, nhà cung cấp, trọng lượng/đơn vị tính).

•    Kiểm soát Nhập hàng hằng ngày & Tính Cost: Quy trình nhập hàng hằng ngày được kiểm soát như thế nào để đảm bảo tính giá vốn (Cost) chính xác vào cuối tháng?

o    Giải pháp: Hằng ngày, nhân viên kho chỉ cần nhập số lượng hàng mua về (vì danh mục đã bị khóa cứng bởi Owner). Khi tạo phiếu nhập, hệ thống yêu cầu lưu trữ thông tin hóa đơn (có thể là upload ảnh hóa đơn cứng vào trường invoice_file_url trong bảng inventory_transactions) . Việc này giúp xác thực chéo 2 lần, làm tiền đề chuẩn xác để Job ngầm tính toán lại giá vốn trung bình (average_cost) vào cuối tháng.

•    Giới hạn quyền lực & Phê duyệt: Làm thế nào để tránh việc nhân viên kho tự ý nhập/xuất khống số liệu?

o    Giải pháp: Áp dụng cơ chế kiểm duyệt chéo. Các lệnh nhập nguyên liệu đầu vào hằng ngày của nhân viên không được hệ thống cộng thẳng vào kho vật lý ngay, mà phải gửi thông báo tới Admin duyệt trước khi lệnh thực thi.

•    Tích hợp AI Dự báo: Làm sao để biết hôm nay cần nhập bao nhiêu nguyên liệu là đủ?

o    Giải pháp: Python Microservice phân tích lịch sử tiêu thụ và doanh thu các ngày trước đó để chạy mô hình AI đánh giá và đề xuất lượng nguyên liệu cần nhập, tránh tình trạng ứ đọng vốn hoặc thiếu hụt hàng .

2.3. Kiểm toán Đơn hàng & Chống gian lận (Order Auditing & Fraud Detection)

Đây là "mắt thần" của Chủ nhà hàng, đảm bảo sự trung thực của nhân viên mà không cần lắp camera hay có mặt tại quán.

•    Xử lý Tách bàn/Hủy món: Làm sao để phát hiện mánh khóe nhân viên tách đơn thật của khách ra bàn trống để bỏ túi riêng khoản tiền đó?

o    Giải pháp: Thu ngân có quyền tách đơn ra bàn trống, nhưng hệ thống lập tức "đánh dấu đỏ" đơn này và gửi thông báo Realtime thẳng cho Chủ quán/Quản lý. Khi hết ca, đơn bị tách sẽ in ra và tính là âm số tiền. Chỉ có Chủ doanh nghiệp (Owner) mới có quyền hạn tối cao để vô hiệu hóa khoản âm đơn đó sau khi đã đối soát rõ ràng.

•    Nhật ký Kiểm toán toàn diện (Audit Logs): Làm sao để biết chính xác ai đã sửa giá món, ai đã hủy hóa đơn và hủy lúc nào?

o    Giải pháp: Bất kỳ thao tác nhạy cảm nào (sửa giá, áp mã giảm giá, hủy đơn) đều không bị xóa vĩnh viễn mà được lưu vết tĩnh vào bảng audit_logs . Hệ thống lưu lại chính xác: ID người thực hiện, địa chỉ IP (để xem có thao tác từ xa ngoài quán không), và lưu cục dữ liệu JSON so sánh giá trị cũ (old_values) và giá trị mới (new_values) .

•    Đánh giá rủi ro tự động (AI Fraud Detection): Thuật toán Python liên tục quét các dữ liệu từ audit_logs. Nếu phát hiện nhân viên sửa/xóa đơn nhiều lần hoặc hủy món sau khi khách đã thanh toán, hệ thống sẽ tự động gửi cảnh báo khẩn cấp đến Quản lý .

💡 Góc nhìn khi Lập trình (Logic Backend): Đối với cụm chức năng này, điểm khó nhất khi bạn code Laravel là tính toàn vẹn dữ liệu lúc thanh toán (Atomicity). Khi Thu ngân bấm nút Thanh toán, bạn bắt buộc phải bọc logic trong DB::transaction(function () { ... }); . Trong một block code đó phải xảy ra đồng thời 3 việc: (1) Cập nhật trạng thái orders thành paid $\rightarrow$ (2) Trừ quantity_on_hand trong bảng inventories dựa theo product_recipes $\rightarrow$ (3) Ghi một dòng vào inventory_transactions. Nếu 1 trong 3 bước lỗi, hệ thống sẽ tự động rollback (trả lại như cũ) để kho và tiền không bao giờ bị lệch .

1. Cụm Nhân sự, Lịch làm & Tính lương (HR & Payroll System)

Gộp nghiệp vụ quản lý hành chính và tính lương vào một luồng duy nhất:

•    Quản lý Hồ sơ: Tạo mới, chỉnh sửa, khóa tài khoản nhân sự cấp dưới, lưu trữ thông tin cá nhân.

•    Xếp lịch & Duyệt phép: Lên lịch làm việc hàng tuần cho nhân sự. Trực tiếp phê duyệt các đơn xin nghỉ phép, nghỉ việc, đi trễ. Việc xếp lịch giúp truy vết chính xác lỗi xảy ra ở ca của ai.

•    Tự động hóa Tính lương: Hệ thống tự động kéo dữ liệu từ máy chấm công/lịch làm việc để tính lương. Owner thực hiện chốt lương cuối tháng với các khoản trừ tự động:

o    Trừ lương Bếp/Pha chế nếu tỷ lệ hao hụt kho (cost âm) vượt mức cho phép.

o    Trừ lương Thu ngân nếu chốt ca bị âm két.

o    Trừ các vi phạm kỷ luật khác.

Phân tích kỹ nội dung từng chức năng trong cụm:

1. 1. Quản lý Hồ sơ & Khóa tài khoản (Bảo mật nhân sự đầu vào)

Hồ sơ nhân sự trong hệ thống không chỉ để lưu tên tuổi, mà là cơ sở pháp lý và rào chắn an ninh của quán.

•    Kiểm soát tạo tài khoản: Nhân viên có được tự đăng ký tài khoản không và hệ thống yêu cầu thông tin gì để đảm bảo an toàn?

o    Giải pháp: Hệ thống tuyệt đối không có chức năng để nhân viên tự đăng ký. Quản lý hoặc Admin sẽ là người trực tiếp tạo và cấp phát tài khoản. Khi tạo, bắt buộc phải khai báo hồ sơ rất sâu: Họ tên, ngày sinh, địa chỉ tạm trú, số điện thoại và đặc biệt là ảnh CCCD (căn cước công dân mặt trước/sau) .

•    Mục đích pháp lý & Rủi ro: Tại sao cần thông tin chi tiết đến mức như vậy?

o    Giải pháp: Thông tin càng chi tiết càng giúp ràng buộc trách nhiệm, đảm bảo an toàn khi xảy ra sự cố (như mất cắp, gian lận). Về mặt vĩ mô, dữ liệu này giúp Chủ nhà hàng dễ dàng xuất file để khai báo với cơ quan chức năng khi cần làm thủ tục đóng thuế, đăng ký an toàn vệ sinh thực phẩm hoặc làm việc với công an địa phương.

1. 1. Xếp lịch, Duyệt phép & Truy vết Trách nhiệm (Scheduling System)

Việc xếp lịch (Bảng schedule_assignments) trong hệ thống này đóng vai trò như một chiếc "chìa khóa điện tử" kiểm soát toàn bộ hành vi của nhân viên.

•    Kiểm soát truy cập (Block Access): Làm sao để ngăn chặn nhân viên ở nhà vẫn đăng nhập vào hệ thống để tạo đơn khống hoặc xem lén dữ liệu doanh thu?

o    Giải pháp: Tài khoản của nhân viên (Order, Thu ngân) chỉ được phép hoạt động đúng trong khung giờ của ca làm việc đã được xếp . Ví dụ: Ca của nhân viên A là 16h00 - 23h00 thứ 3, thì tài khoản chỉ khả dụng đăng nhập trong đúng khung giờ đó, ngoài giờ hệ thống tự động vô hiệu hóa quyền truy cập API .

•    Truy vết sự cố: Việc xếp lịch làm việc hàng tuần có ý nghĩa gì ngoài việc chấm công?

o    Giải pháp: Nó giúp hệ thống quy chiếu trách nhiệm trực tiếp. Khi có một sự cố xảy ra (như mất đồ trong kho, hoặc thu ngân chốt ca bị âm tiền), Chủ quán chỉ cần đối chiếu mốc thời gian sự cố với lịch làm việc là biết chính xác lỗi đó thuộc về ca của ai để xử lý.

•    Duyệt nghỉ phép/Nghỉ việc: Quy trình xử lý nhân sự xin nghỉ đột xuất hoặc nghỉ việc hẳn diễn ra như thế nào?

o    Giải pháp: Quản lý duyệt đơn trên hệ thống. Nếu là nghỉ đột xuất, hệ thống tự động thay đổi lịch làm việc. Nếu là xin nghỉ việc, hệ thống tự động chuyển trạng thái nhân sự sang terminated (đã nghỉ việc), vô hiệu hóa tài khoản và kích hoạt "Xóa mềm" (SoftDeletes) . Nhân sự đó biến mất khỏi danh sách làm việc nhưng lịch sử giao dịch (hóa đơn họ từng tạo) vẫn giữ nguyên để đối soát kế toán .

1. 1. Tự động hóa Tính lương & Cơ chế Phạt lũy tiến (Auto Payroll)

Đây là bước cuối cùng khép lại vòng lặp quản lý, móc nối trực tiếp với phân hệ Kho bãi và Doanh thu ở các phần trước.

•    Tính lương tự động: Làm thế nào để giảm tải việc cộng sổ tính lương thủ công vào mỗi cuối tháng?

o    Giải pháp: Cuối tháng, tính năng Auto Salary Calculation sẽ tự động quét bảng dữ liệu lịch trực và lịch sử chấm công (chốt ca) để tự động cấu hình ra bảng lương thô (salaries).

•    Cơ chế trừ lương tự động (Quy trách nhiệm tài chính): Các rủi ro thất thoát trong tháng sẽ được quy đổi thành tiền phạt vào lương như thế nào?

o    Giải pháp: Hệ thống (thông qua bảng salary_adjustments) sẽ tự động kéo các biên bản vi phạm và hao hụt để cấn trừ trực tiếp vào lương :

1. Trừ lương Thu ngân (Cash shortage): Nếu trong tháng, thu ngân có các ca chốt bị "âm két" (đã được ghi nhận tại phân hệ Doanh thu), số tiền âm sẽ tự động trừ vào bảng lương cuối tháng của chính người đó.

2. Trừ lương Bếp/Pha chế (Inventory loss): Nếu Python Service đối chiếu kho và phát hiện hao hụt nguyên liệu (cost âm) vượt định mức cho phép do làm hỏng, đổ vỡ, số tiền thất thoát sẽ tự động gán phạt trừ vào lương của bộ phận Bếp/Pha chế ca đó.

3. Trừ các vi phạm khác (Violations): Trừ lương dựa trên hòm thư tố cáo sai phạm ẩn danh hoặc các báo cáo đi trễ, vi phạm kỷ luật đã được Admin duyệt (từ bảng violation_reports) .

💡 Góc nhìn khi Lập trình (Logic Backend): Để làm được tính năng Khóa tài khoản ngoài giờ làm (Block Access), ở Laravel, bạn cần viết một lớp Middleware (ví dụ: CheckShiftSchedule). Middleware này sẽ chạy ở mọi API Route của nhóm nhân viên (như tạo đơn, xem món). Nó lấy ID của nhân viên đang đăng nhập $\rightarrow$ Query vào bảng schedule_assignments xem now() (giờ hiện tại) có nằm giữa start_time và end_time của ca trực ngày hôm nay không. Nếu không, trả về HTTP Code 403 Forbidden (Bạn không trong ca làm việc). Cách này đảm bảo không một ai qua mặt được hệ thống .

1. Cụm Marketing & Khách hàng (CRM & Promotions)

Owner trực tiếp nắm giữ tài sản data và ra quyết định kích cầu:

•    Quản lý Data Khách hàng: Nắm giữ toàn bộ dữ liệu tên, tuổi, SĐT của khách hàng. Có quyền xuất (export) tệp dữ liệu này để chạy chiến dịch bên ngoài.

•    Thiết lập Khuyến mãi: Tạo, cấu hình và phê duyệt các chương trình giảm giá, voucher cho các dịp lễ hoặc combo đẩy số.

Phân tích kỹ nội dung từng chức năng trong cụm:

1. Quản lý Data Khách hàng (CRM Mini) & Bảo mật tài sản số

Dữ liệu khách hàng là tài sản cốt lõi của mỗi thương hiệu F&B, hệ thống khóa chặt luồng này để tránh rò rỉ hoặc thất thoát thông tin.

•    Làm sao để lưu trữ và bảo vệ dữ liệu khách hàng không bị nhân viên tự ý sao chép hoặc đánh cắp mang sang đối thủ cạnh tranh?

o    Giải pháp: Toàn bộ thông tin khách hàng (Họ tên, SĐT, email, ngày sinh, giới tính, điểm tích lũy thành viên) được lưu trữ tập trung tại bảng customers . Hệ thống áp dụng rào chắn quyền hạn (Permission-based Access Control) nghiêm ngặt. Nhân viên Thu ngân hay Phục vụ chỉ được cấp quyền tra cứu SĐT hoặc ghi nhận thông tin khách khi tạo đơn , giao diện của họ hoàn toàn bị ẩn nút "Xuất dữ liệu" (Export) . Quyền export tệp dữ liệu khách hàng thành file Excel/CSV là quyền tối cao, được rào chắn bằng logic Backend và chỉ gán riêng cho vai trò Chủ nhà hàng (Owner) để phục vụ các chiến dịch remarketing qua kênh ngoài.

•    Hệ thống làm thế nào để đảm bảo tính riêng tư tuyệt đối của data khách hàng giữa các nhà hàng khác nhau trong mô hình SaaS dùng chung cơ sở dữ liệu?

o    Giải pháp: Toàn bộ các bản ghi trong bảng customers bắt buộc phải chứa khóa ngoại restaurant_id. Tại tầng Backend Laravel, hệ thống áp dụng cơ chế Global Scope để tự động lọc dữ liệu . Mọi câu lệnh truy vấn dữ liệu từ Frontend gửi lên, dù viết đơn giản đến đâu, hệ thống cũng tự ngầm đính kèm điều kiện WHERE restaurant_id = ?. Điều này chặn đứng hoàn toàn nguy cơ rò rỉ tệp khách hàng giữa các Tenant.

1. Thiết lập Khuyến mãi & Chiến lược cấu hình Combo thông minh

Phân hệ giúp số hóa các chương trình Marketing, đồng thời loại bỏ các lỗ hổng gian lận từ nhân sự.

•    Làm thế nào để thiết lập các chương trình khuyến mãi, voucher linh hoạt mà không sợ nhân viên thu ngân cấu kết với người ngoài áp mã vô tội vạ để gian lận tiền mặt?

o    Giải pháp: Chỉ có tài khoản có quyền quản trị tối cao mới được phép cấu hình và duyệt các chương trình giảm giá, mã voucher. Khi Thu ngân áp dụng một mã giảm giá vào bảng giao dịch orders , hệ thống (thông qua Model Observers) sẽ lập tức bắt lấy sự kiện và đẩy ngầm vào Redis Queue để ghi vết vào bảng audit_logs . Log ghi lại chi tiết: ai áp mã, áp vào hóa đơn nào, từ IP nào, và giá trị cũ/mới của hóa đơn . Song song đó, thuật toán Fraud Detection của Python Service sẽ liên tục quét bảng log này. Nếu phát hiện tài khoản thu ngân áp voucher liên tục bất thường sai quy trình, hệ thống lập tức gắn cờ cảnh báo (Flagged) gửi thẳng về máy của Chủ nhà hàng .

•    Làm thế nào để tạo ra các combo món ăn hoặc chương trình khuyến mãi thực sự hiệu quả, mang lại lợi nhuận cao dựa trên khoa học hành vi của khách chứ không phải đoán mò?

o    Giải pháp: Hệ thống tích hợp sức mạnh phân tích dữ liệu của Python Microservice thông qua FastAPI . Định kỳ, dữ liệu sạch từ bảng orders và order_items sẽ được đẩy bất đồng bộ sang Python. Tại đây, các thư viện Pandas và Scikit-learn sẽ chạy thuật toán phân tích giỏ hàng (Market Basket Analysis) để tìm ra nhóm các món ăn thường được khách hàng mua cùng nhau (Ví dụ: Khách gọi món Lẩu gà luôn có xu hướng gọi thêm Nước cốt sấu hoặc Khoai tây chiên). Python trả kết quả trực quan hóa lên Dashboard của Owner, giúp chủ quán có cơ sở khoa học để thiết lập các gói Combo đẩy số, kích cầu trúng đích .

1. Trợ lý AI Kích cầu doanh thu trực tiếp tại bàn (Smart Upselling Engine)

Tính năng nâng cao giúp biến các dữ liệu cấu hình khuyến mãi của Chủ quán thành hành động thực tế của nhân viên phục vụ nhằm tăng giá trị đơn hàng trung bình (AOV).

•    Làm sao để nhân viên phục vụ biết cách mời chào khách gọi thêm món một cách khéo léo và hiệu quả nhất khi đứng tại bàn?

o    Giải pháp: Hệ thống phát triển tính năng gợi ý thông minh chạy ngầm bằng Python . Ngay tại thời điểm khách hàng gọi món qua QR hoặc nhân viên thao tác chọn món trên thiết bị máy tính bảng/POS (giao diện Vue.js SPA) , hệ thống sẽ phát một HTTP Request sang FastAPI. Dựa trên các món đang có trong giỏ hàng đệm, AI sẽ tính toán tỷ lệ kết hợp món ăn ngay lập tức và bắn ngược gợi ý hiển thị trực tiếp lên màn hình của nhân viên .

o    Ví dụ thực tế hiển thị trên giao diện: Màn hình máy POS của nhân viên phục vụ sẽ hiển thị một thông báo gợi ý: "AI đề xuất: Khách đang gọi Set Lẩu, mời dùng thêm Coca-Cola hoặc Mì thả lẩu để được áp dụng mã giảm giá Combo 10% đã cấu hình" . Nhân viên chỉ cần đọc theo gợi ý để thuyết phục khách hàng tại chỗ, giúp tối ưu hóa biên lợi nhuận thực tế trên từng bàn ăn một cách triệt để.

💡 Góc nhìn khi Lập trình (Logic Frontend & API): Khi bạn phát triển giao diện Vue.js cho phân hệ CRM, để tối ưu hiệu năng tải trang ban đầu, bạn bắt buộc phải áp dụng kỹ thuật Lazy Loading Routes và Code Splitting thông qua Vite. Nghĩa là mã nguồn của module quản lý data khách hàng và cấu hình khuyến mãi nâng cao sẽ chỉ được tải về trình duyệt khi Owner thực sự click chọn vào menu "Marketing & Khách hàng". Điều này giúp giao diện POS bán hàng hằng ngày của nhân viên nhẹ nhàng, mượt mà và đáp ứng nghiêm ngặt KPI phản hồi API < 2s của toàn hệ thống.

1. Cụm Quản trị Rủi ro & Khủng hoảng (Crisis & Feedback Management)

Owner là chốt chặn cuối cùng giải quyết các vấn đề nội bộ và ngoại vi:

•    Xử lý Phản hồi Khách hàng: Tiếp nhận review/đánh giá từ khách đặt qua QR code. Trực tiếp phản hồi hoặc xử lý đền bù để bảo vệ uy tín quán.

•    Quản lý Tố cáo Nội bộ: Nhận các báo cáo, hòm thư "tố cáo, sai phạm" từ nhân viên.

Gợi ý kỹ thuật (Dành cho việc code chức năng này):

Khi thiết lập trên Laravel với thư viện spatie/laravel-permission:

1. Bạn hãy tạo ra tất cả các Permissions (ví dụ: view_net_profit, manage_roster, calculate_payroll, handle_anonymous_reports...) .

2. Gán toàn bộ danh sách Permission này cho Role owner .

3. Tại Frontend (Vue.js), bạn chỉ cần kiểm tra if (user.permissions.includes('...')) để render toàn bộ các menu sidebar từ Báo cáo tài chính, Chấm công, đến Kho bãi.

Phân tích kỹ nội dung từng chức năng trong cụm:

5.1. Xử lý Phản hồi Khách hàng (External Crisis Management)

Trong ngành F&B, một đánh giá tồi trên mạng xã hội có thể làm sụt giảm nghiêm trọng doanh thu. Phân hệ này giúp chủ quán "dập lửa" ngay từ bên trong.

•    Thu thập phản hồi thời gian thực: Làm sao để chủ quán nắm bắt ngay lập tức trải nghiệm tệ của khách hàng trước khi họ rời khỏi quán và bóc phốt lên mạng?

o    Giải pháp: Khi khách hàng sử dụng tính năng gọi món hoặc xem menu qua việc quét mã QR tại bàn , hệ thống cung cấp sẵn một biểu mẫu đánh giá. Dữ liệu này được đẩy thẳng vào bảng customer_feedback với các trường quan trọng như điểm đánh giá (rating), nội dung phàn nàn (content) . Trạng thái mặc định của phản hồi là new.

•    Quy trình xử lý đền bù: Quản lý hoặc Chủ nhà hàng sẽ nhận được thông báo tức thì. Từ đó, họ có thể trực tiếp ra bàn xin lỗi, tặng voucher đền bù, và chuyển trạng thái phản hồi sang reviewed (đã xem xét) hoặc resolved (đã giải quyết xong). Dữ liệu này được đối chiếu với mã hóa đơn (order_id) để biết chính xác ca làm việc nào, món ăn nào gặp vấn đề.

5.2. Quản lý Tố cáo Nội bộ & Sai phạm (Internal Integrity Management)

Một hệ thống minh bạch cần cơ chế giám sát chéo giữa các nhân viên để ngăn chặn tình trạng cấu kết gian lận.

•    Bảo vệ người tố giác: Làm thế nào để nhân viên an tâm báo cáo các hành vi gian lận (như thu ngân lấy tiền bỏ túi, bếp ăn bớt nguyên liệu) mà không sợ bị quản lý trù dập?

o    Giải pháp: Hệ thống cung cấp hòm thư tố cáo ẩn danh. Khi một nhân sự gửi báo cáo, bản ghi được lưu vào bảng violation_reports . Giao diện hiển thị (Frontend) sẽ tự động ẩn đi thông tin người gửi (reported_by) nếu cờ ẩn danh được bật.

•    Quy trình xử lý kỷ luật: Chủ nhà hàng tiếp nhận báo cáo, phân loại mức độ nghiêm trọng (severity: low, medium, high, critical). Nếu xác định sai phạm là có thật, Chủ quán sẽ thiết lập mức tiền phạt (penalty_amount). Số tiền phạt này lập tức được móc nối sang phân hệ Tính lương, tự động cấn trừ vào kỳ lương cuối tháng của nhân sự vi phạm (thông qua bảng salary_adjustments) . Trạng thái của vé tố cáo sẽ chuyển từ open sang resolved.

5.3. Giải mã Gợi ý Kỹ thuật (Spatie Permission & Vue.js Integration)

Phần gợi ý kỹ thuật bạn đưa ra chính là bản chất của kiến trúc Permission-based Access Control (RBAC hướng hành động) được định nghĩa trong dự án. Để xây dựng tài khoản Chủ nhà hàng (Owner) với sức mạnh toàn diện như chúng ta vừa phân tích, đây là luồng thực thi cụ thể trong code:

•    Bước 1: Thiết lập nền móng Backend (Laravel & Spatie)

o    Thay vì code cứng chức vụ (ví dụ: if role == owner), bạn sẽ seeding toàn bộ các hành động thực tế vào bảng permissions . Theo file Database, hệ thống đã chuẩn bị sẵn các quyền như manage_feedback, view_audit_log, manage_salary, view_report .

o    Tiếp theo, bạn gán toàn bộ mảng quyền này cho Role owner thông qua bảng role_has_permissions . File SQL đã cấp chính xác các quyền từ ID 2 đến ID 15 cho role_id = 2 (Chủ nhà hàng) .

•    Bước 2: Triển khai kiểm duyệt trên Frontend (Vue.js & Pinia)

o    Khi Chủ nhà hàng đăng nhập, API (Sanctum/JWT) sẽ trả về một chuỗi Token kèm theo mảng danh sách các chuỗi Permission của tài khoản đó .

o    Ứng dụng Vue.js (SPA) sẽ lưu mảng này vào thư viện quản lý trạng thái Pinia .

o    Trên các file .vue (Sidebar Menu, Nút bấm), bạn hoàn toàn loại bỏ việc kiểm tra chức vụ. Thay vào đó, chỉ cần dùng Directive của Vue:

JavaScript

v-if="user.permissions.includes('manage_feedback')"

để tự động render Menu "Phản hồi & Khủng hoảng". Cách này giúp Frontend hoạt động mượt mà, render chính xác menu chức năng theo đúng những gì Backend cấp phép mà không cần tải lại trang . Sau này, nếu Chủ quán muốn tạo thêm một vai trò "Phó Quản lý" và chỉ cấp cho họ 3 quyền nhất định, Frontend vẫn tự động thích ứng mà bạn không cần sửa lại mã nguồn.

1. Cụm Quản trị Tích hợp, Quy trình & Tài sản (Integration, Asset & Process Management)

Để vận hành một chuỗi nhà hàng chuyên nghiệp và kết nối với các đối tác bên thứ ba, Owner nắm toàn quyền cấu hình các cổng tích hợp, quy trình phê duyệt nội bộ và kiểm soát khấu hao tài sản.

6.1. Tích hợp Đa kênh & Quản lý thiết bị POS

Hệ thống hóa hạ tầng phần cứng và phần mềm, kết nối trực tiếp với các đối tác F&B bên ngoài:

•   Đăng ký và cấp quyền thiết bị POS (PosDeviceController): Cho phép Owner đăng ký, cấp phép và quản lý các thiết bị máy POS được dùng tại quầy hoặc máy POS cầm tay của nhân viên phục vụ. Chỉ thiết bị được cấp phép và kích hoạt mới được quyền gọi API bán hàng.

•   Quản lý cổng kết nối & API Key (ApiKeyController, IntegrationSettingsController, WebhookEndpointController): Chủ nhà hàng tự cấu hình tích hợp với các ứng dụng giao hàng bên thứ ba (như GrabFood, ShopeeFood) và cổng thanh toán. Hệ thống hỗ trợ tạo API Key bảo mật cho các hệ thống kế toán hoặc ERP khác của doanh nghiệp để đồng bộ dữ liệu.

6.2. Quy trình Phê duyệt & Checklists Vận hành

Giảm thiểu rủi ro lạm quyền của cấp dưới và đảm bảo quy chuẩn nhà hàng được thực thi nghiêm ngặt:

•   Hệ thống phê duyệt vượt cấp (ApprovalController): Cung cấp một cổng kiểm soát (Approvals). Mọi hành động nhạy cảm vượt thẩm quyền của Manager (như điều chỉnh giảm tồn kho đột xuất với giá trị lớn, duyệt chi phí vận hành ngoài hạn mức, duyệt đổi ca làm việc của nhân sự) bắt buộc phải gửi yêu cầu lên cổng chờ duyệt của Owner trước khi có hiệu lực trong DB.

•   Checklist vận hành hằng ngày (OperationsChecklistController): Owner tạo các danh sách công việc bắt buộc nhân viên thực hiện vào đầu ca (mở cửa, vệ sinh máy POS, kiểm kho ban đầu) và cuối ca (dọn dẹp bếp, tắt điện, bàn giao két). Nhân viên phải tích chọn xác nhận hoàn thành, giúp giảm thiểu sai sót do con người.

6.3. Quản lý Tài sản Thiết bị & Luân chuyển kho chuỗi

Tối ưu hóa nguồn lực tài sản cố định và lưu thông nguyên vật liệu:

•   Theo dõi thiết bị và khấu hao tài sản (EquipmentController): Lưu trữ thông tin toàn bộ thiết bị nhà bếp, bàn ghế, hệ thống lạnh... kèm theo hạn bảo trì, lịch sử sửa chữa và tự động tính toán khấu hao tài sản để đưa vào chi phí vận hành.

•   Luân chuyển kho nội bộ chi nhánh (InternalTransferController): Cho phép Owner thực hiện điều phối nguyên vật liệu giữa các kho của các chi nhánh khác nhau trong cùng một chuỗi nhà hàng (Ví dụ: chi nhánh A đang thừa thịt bò nhưng thiếu bánh phở, chi nhánh B ngược lại), tự động sinh vận đơn luân chuyển nội bộ và trừ kho đối ứng mà không tạo giao dịch bán hàng ảo.

6.4. Quản lý Công nợ Đối tác & Đấu thầu báo giá RFP

Kiểm soát dòng tiền công nợ và tối ưu giá vốn hàng bán:

•   Theo dõi công nợ toàn diện (DebtController): Quản lý các khoản nợ phải trả cho nhà cung cấp nguyên liệu và các khoản nợ phải thu từ khách hàng lớn (hợp đồng tiệc, công ty đặt hàng định kỳ). Tự động thông báo nhắc nợ khi đến hạn.

•   Đấu thầu báo giá nguyên liệu (RfpController): Chủ nhà hàng có thể gửi yêu cầu chào hàng và báo giá (Request for Proposal) đối với danh sách nguyên liệu cần mua định kỳ đến nhiều nhà phân phối. Giúp Owner dễ dàng đối chiếu, đấu giá để chọn đối tác cung ứng có giá và chất lượng tốt nhất.

6.5. Đào tạo Nhân sự & Cấu hình Cửa hàng Online

Đảm bảo chất lượng chuyên môn đồng đều và mở rộng kênh bán hàng trực tuyến:

•   Cổng đào tạo nhân viên trực tuyến (TrainingController): Nơi Owner đăng tải các tài liệu quy chuẩn phục vụ, video hướng dẫn nghiệp vụ, công thức pha chế mẫu và các bài kiểm tra trắc nghiệm năng lực nhân sự. Nhân viên bắt buộc phải hoàn thành khóa học và đạt điểm đỗ trước khi được xếp ca chính thức.

•   Thiết lập cửa hàng trực tuyến (OnlineStoreSettingsController): Cấu hình trang web đặt món trực tuyến (Online Ordering Web) của riêng nhà hàng bao gồm giao diện, menu hiển thị trực tuyến, phương thức thanh toán chuyển khoản và phí giao hàng theo khu vực.

15.4: Phát triển nhân viên quán ( order, thu ngân, bếp )

15.4.1: Phát triển nhân viên thu ngân

?  Tài khoản hoạt động khi nào: dựa vào bảng “Lịch làm mà chủ quán/quản lý” xếp, ví dụ: ca làm của tài khoản này là 16 giờ 00 đến 23 giờ 00 thứ 3 ngày... thì trong khoảng thời gian đó sẽ được mở, ngoài ra khung thời gian đó sẽ bị vô hiệu. => Mục đích: tránh vào đơn ko có thực khi ngoài giờ làm, ảnh hưởng tới người trực ca và doanh thu thực

? Tài khoản này có thể thực hiện thanh toán không? Hệ thống kiểm soát hành động dựa trên quyền hạn chi tiết. Tài khoản thông thường phụ trách gọi món (Order) mặc định sẽ không được cấp quyền thực hiện thủ tục thanh toán. Chức năng thanh toán và kết ca chỉ hiển thị và khả dụng đối với các tài khoản được kích hoạt tính năng payment_order (quyền thanh toán hóa đơn).

- Tạo đơn: Có 20 bàn trong 1 khu vực đánh số từ nhỏ tới lớn theo bảng chữ cái, ví dụ A1, A2, A3,....B1,B2,B3,....( có thể nhiều hơn tùy thuộc vào tùy chỉnh của quản lý/chủ ), nhân viên order cần chọn bàn nào đang trống để vào đơn, khi ấn vào bàn trống thì sẽ hiện lên danh mục món ăn/uống để chọn món, khi chọn sẽ hiển thị số bên cạnh mặc định là 1, mỗi khi chọn món thì biểu tượng giỏ hàng sẽ nảy số lượng tổng món, sau khi chọn thì ấn nút xác nhận và sẽ chuyển tới màn hình xác nhận, nơi đó sẽ có tất cả các món đã chọn mà và số lượng, tổng tiền, giá tiền từng món có thể xóa món hoặc điều chỉnh số lượng hoặc quay lại danh mục để thêm, đồ uống khi ở đây, khi này nút xác nhận sẽ chuyển thành thông báo, khi đã “thông báo” thì không thể xóa hay giảm số lượng của đơn đó nữa mà chỉ có thể tăng thêm và tiếp tục gửi thông báo.

- Xem lịch làm việc, đăng ký lịch làm

- Chức năng khiếu nại ( luôn được hiển thị là ẩn danh đối với người xem )

-Chức năng làm đơn => khi xin nghỉ đột xuất hay xin nghỉ việc,.. mục đính chính để quản lý, chủ dễ quản lý và có bằng chứng khi tính lương ( chỉ cần cấp trên ấn xác nhận thì hệ thống sẽ tự động thay đổi lịch làm việc hoặc hệ thống sẽ tự động chuyển trạng thái nhân sự sang terminated (đã nghỉ việc) , vô hiệu hóa tài khoản liên kết và kích hoạt chế độ xóa mềm (SoftDeletes) để ẩn khỏi danh sách vận hành nhưng giữ nguyên lịch sử giao dịch kế toán.)

 Cụm Vận hành Tiền sảnh & Chăm sóc Khách hàng (Dành cho nhân viên thu ngân)

Đây là tuyến đầu tiếp xúc với khách, cần tốc độ thao tác nhanh, độ chính xác cao và khả năng quản lý luồng đơn đa kênh.

•    Tạo đơn & Quản lý sơ đồ bàn:

o    Hệ thống hiển thị 20 bàn trong 1 khu vực, được đánh số từ nhỏ tới lớn theo bảng chữ cái (ví dụ: A1, A2... B1, B2...).

o    Nhân viên thao tác chọn bàn đang trống để vào đơn, hệ thống sẽ hiển thị danh mục món ăn và đồ uống.

o    Khi nhân viên chọn món, số lượng mặc định hiển thị là 1, đồng thời biểu tượng giỏ hàng sẽ nảy số lượng tổng món.

o    Tại màn hình xác nhận, nhân viên xem được tất cả các món đã chọn, điều chỉnh số lượng, xóa món, xem giá tiền từng món và tổng tiền hóa đơn.

•    Khóa trạng thái đơn hàng (Chống gian lận):

o    Khi nhân viên nhấn "thông báo" để gửi lệnh xuống bếp, hệ thống sẽ khóa đơn; nhân viên không thể tự ý xóa hay giảm số lượng của đơn đó nữa mà chỉ có thể tăng thêm món.

•    Xác thực đơn QR & Trợ lý AI Kích cầu (Upselling):

o    Nhân viên Order nhận cảnh báo Realtime trực tiếp trên máy POS/Tablet ngay khi có khách tự gọi món qua mã QR.

o    Nhân viên phải di chuyển ra tận bàn để đối chiếu và bấm "Xác nhận" trên giao diện SPA, giúp đơn đệm chuyển thành đơn chính thức đẩy xuống bếp.

o    Ngay thời điểm xác nhận, thuật toán Python sẽ hiển thị gợi ý AI trên màn hình (ví dụ: "AI đề xuất: Khách gọi Lẩu, mời dùng thêm Coca-Cola..."), hỗ trợ nhân viên mời khách gọi thêm đồ để tăng doanh thu.

•    Quản lý Đơn hàng Đa kênh & Hiệu suất bán hàng (Bổ sung mới):

o    Nhân viên theo dõi trạng thái đơn hàng liên tục để nắm bắt tiến độ phục vụ món ăn cho khách.

o    Nhân viên có quyền truy cập để xem lịch sử các đơn hàng đã thực hiện.

o    Hệ thống cho phép nhân viên tiếp nhận đơn hàng đến từ các ứng dụng bên thứ ba (app khác).

o    Nhân viên được phép xem doanh thu để nắm bắt hiệu suất làm việc trong ca của mình.

•    Hành chính & Cá nhân Tự phục vụ (Bổ sung mới):

o    Nhân viên trực tiếp xem lịch làm việc cá nhân và thực hiện đăng ký lịch làm trên hệ thống.

o    Sử dụng chức năng làm đơn trực tuyến khi có nhu cầu xin nghỉ đột xuất hoặc xin nghỉ việc.

o    Sử dụng chức năng khiếu nại nội bộ; thông tin khiếu nại luôn được hiển thị ẩn danh đối với người xem.

•  Quản lý Hóa đơn & Thanh toán:

•    Chỉ tài khoản có vai trò Thu ngân mới được phép thực hiện thanh toán.

•    Thu ngân có quyền thêm các mã giảm giá (add voucher) vào đơn hàng.

•    Khi Thu ngân ấn thanh toán, tổng tiền của đơn đó sẽ được cộng vào doanh thu, đồng thời trạng thái bàn đó sẽ trở lại thành bàn trống.

•  Xử lý Tách đơn & Rủi ro "Âm tiền":

•    Thu ngân có thể thực hiện tách đơn ra một bàn trống.

•    Kiểm soát gian lận: Tuy nhiên, đơn bị tách sẽ bị hệ thống đánh dấu và lập tức gửi thông báo theo thời gian thực (realtime) cho Chủ/Quản lý.

•    Quy trách nhiệm: Khi hết ca của tài khoản thu ngân đó, đơn bị tách (nếu chưa thanh toán) sẽ bị in ra và tính là âm số tiền đối với đơn đã bị tách. Chỉ có Chủ doanh nghiệp mới có quyền vô hiệu hóa khoản âm đơn đó.

15.4.2: Phát triển nhân viên thu ngân

- Có đầy đủ các chức năng mà nhân viên order có

- Có thể thanh toán đơn và tách đơn ra bàn trống ( nhưng đơn bị tách sẽ bị đánh dấu và gửi thông báo theo thời gian thực cho chủ/quản lý ) khi hết ca của tài khoản thu ngân đó thì đơn sẽ bị in ra và tính là âm số tiền đơn đã bị tách ( chỉ có chủ doanh nghiệp mới có thể vô hiệu khoản âm đơn đó ). Khi ấn thanh toán thì tổng tiền đơn đó sẽ cộng vào doanh thu, và đơn đó sẽ trống trở lại

15.4.3: Phát triển nhân viên bếp

- khi chuyển tài khoản nhân viên sang trạng thái nhân viên bếp thì giao diện sẽ đơn giản hóa, chia màn hình làm 2 phần chính, 1 bên nhận đơn, 1 bên là các đơn đã làm xong

15.5. Phân hệ Nhà cung cấp (Portal Chuỗi cung ứng - Supplier Portal)

Phân hệ này đóng vai trò là cổng giao tiếp B2B chuyên biệt kết nối trực tiếp các đối tác cung ứng vật tư vào hệ thống. Phân hệ được thiết kế theo chủ trương "đề cao tính minh bạch, giảm sự phụ thuộc và tính trung thực của nhân viên" , giúp doanh nghiệp chủ động nắm bắt biến động giá cả thị trường , tối ưu hóa danh mục kho bãi và triệt tiêu các hành vi gian lận nâng giá khống giữa nhân sự nội bộ và đối tác giao hàng.

? Nhà cung cấp tương tác với hệ thống SaaS đa doanh nghiệp này như thế nào?

Giải pháp: Hệ thống triển khai kiến trúc Multi-tenancy theo mô hình Shared Database - Shared Schema. Thực thể nhà cung cấp được lưu trữ tập trung tại bảng suppliers và được cô lập dữ liệu theo từng restaurant_id bằng cơ chế Global Scope của Laravel. Tài khoản đại diện của Nhà cung cấp (được phân quyền qua Spatie Permission ) sẽ truy cập thông qua một giao diện Frontend Vue.js SPA riêng biệt , thực hiện các tác vụ được chỉ định mà không thể can thiệp vào logic bán hàng nội bộ của nhà hàng.

Chức năng và Khả năng xử lý của Tài khoản Nhà cung cấp (supplier_admin)

•    Khả năng tự quản lý và niêm yết bảng giá (Catalog & Price Control): * Khởi tạo, sửa đổi và đánh dấu trạng thái hoạt động của danh mục nguyên vật liệu cung ứng (tên, quy cách đóng gói, đơn vị tính ).

o    Cấu hình niêm yết giá bán và cập nhật giá mới theo thời gian thực. Hệ thống tự động lưu trữ dữ liệu này dưới dạng lịch sử biến động giá (supplier_price_histories) để làm cơ sở đối soát.

•    Khả năng tiếp nhận và xử lý đơn đặt hàng hằng ngày (PO Fulfillment):

o    Tiếp nhận các yêu cầu mua nguyên liệu hằng ngày từ bộ phận kho của nhà hàng gửi đến thông qua hệ thống tín hiệu Realtime của Laravel Reverb.

o    Chuyển đổi trạng thái vận đơn theo Workflow chuẩn hóa: Đã tiếp nhận → Đang chuẩn bị hàng → Đang vận chuyển → Đã hạ hàng tại kho.

•    Khả năng số hóa chứng từ giao nhận (E-Invoicing):

o    Đính kèm hình ảnh hóa đơn giấy hoặc tải lên tệp hóa đơn điện tử trực tiếp trên giao diện khi thực hiện giao hàng. Tệp tin được đẩy thẳng lên Cloud Storage (MinIO/S3) để lưu vết liên kết kiểm toán.

Các phân hệ chức năng hệ thống bổ trợ

1. Hệ thống Đặt hàng Nguyên liệu hằng ngày (Daily Purchase Order Lifecycle)

•    Thao tác đơn giản hóa: Hằng ngày, nhân sự phụ trách kho (Inventory Staff) hoặc Quản lý (Manager) truy cập vào menu niêm yết của nhà cung cấp, chọn nguyên liệu và chỉ cần nhập số lượng cần mua (do danh mục sản phẩm đã được cấu hình sẵn ) rồi nhấn gửi đơn.

•    Xử lý nền bất đồng bộ (Redis Queue): Lệnh đặt hàng PO ngay khi được phê duyệt sẽ được đẩy ngầm vào Redis Queue để kích hoạt thông báo Realtime sang màn hình của nhà cung cấp ngay lập tức.

1. Minh bạch hóa Biến động giá & Đối soát hai lần (Price Analytics & Dual-Verification)

•    Biểu đồ theo dõi Biến động giá: Hệ thống tự động tổng hợp lịch sử thay đổi giá của nhà cung cấp, sử dụng Python Microservice (FastAPI kết hợp Pandas) để trích xuất biểu đồ phân tích biên độ tăng/giảm giá theo tuần/tháng. Giúp Chủ nhà hàng (Owner) chủ động nắm bắt xu hướng thị trường để điều chỉnh cấu hình giá vốn (cost_price) và giá bán món ăn kịp thời.

•    Xác thực đối soát 2 lần (Dual-Verification Audit): Khi hàng đến kho, nhân viên kho thực hiện kiểm đếm thực tế. Hệ thống tự động chạy thuật toán đối chiếu chéo 3 bên: Giá niêm yết ban đầu trên hệ thống ↔ Giá ghi trên hóa đơn số tải lên ↔ Số lượng thực tế bàn giao. Mọi sai lệch về dòng tiền hoặc số lượng sẽ bị hệ thống đóng băng giao dịch , tự động ghi vết JSON vào audit_logs (old_values và new_values) và phát cảnh báo gian lận (Fraud Detection) trực tiếp về thiết bị của chủ nhà hàng.

________________________________________

15.6. Phân hệ Khách của quán & Quy trình xác thực gọi món hai lớp (Customer QR-Ordering Lifecycle & Double-Check Verification)

Phân hệ này là một ứng dụng Frontend Vue.js SPA siêu nhẹ , cho phép khách hàng tự truy cập, xem thực đơn và gọi món thông qua mã QR code động được dán cố định tại từng bàn ăn. Nhằm bảo vệ toàn vẹn dữ liệu hệ thống, ngăn chặn triệt để tình trạng "đơn hàng ảo" hoặc phá hoại hệ thống từ xa, luồng đặt món QR được rào chắn bằng quy trình duyệt đệm trung gian trước khi ghi nhận chính thức vào Cơ sở dữ liệu lõi.

? Làm sao để khách hàng đặt món an toàn, chính xác mà không làm rò rỉ dữ liệu hoặc xung đột kho?

Giải pháp: Khi khách hàng thao tác quét mã và nhấn đặt đồ, hệ thống tuyệt đối không tạo trực tiếp bản ghi vào bảng orders. Toàn bộ dữ liệu giỏ hàng cấu trúc JSON sẽ được lưu trữ tạm thời tại một vùng nhớ đệm (sử dụng Redis Cache hoặc bảng đệm temporary_orders với thời gian sống TTL nhất định). Đơn hàng đệm này được neo giữ dưới trạng thái waiting_verification (Chờ nhân sự xác thực).

Chức năng và Khả năng xử lý của các Tài khoản trong luồng QR-Ordering

[Khách hàng quét QR] ──(Gửi đơn đệm)──> [Redis Cache / Temporary Storage]

                                                                                                   │

                                                                          (Phát tín hiệu Realtime < 500ms)

                                                                                                   │

                                                                                                   ▼

                                                                    ┌──────────────────┐

                                                                    │  Popup báo động Realtime trên │

                                                                    │     giao diện Vue.js SPA            │

                                                                    └───────┬──────────┘

                                                                                           │

                                                                             (Phân phối đồng thời)

                                                                                           │

                    ┌────────────────────────┴──────────┐

                     ▼                                                                                               ▼

       【Tài khoản Phục vụ / Order】                                       【Tài khoản Thu ngân】

- Khả năng: Xem chi tiết đơn đệm.                           - Khả năng: Xem chi tiết đơn đệm.

- Hành động: Ra tận bàn đối chiếu thực tế.                  - Hành động: Ra tận bàn đối chiếu thực tế.  │                                                                                                                                            │                     └──── ────────────────────────┬────────────────────┘

                                                                                    │

                                                                       (Hành động của Nhân sự)

                                                                                     │

                    ┌──────────────────────┼─────────────────┐

                     ▼ (Nút bấm: XÁC NHẬN)         ▼ (Nút bấm: HỦY YÊU CẦU)      ▼ (Sau 2 phút: QUÁ HẠN PHẢN HỒI)

       ┌───────────────┐┌───────────────┐┌───────────────┐

       │ Kích hoạt DB Transaction││Hệ thống xóa sạch đơn đệm│ Hệ thống ngầm tự kích hoạt│

       │  Chính thức ghi bảng lõi  ││  Không ghi bảng orders lõi││  Event chuyển cấp cứu xét │

       │    Chuyển dữ liệu xuống   ││   Ghi vết JSON log và gửi ││   Báo động đỏ Realtime về │

       │     Màn hình nhà Bếp       ││cảnh báo thẳng cho Manager││    màn hình của Manager│

       └───────────────┘ └───────────────┘ └───────────────┘

1. Tài khoản Khách hàng của quán (customer)

•    Chức năng hiển thị và khám phá thực đơn: Xem menu, mô tả chi tiết sản phẩm (hương vị, nguyên liệu món ăn) , nắm bắt giá cả công khai. Danh mục tự động cập nhật trạng thái "Hết hàng" qua WebSocket nếu kho vật lý lõi không đủ đáp ứng công thức định lượng (product_recipes).

•    Chức năng gửi yêu cầu đặt món (Self-Ordering Request): Tự chọn món, tăng giảm số lượng trực tiếp trong giỏ hàng và phát lệnh gửi yêu cầu đặt đồ đệm lên hệ thống.

•    Chức năng theo dõi và tương tác: Khách hàng sử dụng giao diện SPA hiển thị trên di động để theo dõi trạng thái món ăn của mình theo thời gian thực thông qua Laravel Echo: Chờ nhân viên duyệt → Bếp đang chế biến → Đã lên món. Khách có khả năng nhấn nút "Gọi nhân viên" hoặc "Yêu cầu thanh toán" ngay tại giao diện.

1. Tài khoản Nhân viên Order (order_staff) & Nhân viên Thu ngân (cashier)

•    Chức năng tiếp nhận cảnh báo Realtime: Nhận Popup thông báo đẩy và âm thanh chuông báo động thời gian thực trên màn hình máy POS/Tablet ngay khi có khách quét QR gửi đơn đệm. Thông báo hiển thị đầy đủ thông số: Số lượng món, giá tiền từng món, tổng tiền hóa đơn, số bàn/tầng thực hiện, thời gian bấm lệnh.

•    Khả năng xác thực và duyệt đơn (Verification & Conversion): Sau khi nhận thông báo, nhân viên di chuyển ra tận bàn khách để đối chiếu trực tiếp. Nhân viên có quyền ấn nút "Xác nhận" trên giao diện ứng dụng Vue.js SPA. Lệnh này giải phóng đơn đệm, kích hoạt vòng đời DB::transaction để khởi tạo đơn hàng chính thức vào bảng orders , trừ kho đệm và đẩy thẳng dữ liệu realtime xuống màn hình chuyên dụng của Bếp.

•    Khả năng hủy và gắn cờ đơn hàng ảo (Anti-Spam Rejection): Nhân viên có quyền ấn nút "Hủy yêu cầu" nếu xác định đây là đơn hàng không có thật (khách bấm đùa, bàn trống nhưng bị quét mã phá hoại từ xa). Lệnh hủy sẽ xóa sạch đơn đệm ra khỏi RAM của Redis , ngăn chặn rác dữ liệu ở database lõi. Tuy nhiên, thông tin hủy này sẽ lập tức được hệ thống đóng gói và phát tín hiệu báo cáo ngược lên tài khoản của Quản lý để tra soát minh bạch.

1. Tài khoản Quản lý nhà hàng (manager)

•    Chức năng can thiệp khẩn cấp quá hạn (Escalation Alarm Handling): Khi một đơn hàng QR được khách gửi lên, hệ thống tự động khởi tạo một tác vụ theo dõi lùi bước (Delay Job) trong Redis Queue với chu kỳ sống (TTL) là 2 phút. Nếu quá 2 phút mà tài khoản nhân viên order hoặc thu ngân không thực hiện phản hồi (xác nhận hoặc hủy), hệ thống tự động kích hoạt Event chuyển cấp. Màn hình của Quản lý sẽ hiển thị một thông báo khẩn cấp (màu đỏ, ưu tiên cao) để họ xuống tận bàn kiểm tra, trực tiếp xác nhận cho khách hoặc xử lý thái độ làm việc của nhân viên ca trực.

•    Chức năng giám sát lịch sử hủy yêu cầu (Rejected Logs Monitoring): Xem danh sách toàn bộ các yêu cầu gọi món QR bị nhân viên ấn hủy trong ngày (hiển thị rõ số bàn, danh sách món bị hủy, thời gian hủy và tên nhân viên thực hiện hủy) để đối chiếu, ngăn chặn tình trạng nhân viên tự ý hủy đơn thật của khách nhằm trục lợi hoặc che giấu doanh thu.

1. AI hỗ trợ nâng cao doanh thu tại bàn (Smart Upselling Engine)

•    Hệ thống tích hợp Microservice của Python (FastAPI) chạy ngầm. Ngay tại thời điểm nhân viên cầm máy POS/máy tính bảng ra bàn xác nhận món ăn cho khách , giao diện của nhân viên sẽ hiển thị thêm một gợi ý thông minh từ thuật toán AI (Scikit-learn) dựa trên lịch sử mua hàng : "AI đề xuất: Khách gọi Lẩu, mời dùng thêm Coca-Cola hoặc Mì thả lẩu để nhận chiết khấu 10%". Giúp nhân viên có cơ sở tăng tính thuyết phục khi mời chào khách, tối ưu hóa lợi nhuận thực tế trên từng bàn ăn.

15.7. Phân hệ Hóa đơn điện tử và Tích hợp Cơ quan Thuế (E-Invoicing Integration Service)

Nhằm tuân thủ quy định pháp luật và hiện đại hóa giao dịch tài chính, hệ thống Aventura tích hợp sẵn giải pháp hóa đơn điện tử tự động hóa hoàn toàn.

•   Tự động sinh dữ liệu XML chuẩn Thông tư 78/2021/TT-BTC: Khi thu ngân hoàn tất thanh toán cho đơn hàng, hệ thống tự động gọi EInvoiceService để biên dịch toàn bộ dữ liệu đơn hàng thành tệp dữ liệu XML theo đúng định dạng chuẩn do Tổng cục Thuế ban hành (Quyết định 1450/TCT).

•   Tách thuế VAT tự động: Hệ thống tự động bóc tách thuế suất GTGT 8% (hoặc thuế suất hiện hành đối với ngành hàng ăn uống F&B) để tính toán chính xác doanh thu trước thuế và số thuế GTGT đầu ra tương ứng.

•   Lưu trữ đối soát chéo: File XML hóa đơn sau khi được tạo sẽ được lưu trữ an toàn trên Cloud Storage (S3/R2/MinIO) để phục vụ việc tải về và tích hợp trực tiếp với chữ ký số doanh nghiệp cũng như các nhà cung cấp dịch vụ HĐĐT đầu mối (như Viettel, VNPT, MISA meInvoice).

15.8. Phân hệ Quản lý & Xếp chồng Khuyến mại (Advanced Promotion Stacking Engine)

Để giải quyết bài toán tiếp thị phức tạp mà vẫn bảo vệ được biên lợi nhuận ròng của quán, Aventura thiết kế một công cụ tính toán khuyến mại đa tầng.

•   Cơ chế kiểm soát lồng ghép khuyến mại (Promotion Stacking): Hệ thống sử dụng PromotionStackingService để điều phối thứ tự ưu tiên áp dụng giữa: Khuyến mãi món ăn -> Giảm giá danh mục sản phẩm -> Mã Voucher giảm giá hóa đơn -> Ưu đãi từ thứ hạng thẻ thành viên.

•   Ngăn ngừa lạm dụng chiết khấu: Thuật toán tự động tính toán tổng số tiền giảm trừ tối đa trên một đơn hàng, ngăn chặn tình huống nhân viên áp dụng chồng chéo nhiều loại mã làm phát sinh đơn hàng 0đ hoặc gây lỗ chéo cho nhà hàng.

•   Tích lũy & Sử dụng Điểm linh hoạt: Kết hợp PromotionTriggerService để tự động kích hoạt quà tặng/voucher dựa trên hóa đơn hiện thời và cấp hạng thẻ Loyalty hiện tại của khách hàng.

15.9. Phân hệ Bản đồ nhiệt và Phân tích Không gian (Geo-spatial & Order Heatmap Analytics)

Phục vụ nhu cầu mở rộng quy mô kinh doanh và tối ưu hóa hậu cần giao hàng, hệ thống thu thập và xử lý tọa độ địa lý của các đơn hàng.

•   Dựng bản đồ nhiệt giao hàng (Order Heatmap): GeoAnalyticsService tự động phân tích và gom cụm các tọa độ địa lý (GPS) nhận từ delivery_details của các đơn hàng trực tuyến đã giao thành công trong vòng 30 ngày.

•   Tối ưu hóa chiến lược chuỗi: Dữ liệu được cache an toàn trên Redis để hiển thị trực quan bản đồ nhiệt mật độ đơn hàng và phân vùng doanh thu theo khu vực địa lý cho chủ nhà hàng (Owner). Giúp chủ quán dễ dàng đưa ra quyết định mở thêm chi nhánh mới ở khu vực tập trung đông khách hàng mục tiêu, hoặc tối ưu hóa bán kính giao hàng của chi nhánh hiện tại.

15.10. Phân hệ Dự báo Nhu cầu theo Thời tiết và Mùa vụ (Weather-based Demand Forecasting)

Tính năng thông minh giúp tối thiểu hóa việc lãng phí thực phẩm hoặc cháy kho nguyên liệu do sự thay đổi của thời tiết.

•   Tích hợp OpenWeatherMap API: WeatherForecastService tự động kết nối API thời tiết thời gian thực để trích xuất dự báo nhiệt độ, lượng mưa của 7 ngày kế tiếp dựa trên tọa độ GPS đã cấu hình của từng chi nhánh nhà hàng.

•   Mô hình dự đoán nhu cầu nguyên liệu: Hệ thống liên kết dữ liệu bán hàng 30 ngày qua của chi nhánh và đối chiếu với thời tiết hiện tại. Nếu có biến động thời tiết (như trời lạnh đột ngột hoặc mưa kéo dài), thuật toán Python sẽ tính toán hệ số tác động cầu (ví dụ: món Lẩu/súp tăng 40%, đồ uống đá lạnh giảm 30%) để đưa ra gợi ý nhập kho nguyên vật liệu chính xác nhất cho quản lý chi nhánh.

15.11. Phân hệ Phân tích Cảm xúc Phản hồi Khách hàng (Lexicon-based Sentiment Analysis)

Chủ động kiểm soát chất lượng phục vụ và xử lý khủng hoảng truyền thông nội bộ từ sớm.

•   Thuật toán Lexicon-based cho tiếng Việt F&B: Hệ thống tích hợp SentimentAnalysisService chuyên trách chấm điểm cảm xúc các bình luận, phản hồi của khách hàng. Thuật toán phân tích chuỗi văn bản dựa trên từ điển hàng trăm từ khóa tích cực (ngon, nhanh, sạch...) và tiêu cực (tệ, chán, dơ, thái độ...) có ngữ cảnh F&B Việt Nam.

•   Xử lý từ phủ định: Nhận diện chính xác ngữ nghĩa phủ định như "không ngon", "không sạch", "không hài lòng" để tránh sai lệch điểm số.

•   Báo động đỏ phản hồi tiêu cực: Nếu phản hồi có điểm cảm xúc bị đánh giá là Tiêu cực (Negative), hệ thống tự động gắn cờ đỏ cảnh báo và gửi thông báo khẩn cấp (Push Notification) đến Quản lý chi nhánh và Chủ nhà hàng thông qua Redis Queue để họ trực tiếp xử lý bồi thường hoặc chấn chỉnh nhân sự ca trực.

15.12. Phân hệ Hồ sơ Khách hàng & Phân tích RFM (CDP - Customer Data Platform)

Hệ thống CDP mini tích hợp ngay trong Aventura giúp cá nhân hóa trải nghiệm khách hàng và tối ưu hóa tỷ lệ quay lại.

•   Thu thập hành vi số (Digital Footprint): CdpService theo dõi và ghi lại toàn bộ hành trình tương tác của khách hàng từ lúc quét mã QR (view_product, search_menu, add_to_cart, checkout). Dữ liệu này được gom cụm theo phiên làm việc (session) và tự động đồng bộ khi khách hàng định danh.

•   Phân nhóm RFM (Recency, Frequency, Monetary): Tự động tính toán các chỉ số: Số ngày kể từ đơn hàng cuối cùng (Recency), Tần suất mua hàng (Frequency) và Tổng chi tiêu (Monetary) của từng khách hàng.

•   Gợi ý tiếp thị cá nhân hóa: Python Service phân tích dữ liệu RFM để phân loại khách hàng thành các nhóm: "Khách hàng VIP" (giữ chân đặc biệt), "Khách hàng có nguy cơ rời bỏ" (gửi voucher khuyến mãi tự động), "Khách hàng mới tiềm năng".

15.13. Cổng tích hợp Cổng thanh toán Tự động (Automated Payment Gateways & Sepay)

Đảm bảo dòng tiền được đối soát tự động theo thời gian thực và loại bỏ hoàn toàn các lỗi nhập tay hóa đơn thủ công.

•   Tích hợp sâu dịch vụ SePay Checkout: SepayCheckoutService cung cấp cổng thanh toán quét mã QR động tự động chèn mã giao dịch duy nhất cho từng yêu cầu gia hạn/nâng cấp gói SaaS của nhà hàng.

•   Xử lý Webhook tức thì: Nhận tín hiệu Webhook từ cổng thanh toán tự động, so khớp mã chuyển khoản (transaction_code) để kích hoạt ngay lập tức gói cước, gia hạn thời gian hết hạn (expired_at) và thay đổi trạng thái của Tenant mà không cần nhân sự duyệt thủ công.

•   Lưu giữ lịch sử giao dịch: Lưu vết chi tiết toàn bộ hóa đơn thanh toán phục vụ việc xuất hóa đơn và khai báo đối soát tài chính SaaS.

15.14. Phân hệ Giám sát Dịch vụ & Cơ chế Chịu lỗi (Service Monitor & Circuit Breaker)

Đảm bảo hệ thống SaaS luôn hoạt động bền bỉ, ổn định ngay cả khi các dịch vụ bên ngoài bị sập hoặc gặp sự cố quá tải.

•   Cơ chế ngắt mạch tự động (Circuit Breaker): Để ngăn chặn lỗi cascading (sập dây chuyền), hệ thống triển khai CircuitBreaker cho các kết nối liên microservice (như gọi sang Python API, Mail Server hoặc cổng thanh toán).

•   Máy trạng thái 3 cấp (Closed, Open, Half-Open): Khi số lần kết nối lỗi vượt quá ngưỡng cho phép (ví dụ 3 lần liên tiếp), mạch chuyển sang trạng thái OPEN, lập tức chặn các cuộc gọi API tiếp theo và định tuyến trực tiếp đến hàm xử lý dự phòng (fallback) để giữ cho giao diện người dùng không bị đơ/timeout. Sau khoảng thời gian cooldown, mạch tự động chuyển sang HALF_OPEN để thăm dò (probe) và khôi phục trạng thái CLOSED nếu dịch vụ đích đã ổn định trở lại.

•   Hệ thống giám sát hiệu năng Pulse & Horizon: ServiceMonitorService giám sát liên tục tình trạng RAM/CPU, backlog của Redis Queue và trạng thái hàng đợi để tự động gửi thông tin cảnh báo qua Webhook Discord/Telegram cho đội ngũ kỹ thuật khi có dấu hiệu quá tải hệ thống.

15.15. Phân hệ Báo cáo & Thống kê Nghiệp vụ Chuyên sâu (Advanced Analytical Reports)

Nhằm cung cấp cho Chủ nhà hàng (Owner) và Quản lý (Manager) cái nhìn toàn diện và chính xác nhất về mọi khía cạnh vận hành, hệ thống tích hợp bộ công cụ báo cáo phân tích chuyên sâu được hỗ trợ bởi Python Microservice và dữ liệu đối soát thực tế.

1. Báo cáo Hiệu suất Thực đơn & Ma trận BCG (Menu Analytics & BCG Matrix)

•   Phân nhóm ma trận BCG (Boston Consulting Group): MenuInsightService tự động xếp hạng toàn bộ sản phẩm trong thực đơn vào 4 nhóm dựa trên sản lượng bán ra (Volume) và biên lợi nhuận (Margin):

    o   Ngôi sao (Stars - Lợi nhuận cao, Bán chạy): Đề xuất giữ nguyên giá và vị trí bắt mắt trên thực đơn.

    o   Bò sữa (Plowhorses - Lợi nhuận thấp, Bán chạy): Đề xuất thương thảo giảm giá nguyên liệu đầu vào, hoặc tăng giá bán nhẹ (3-5%).

    o   Câu đố (Puzzles - Lợi nhuận cao, Bán chậm): Đề xuất ghép món vào các COMBO khuyến mại để kích cầu hoặc tăng vị trí hiển thị.

    o   Thú cưng (Dogs - Lợi nhuận thấp, Bán chậm): Cân nhắc loại bỏ hoàn toàn hoặc thay đổi công thức chế biến.

•   Cảnh báo biên lợi nhuận thấp (Low Margin Alerts): Tự động gắn cờ cảnh báo đối với các món ăn có biên lợi nhuận ròng rơi xuống dưới ngưỡng an toàn (25%) để chủ quán kịp thời điều chỉnh giá vốn hoặc giá bán.

1. Báo cáo Kết quả Kinh doanh & Lợi nhuận ròng (P&L - Profit and Loss Reporting)

•   Tính toán giá vốn hàng bán (COGS): ProfitLossService tổng hợp chi phí nguyên vật liệu tiêu hao thực tế từ công thức định lượng (product_recipes) kết hợp với giá vốn bình quân (average_cost) do hệ thống tính toán.

•   Khấu trừ chi phí vận hành & nhân sự: Báo cáo tự động cấn trừ chi phí lương nhân viên, chi phí thất thoát kho bãi, và các khoản đền bù/phạt âm két ca trực để tính toán ra Lợi nhuận ròng (Net Profit) thực tế của nhà hàng.

1. Báo cáo Hiệu suất & Chỉ số KPI Nhân viên (Employee KPI Report)

•   Tự động tính toán điểm số năng lực nhân viên: KpiService tự động thu thập và xử lý số liệu hiệu suất theo từng vai trò cụ thể:

    o   Phục vụ (Waiter): Đo lường thời gian phục vụ trung bình (serving time), tổng số bàn phục vụ thành công, và tỷ lệ phản hồi tích cực từ khách hàng.

    o   Bếp/Pha chế (Kitchen): Đo lường thời gian chế biến món, tỷ lệ kiểm soát hao hụt kho, và điểm đánh giá chất lượng món ăn.

    o   Thu ngân (Cashier): Đo lường tốc độ xử lý thanh toán, tần suất chênh lệch quỹ két ca trực, và tỷ lệ upselling (mời khách áp dụng voucher/gọi thêm món).

•   Cơ sở xếp lịch và tăng lương: Điểm số KPI là bằng chứng minh bạch giúp chủ nhà hàng đánh giá nhân sự, tự động cộng thưởng chuyên cần hoặc khấu trừ vi phạm vào bảng lương cuối tháng.

1. Báo cáo Phân tích Hao hụt & Rác thải thực phẩm (Inventory Waste Analytics)

•   Tính toán tỷ lệ hao hụt (Waste Ratio): WasteAnalyticsService tổng hợp toàn bộ các giao dịch xuất kho hao hụt (type = 'waste') để tính toán tỷ lệ chi phí hao hụt trên tổng doanh thu.

•   Đánh giá định mức (Benchmarking): Phân loại tình trạng kiểm soát hao hụt của chi nhánh theo ba mức độ: Xuất sắc (≤5% doanh thu), Bình thường (5-10%), và Nguy cấp (>10% - cần có sự can thiệp của quản lý).

•   Top nguyên liệu hao hụt: Liệt kê chi tiết danh sách các nguyên vật liệu bị lãng phí nhiều nhất kèm theo giá trị quy đổi thành tiền mặt để chủ quán điều chỉnh quy trình bảo quản hoặc định lượng món ăn.

1. Báo cáo Biến động Giá & Đánh giá Nhà cung cấp (Price Elasticity & Procurement Analysis)

•   Biểu đồ xu hướng giá: PriceAnalyticsService kết nối với Python để phân tích lịch sử biến động giá của từng nguyên vật liệu từ các nhà cung cấp khác nhau (Supplier Price History).

•   Khuyến nghị mua sắm thông minh: Đưa ra cảnh báo và khuyến nghị hành động cụ thể khi giá của một mặt hàng có xu hướng tăng đột biến, giúp chủ quán chủ động ký hợp đồng dài hạn nhằm bao thầu giá tốt hoặc tìm kiếm đối tác cung ứng thay thế.

1. Báo cáo Hiệu quả Chiến dịch Tiếp thị (Promotion ROI Analytics)

•   Đo lường doanh thu từ khuyến mãi: PromotionAnalyticsService phân tích hiệu quả hoạt động của các chương trình giảm giá và mã voucher.

•   Tính toán chỉ số ROI của chiến dịch: Xác định rõ tỷ lệ doanh thu tăng thêm so với chi phí giảm giá đã bỏ ra, giúp nhà hàng tối ưu hóa các chiến dịch tiếp thị tiếp theo, tránh việc chạy các chương trình khuyến mãi kém hiệu quả gây lãng phí ngân sách.

15.16. Phân hệ Giao hàng & Điều phối Shipper (Smart Delivery & Dispatch Hub)

Nhằm phục vụ kênh bán hàng trực tuyến và tối ưu hóa hậu cần logistics, hệ thống tích hợp bộ công cụ quản lý giao hàng thông minh:

•   Tự động gợi ý Shipper (`suggestShippers`): Dựa trên khoảng cách định vị GPS và trạng thái rảnh/bận của nhân viên giao hàng để tự động đề xuất người nhận đơn tối ưu.

•   Gom đơn theo lô (`suggestBatches`, `createBatch`): Thuật toán tự động gom các đơn hàng có chung tuyến đường hoặc cùng khu vực địa lý giao hàng thành một lô (batch) để một shipper giao hàng loạt, tiết kiệm 40% chi phí vận hành.

•   Tối ưu hóa lộ trình (Route Optimization): Python Service tính toán thứ tự các điểm giao hàng trên bản đồ số để tối ưu hóa quãng đường di chuyển ngắn nhất cho Shipper.

•   Ứng dụng Shipper PWA: Giao diện web di động dành riêng cho nhân viên giao hàng để cập nhật vị trí GPS thời gian thực (`updateLocationBatch`) và cập nhật tiến độ giao nhận (`updateItemStatus`).

15.17. Phân hệ Xếp lịch thông minh & Kiểm soát nghỉ phép (AI Roster & HR Compliance)

Đảm bảo công bằng xã hội và tuân thủ các quy định về an toàn lao động trong vận hành F&B:

•   Quy tắc 11 giờ nghỉ ngơi: Khi Quản lý phân lịch trực (`ScheduleController`), hệ thống tự động kiểm tra khoảng thời gian trống giữa ca làm việc cũ và ca làm việc mới của từng nhân viên. Nếu thời gian nghỉ ngơi dưới 11 giờ liên tục, hệ thống chặn lưu ca trực và báo lỗi vi phạm luật lao động.

•   Hạn ngạch xin nghỉ phép (Leave Quota): Chặn không cho phép số lượng nhân sự cùng một vai trò tại chi nhánh nghỉ phép trùng ngày vượt quá 30% tổng số nhân sự của vai trò đó, tránh nguy cơ sập dây chuyền phục vụ.

•   Gợi ý đổi ca thông minh (`getSwapSuggestions`): Khi nhân viên gửi yêu cầu xin đổi ca (Shift Swap), hệ thống tự động rà soát cơ sở dữ liệu và đề xuất danh sách đồng nghiệp rảnh rỗi có cùng chuyên môn, đảm bảo không bị trùng ca trực và không vi phạm quy tắc nghỉ 11 giờ.

15.18. Phân hệ Quản lý Lương & Tạm ứng (Payroll & Financial Control)

Số hóa toàn bộ quy trình hành chính và gắn chặt trách nhiệm tài chính của từng nhân sự:

•   Giới hạn tạm ứng lương (Salary Advance Limit Check): Chặn không cho phép nhân viên yêu cầu tạm ứng lương giữa tháng vượt quá 50% số tiền lương thực tế họ đã kiếm được tính đến thời điểm yêu cầu (tính bằng công thức: `(Lương cơ bản / tổng ngày trong tháng) * số ngày công đã chấm công thực tế`).

•   Khấu trừ phạt tự động (`salary_adjustments`): Tích hợp trực tiếp kết quả chốt ca (đối soát két tiền mặt của Thu ngân) và kiểm kho (hao hụt nguyên liệu của Bếp). Số tiền chênh lệch âm két hoặc hao hụt kho vượt định mức sẽ tự động tạo thành khoản phạt trừ trực tiếp vào lương cuối tháng.

•   Giải quyết tranh chấp lương (Dispute Adjustment): Cho phép nhân sự gửi khiếu nại đối với các khoản phạt lương ngay trên hệ thống để Chủ quán thực hiện đối soát và duyệt lại thủ công.

15.19. Phân hệ Menu Engineering & Thử nghiệm giá A/B (Menu Insights & A/B Testing)

Công cụ hỗ trợ ra quyết định giá bán dựa trên khoa học dữ liệu và hành vi thực tế của thực khách:

•   Thống kê điểm số ma trận BCG: MenuEngineeringController tự động tính điểm và trực quan hóa sơ đồ BCG của thực đơn.

•   Thiết lập thử nghiệm giá A/B (Price A/B Testing): Cho phép Owner chạy chiến dịch thử nghiệm thay đổi giá bán (`storePriceTest`). Hệ thống tự động chia lưu lượng truy cập (50% khách hàng quét QR thấy giá cũ, 50% thấy giá mới) và theo dõi sản lượng bán ra.

•   Phân tích độ co giãn (Price Elasticity): Python Service chạy phân tích hồi quy đánh giá tác động của thay đổi giá đối với doanh số. Hệ thống tự động đưa ra khuyến nghị chọn mức giá tối ưu nhất mang lại doanh thu cao nhất để Owner duyệt áp dụng chính thức.

15.20. Phân tích chi tiết 3 Dịch vụ Python Microservices (FastAPI System Architecture)

Kiến trúc microservice chuyên biệt xử lý các bài toán nặng về dữ liệu, AI và giao tiếp ngoài:

1. Dịch vụ Phân tích & AI (Analytics Service - Port 8003/8000):

    o   Phân tích giỏ hàng (Market Basket Analysis): Pandas chạy thuật toán tìm luật liên kết để tính Support, Confidence và Lift giữa các món ăn, gợi ý tạo Combo bán kèm tăng giá trị trung bình đơn (AOV).

    o   Dự báo doanh thu & tồn kho: Chạy hồi quy tuyến tính `LinearRegression` trên 14 ngày dữ liệu tài chính gần nhất để dự đoán doanh thu ngày mai (độ tin cậy Cao/Trung bình/Thấp) và chạy Linear Regression dự báo lượng tiêu thụ nguyên liệu trong 7 ngày tới để tự tạo RFP nháp.

    o   Dự báo nhu cầu theo thời tiết: Kết nối OpenWeatherMap API để cập nhật thời tiết thực tế tại tọa độ GPS nhà hàng và tự động áp dụng hệ số nhân (multiplier) nhu cầu món ăn (trời mưa lạnh -> lẩu/súp nóng tăng 35%, đồ uống đá giảm 20%).

    o   OCR đối soát hóa đơn: Trích xuất thông tin tệp hóa đơn của nhà cung cấp và chạy thuật toán so khớp chéo 3 bên với PO gốc.

    o   Phân tích luân chuyển kho nội bộ: Đề xuất chuyển kho nguyên liệu dư thừa từ chi nhánh có coverage_days > 14 ngày sang chi nhánh thiếu hụt.

2. Dịch vụ Chatbot NLP (Chatbot Service - Port 8002):

    o   Ensemble NLP Engine: Kết hợp 4 tín hiệu chấm điểm: TF-IDF char n-gram (bắt âm tiết tiếng Việt), TF-IDF word n-gram (cụm từ), BM25Okapi (chống typo nhẹ) và Keyword Overlap (từ khóa do admin gắn) để so khớp câu hỏi FAQ với độ tin cậy cao nhất.

    o   Cố vấn doanh nghiệp (`/advisor-chat`): Gọi trực tiếp SQL engine vào CSDL MySQL để tổng hợp báo cáo tài chính hôm nay/tuần qua, Top 5 món chạy nhất, danh sách cảnh báo gian lận đang mở và dự báo doanh thu ngày mai trả về Markdown thời gian thực cho Owner.

    o   Playground diagnostics: Cho phép Super Admin kiểm thử độ tự tin (Confidence score) và duyệt xử lý các câu hỏi chưa được trả lời (unanswered queries).

3. Dịch vụ Email & AI Insights (Email Service - Port 8001):

    o   Dự báo doanh thu SaaS (MRR): Chạy Polyfit bậc 1 dự báo dòng tiền MRR/ARR 3 tháng tới của nền tảng dựa trên số lượng nâng cấp gói Pro (giá gói 499.000đ/tháng).

    o   Phân tích Churn Risk: Đánh giá điểm rủi ro rời bỏ (SaaS Churn Score) của từng nhà hàng dựa trên tần suất thao tác và số ngày dùng thử còn lại.

    o   Mailer: Tích hợp gửi email chào mừng, mã xác thực OTP đăng nhập, và email gửi tệp hóa đơn PDF tự động qua Brevo API, kết hợp driver SMTP dự phòng tại Laravel.

## 4. Các vấn đề cần lưu ý khi thiết kế và phát triển hệ thống SaaS quản lý nhà hàng bằng Laravel và Vue.js

16.1 Kiến trúc Backend & Logic Nghiệp vụ (Clean Architecture)

Để hệ thống không trở thành một "Big Ball of Mud" khi mở rộng, việc áp dụng nguyên lý Separation of Concerns là bắt buộc.

•    Cấu trúc "Chia để trị":

o    Controller: Chỉ đóng vai trò điều hướng, nhận Request, validate sơ bộ và trả về Response.

o    Service Layer: Nơi chứa logic nghiệp vụ chính (Business Logic). Ví dụ: Tính toán khuyến mãi, kiểm tra tồn kho trước khi đặt món.

o    Repository Pattern: Tách biệt logic truy vấn dữ liệu khỏi Service. Giúp dễ dàng thay đổi DB hoặc viết Unit Test.

o    Action/Job: Chia nhỏ các tác vụ đơn lẻ (như CreateOrderAction) để có thể tái sử dụng ở cả Web, API và Console.

•    Tính toàn vẹn dữ liệu (Atomicity): Sử dụng Database Transactions cho các luồng thanh toán và kho bãi.

Ví dụ: Khi khách thanh toán, hệ thống phải đồng thời: Tạo hóa đơn -> Trừ kho -> Tích điểm thành viên. Nếu một bước lỗi, toàn bộ phải rollback.

• Các logic không nên gộp hết lại, cần chia để trị:

o Controller

o Service

o Repository

o Event / Listener

Ví dụ:

Không nên xử lý toàn bộ logic tạo đơn hàng trong Controller vì sẽ rất khó bảo trì và mở rộng. Controller chỉ nên nhận request và gọi Service xử lý nghiệp vụ.

Mô hình xử lý:

Controller

↓

Service

↓

Repository

↓

Database

16.2 Tối ưu Hiệu năng & Khả năng mở rộng (Scalability)

Hệ thống cần xử lý mượt mà khi có từ $2.000$ đến $3.000$ người dùng đồng thời trong giờ cao điểm. Để đạt được điều này, các chiến lược tối ưu hóa tầng dữ liệu và hạ tầng được phân rã như sau:

1. Tối ưu hóa Cơ sở dữ liệu (Database Optimization)

•    Tuyệt đối tránh lỗi truy vấn $N+1$: Luôn áp dụng kỹ thuật Eager Loading thông qua phương thức with() để gom các câu lệnh truy vấn quan hệ thành một single query, giảm tải tối đa cho MySQL.

o    Sai (Gây ra $N+1$ queries):

o    $orders = Order::all();

o    foreach ($orders as $order) {

o        echo $order->user->name; // Mỗi vòng lặp lại tạo ra 1 query SELECT mới vào bảng users

o    }

o    Đúng (Chỉ chạy 2 queries):

o    Order::with('user')->get();

•    Chiến lược Đánh Chỉ mục (Indexing): Thiết lập chỉ mục đơn và chỉ mục hỗn hợp (Composite Index) một cách chiến lược trên các cột thường xuyên xuất hiện trong mệnh đề WHERE hoặc ORDER BY, đặc biệt là restaurant_id, status và created_at.

•    Nghiêm cấm sử dụng SELECT *: Chỉ truy vấn chính xác các trường dữ liệu cần thiết phục vụ cho logic nghiệp vụ hiện tại nhằm tiết kiệm dung lượng RAM của Server và băng thông truyền tải mạng. Luôn kết hợp phân trang (Pagination) cho các danh sách dữ liệu lớn.

o    Nên làm:

o    SELECT id, total_amount, status FROM orders WHERE restaurant_id = 1 LIMIT 20;

o    Không nên làm:

o    SELECT * FROM orders;

•    Tính toàn vẹn và nhất quán (Atomicity & Concurrency): Sử dụng Database Transactions cho các luồng nghiệp vụ phức tạp liên quan đến tiền tệ và kho bãi (Tạo hóa đơn $\rightarrow$ Trừ kho $\rightarrow$ Tích điểm). Khi có tranh chấp dữ liệu (nhiều nhân viên cùng thao tác), áp dụng cơ chế Khóa bi quan (Pessimistic Locking) để đảm bảo tính nhất quán.

1. Cơ chế Lưu trữ đệm (Caching với Redis)

Hệ thống sử dụng Redis làm In-memory Database để giảm thiểu số lượng truy vấn trực tiếp vào MySQL:

•    Application Cache: Lưu trữ các dữ liệu có tần suất đọc cực cao nhưng ít khi thay đổi như: Menu món ăn, danh mục thực đơn, cấu hình phân bàn và thiết lập riêng của từng nhà hàng.

o    Ví dụ: Menu món ăn của nhà hàng được cache lại trên Redis. Hệ thống chỉ đọc từ Redis thay vì query MySQL, giúp giảm tải đến $80\%$ áp lực cho database chính trong giờ cao điểm.

•    Dashboard & Báo cáo Cache: Cache các số liệu thống kê doanh thu tạm thời theo giờ để chủ quán xem dashboard realtime mà không cần tính toán lại hàng triệu bản ghi hóa đơn.

•    Session Cache: Cấu hình lưu trữ session người dùng trực tiếp trên Redis thay vì lưu file vật lý (file driver), tăng tốc độ xác thực và kiểm tra phiên làm việc.

1.     Hàng đợi và Xử lý nền bất đồng bộ (Queue & Background Jobs)

Hệ thống tách biệt rõ ràng tiến trình để đảm bảo tốc độ phản hồi API < 2s , đồng thời áp dụng cơ chế Seeding tự động khép kín (Món ăn - Nguyên liệu - Công thức mẫu) nhằm tối ưu trải nghiệm Onboarding cho Tenant mới. Việc này giúp chủ nhà hàng có thể chạy thử nghiệm luồng bán hàng ngay lập tức mà không gặp các lỗi dữ liệu rỗng (Null Pointer) từ các tiến trình xử lý ngầm.

•    Các tác vụ cơ bản đưa vào Queue bao gồm: Gửi email xác nhận đơn hàng, gửi thông báo đẩy (Notification), xuất tệp báo cáo định dạng PDF/Excel, đồng bộ dữ liệu kế toán và tự động tính toán lương nhân sự.

•    Nguyên tắc đồng bộ bắt buộc trong nghiệp vụ Kho: Tuyệt đối không đưa logic kiểm tra tồn kho, trừ số lượng vật lý lõi (quantity_on_hand trong bảng inventories) và việc khởi tạo bản ghi nhật ký kho (inventory_transactions) vào Queue ngầm. Toàn bộ chuỗi hành động này bắt buộc phải được xử lý đồng bộ (Synchronous) ngay tại thời điểm Thu ngân bấm thanh toán , bọc chung trong một DB::transaction của Laravel kết hợp với cơ chế Khóa (Locking) để chặn đứng nguy cơ kho bị âm do hiện tượng bán quá vạch kho (Over-selling) khi có hàng ngàn đơn hàng phát sinh cùng một thời điểm. Quy trình gắn chặt này giúp ngăn chặn hoàn toàn tình trạng lệch kho vật lý hoặc mất dấu log kiểm toán nếu hệ thống hàng đợi (Queue Worker) gặp sự sự cố sập hoặc nghẽn mạch.

•    Tận dụng Queue cho các tác vụ kho nâng cao: Hệ thống chỉ đẩy các tác vụ tính toán độc lập hoặc phân tích chuyên sâu hậu cần sang hàng đợi ngầm bao gồm: Tính toán lại biên độ giá vốn trung bình (average_cost) , đồng bộ chỉ mục tìm kiếm Meilisearch, và đóng gói dữ liệu phát một HTTP Request bất đồng bộ sang Python Microservice thông qua FastAPI để phục vụ thuật toán phân tích gian lận (Fraud Detection).

•    Công cụ hỗ trợ giám sát hạ tầng: Sử dụng kết hợp hệ sinh thái mạnh mẽ của Laravel bao gồm: Queue, Job, Redis Queue và trình quản lý giao diện trực quan Laravel Horizon để theo dõi tải, tỷ lệ lỗi và điều phối các Worker xử lý ngầm một cách thời gian thực (Realtime).

16.3 Trải nghiệm Realtime & Frontend (Vue.js)

Trong nhà hàng, tốc độ là ưu tiên số 1. Bếp cần nhận đơn ngay khi phục vụ bấm máy.

•    Kết nối Realtime (WebSockets):

o    Sử dụng Laravel Reverb kết hợp Laravel Echo: Triển khai WebSocket Server thuần PHP (tích hợp từ Laravel 11/12) chạy độc lập được quản lý bởi Supervisor để duy trì hàng nghìn kết nối mở đồng thời mà không tốn chi phí cho bên thứ ba (Pusher) hay làm phức tạp hạ tầng (Socket.IO).

o    Cấm Polling: Không dùng setInterval để gọi API liên tục, việc này sẽ gây nghẽn và làm sập server khi số lượng nhà hàng tăng lên.

•    Quản lý State (Pinia):

o    Quản lý giỏ hàng, thông tin bàn và trạng thái đơn hàng tập trung tại Frontend thông qua Pinia để tránh việc phải tải lại trang hoặc gọi API dư thừa.

•    Tối ưu Frontend:

o    Lazy Loading routes: Chỉ tải mã nguồn của module Báo cáo khi người dùng click vào mục Báo cáo để giảm thời gian tải trang ban đầu.

o    Responsive UI: Giao diện phải tương thích tốt trên máy POS (màn hình ngang cố định tại quầy) và Tablet/Phone (cho phục vụ di động).

• Cần có WebSocket để hệ thống hoạt động Realtime:

•    Cập nhật đơn hàng (Order) tức thì.

•    Đồng bộ trạng thái xử lý món ăn tại nhà bếp.

•    Phát thông báo nội bộ cho nhân viên (gọi thanh toán, gọi món mới).

•    Đồng bộ trạng thái bàn ăn (Trống / Có khách / Đang dọn) theo thời gian thực.

Ví dụ: Nhân viên phục vụ vừa tạo đơn trên điện thoại thì màn hình của đầu bếp phải hiển thị món ăn cần chế biến ngay lập tức mà không cần F5 lại trang.

Frontend Vue.js sẽ nhận dữ liệu realtime thông qua hệ sinh thái:

•    Laravel Echo: Thư viện JavaScript ở client chịu trách nhiệm lắng nghe các kênh (Channels).

•    Laravel Reverb: WebSocket Server ở Backend phát tín hiệu dựa trên giao thức bảo mật (wss://).

• Không nên xử lý realtime bằng cơ chế polling liên tục:

•    Tránh spam request lên Web Server.

•    Giảm tải tối đa cho hệ thống Database trung tâm.

•    Ưu tiên tuyệt đối kiến trúc hướng sự kiện (Event-Driven) với WebSockets.

Ví dụ:

•    Không nên: setInterval(fetchOrders, 1000) vì với hàng nghìn người dùng hoạt động cùng lúc vào giờ cao điểm, hệ thống sẽ phải hứng chịu hàng triệu request dư thừa, dẫn đến treo Server.

•    Nên: Dùng Laravel Echo để lắng nghe sự kiện cụ thể: Echo.private('restaurant.' + tenantId).listen('.OrderCreated', (e) => { ... }).

• Cần đảm bảo tính Atomicity để tránh xử lý nửa chừng:

o sử dụng Database Transaction

o rollback khi xảy ra lỗi

o đảm bảo dữ liệu nhất quán

Ví dụ:

Tạo order

↓

Trừ kho

↓

Tạo payment

Nếu lỗi ở bước payment thì cần rollback toàn bộ.

Ví dụ Laravel:

DB::transaction(function () {

...

});

• Cần xử lý concurrency:

o tránh nhiều người sửa cùng một dữ liệu

o tránh sai lệch kho và đơn hàng

o sử dụng locking hoặc transaction

Ví dụ:

2 nhân viên cùng xác nhận một order hoặc cùng trừ số lượng nguyên liệu.

• Cần có cơ chế phân quyền rõ ràng (RBAC):

o Super Admin

o Chủ nhà hàng

o Quản lý

o Nhân viên

o Bếp

o Kho

Ví dụ:

Nhân viên order không được quyền xem doanh thu hoặc quản lý tài khoản.

• Hệ thống cần hỗ trợ Multi-tenant:

o dữ liệu mỗi nhà hàng phải tách biệt

o luôn filter theo restaurant_id

o tránh lộ dữ liệu giữa các nhà hàng

Ví dụ:

Order::where('restaurant_id', auth()->user()->restaurant_id)

• Không nên hard-code role và logic:

o cần dễ mở rộng khi thêm chức năng hoặc role mới

Ví dụ:

Không nên:

if ($user->role == 'admin')

Nên:

$user->hasPermission('manage_orders')

• Cần thiết kế trạng thái rõ ràng cho hệ thống:

o pending

o confirmed

o preparing

o completed

o cancelled

Ví dụ:

Đơn hàng phải có luồng trạng thái rõ ràng để tránh xử lý sai logic.

• Database cần chuẩn hóa:

o sử dụng foreign key

o tránh dư thừa dữ liệu

o dễ mở rộng và bảo trì

Ví dụ:

Không nên lưu tên món ăn trực tiếp trong bảng orders mà nên dùng order_items liên kết products.

• Cần validate dữ liệu đầu vào:

o chống dữ liệu rác

o chống SQL Injection

o chống XSS

Ví dụ:

Không cho phép nhập giá âm hoặc script độc hại vào ô nhập liệu.

Laravel hỗ trợ:

•    Form Request Validation

•    Middleware

•    CSRF Protection

• Cần đảm bảo bảo mật hệ thống:

o mã hóa password

o Laravel Sanctum

o rate limiting

o chống spam request

o chống truy cập trái phép

Ví dụ:

Không lưu password dạng plain text trong database.

• Cần có logging và theo dõi hệ thống:

o log lỗi

o log thao tác người dùng

o log đăng nhập

o log query chậm

Ví dụ:

Khi hệ thống bị chậm cần biết query nào đang gây tải lớn.

• Cần backup dữ liệu định kỳ:

o backup database

o backup file upload

o có phương án restore dữ liệu

Ví dụ:

Nếu server lỗi vẫn có thể khôi phục dữ liệu nhà hàng.

• Hệ thống cần dễ mở rộng:

o thêm chi nhánh

o thêm role

o thêm module thanh toán

o thêm mobile app

Ví dụ:

Kiến trúc cần đủ linh hoạt để sau này tích hợp app giao hàng.

• Thiết kế API theo chuẩn RESTful:

o dễ tích hợp mobile app

o dễ bảo trì frontend/backend

o dễ mở rộng hệ thống

Ví dụ:

GET /orders

POST /orders

PUT /orders/{id}

• Cần tách frontend và backend:

o Backend Laravel API

o Frontend Vue.js

o dễ scale và nâng cấp

Ví dụ:

Frontend Vue.js gọi API từ Laravel thông qua Axios hoặc Fetch API.

• Frontend Vue.js cần quản lý state hợp lý:

o tránh gọi API dư thừa

o đồng bộ dữ liệu realtime

o quản lý giỏ hàng và order

Ví dụ:

Sử dụng Pinia hoặc Vuex để quản lý state.

• Frontend cần tối ưu tải trang:

o lazy loading component

o code splitting

o giảm thời gian tải ban đầu

Ví dụ:

Chỉ load module báo cáo khi người dùng truy cập thay vì tải toàn bộ ngay từ đầu.

• UI cần responsive:

o hỗ trợ máy POS

o tablet

o điện thoại

Ví dụ:

Nhân viên phục vụ có thể order trực tiếp bằng tablet.

• Các module cần tách rõ:

o Auth

o Restaurant

o Order

o Inventory

o Billing

Ví dụ:

Không nên để toàn bộ code trong một thư mục lớn gây khó quản lý.

• Cần có cơ chế xử lý lỗi:

o retry queue

o rollback transaction

o thông báo lỗi rõ ràng

Ví dụ:

Nếu gửi email thất bại thì Queue có thể retry tự động.

• Cần kiểm thử hệ thống:

o test chức năng

o test tải lớn

o test bảo mật

o test nhiều người dùng cùng lúc

Ví dụ:

Kiểm tra hệ thống khi có hàng nghìn request đồng thời.

• Cần chuẩn bị kiến trúc để đáp ứng:

o 100+ nhà hàng cùng sử dụng

o khoảng 10000+ người dùng hoạt động đồng thời

o lượng order realtime liên tục

Ví dụ:

Khi giờ cao điểm có nhiều nhà hàng tạo order cùng lúc hệ thống vẫn phải hoạt động ổn định.

• Server cần được thiết kế hợp lý:

o Nginx

o Laravel App

o Redis

o MySQL

o Supervisor cho Queue Worker

Ví dụ:

Redis dùng cho cache và queue để giảm tải database.

• Cần tối ưu file storage:

o không lưu file trực tiếp trong project

o sử dụng cloud storage như S3, R2 hoặc MinIO

Ví dụ:

Ảnh món ăn nên lưu trên cloud storage thay vì lưu trong source code.

• Hệ thống cần có rate limiting:

o tránh spam request

o giảm nguy cơ DDOS nhẹ

Ví dụ:

Giới hạn số lần gọi API đăng nhập trong 1 phút.

Laravel hỗ trợ:

ThrottleRequests Middleware

• Cần có monitoring hệ thống:

o theo dõi CPU/RAM

o theo dõi hiệu năng

o phát hiện bottleneck sớm

Ví dụ:

Theo dõi server để phát hiện RAM đầy hoặc CPU quá tải trước khi hệ thống bị sập.
