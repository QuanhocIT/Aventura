<?php

namespace Database\Seeders\System;

use App\Models\NewsPost;
use Illuminate\Database\Seeder;

class NewsPostSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'title'        => 'Aventura ra mắt phiên bản 2.0 với AI Insights và Chatbot hỗ trợ',
                'category'     => 'thong-bao',
                'is_featured'  => true,
                'featured_image' => 'news/post1-aventura-ai.jpg',
                'excerpt'      => 'Phiên bản 2.0 mang đến trải nghiệm hoàn toàn mới với module phân tích AI, chatbot thông minh và giao diện được thiết kế lại từ đầu.',
                'content'    => "**Aventura 2.0 chính thức ra mắt!**\n\nSau nhiều tháng phát triển, chúng tôi tự hào giới thiệu phiên bản 2.0 với hàng loạt tính năng mới:\n\n**AI Insights**\n- Phân tích doanh thu theo ngày/tuần/tháng\n- Dự báo xu hướng món ăn bán chạy\n- Cảnh báo bất thường tự động\n\n**Chatbot hỗ trợ 24/7**\n- Trả lời câu hỏi về hệ thống ngay lập tức\n- Học hỏi từ knowledge base của bạn\n- Tích hợp trực tiếp vào trang quản lý\n\nCập nhật ngay hôm nay để trải nghiệm!",
                'tags'       => ['phiên bản mới', 'AI', 'chatbot', 'cập nhật'],
                'is_published' => true,
                'published_at' => now()->subDays(1),
            ],
            [
                'title'        => 'Nhà hàng Phở Hà Nội tăng 40% hiệu suất sau 3 tháng dùng Aventura',
                'category'     => 'thanh-cong',
                'is_featured'  => true,
                'featured_image' => 'news/post2-restaurant-pho.jpg',
                'excerpt'      => 'Câu chuyện thành công của anh Minh Tuấn — chủ chuỗi 3 chi nhánh Phở Hà Nội tại TP.HCM, từ lộn xộn đến kiểm soát hoàn toàn.',
                'content'    => "**\"Trước đây chúng tôi mất 30 phút mỗi ca để đối soát doanh thu. Bây giờ chỉ cần 2 phút.\"** — Anh Minh Tuấn, chủ Phở Hà Nội\n\nAnh Tuấn bắt đầu dùng Aventura vào tháng 2/2026 cho chuỗi 3 chi nhánh tại quận 1, 3 và Bình Thạnh.\n\n**Kết quả sau 3 tháng:**\n- Thời gian phục vụ mỗi bàn giảm từ 8 phút xuống còn 4,5 phút\n- Sai sót order giảm 90% nhờ QR menu\n- Doanh thu tăng 40% so với cùng kỳ\n- Tiết kiệm 2 nhân viên thu ngân mỗi ca\n\n\"Tôi có thể xem doanh thu 3 chi nhánh cùng lúc trên điện thoại. Điều này trước đây là không thể.\"",
                'tags'       => ['thành công', 'nhà hàng', 'hiệu suất', 'case study'],
                'is_published' => true,
                'published_at' => now()->subDays(3),
            ],
            [
                'title'        => 'Hướng dẫn sử dụng QR Order — Khách tự đặt món không cần gọi nhân viên',
                'category'     => 'cap-nhat',
                'is_featured'  => false,
                'featured_image' => 'news/post3-qr-mobile.jpg',
                'excerpt'      => 'Tính năng QR Order cho phép khách quét mã QR tại bàn để tự chọn món, giảm tải cho nhân viên và tăng trải nghiệm khách hàng.',
                'content'    => "**QR Order là gì?**\n\nThay vì gọi nhân viên để order, khách hàng chỉ cần:\n1. Quét mã QR trên bàn bằng điện thoại\n2. Xem menu với hình ảnh và mô tả đầy đủ\n3. Chọn món và gửi order\n4. Order tự động lên màn hình bếp (KDS)\n\n**Lợi ích thực tế:**\n- Giảm 60% lượt gọi nhân viên\n- Khách hàng ít chờ đợi hơn\n- Sai sót order gần như bằng 0\n- Nhân viên tập trung vào phục vụ thay vì ghi chép\n\n**Cách bật tính năng:**\nVào Cài đặt → QR Menu → Kích hoạt → In mã QR cho từng bàn.",
                'tags'       => ['QR order', 'hướng dẫn', 'tính năng'],
                'is_published' => true,
                'published_at' => now()->subDays(5),
            ],
            [
                'title'        => 'Ưu đãi đặc biệt: Miễn phí 3 tháng gói Pro cho nhà hàng đăng ký trước 30/6',
                'category'     => 'khuyen-mai',
                'is_featured'  => true,
                'featured_image' => 'news/post4-promotion.jpg',
                'excerpt'      => 'Nhân kỷ niệm 1 năm thành lập, Aventura tặng 3 tháng sử dụng gói Pro miễn phí cho 50 nhà hàng đăng ký đầu tiên.',
                'content'    => "**Chương trình ưu đãi kỷ niệm 1 năm Aventura**\n\nĐể tri ân cộng đồng nhà hàng đã tin tưởng sử dụng dịch vụ, Aventura triển khai chương trình:\n\n**Miễn phí 3 tháng gói Pro**\n- Áp dụng cho 50 nhà hàng đầu tiên đăng ký\n- Không cần thẻ tín dụng\n- Đầy đủ tính năng: multi-branch, analytics, AI insights, audit log\n- Hỗ trợ onboarding 1-1 miễn phí\n\n**Điều kiện:**\n- Chưa từng dùng gói Pro\n- Đăng ký trước 30/06/2026\n- Nhà hàng có ít nhất 1 chi nhánh đang hoạt động\n\n**Đăng ký ngay** tại trang Bảng giá → chọn gói Pro → nhập mã: ANNIVERSARY2026",
                'tags'       => ['khuyến mãi', 'miễn phí', 'gói Pro', 'ưu đãi'],
                'is_published' => true,
                'published_at' => now()->subDays(7),
            ],
            [
                'title'        => 'Cải tiến hệ thống báo cáo realtime — Xem doanh thu theo phút',
                'category'     => 'cap-nhat',
                'is_featured'  => false,
                'featured_image' => 'news/post5-analytics.jpg',
                'excerpt'      => 'Module báo cáo được cải tiến hoàn toàn: dashboard realtime, biểu đồ tương tác và export Excel/PDF chỉ một click.',
                'content'    => "**Báo cáo realtime thế hệ mới**\n\nChúng tôi đã viết lại toàn bộ module báo cáo với các cải tiến:\n\n**Dashboard realtime**\n- Doanh thu cập nhật mỗi 30 giây\n- Số đơn đang xử lý, bàn đang có khách\n- Cảnh báo tồn kho thấp ngay trên dashboard\n\n**Biểu đồ tương tác**\n- So sánh doanh thu theo ngày/tuần/tháng\n- Phân tích theo khung giờ cao điểm\n- Top 10 món bán chạy với trend\n\n**Export dữ liệu**\n- Xuất Excel cho kế toán\n- Xuất PDF báo cáo tháng\n- API tích hợp phần mềm bên ngoài\n\nTính năng có sẵn cho tất cả gói Pro và Enterprise.",
                'tags'       => ['báo cáo', 'realtime', 'analytics', 'cải tiến'],
                'is_published' => true,
                'published_at' => now()->subDays(10),
            ],
        ];

        foreach ($posts as $data) {
            NewsPost::create([
                ...$data,
                'slug' => NewsPost::generateSlug($data['title']),
            ]);
        }
    }
}
