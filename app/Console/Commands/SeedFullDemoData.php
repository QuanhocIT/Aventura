<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Onboarding\DemoDataSeederService;
use App\Services\Onboarding\DemoReportDataSeeder;
use Illuminate\Console\Command;

/**
 * Một lệnh nạp đầy đủ dữ liệu demo để MỌI báo cáo (P&L, BI, KDS, xu hướng
 * 6 tháng) hiển thị số liệu đẹp:
 *   php artisan demo:full --email=owner@example.com
 *
 * Gồm 2 phần:
 *   1. DemoDataSeederService  — menu + BOM + nhân viên + ca + đơn 30 ngày
 *   2. DemoReportDataSeeder   — đơn 6 tháng + lương + chi phí + bản ghi showcase
 */
class SeedFullDemoData extends Command
{
    protected $signature = 'demo:full
        {--email= : Email chủ nhà hàng (bỏ trống = nhà hàng đầu tiên có owner)}
        {--template=bbq : Template menu (bbq, cafe, bubble_tea)}
        {--reports-only : Chỉ chạy phần bổ sung báo cáo, không seed lại menu/nhân viên}';

    protected $description = 'Nạp đầy đủ dữ liệu demo (menu, nhân viên, 6 tháng đơn hàng, lương, chi phí) cho một nhà hàng.';

    public function handle(DemoDataSeederService $base, DemoReportDataSeeder $reports): int
    {
        $owner = $this->resolveOwner();

        if (! $owner) {
            $this->error('Không tìm thấy chủ nhà hàng. Dùng --email= để chỉ định.');

            return self::FAILURE;
        }

        $restaurant = $owner->restaurant;

        if (! $restaurant) {
            $this->error("Tài khoản {$owner->email} chưa gắn nhà hàng nào.");

            return self::FAILURE;
        }

        $this->info("Nhà hàng: {$restaurant->name} (ID {$restaurant->id})");

        if (! $this->option('reports-only')) {
            $template = $this->option('template');
            $this->line("→ Nạp menu/nhân viên/ca (template: {$template})...");

            if ($restaurant->sandbox_mode) {
                $base->reset($restaurant);
            }

            $base->seed($restaurant, $template);
            $this->info('  ✓ Menu, BOM, nhân viên, ca, đơn 30 ngày');
        }

        $this->line('→ Bổ sung dữ liệu báo cáo (6 tháng đơn, lương, chi phí)...');
        $stats = $reports->enrich($restaurant);

        $this->info('  ✓ Xong');
        $this->table(
            ['Hạng mục', 'Số lượng'],
            [
                ['Đơn hàng 6 tháng', $stats['orders']],
                ['Bảng lương đã duyệt', $stats['salaries']],
                ['Chi phí vận hành', $stats['expenses']],
            ]
        );

        $this->newLine();
        $this->info('✅ Hoàn tất! Mở Báo cáo Lãi/Lỗ, BI Dashboard, Màn hình Bếp để xem số liệu.');

        return self::SUCCESS;
    }

    private function resolveOwner(): ?User
    {
        if ($email = $this->option('email')) {
            return User::where('email', $email)->first();
        }

        return User::role('owner')->whereNotNull('restaurant_id')->first()
            ?? User::whereNotNull('restaurant_id')->first();
    }
}
