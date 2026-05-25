<?php

namespace Database\Seeders\System;

use App\Models\ChatbotKnowledge;
use Illuminate\Database\Seeder;

class ChatbotKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        ChatbotKnowledge::truncate();

        $items = [

            // ── ĐĂNG KÝ / TÀI KHOẢN ────────────────────────────────────────
            [
                'category' => 'dang-ky',
                'question' => 'Làm sao để đăng ký sử dụng Aventura?',
                'answer' => "Bạn có thể đăng ký hoàn toàn miễn phí tại trang chủ của chúng tôi.\n\n**Các bước thực hiện:**\n1. Nhấn nút **\"Đăng ký miễn phí\"** ở góc trên trang chủ\n2. Điền thông tin: Tên nhà hàng, Email, Mật khẩu\n3. Xác nhận email\n4. Hệ thống tự động tạo không gian quản lý riêng cho bạn trong vòng 3 giây\n\nSau khi đăng ký, bạn sẽ được **dùng thử gói Pro 7 ngày miễn phí** trước khi chuyển sang gói Free.",
                'alt_questions' => ['Tôi muốn tạo tài khoản', 'Đăng ký như thế nào', 'Tôi muốn dùng thử', 'Cách tạo tài khoản mới', 'Tôi muốn mở tài khoản'],
                'keywords' => ['đăng ký', 'tài khoản', 'tạo tài khoản', 'mở tài khoản', 'register'],
                'suggested_questions' => ['Phần mềm có miễn phí không?', 'Gói Pro và Free khác nhau như thế nào?', 'Dùng thử bao nhiêu ngày?'],
                'display_order' => 1,
            ],
            [
                'category' => 'dang-ky',
                'question' => 'Tôi quên mật khẩu, phải làm sao?',
                'answer' => "Đừng lo! Bạn có thể lấy lại mật khẩu dễ dàng:\n\n1. Vào trang đăng nhập, nhấn **\"Quên mật khẩu\"**\n2. Nhập địa chỉ email đã đăng ký\n3. Kiểm tra hộp thư  chúng tôi sẽ gửi link đặt lại mật khẩu trong vòng 2 phút\n4. Nhấn link trong email và tạo mật khẩu mới\n\n> Nếu không nhận được email, hãy kiểm tra thư mục **Spam/Junk**.",
                'alt_questions' => ['Quên mật khẩu', 'Không nhớ mật khẩu', 'Đổi mật khẩu', 'Lấy lại mật khẩu', 'Reset password'],
                'keywords' => ['mật khẩu', 'quên', 'reset', 'password', 'lấy lại'],
                'suggested_questions' => ['Làm sao để đổi email tài khoản?', 'Đăng nhập bằng Google được không?'],
                'display_order' => 2,
            ],
            [
                'category' => 'dang-ky',
                'question' => 'Tôi có thể đăng nhập bằng Google không?',
                'answer' => "**Có!** Aventura hỗ trợ đăng nhập nhanh bằng tài khoản Google.\n\nChỉ cần nhấn nút **\"Đăng nhập với Google\"** trên trang đăng nhập, chọn tài khoản Google của bạn và xác nhận quyền truy cập  không cần nhớ thêm mật khẩu nào cả.",
                'alt_questions' => ['Đăng nhập Google', 'Login bằng Google', 'Dùng tài khoản Google', 'Google sign in'],
                'keywords' => ['google', 'đăng nhập', 'login'],
                'suggested_questions' => ['Hệ thống có bảo mật 2 lớp không?', 'Dữ liệu của tôi có an toàn không?'],
                'display_order' => 3,
            ],
            [
                'category' => 'dang-ky',
                'question' => 'Một tài khoản có thể quản lý nhiều nhà hàng không?',
                'answer' => "**Có thể!** Với gói Pro, một tài khoản chủ có thể quản lý **nhiều chi nhánh** của cùng một thương hiệu nhà hàng.\n\nMỗi chi nhánh có:\n- Báo cáo doanh thu riêng\n- Danh sách nhân sự riêng\n- Kho nguyên liệu riêng\n- Sơ đồ bàn riêng\n\nTất cả đều được quản lý tập trung từ một bảng điều khiển duy nhất.",
                'alt_questions' => ['Nhiều chi nhánh', 'Quản lý chuỗi nhà hàng', 'Multi-branch', 'Nhiều cơ sở'],
                'keywords' => ['chi nhánh', 'nhiều nhà hàng', 'chuỗi', 'multi-branch'],
                'suggested_questions' => ['Gói Pro có gì đặc biệt?', 'Giá gói Pro bao nhiêu?'],
                'display_order' => 4,
            ],

            // ── GIÁ CẢ / GÓI DỊCH VỤ ──────────────────────────────────────
            [
                'category' => 'gia-ca',
                'question' => 'Phần mềm có miễn phí không? Giá bao nhiêu?',
                'answer' => "Aventura có **2 gói dịch vụ**:\n\n**🆓 Gói Free (Miễn phí mãi mãi)**\n- Tối đa 1 chi nhánh\n- Tối đa 10 bàn\n- Tối đa 5 nhân viên\n- Quản lý đơn hàng, menu, kho cơ bản\n- Không có báo cáo AI\n\n**⭐ Gói Pro**\n- Không giới hạn bàn, chi nhánh, nhân viên\n- Báo cáo AI & dự báo doanh thu\n- Phát hiện gian lận tự động\n- Hỗ trợ ưu tiên 24/7\n- Thử miễn phí 7 ngày\n\nVui lòng liên hệ để biết giá cụ thể theo nhu cầu của bạn.",
                'alt_questions' => ['Giá bao nhiêu', 'Có gói free không', 'Mất bao nhiêu tiền', 'Phí sử dụng', 'Chi phí', 'Bảng giá'],
                'keywords' => ['giá', 'miễn phí', 'phí', 'free', 'pro', 'gói', 'tiền'],
                'suggested_questions' => ['Dùng thử bao nhiêu ngày?', 'Gói Pro có gì hơn gói Free?', 'Thanh toán bằng hình thức nào?'],
                'display_order' => 10,
            ],
            [
                'category' => 'gia-ca',
                'question' => 'Dùng thử bao nhiêu ngày? Có cần nhập thẻ không?',
                'answer' => "Bạn được **dùng thử gói Pro đầy đủ tính năng trong 7 ngày** hoàn toàn miễn phí.\n\n✅ **Không cần nhập thông tin thẻ tín dụng**\n✅ Không cần ký hợp đồng\n✅ Hủy bất cứ lúc nào\n\nSau 7 ngày, nếu không nâng cấp, tài khoản tự động chuyển về gói Free  **dữ liệu của bạn vẫn được giữ nguyên**.",
                'alt_questions' => ['Dùng thử', 'Trial', 'Thử miễn phí', 'Free trial', 'Có demo không'],
                'keywords' => ['dùng thử', 'trial', 'demo', 'thử', 'miễn phí'],
                'suggested_questions' => ['Phần mềm có miễn phí không?', 'Thanh toán như thế nào?'],
                'display_order' => 11,
            ],
            [
                'category' => 'gia-ca',
                'question' => 'Thanh toán bằng hình thức nào?',
                'answer' => "Aventura hỗ trợ nhiều hình thức thanh toán tiện lợi:\n\n- **Chuyển khoản ngân hàng** (quét mã QR SePay)\n- **Ví điện tử** (MoMo, ZaloPay)\n- **Thẻ ngân hàng nội địa / quốc tế**\n\nSau khi thanh toán thành công, hóa đơn sẽ được gửi tự động về email của bạn trong vòng vài phút.",
                'alt_questions' => ['Thanh toán như thế nào', 'Chuyển khoản được không', 'Trả tiền bằng gì', 'Phương thức thanh toán'],
                'keywords' => ['thanh toán', 'chuyển khoản', 'ngân hàng', 'QR', 'MoMo', 'ZaloPay'],
                'suggested_questions' => ['Có xuất hóa đơn VAT không?', 'Hủy gói dịch vụ như thế nào?'],
                'display_order' => 12,
            ],
            [
                'category' => 'gia-ca',
                'question' => 'Khi hết hạn gói Pro, dữ liệu có bị mất không?',
                'answer' => "**Không mất dữ liệu.** Khi gói Pro hết hạn:\n\n1. Tài khoản chuyển sang chế độ **\"Chỉ đọc\"** (Read-Only)\n2. Bạn vẫn xem được toàn bộ lịch sử đơn hàng, báo cáo, nhân sự cũ\n3. **Không thể tạo đơn mới, thêm bàn hoặc nhân viên**\n4. Sau 30 ngày không gia hạn → tài khoản bị tạm khóa (dữ liệu vẫn còn)\n\nGia hạn bất cứ lúc nào để tiếp tục hoạt động bình thường.",
                'alt_questions' => ['Hết hạn thì sao', 'Dữ liệu có mất không', 'Không gia hạn thì sao', 'Expired'],
                'keywords' => ['hết hạn', 'dữ liệu', 'mất', 'gia hạn', 'expired'],
                'suggested_questions' => ['Thanh toán bằng hình thức nào?', 'Hủy gói dịch vụ như thế nào?'],
                'display_order' => 13,
            ],

            // ── TÍNH NĂNG ──────────────────────────────────────────────────
            [
                'category' => 'tinh-nang',
                'question' => 'Aventura có những tính năng chính gì?',
                'answer' => "Aventura là nền tảng quản lý nhà hàng toàn diện với các tính năng:\n\n🍽️ **Vận hành**\n- Đặt món qua QR tại bàn\n- Màn hình bếp realtime\n- Thu ngân & thanh toán đa kênh\n\n📦 **Kho & Menu**\n- Quản lý nguyên liệu, công thức định lượng\n- Tự động trừ kho khi thanh toán\n- Cảnh báo nguyên liệu sắp hết\n\n👥 **Nhân sự**\n- Xếp ca, chấm công, tính lương tự động\n- Phát hiện gian lận thu ngân\n\n📊 **Báo cáo AI**\n- Dự báo doanh thu\n- Phân tích lợi nhuận theo món\n- Báo cáo KPI nhân viên\n\n🔒 **Bảo mật**\n- Audit Log toàn bộ hành động nhạy cảm\n- Phân quyền chi tiết theo vai trò",
                'alt_questions' => ['Có tính năng gì', 'Phần mềm làm được gì', 'Chức năng của Aventura', 'Hệ thống hỗ trợ gì'],
                'keywords' => ['tính năng', 'chức năng', 'làm được gì', 'hỗ trợ'],
                'suggested_questions' => ['Quản lý kho như thế nào?', 'Realtime là gì?', 'AI trong Aventura làm gì?'],
                'display_order' => 20,
            ],
            [
                'category' => 'tinh-nang',
                'question' => 'Có tính năng quản lý kho nguyên liệu không?',
                'answer' => "**Có, đây là một trong những tính năng mạnh nhất của Aventura.**\n\nHệ thống quản lý kho bao gồm:\n\n- **Công thức định lượng**: Gắn nguyên liệu vào từng món (VD: 1 ly trà sữa cần 200ml trà, 50ml sữa...)\n- **Tự động trừ kho**: Khi thanh toán, hệ thống tự trừ đúng lượng nguyên liệu đã dùng\n- **Cảnh báo hết kho**: Thông báo khi nguyên liệu dưới mức tối thiểu\n- **Kiểm kho thực tế**: So sánh tồn kho lý thuyết với thực tế để phát hiện hao hụt\n- **AI dự báo nhập hàng**: Đề xuất lượng cần nhập cho tuần tới dựa trên lịch sử bán",
                'alt_questions' => ['Quản lý kho', 'Theo dõi nguyên liệu', 'Kiểm kho', 'Nhập kho', 'Inventory'],
                'keywords' => ['kho', 'nguyên liệu', 'tồn kho', 'nhập hàng', 'inventory'],
                'suggested_questions' => ['AI trong Aventura làm gì?', 'Tự động trừ kho hoạt động thế nào?'],
                'display_order' => 21,
            ],
            [
                'category' => 'tinh-nang',
                'question' => 'Tính năng realtime là gì? Có cần F5 liên tục không?',
                'answer' => "**Hoàn toàn không cần F5!** Aventura dùng công nghệ WebSocket (Laravel Reverb) để cập nhật tức thì.\n\n**Ví dụ thực tế:**\n- Khách quét QR gọi món → Màn hình bếp hiển thị ngay lập tức (< 500ms)\n- Bếp hoàn thành món → Thu ngân thấy thông báo ngay\n- Trạng thái bàn thay đổi → Sơ đồ bàn cập nhật tức thì cho tất cả nhân viên\n\nĐiều này giúp phục vụ nhanh hơn và tránh sai sót trong giờ cao điểm.",
                'alt_questions' => ['Realtime', 'Cập nhật tức thì', 'WebSocket', 'Không cần tải lại trang', 'Live update'],
                'keywords' => ['realtime', 'tức thì', 'websocket', 'real-time', 'cập nhật'],
                'suggested_questions' => ['Màn hình bếp hoạt động thế nào?', 'Đặt món qua QR là gì?'],
                'display_order' => 22,
            ],
            [
                'category' => 'tinh-nang',
                'question' => 'AI trong Aventura làm được gì?',
                'answer' => "Aventura tích hợp **Python Microservice AI** để phân tích dữ liệu chuyên sâu:\n\n📈 **Phân tích doanh thu**\n- Thống kê theo ngày/tuần/tháng\n- Xác định khung giờ đông khách\n- Tính toán lợi nhuận thực tế từng món\n\n🔮 **Dự báo**\n- Dự đoán doanh thu tháng tới\n- Đề xuất lượng nguyên liệu cần nhập\n\n🕵️ **Phát hiện gian lận**\n- Cảnh báo thu ngân hủy đơn bất thường\n- Phát hiện sửa giá, tách đơn đáng ngờ\n- Phân tích hao hụt kho bất thường\n\n💡 **Gợi ý kinh doanh**\n- Đề xuất combo từ các món thường mua cùng\n- Thời điểm tốt nhất để chạy khuyến mãi",
                'alt_questions' => ['AI làm gì', 'Trí tuệ nhân tạo', 'Machine learning', 'Phân tích dữ liệu', 'Báo cáo thông minh'],
                'keywords' => ['AI', 'trí tuệ nhân tạo', 'phân tích', 'dự báo', 'machine learning'],
                'suggested_questions' => ['Phát hiện gian lận như thế nào?', 'Báo cáo doanh thu xem ở đâu?'],
                'display_order' => 23,
            ],
            [
                'category' => 'tinh-nang',
                'question' => 'Có tính năng quản lý nhân sự không?',
                'answer' => "**Có!** Aventura quản lý nhân sự toàn diện:\n\n👤 **Hồ sơ nhân viên**: Thông tin cá nhân, CCCD, ảnh\n\n📅 **Xếp ca làm việc**: Lịch hàng tuần, quản lý nghỉ phép\n\n⏰ **Chấm công**: Ghi nhận giờ vào/ra theo ca\n\n💰 **Tính lương tự động**: Tự trừ phạt âm két, hao hụt kho, vi phạm; cộng thưởng chuyên cần\n\n📋 **Quản lý vi phạm**: Ghi nhận, báo cáo ẩn danh\n\n🔒 **Khóa tài khoản ngoài giờ**: Nhân viên chỉ đăng nhập được đúng ca làm việc",
                'alt_questions' => ['Quản lý nhân viên', 'Nhân sự', 'Tính lương', 'Chấm công', 'Xếp ca'],
                'keywords' => ['nhân sự', 'nhân viên', 'lương', 'ca làm việc', 'chấm công'],
                'suggested_questions' => ['Phát hiện gian lận thu ngân thế nào?', 'Tài khoản nhân viên có bị hạn chế giờ không?'],
                'display_order' => 24,
            ],
            [
                'category' => 'tinh-nang',
                'question' => 'Có hỗ trợ chế độ ngoại tuyến (offline) không?',
                'answer' => "**Có!** Aventura hỗ trợ **Offline Mode** cho các thiết bị POS tại quán.\n\nKhi mất mạng:\n- **Vẫn xem được menu và sơ đồ bàn**\n- **Vẫn tạo đơn và in bill được**\n- Dữ liệu lưu tạm trên thiết bị\n- Khi có mạng trở lại → **tự động đồng bộ** lên server\n\nĐảm bảo nhà hàng hoạt động liên tục kể cả khi gặp sự cố internet.",
                'alt_questions' => ['Offline', 'Mất mạng', 'Không có internet', 'Ngoại tuyến'],
                'keywords' => ['offline', 'mất mạng', 'ngoại tuyến', 'internet'],
                'suggested_questions' => ['Có ứng dụng mobile không?', 'Cần thiết bị gì để dùng?'],
                'display_order' => 25,
            ],

            // ── VẬN HÀNH / ORDER ──────────────────────────────────────────
            [
                'category' => 'van-hanh',
                'question' => 'Đặt món qua mã QR hoạt động thế nào?',
                'answer' => "Đây là tính năng giúp **khách tự order** mà không cần nhân viên phục vụ.\n\n**Quy trình:**\n1. Mỗi bàn có **mã QR dán sẵn** (in từ hệ thống)\n2. Khách quét bằng điện thoại → Truy cập **thực đơn kỹ thuật số** ngay lập tức\n3. Xem ảnh, mô tả, giá từng món → Chọn và đặt\n4. Đơn gửi **realtime** xuống màn hình bếp\n5. Bếp làm xong → Phục vụ mang ra bàn\n\n> **Lợi ích**: Tiết kiệm nhân lực, giảm sai sót order, khách chủ động hơn.",
                'alt_questions' => ['QR code', 'Quét mã', 'Khách tự order', 'Order tại bàn', 'Menu QR'],
                'keywords' => ['QR', 'quét mã', 'đặt món', 'tự order', 'menu'],
                'suggested_questions' => ['Màn hình bếp hoạt động thế nào?', 'Thu ngân thanh toán như thế nào?'],
                'display_order' => 30,
            ],
            [
                'category' => 'van-hanh',
                'question' => 'Màn hình bếp (Kitchen Display) hoạt động thế nào?',
                'answer' => "Màn hình bếp được thiết kế **chuyên biệt cho đầu bếp**:\n\n**Giao diện chia 2 cột:**\n- Cột trái: **Đơn chưa làm** (hiển thị tên món, số lượng, số bàn, giờ vào đơn)\n- Cột phải: **Đơn đã hoàn thành**\n\n**Tính năng nổi bật:**\n- Nhận đơn **tức thì** không cần F5\n- **Đồng hồ đếm thời gian** từ lúc khách order\n- Chuyển trạng thái bằng 1 cú chạm\n- Giao diện đơn giản, không thoát giữa chừng\n\nKhi bếp hoàn thành → Thu ngân nhận thông báo ngay.",
                'alt_questions' => ['Màn hình bếp', 'Kitchen display', 'Bếp nhận đơn thế nào', 'KDS'],
                'keywords' => ['bếp', 'kitchen', 'màn hình bếp', 'KDS', 'đầu bếp'],
                'suggested_questions' => ['Đặt món qua QR hoạt động thế nào?', 'Thu ngân làm gì?'],
                'display_order' => 31,
            ],
            [
                'category' => 'van-hanh',
                'question' => 'Thu ngân thanh toán và chốt ca như thế nào?',
                'answer' => "**Quy trình thu ngân:**\n\n1. Kiểm tra đơn hàng, áp mã giảm giá (nếu có)\n2. Nhấn **Thanh toán** → Chọn hình thức (tiền mặt / chuyển khoản)\n3. Hệ thống tự động trừ kho, chuyển bàn về trạng thái \"Trống\"\n\n**Chốt ca:**\n- Cuối ca, thu ngân thực hiện **khớp két** (so sánh tiền mặt thực tế với hệ thống)\n- Nếu thiếu → Hệ thống ghi nhận âm két, gắn trách nhiệm vào tài khoản thu ngân ca đó\n- Báo cáo tự động gửi về email chủ nhà hàng lúc 23h59 hàng ngày",
                'alt_questions' => ['Thanh toán', 'Thu ngân', 'Chốt ca', 'POS', 'Khớp két'],
                'keywords' => ['thu ngân', 'thanh toán', 'chốt ca', 'két', 'POS'],
                'suggested_questions' => ['Hệ thống phát hiện gian lận thu ngân thế nào?', 'Báo cáo doanh thu xem ở đâu?'],
                'display_order' => 32,
            ],
            [
                'category' => 'van-hanh',
                'question' => 'Tài khoản nhân viên có bị hạn chế giờ đăng nhập không?',
                'answer' => "**Có!** Đây là tính năng bảo mật quan trọng của Aventura.\n\nTài khoản nhân viên (thu ngân, order, bếp) **chỉ hoạt động đúng trong ca làm việc** được xếp lịch.\n\n**Ví dụ:** Ca tối từ 16:00 - 23:00 → Tài khoản tự động:\n- ✅ **Mở** lúc 16:00\n- ❌ **Khóa** lúc 23:00\n\n**Mục đích:** Ngăn nhân viên tạo đơn giả ngoài giờ làm, bảo vệ doanh thu thực của nhà hàng.",
                'alt_questions' => ['Giờ đăng nhập', 'Khóa tài khoản', 'Hạn chế ca làm', 'Tài khoản nhân viên'],
                'keywords' => ['đăng nhập', 'ca làm việc', 'khóa', 'giờ', 'hạn chế'],
                'suggested_questions' => ['Xếp lịch làm việc như thế nào?', 'Quản lý nhân sự có gì?'],
                'display_order' => 33,
            ],

            // ── BẢO MẬT ──────────────────────────────────────────────────
            [
                'category' => 'bao-mat',
                'question' => 'Dữ liệu của nhà hàng tôi có an toàn không?',
                'answer' => "**Bảo mật là ưu tiên số 1 của Aventura.** Chúng tôi áp dụng nhiều lớp bảo vệ:\n\n🔐 **Cô lập dữ liệu (Multi-tenant)**\n- Mỗi nhà hàng có ID riêng biệt\n- Tất cả truy vấn đều lọc theo nhà hàng của bạn → Không thể xem dữ liệu của nhà hàng khác\n\n🔑 **Xác thực bảo mật**\n- Mật khẩu mã hóa Bcrypt (không lưu dạng thô)\n- Hỗ trợ xác thực 2 lớp (2FA)\n- Đăng nhập Google OAuth\n\n📋 **Audit Log**\n- Mọi hành động nhạy cảm đều được ghi lại: ai làm, lúc nào, từ IP nào\n\n☁️ **Sao lưu tự động** hàng ngày",
                'alt_questions' => ['Bảo mật', 'Dữ liệu an toàn không', 'Có bị hack không', 'Bảo vệ dữ liệu'],
                'keywords' => ['bảo mật', 'an toàn', 'dữ liệu', 'hack', 'security'],
                'suggested_questions' => ['Bảo mật 2 lớp (2FA) là gì?', 'Audit Log là gì?'],
                'display_order' => 40,
            ],
            [
                'category' => 'bao-mat',
                'question' => 'Xác thực 2 lớp (2FA) là gì? Có bắt buộc không?',
                'answer' => "**2FA (Two-Factor Authentication)** là lớp bảo mật thứ 2 ngoài mật khẩu.\n\n**Cách hoạt động:**\n1. Đăng nhập bằng email + mật khẩu như bình thường\n2. Nhập **mã OTP 6 số** từ ứng dụng Google Authenticator / Authy trên điện thoại\n3. Mã thay đổi mỗi 30 giây → Kể cả kẻ xấu biết mật khẩu cũng không đăng nhập được\n\n**Có bắt buộc không?** Không bắt buộc, nhưng **chúng tôi khuyến khích bật** cho tài khoản chủ nhà hàng và quản lý.",
                'alt_questions' => ['2FA', 'Xác thực 2 bước', 'Two factor', 'OTP', 'Bảo mật 2 lớp'],
                'keywords' => ['2FA', 'xác thực', 'OTP', 'two factor', 'bảo mật 2 lớp'],
                'suggested_questions' => ['Dữ liệu của tôi có an toàn không?', 'Đăng nhập bằng Google được không?'],
                'display_order' => 41,
            ],
            [
                'category' => 'bao-mat',
                'question' => 'Hệ thống phát hiện gian lận thu ngân như thế nào?',
                'answer' => "Aventura dùng **AI Python** để tự động phát hiện hành vi bất thường:\n\n🚨 **Các dấu hiệu được theo dõi:**\n- Thu ngân liên tục hủy đơn sau khi khách đã thanh toán\n- Tách đơn ra bàn khác một cách bất thường\n- Áp dụng mã giảm giá không đúng quy trình\n- Âm két nhiều lần trong tháng\n\n📲 **Khi phát hiện:**\n- Chủ nhà hàng nhận **thông báo tức thì**\n- Chi tiết ghi vào **Audit Log** với đầy đủ thông tin\n- Khoản âm tự động gắn vào bảng lương tháng của nhân viên liên quan\n\nMọi thay đổi đều có dấu vết: ai làm, lúc mấy giờ, từ thiết bị nào.",
                'alt_questions' => ['Gian lận', 'Phát hiện gian lận', 'Nhân viên gian lận', 'Fraud detection', 'Audit'],
                'keywords' => ['gian lận', 'fraud', 'audit', 'phát hiện', 'bất thường'],
                'suggested_questions' => ['Audit Log là gì?', 'Thu ngân chốt ca thế nào?'],
                'display_order' => 42,
            ],

            // ── KỸ THUẬT / THIẾT BỊ ──────────────────────────────────────
            [
                'category' => 'ky-thuat',
                'question' => 'Cần thiết bị gì để sử dụng Aventura?',
                'answer' => "Aventura là **ứng dụng web**, chạy trực tiếp trên trình duyệt  **không cần cài đặt phần mềm**.\n\n**Thiết bị tương thích:**\n- 💻 Máy tính / Laptop (cho quản lý, thu ngân)\n- 📱 Máy tính bảng (cho phục vụ di động)\n- 📺 Màn hình bếp (tablet hoặc màn hình cảm ứng)\n- 📱 Điện thoại khách hàng (quét QR đặt món)\n\n**Trình duyệt:** Chrome, Edge, Safari, Firefox (phiên bản mới)\n\n**Kết nối:** WiFi ổn định cho hoạt động tốt nhất. Có hỗ trợ offline khi mất mạng.",
                'alt_questions' => ['Thiết bị gì', 'Dùng máy gì', 'Cài đặt thế nào', 'Tương thích thiết bị', 'Phần cứng'],
                'keywords' => ['thiết bị', 'máy tính', 'tablet', 'cài đặt', 'trình duyệt'],
                'suggested_questions' => ['Có ứng dụng mobile không?', 'Có hỗ trợ offline không?'],
                'display_order' => 50,
            ],
            [
                'category' => 'ky-thuat',
                'question' => 'Có ứng dụng mobile (app) không?',
                'answer' => "Hiện tại Aventura là **ứng dụng web** tối ưu cho mobile  tức là bạn mở bằng trình duyệt điện thoại mà giao diện vẫn đẹp và dễ dùng.\n\n**Ưu điểm:**\n- Không cần tải app từ App Store / CH Play\n- Luôn cập nhật phiên bản mới nhất tự động\n- Giao diện responsive  dùng tốt trên mọi màn hình\n\nBạn có thể **thêm vào màn hình chính** (Add to Home Screen) của điện thoại để dùng như một app thực sự.\n\n> Ứng dụng mobile native đang trong lộ trình phát triển.",
                'alt_questions' => ['App mobile', 'Ứng dụng điện thoại', 'Tải app', 'iOS', 'Android', 'App Store'],
                'keywords' => ['app', 'mobile', 'điện thoại', 'iOS', 'Android', 'ứng dụng'],
                'suggested_questions' => ['Cần thiết bị gì?', 'Giao diện có responsive không?'],
                'display_order' => 51,
            ],
            [
                'category' => 'ky-thuat',
                'question' => 'Hệ thống có thể phục vụ bao nhiêu nhà hàng cùng lúc?',
                'answer' => "Aventura được thiết kế theo kiến trúc **SaaS Multi-tenant** để phục vụ quy mô lớn:\n\n**Hiệu năng mục tiêu:**\n- 100+ nhà hàng vận hành đồng thời\n- 10.000+ người dùng truy cập cùng lúc\n- 1.000+ đơn hàng mỗi ngày/nhà hàng\n- Thời gian phản hồi API < 2 giây\n\nĐạt được nhờ: Redis caching, Queue bất đồng bộ, Composite Index database, và kiến trúc tách biệt Python microservice cho AI.",
                'alt_questions' => ['Bao nhiêu người dùng', 'Hiệu năng', 'Scale', 'Chịu tải'],
                'keywords' => ['hiệu năng', 'scale', 'tải', 'người dùng', 'performance'],
                'suggested_questions' => ['Có hỗ trợ nhiều chi nhánh không?', 'Sao lưu dữ liệu thế nào?'],
                'display_order' => 52,
            ],

            // ── HỖ TRỢ ──────────────────────────────────────────────────
            [
                'category' => 'ho-tro',
                'question' => 'Tôi gặp sự cố, liên hệ hỗ trợ bằng cách nào?',
                'answer' => "Chúng tôi có nhiều kênh hỗ trợ:\n\n📩 **Ticket hỗ trợ**: Vào mục **Hỗ trợ** trong ứng dụng → Tạo ticket → Đội ngũ phản hồi trong vòng **4 giờ làm việc** (gói Pro) hoặc **24 giờ** (gói Free)\n\n💬 **Chat trực tiếp**: Ngay tại đây!\n\n📚 **Knowledge Base**: Xem bài viết hướng dẫn chi tiết tại mục Hỗ trợ\n\n> Gói Pro được **ưu tiên hỗ trợ** và có thể đặt lịch demo trực tiếp với kỹ thuật viên.",
                'alt_questions' => ['Liên hệ hỗ trợ', 'Gặp lỗi', 'Cần giúp đỡ', 'Contact', 'Support', 'Hotline'],
                'keywords' => ['hỗ trợ', 'liên hệ', 'support', 'lỗi', 'giúp đỡ', 'contact'],
                'suggested_questions' => ['Làm sao tạo ticket hỗ trợ?', 'SLA hỗ trợ là bao lâu?'],
                'display_order' => 60,
            ],
            [
                'category' => 'ho-tro',
                'question' => 'Có tài liệu hướng dẫn sử dụng không?',
                'answer' => "**Có đầy đủ!** Aventura cung cấp:\n\n📖 **Knowledge Base**: Bài viết hướng dẫn từng tính năng tại mục **Hỗ trợ** trong ứng dụng\n\n🎥 **Video hướng dẫn**: Xem trực quan cách sử dụng từng module\n\n🗺️ **Guided Tour**: Khi đăng nhập lần đầu, hệ thống **tự động hướng dẫn** từng bước setup nhà hàng trong 3 ngày đầu\n\n> Không cần đọc tài liệu dày  hệ thống chủ động chỉ dẫn bạn từng bước!",
                'alt_questions' => ['Tài liệu', 'Hướng dẫn sử dụng', 'Manual', 'Video tutorial', 'Hướng dẫn'],
                'keywords' => ['tài liệu', 'hướng dẫn', 'manual', 'tutorial', 'video'],
                'suggested_questions' => ['Onboarding là gì?', 'Liên hệ hỗ trợ bằng cách nào?'],
                'display_order' => 61,
            ],
            [
                'category' => 'ho-tro',
                'question' => 'Onboarding là gì? Mất bao lâu để setup xong?',
                'answer' => "**Onboarding** là quy trình thiết lập ban đầu khi bạn đăng ký mới.\n\n**Hệ thống tự động hóa 100%:**\n- Sau khi đăng ký → Nhà hàng của bạn **sẵn sàng trong 3 giây**\n- Có sẵn: 1 khu vực, 3 bàn mẫu, 3 danh mục món, 2-3 món demo, nguyên liệu mẫu\n\n**Hướng dẫn 3 ngày đầu:**\n- Ngày 1: Tạo thực đơn thực tế\n- Ngày 2: Thiết lập công thức nguyên liệu\n- Ngày 3: Thêm nhân viên, phân quyền\n\nChỉ mất **15-30 phút** là có thể bắt đầu nhận đơn thật!",
                'alt_questions' => ['Setup', 'Thiết lập ban đầu', 'Bắt đầu như thế nào', 'Cài đặt', 'Onboarding'],
                'keywords' => ['onboarding', 'setup', 'thiết lập', 'bắt đầu', 'cài đặt'],
                'suggested_questions' => ['Làm sao để đăng ký?', 'Mất bao lâu để quen với hệ thống?'],
                'display_order' => 62,
            ],

            // ── CHUNG ──────────────────────────────────────────────────
            [
                'category' => 'chung',
                'question' => 'Aventura là gì?',
                'answer' => "**Aventura** là nền tảng **quản lý nhà hàng SaaS** toàn diện, được thiết kế để:\n\n✅ Quản lý toàn phần: đơn hàng, kho, nhân sự, doanh thu\n✅ Tăng tính minh bạch, giảm gian lận nội bộ\n✅ Tích hợp AI phân tích dữ liệu thông minh\n✅ Phục vụ từ 1 nhà hàng đến chuỗi nhiều chi nhánh\n\nGiống như KiotViet nhưng chuyên sâu hơn cho ngành F&B với công nghệ realtime và AI.",
                'alt_questions' => ['Aventura là gì', 'Giới thiệu Aventura', 'Phần mềm này là gì', 'F&B Viet', 'BepsoViet'],
                'keywords' => ['aventura', 'giới thiệu', 'là gì', 'phần mềm'],
                'suggested_questions' => ['Aventura có những tính năng chính gì?', 'Phần mềm có miễn phí không?', 'Làm sao để đăng ký?'],
                'display_order' => 0,
            ],
            [
                'category' => 'chung',
                'question' => 'Aventura khác gì so với các phần mềm khác như KiotViet?',
                'answer' => "Aventura được xây dựng với **triết lý khác biệt** so với các phần mềm quản lý thông thường:\n\n| Tính năng | Aventura | Phần mềm thông thường |\n|---|---|---|\n| Phát hiện gian lận AI | ✅ Tự động | ❌ Không có |\n| Dự báo kho AI | ✅ | ❌ |\n| Màn hình bếp realtime | ✅ | ⚠️ Hạn chế |\n| Offline mode | ✅ | ⚠️ |\n| Audit Log chi tiết | ✅ | ⚠️ |\n| Giá | Miễn phí → Pro | Chủ yếu trả phí |\n\nĐặc biệt, Aventura **đề cao tính minh bạch**  mọi hành động của nhân viên đều có dấu vết.",
                'alt_questions' => ['So sánh', 'Khác gì KiotViet', 'Tại sao chọn Aventura', 'Ưu điểm'],
                'keywords' => ['so sánh', 'khác', 'KiotViet', 'ưu điểm', 'tại sao'],
                'suggested_questions' => ['Aventura có những tính năng chính gì?', 'Dùng thử bao nhiêu ngày?'],
                'display_order' => 1,
            ],
            [
                'category' => 'chung',
                'question' => 'Sao lưu dữ liệu như thế nào? Có bị mất không?',
                'answer' => "Dữ liệu của bạn được **sao lưu tự động hàng ngày**:\n\n☁️ **Database MySQL**: Backup toàn bộ dữ liệu mỗi đêm\n\n🖼️ **File & Hình ảnh**: Đồng bộ lên Cloud Storage (S3/MinIO)\n\n🔄 **Khôi phục nhanh**: Nếu có sự cố, có thể restore trong vòng 2-4 giờ\n\n**Cam kết Uptime > 99%**  Hệ thống giám sát 24/7 bằng Sentry & Laravel Pulse để phát hiện sự cố trước khi ảnh hưởng đến bạn.",
                'alt_questions' => ['Sao lưu', 'Backup', 'Mất dữ liệu', 'Uptime', 'Phục hồi'],
                'keywords' => ['sao lưu', 'backup', 'mất dữ liệu', 'uptime', 'phục hồi'],
                'suggested_questions' => ['Dữ liệu có an toàn không?', 'Khi hết hạn dữ liệu có mất không?'],
                'display_order' => 70,
            ],
        ];

        foreach ($items as $item) {
            ChatbotKnowledge::create($item);
        }
    }
}
