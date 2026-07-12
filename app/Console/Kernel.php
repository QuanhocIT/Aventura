<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('billing:send-reminders')->dailyAt('08:00');
        $schedule->command('billing:sync-statuses')->hourly();
        $schedule->command('reports:generate-daily')->dailyAt('23:59');
        $schedule->command('restaurants:validate-activity')->dailyAt('23:00');
        $schedule->command('restaurants:calculate-health')->dailyAt('23:15');
        $schedule->command('tickets:check-sla')->everyFiveMinutes();

        $schedule->command('reservations:send-reminders')->everyThirtyMinutes();
        $schedule->command('reservations:mark-no-shows')->everyFifteenMinutes();
        $schedule->command('promotions:expire-outdated')->dailyAt('00:01');
        $schedule->command('shifts:auto-close-expired')->dailyAt('23:45');
        $schedule->command('kitchen:alert-overdue-orders')->everyFiveMinutes();
        $schedule->command('trial:onboarding-emails')->dailyAt('09:00');
        $schedule->command('onboarding:sync')->everyThirtyMinutes();
        $schedule->command('kpis:recalculate')->dailyAt('02:00');

        // ── P0: Hot/Cold Separation ──────────────────────────────────────────────
        // Archive đơn cũ > 1 tháng sang orders_archive mỗi đêm lúc 02:30
        // (sau khi reports:generate-daily và kpis:recalculate hoàn tất).
        $schedule->command('orders:archive-old --months=1')
            ->dailyAt('02:30')
            ->withoutOverlapping(60)        // Không chạy đồng thời nếu lần trước chưa xong
            ->onOneServer()                 // Chỉ chạy 1 server khi deploy nhiều instance
            ->runInBackground();

        // Đảm bảo partition tương lai luôn có sẵn cho orders_archive và audit_logs.
        // Chạy ngày đầu mỗi tháng lúc 03:00.
        $schedule->command('db:manage-partitions --forward=6')
            ->monthlyOn(1, '03:00')
            ->withoutOverlapping()
            ->onOneServer();

        // ── P0: Intraday Summary Update ──────────────────────────────────────────
        // Cập nhật RestaurantRevenueSummary mỗi 15 phút trong giờ hoạt động
        // để dashboard summary card luôn fresh mà không scan bảng orders.
        // Job cũng tự dispatch per-restaurant khi order mới hoàn thành (real-time).
        $schedule->job(new \App\Jobs\UpdateIntradaySummaryJob())
            ->everyFifteenMinutes()
            ->between('06:00', '23:59')     // Chỉ chạy trong giờ kinh doanh
            ->onQueue('low')
            ->withoutOverlapping(15)
            ->onOneServer();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
