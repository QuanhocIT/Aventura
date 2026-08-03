<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class SuperAdminCouponDemoSeeder extends Seeder
{
    /**
     * Create isolated demo coupons for Super Admin > Coupons.
     */
    public function run(): void
    {
        $coupons = [
            ['DEMO2026WELCOME01', 'Chào mừng khách hàng mới', 'percent', 10, 100, 12, 'active', -30, 180],
            ['DEMO2026WELCOME02', 'Ưu đãi đăng ký gói dịch vụ', 'percent', 15, 80, 9, 'active', -25, 150],
            ['DEMO2026PROPLUS03', 'Giảm giá cho gói Pro', 'fixed', 100000, 40, 7, 'active', -20, 120],
            ['DEMO2026ENTER04', 'Ưu đãi khách hàng doanh nghiệp', 'percent', 20, 50, 18, 'active', -15, 90],
            ['DEMO2026SUMMER05', 'Khuyến mãi mùa hè', 'fixed', 150000, 60, 22, 'active', -10, 75],
            ['DEMO2026PARTNER06', 'Mã dành cho đối tác giới thiệu', 'percent', 12, 120, 35, 'active', -8, 60],
            ['DEMO2026LOYALTY07', 'Ưu đãi thành viên thân thiết', 'percent', 8, 200, 46, 'active', -5, 45],
            ['DEMO2026BUNDLE08', 'Giảm giá khi mua theo gói', 'fixed', 200000, 35, 11, 'active', -2, 30],
            ['DEMO2026FLASH09', 'Flash sale cuối tuần', 'percent', 25, 30, 30, 'inactive', -40, -5],
            ['DEMO2026NEWYEAR10', 'Khuyến mãi năm mới', 'fixed', 300000, 25, 25, 'inactive', -60, -20],
            ['DEMO2026SPRING11', 'Ưu đãi khai xuân', 'percent', 18, 100, 100, 'inactive', -90, -45],
            ['DEMO2026BIRTHDAY12', 'Ưu đãi sinh nhật thương hiệu', 'percent', 30, 15, 15, 'active', -12, 14],
            ['DEMO2026LIMIT13', 'Mã giới hạn lượt dùng', 'fixed', 50000, 5, 5, 'active', -3, 30],
            ['DEMO2026VIP14', 'Ưu đãi riêng cho khách VIP', 'percent', 35, 20, 4, 'active', -1, 25],
            ['DEMO2026TEST15', 'Mã kiểm thử quy trình vận hành', 'fixed', 75000, 10, 2, 'inactive', -7, 7],
        ];

        foreach ($coupons as $index => [$code, $description, $type, $value, $maxUses, $usesCount, $status, $startDays, $expiryDays]) {
            $startsAt = now()->addDays($startDays)->setTime(0, 0);
            $expiresAt = now()->addDays($expiryDays)->setTime(23, 59);
            $createdAt = now()->subDays(45 - ($index * 3));

            $coupon = Coupon::withTrashed()->firstOrNew(['code' => $code]);
            if ($coupon->trashed()) {
                $coupon->restore();
            }

            $coupon->forceFill([
                'description' => $description,
                'discount_type' => $type,
                'discount_value' => $value,
                'max_uses' => $maxUses,
                'uses_count' => $usesCount,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => $status,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        $demoCount = Coupon::withTrashed()
            ->where('code', 'like', 'DEMO2026%')
            ->count();

        $this->command?->info("Đã tạo/cập nhật {$demoCount}/15 mã giảm giá mẫu cho trang Super Admin > Mã giảm giá.");
    }
}
