<?php

namespace Database\Seeders;

use Database\Seeders\System\ChatbotKnowledgeSeeder;
use Database\Seeders\System\SubscriptionPlanSeeder;
use Illuminate\Database\Seeder;

/**
 * ProductionSeeder — chỉ seed dữ liệu hệ thống tối thiểu.
 *
 * KHÔNG chứa demo/test data. Dùng cho lần deploy đầu tiên trên server thật:
 *   docker compose exec -T app php artisan db:seed --class=ProductionSeeder --force
 *
 * Để seed thêm demo (môi trường staging/local):
 *   php artisan db:seed  ← chạy DatabaseSeeder đầy đủ
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Bắt đầu seed dữ liệu hệ thống cho production...');

        $this->call([
            PermissionsSeeder::class,       // Roles (super_admin, admin, owner, manager...) + Permissions
            SubscriptionPlanSeeder::class,  // Gói Free / Pro / Custom với giới hạn tài nguyên
            SuperAdminSeeder::class,        // Tài khoản Super Admin mặc định
            ChatbotKnowledgeSeeder::class,  // Cơ sở kiến thức FAQ chatbot landing page
        ]);

        $this->command->newLine();
        $this->command->info('✅ Production seed hoàn tất thành công!');
        $this->command->newLine();
        $this->command->alert('⚠️  BẢO MẬT: Đổi mật khẩu Super Admin NGAY sau khi đăng nhập lần đầu!');
        $this->command->line('   URL đăng nhập : '.config('app.url').'/super-admin/login');
        $this->command->line('   Email mặc định: superadmin@aventura.local');
        $this->command->line('   Mật khẩu tạm  : Aventura@2026!');
        $this->command->newLine();
    }
}
