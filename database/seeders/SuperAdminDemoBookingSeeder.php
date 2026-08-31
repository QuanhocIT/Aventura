<?php

namespace Database\Seeders;

use App\Models\DemoBooking;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminDemoBookingSeeder extends Seeder
{
    /**
     * Create isolated demo registrations for Super Admin > Demo bookings.
     */
    public function run(): void
    {
        $bookings = [
            ['Nguyễn Minh Anh', '0901000001', 'minh.anh@demo.local', 'Bếp Nhà Mộc', 2, '09:00 - 10:00', 'Muốn xem quy trình quản lý đơn và bếp.', 'pending'],
            ['Trần Quốc Bảo', '0901000002', 'quoc.bao@demo.local', 'Sài Gòn Diner', 4, '10:00 - 11:00', 'Quan tâm đến báo cáo doanh thu theo chi nhánh.', 'contacted'],
            ['Lê Hoàng Nam', '0901000003', 'hoang.nam@demo.local', 'Phở Việt 24', 6, '14:00 - 15:00', 'Cần tư vấn giải pháp cho chuỗi nhiều cơ sở.', 'completed'],
            ['Phạm Thùy Linh', '0901000004', 'thuy.linh@demo.local', 'Lá & Cà Phê', 1, '15:00 - 16:00', 'Muốn triển khai nhanh trong tháng này.', 'pending'],
            ['Đỗ Gia Huy', '0901000005', 'gia.huy@demo.local', 'Góc Bếp Việt', 3, '08:30 - 09:30', 'Đang so sánh phần mềm quản lý nhà hàng.', 'contacted'],
            ['Vũ Ngọc Mai', '0901000006', 'ngoc.mai@demo.local', 'Mây Rooftop', 2, '16:00 - 17:00', 'Cần tích hợp đặt bàn và chăm sóc khách hàng.', 'completed'],
            ['Bùi Đức Long', '0901000007', 'duc.long@demo.local', 'Cơm Nhà Xưa', 5, '09:30 - 10:30', 'Muốn xem tính năng phân quyền nhân viên.', 'pending'],
            ['Ngô Khánh Vy', '0901000008', 'khanh.vy@demo.local', 'Tiệm Bánh An Nhiên', 1, '13:30 - 14:30', 'Quan tâm đến tồn kho nguyên liệu.', 'contacted'],
            ['Hoàng Tuấn Kiệt', '0901000009', 'tuan.kiet@demo.local', 'Lẩu Nướng Phố', 8, '11:00 - 12:00', 'Cần báo giá cho mô hình chuỗi 8 chi nhánh.', 'pending'],
            ['Đặng Phương Thảo', '0901000010', 'phuong.thao@demo.local', 'The Garden Bistro', 2, '14:30 - 15:30', 'Muốn xem demo trên máy tính bảng tại quầy.', 'cancelled'],
            ['Phan Nhật Minh', '0901000011', 'nhat.minh@demo.local', 'Bún Bò Huế Nhà Mình', 4, '08:00 - 09:00', 'Cần tư vấn chuyển dữ liệu từ hệ thống cũ.', 'completed'],
            ['Dương Hải Yến', '0901000012', 'hai.yen@demo.local', 'Chay An Lạc', 2, '10:30 - 11:30', 'Quan tâm đến menu QR và đơn tại bàn.', 'pending'],
            ['Mai Thành Đạt', '0901000013', 'thanh.dat@demo.local', 'Mộc Miên Restaurant', 7, '15:30 - 16:30', 'Muốn quản lý doanh thu tập trung.', 'contacted'],
            ['Nguyễn Hà My', '0901000014', 'ha.my@demo.local', 'Kichi House', 3, '17:00 - 18:00', 'Cần xem báo cáo ca và hiệu suất nhân viên.', 'completed'],
            ['Cao Anh Khoa', '0901000015', 'anh.khoa@demo.local', 'Bếp Phố Cổ', 1, '09:00 - 10:00', 'Đăng ký để trải nghiệm gói miễn phí.', 'cancelled'],
        ];

        $superAdminId = User::where('email', env('SUPERADMIN_EMAIL', 'superadmin@aventura.local'))->value('id');

        foreach ($bookings as $index => [$name, $phone, $email, $restaurant, $branchCount, $preferredTime, $notes, $status]) {
            $createdAt = now()->subDays(20 - $index)->setTime(8 + ($index % 8), 30);
            $contactedAt = $status === 'pending'
                ? null
                : $createdAt->copy()->addHours(4);

            $booking = DemoBooking::withTrashed()->firstOrNew(['email' => $email]);

            if ($booking->trashed()) {
                $booking->restore();
            }

            $booking->forceFill([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'restaurant_name' => $restaurant,
                'branch_count' => $branchCount,
                'preferred_date' => now()->addDays(($index % 10) + 1)->toDateString(),
                'preferred_time' => $preferredTime,
                'notes' => $notes,
                'status' => $status,
                'contacted_at' => $contactedAt,
                'contacted_by' => $contactedAt ? $superAdminId : null,
                'admin_notes' => $this->adminNotesFor($status),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        $demoCount = DemoBooking::where('email', 'like', '%@demo.local')->count();

        $this->command?->info("Đã tạo/cập nhật {$demoCount}/15 lịch đăng ký demo mẫu cho tài khoản Super Admin.");
    }

    private function adminNotesFor(string $status): ?string
    {
        return match ($status) {
            'contacted' => 'Đã liên hệ và gửi thông tin tư vấn cho khách hàng.',
            'completed' => 'Đã hoàn thành buổi demo, khách hàng đang đánh giá giải pháp.',
            'cancelled' => 'Khách hàng xin dời lịch hoặc chưa sắp xếp được thời gian.',
            default => null,
        };
    }
}
