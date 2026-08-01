<?php

namespace Database\Seeders;

use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Vài bài viết mẫu (evergreen) cho trang tin tức công khai /tin-tuc — để trang không
 * trống khi mới ra mắt. Chủ nền tảng có thể sửa/xoá/thêm trong khu Super Admin.
 * Idempotent: key theo slug cố định nên chạy lại không nhân đôi.
 *
 *   php artisan db:seed --class=SampleNewsSeeder
 */
class SampleNewsSeeder extends Seeder
{
    public function run(): void
    {
        $authorId = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->orderBy('id')->value('id')
            ?? User::orderBy('id')->value('id');

        if (! $authorId) {
            return; // chưa có user nào để làm tác giả
        }

        foreach ($this->posts() as $i => $post) {
            NewsPost::updateOrCreate(
                ['slug' => $post['slug']],
                array_merge($post, [
                    'author_id' => $authorId,
                    'is_published' => true,
                    'published_at' => now()->subDays(($i + 1) * 3),
                    'view_count' => random_int(120, 900),
                ]),
            );
        }
    }

    private function posts(): array
    {
        return [
            [
                'slug' => 'meo-tang-doanh-thu-nha-hang-mua-cao-diem',
                'title' => '5 mẹo tăng doanh thu nhà hàng mùa cao điểm',
                'category' => 'Vận hành',
                'is_featured' => true,
                'tags' => ['doanh thu', 'vận hành', 'cao điểm'],
                'excerpt' => 'Cao điểm là lúc kiếm lời — nhưng cũng dễ vỡ trận. 5 cách giữ tốc độ phục vụ mà vẫn tối ưu doanh thu mỗi bàn.',
                'content' => '<p>Mùa lễ, cuối tuần hay giờ vàng luôn là cơ hội vàng — nhưng phục vụ chậm một nhịp là mất khách. Dưới đây là 5 đòn bẩy thực chiến:</p>'
                    .'<h3>1. Rút gọn thực đơn giờ cao điểm</h3><p>Tạm ẩn các món chế biến lâu, đẩy mạnh nhóm món "ra nhanh, biên lợi nhuận cao". Bếp đỡ nghẽn, bàn quay vòng nhanh hơn.</p>'
                    .'<h3>2. Đặt món QR tại bàn</h3><p>Khách tự gọi món qua mã QR, đơn về thẳng bếp — giảm thời gian chờ và giải phóng nhân viên cho việc phục vụ.</p>'
                    .'<h3>3. Combo &amp; gợi ý bán kèm</h3><p>Thiết kế combo và nhắc món bán kèm ngay trên màn hình đặt món để tăng giá trị mỗi hoá đơn.</p>'
                    .'<h3>4. Theo dõi tồn kho theo thời gian thực</h3><p>Đừng để "hết nguyên liệu" phá hỏng giờ vàng. Định lượng theo công thức giúp biết trước món nào sắp hết.</p>'
                    .'<h3>5. Đọc số liệu sau mỗi ca</h3><p>Xem báo cáo doanh thu, món bán chạy và giờ đông khách để chuẩn bị tốt hơn cho lần sau.</p>'
                    .'<p><em>Tất cả những việc trên đều có sẵn trong Aventura — từ POS, đặt món QR tới báo cáo tức thời.</em></p>',
            ],
            [
                'slug' => 'quan-ly-ton-kho-dinh-luong-giam-hao-hut',
                'title' => 'Quản lý tồn kho định lượng: chìa khoá giảm hao hụt',
                'category' => 'Vận hành',
                'is_featured' => false,
                'tags' => ['tồn kho', 'định lượng', 'chi phí'],
                'excerpt' => 'Hao hụt nguyên liệu ăn mòn lợi nhuận âm thầm. Quản lý theo công thức định lượng giúp bạn kiểm soát từng gram.',
                'content' => '<p>Phần lớn nhà hàng thất thoát 2–5% doanh thu vì hao hụt và thất thoát nguyên liệu mà không hề hay biết. Gốc rễ nằm ở chỗ: không ai đo được "đáng lẽ dùng bao nhiêu" so với "thực tế dùng bao nhiêu".</p>'
                    .'<h3>Định lượng theo công thức món</h3><p>Mỗi món gắn với công thức (định mức nguyên liệu + tỉ lệ hao). Khi bán một tô phở, hệ thống tự trừ đúng lượng bánh phở, thịt bò… khỏi kho.</p>'
                    .'<h3>Đối chiếu lý thuyết vs thực tế</h3><p>Cuối ca, so tồn kho lý thuyết với kiểm kê thực tế. Chênh lệch lớn là dấu hiệu của hao hụt, thất thoát hoặc định lượng sai.</p>'
                    .'<h3>Cảnh báo sớm khi sắp hết</h3><p>Đặt ngưỡng tồn tối thiểu để được nhắc nhập hàng trước khi đứt nguyên liệu giữa giờ bán.</p>'
                    .'<p><em>Kết quả: giảm lãng phí, biết chính xác giá vốn, và bảo vệ biên lợi nhuận.</em></p>',
            ],
            [
                'slug' => 'dat-mon-qr-tai-ban-xu-huong-khong-cham',
                'title' => 'Đặt món QR tại bàn: xu hướng phục vụ không chạm',
                'category' => 'Xu hướng',
                'is_featured' => true,
                'tags' => ['QR', 'công nghệ', 'trải nghiệm khách'],
                'excerpt' => 'Khách quét mã, tự gọi món, theo dõi đơn và thanh toán — nhân viên tập trung vào trải nghiệm thay vì ghi order.',
                'content' => '<p>Đặt món qua mã QR không còn là "cho sang" — nó đang trở thành kỳ vọng mặc định của thực khách, đặc biệt với nhóm khách trẻ.</p>'
                    .'<h3>Vì sao khách thích?</h3><p>Không phải chờ gọi nhân viên, xem được hình ảnh &amp; mô tả món, biết món nào hết hàng, và chủ động về tốc độ.</p>'
                    .'<h3>Vì sao chủ quán thích?</h3><p>Đơn vào thẳng bếp — không sai sót do ghi tay; nhân viên phục vụ được nhiều bàn hơn; dữ liệu hành vi khách được ghi nhận để chăm sóc sau này.</p>'
                    .'<h3>Bắt đầu thế nào?</h3><p>Chỉ cần in mã QR cho từng bàn. Khi khách quét, thực đơn hiện ra theo đúng tồn kho thời gian thực. Gọi món xong, bếp nhận ngay trên màn hình KDS.</p>'
                    .'<p><em>Aventura hỗ trợ trọn vòng: QR tại bàn → bếp → thanh toán → tích điểm thành viên.</em></p>',
            ],
        ];
    }
}
