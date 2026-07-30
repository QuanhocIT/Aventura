<?php

namespace App\Console\Commands;

use App\Models\OnlineStoreConfig;
use App\Models\User;
use App\Services\Onboarding\DemoDataSeederService;
use App\Services\Onboarding\RestaurantOnboardingService;
use Illuminate\Console\Command;

/**
 * Tạo NHANH một nhà hàng "showcase" sạch, sẵn sàng để demo bán hàng hoặc làm mẫu
 * khi go-live: owner + menu tiếng Việt + bàn + Cửa hàng Online (link đặt món).
 *
 *   php artisan demo:showcase
 *   php artisan demo:showcase --email=me@shop.vn --name="Quán Nướng ABC"
 *   php artisan demo:showcase --with-orders   # nạp thêm menu phong phú + 30 ngày đơn (CHẬM ~vài phút)
 *
 * Mặc định: chỉ tạo tenant sạch qua onboarding (nhanh, tin cậy). Cờ --with-orders
 * mới gọi DemoDataSeederService::seed() (menu phong phú + 30 ngày đơn) — nặng, chỉ
 * dùng khi cần dữ liệu báo cáo cho demo. Idempotent theo email; KHÔNG đụng tenant khác.
 */
class SeedShowcaseDemo extends Command
{
    protected $signature = 'demo:showcase
        {--email=showcase@aventura.local : Email chủ nhà hàng showcase}
        {--name=Nhà hàng Aventura Demo : Tên nhà hàng}
        {--template=bbq : Mẫu menu khi dùng --with-orders (bbq, cafe, bubble_tea)}
        {--password=Aventura@Demo2026 : Mật khẩu owner (đổi ngay nếu dùng thật)}
        {--with-orders : Nạp thêm menu phong phú + 30 ngày đơn (chậm)}';

    protected $description = 'Tạo nhanh một nhà hàng showcase sạch (menu + bàn + storefront) để demo/go-live.';

    public function handle(RestaurantOnboardingService $onboarding, DemoDataSeederService $seeder): int
    {
        $email = (string) $this->option('email');
        $name = (string) $this->option('name');
        $password = (string) $this->option('password');

        $owner = User::where('email', $email)->first();

        if (! $owner) {
            $this->info("→ Tạo nhà hàng showcase: {$name} ({$email})");
            $owner = $onboarding->onboard([
                'name' => 'Chủ ' . $name,
                'restaurant_name' => $name,
                'email' => $email,
                'password' => $password,
                'phone' => '0900000000',
            ]);
            $this->line('  ✓ Owner + nhà hàng + menu mẫu + bàn + Cửa hàng Online');
        } else {
            $this->info("→ Dùng lại nhà hàng của {$email}");
        }

        $restaurant = $owner->restaurant;
        if (! $restaurant) {
            $this->error('Không tìm thấy nhà hàng gắn với owner này.');

            return self::FAILURE;
        }

        // Đảm bảo có Cửa hàng Online (phòng khi là tenant cũ chưa bật).
        OnlineStoreConfig::updateOrCreate(
            ['restaurant_id' => $restaurant->id],
            [
                'is_active' => true,
                'slug' => $restaurant->slug,
                'description' => 'Đặt món trực tuyến tại ' . $restaurant->name,
                'min_order_amount' => 0,
                'delivery_base_fee' => 15000,
                'delivery_fee_per_km' => 5000,
                'max_delivery_km' => 10,
                'enable_takeaway' => true,
                'enable_delivery' => true,
                'enable_preorder' => false,
                'accepted_payments' => ['bank_transfer'],
                'operating_hours' => null,
            ]
        );

        if ($this->option('with-orders')) {
            $this->line('→ Nạp menu phong phú + 30 ngày đơn (có thể mất vài phút)...');
            if ($restaurant->sandbox_mode) {
                $seeder->reset($restaurant);
            }
            $seeder->seed($restaurant, (string) $this->option('template'));
            $this->line('  ✓ Menu phong phú + đơn 30 ngày');
        }

        $this->newLine();
        $this->info('✅ Showcase sẵn sàng!');
        $this->table(['Mục', 'Giá trị'], [
            ['Đăng nhập', $email . '  /  ' . $password],
            ['Trang quản lý', url('/dashboard')],
            ['Cửa hàng Online (khách)', url('/order/' . $restaurant->slug)],
        ]);
        $this->warn('  Lưu ý: dữ liệu MẪU + mật khẩu mặc định — đổi mật khẩu & tạo tenant thật khi kinh doanh.');

        return self::SUCCESS;
    }
}
