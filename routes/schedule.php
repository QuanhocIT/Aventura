<?php

use App\Jobs\RecalculateTrustScoresJob;
use App\Services\SupportPortalService;
use App\Support\MaterializedViews\MaterializedViewRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

/**
 * NGUỒN DUY NHẤT khai báo lịch chạy định kỳ của hệ thống (Task Scheduler).
 *
 * TRƯỚC ĐÂY có 2 nơi định nghĩa lịch cùng lúc:
 *   - app/Console/Kernel.php (14 tác vụ) — đã XOÁ vì là DEAD CODE. Từ Laravel 11+,
 *     bootstrap/app.php dùng Application::configure()->withKernels(), hàm này bind
 *     thẳng Illuminate\Foundation\Console\Kernel, KHÔNG bind App\Console\Kernel.
 *     Xác nhận bằng `php artisan schedule:list` trước khi sửa: chỉ in ra đúng các
 *     dòng từ routes/console.php, không có dòng nào từ Kernel.php.
 *   - routes/console.php (23 tác vụ, qua app(Schedule::class)->command(...)) — vẫn
 *     chạy được nhưng lẫn cùng chỗ định nghĩa Artisan::command() closures dài dòng.
 *
 * File này gộp lại TOÀN BỘ 2 nguồn trên làm MỘT, đăng ký qua bootstrap/app.php
 * bằng ->withSchedule(). routes/console.php giờ chỉ còn giữ các Artisan::command()
 * định nghĩa lệnh (không định nghĩa lịch nữa).
 *
 * Khi hồi sinh 14 tác vụ từng "chết" trong Kernel.php, một số tác vụ MUTATE dữ liệu
 * hoặc GỬI EMAIL cho khách hàng thật (ví dụ trial:onboarding-emails có thể gửi email
 * hàng loạt cho toàn bộ khách đang dùng thử). Để tránh "vách rủi ro" bật đồng loạt,
 * mọi tác vụ mới hồi sinh được bọc qua $restoreGuard — mặc định TẮT, phải bật thủ công
 * từng cái một bằng biến môi trường tương ứng sau khi đã kiểm tra thủ công một lần.
 * Xem SCHEDULER_RESTORE.md để biết trình tự bật khuyến nghị.
 */
return function (Schedule $schedule): void {
    // Task logger helper
    $logRun = function (string $taskName, callable $callback) {
        $startTime = microtime(true);
        $runId = null;
        try {
            $run = \App\Models\ScheduledTaskRun::create([
                'task_name' => $taskName,
                'started_at' => now(),
                'status' => 'running',
            ]);
            $runId = $run->id;
        } catch (\Throwable) {}

        try {
            $callback();
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            if ($runId) {
                \App\Models\ScheduledTaskRun::where('id', $runId)->update([
                    'finished_at' => now(),
                    'status' => 'success',
                    'duration_ms' => $durationMs,
                ]);
            }
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            if ($runId) {
                \App\Models\ScheduledTaskRun::where('id', $runId)->update([
                    'finished_at' => now(),
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'duration_ms' => $durationMs,
                ]);
            }
            throw $e;
        }
    };

    // Operations Center heartbeat: proves that the Laravel scheduler itself is ticking,
    // independently from the health checks it runs.
    $schedule->call(function () use ($logRun): void {
        $logRun('operations-center-heartbeat', function () {
            $path = storage_path('framework/scheduler-heartbeat.json');
            @file_put_contents($path, json_encode([
                'last_run_at' => now()->toIso8601String(),
                'pid' => getmypid(),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        });
    })->everyMinute()->name('operations-center-heartbeat')->withoutOverlapping();

    // ── Nhóm việc đã và đang chạy ổn định (giữ nguyên từ routes/console.php cũ) ──
    $schedule->command('billing:send-reminders')->dailyAt('08:00');
    $schedule->command('billing:sync-statuses')->hourly();
    $schedule->command('dashboard:send-scheduled-reports')->dailyAt('07:30');
    $schedule->command('news:publish-scheduled')->everyFiveMinutes();
    $schedule->command('expenses:process-recurring')->dailyAt('00:05');
    $schedule->command('debts:send-reminders')->dailyAt('08:30');
    $schedule->call(function () {
        app(SupportPortalService::class)->evaluateAlerts();
    })->everyFiveMinutes();
    $schedule->command('services:check-health')->everyFiveMinutes();
    $schedule->command('system:check-maintenance')->everyMinute();
    $schedule->command('db:backup')->dailyAt('02:00');
    $schedule->command('db:optimize')->weeklyOn(0, '03:00');
    $schedule->command('loyalty:expire-points')->dailyAt('00:30');
    $schedule->command('loyalty:birthday-bonuses')->dailyAt('09:00');
    $schedule->command('inventory:check-expiry')->dailyAt('07:00');
    $schedule->command('reservations:cleanup-expired')->everyThirtyMinutes();
    $schedule->command('checklist:send-reminders')->dailyAt('10:00');
    $schedule->command('checklist:send-reminders')->dailyAt('16:00');
    $schedule->command('system:audit-consistency')->dailyAt('03:00');
    $schedule->command('goals:sync')->hourly();
    $schedule->call(function (): void {
        app(\App\Services\ApprovalService::class)->autoEscalateOverdue();
    })->everyMinute()->name('approval-auto-escalation')->withoutOverlapping();

    // ── [P1.11]: Quét phát hiện gian lận kho & Cảnh báo đơn cấp phát quá hạn ───────
    $schedule->call(function () {
        $restaurants = \App\Models\Restaurant::where('status', 'active')->get();
        $fraudService = app(\App\Services\WarehouseFraudDetectionService::class);
        $centralService = app(\App\Services\CentralWarehouseService::class);

        foreach ($restaurants as $restaurant) {
            try {
                $fraudService->analyzeRiskAndFraudPatterns((int) $restaurant->id);
                $centralService->checkAndAlertOverdueRequests((int) $restaurant->id);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Lỗi scheduler kiểm tra kho cho nhà hàng #{$restaurant->id}: " . $e->getMessage());
            }
        }
    })->everyTenMinutes()->name('warehouse-fraud-and-overdue-monitoring')->withoutOverlapping();

    // ── Chuỗi báo cáo/tổng hợp cuối ngày — PHẢI giữ đúng thứ tự (comment gốc trong
    //    Kernel.php cũ đã ghi rõ lý do: archive chạy sau khi report + kpi xong) ──
    $schedule->command('reports:generate-daily')->dailyAt('23:59');

    // promotions:calculate-analytics: MỒ CÔI hoàn toàn trước đây (không nằm ở cả 2
    // nguồn lịch cũ) — bảng promotion_analytics_snapshots chỉ được điền khi ai đó
    // chạy tay. --date mặc định = hôm qua nên đặt giờ chạy sau nửa đêm.
    $schedule->command('promotions:calculate-analytics')->dailyAt('00:20');

    // kpis:calculate vs kpis:recalculate TRÙNG NHAU: cả 2 đều gọi
    // KpiService::calculateKpi() cho toàn bộ nhân viên active của tháng hiện tại.
    // kpis:calculate (ProcessKpis) là bản đầy đủ hơn (có --period/--restaurant, đếm
    // skip, try/catch per-employee) — giữ lại, đã xoá lệnh kpis:recalculate.
    $schedule->command('kpis:calculate')->dailyAt('01:00');

    $schedule->job(new RecalculateTrustScoresJob)->dailyAt('02:00');

    // orders:archive-old: TỪNG chạy monthly không tham số (bỏ sót --months=1 mà
    // Kernel.php cũ định làm). Vì quyết định KHÔNG partition bảng `orders` (giữ FK),
    // việc giữ bảng hot nhỏ qua archive hàng ngày là cơ chế hiệu năng chính. Lên dần:
    // bắt đầu 3 tháng/ngày, xác nhận ổn định vài ngày rồi mới xiết còn 1 tháng.
    $schedule->command('orders:archive-old --months=3')
        ->dailyAt('02:30')
        ->withoutOverlapping(60)
        ->onOneServer()
        ->runInBackground();

    // db:manage-partitions: đảm bảo partition tương lai luôn có sẵn cho
    // orders_archive/order_items_archive/audit_logs. Chạy hàng tháng NGÀY 1 (như cũ)
    // + THÊM hàng tuần — một lần lỗi hàng tháng từng có nghĩa mất cả tháng dự phòng.
    $schedule->command('db:manage-partitions --forward=6')
        ->monthlyOn(1, '03:15')
        ->withoutOverlapping()
        ->onOneServer();
    $schedule->command('db:manage-partitions --forward=6')
        ->weeklyOn(1, '03:15')
        ->withoutOverlapping()
        ->onOneServer();

    // --prune: dọn partition quá hạn retention_months (config/partitioning.php) —
    // bảng retention_months=null (tiền mặt, hoa hồng, điểm thưởng...) tự bỏ qua.
    // Chạy riêng, sau job đảm bảo partition tương lai ở trên, hàng tháng cùng ngày.
    $schedule->command('db:manage-partitions --prune')
        ->monthlyOn(1, '03:45')
        ->withoutOverlapping()
        ->onOneServer();

    // orders:purge: TỪNG KHÔNG được lên lịch ở đâu cả — phải chạy tay. Mặc định lệnh
    // là dry-run (an toàn), phải thêm --confirm mới thực sự DROP partition.
    $schedule->command('orders:purge --months=36 --confirm')
        ->monthlyOn(1, '04:00')
        ->withoutOverlapping()
        ->onOneServer();

    // ── Khung Materialized View (config/materialized_views.php) ──────────────────
    // Mỗi view tự khai báo cron/khung giờ riêng — vòng lặp này chỉ đăng ký, không
    // hard-code tần suất ở đây. `revenue_daily` thay đúng vị trí UpdateIntradaySummaryJob
    // từng nằm trong Kernel.php chết (job cũ giờ là shim tương thích ngược gọi
    // thẳng vào MaterializedViewRefresher, xem app/Jobs/UpdateIntradaySummaryJob.php —
    // Order model vẫn dispatch job cũ trực tiếp sau khi 1 order hoàn thành, real-time).
    foreach (app(MaterializedViewRegistry::class)->enabled() as $mv) {
        $event = $schedule->command("mv:refresh {$mv->name}")
            ->cron($mv->cron)
            ->withoutOverlapping(max(1, intdiv($mv->lockSeconds, 60)))
            ->onOneServer()
            ->runInBackground();

        if ($mv->between) {
            $event->between($mv->between[0], $mv->between[1]);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // NHÓM HỒI SINH TỪ app/Console/Kernel.php (14 tác vụ, TỪNG CHẾT — chưa từng
    // chạy thật trong hệ thống này). Bọc qua cờ env riêng từng tác vụ, MẶC ĐỊNH TẮT.
    // Bật dần theo thứ tự khuyến nghị trong SCHEDULER_RESTORE.md, không bật đồng loạt.
    // ══════════════════════════════════════════════════════════════════════════

    $restoreGuard = fn (string $envKey) => ! filter_var(env($envKey, false), FILTER_VALIDATE_BOOL);

    // Fix #10: Cảnh báo khi các job vận hành quan trọng chưa được bật
    $criticalJobs = [
        'SCHEDULER_RESTORE_RESERVATIONS_NO_SHOWS' => 'reservations:mark-no-shows',
        'SCHEDULER_RESTORE_RESERVATIONS_EXPIRE_PENDING' => 'reservations:expire-pending',
        'SCHEDULER_RESTORE_SHIFTS_AUTO_CLOSE' => 'shifts:auto-close-expired',
        'SCHEDULER_RESTORE_KITCHEN_ALERT_OVERDUE' => 'kitchen:alert-overdue-orders',
        'SCHEDULER_RESTORE_RESERVATIONS_REMINDERS' => 'reservations:send-reminders',
    ];
    foreach ($criticalJobs as $envKey => $cmd) {
        if ($restoreGuard($envKey)) {
            Log::warning("Scheduler: Job '{$cmd}' đang TẮT. Bật env {$envKey}=true trước khi go-live.");
        }
    }

    $schedule->command('restaurants:validate-activity')
        ->dailyAt('23:00')
        ->skip($restoreGuard('SCHEDULER_RESTORE_RESTAURANTS_VALIDATE'));

    $schedule->command('restaurants:calculate-health')
        ->dailyAt('23:15')
        ->skip($restoreGuard('SCHEDULER_RESTORE_RESTAURANTS_HEALTH'));

    $schedule->command('tickets:check-sla')
        ->everyFiveMinutes()
        ->skip($restoreGuard('SCHEDULER_RESTORE_TICKETS_SLA'));

    $schedule->command('reservations:send-reminders')
        ->everyThirtyMinutes()
        ->skip($restoreGuard('SCHEDULER_RESTORE_RESERVATIONS_REMINDERS'));

    $schedule->command('reservations:mark-no-shows')
        ->everyFifteenMinutes()
        ->skip($restoreGuard('SCHEDULER_RESTORE_RESERVATIONS_NO_SHOWS'));

    $schedule->command('reservations:expire-pending')
        ->everyFifteenMinutes()
        ->skip($restoreGuard('SCHEDULER_RESTORE_RESERVATIONS_EXPIRE_PENDING'));

    $schedule->command('promotions:expire-outdated')
        ->dailyAt('00:01')
        ->skip($restoreGuard('SCHEDULER_RESTORE_PROMOTIONS_EXPIRE'));

    $schedule->command('shifts:auto-close-expired')
        ->dailyAt('23:45')
        ->skip($restoreGuard('SCHEDULER_RESTORE_SHIFTS_AUTO_CLOSE'));

    // Tự chốt giờ ra cho nhân viên quên check-out ngay khi ca kết thúc.
    $schedule->command('attendance:auto-checkout')
        ->everyMinute()
        ->withoutOverlapping(1);

    $schedule->command('kitchen:alert-overdue-orders')
        ->everyFiveMinutes()
        ->skip($restoreGuard('SCHEDULER_RESTORE_KITCHEN_ALERT_OVERDUE'));

    // GỬI EMAIL cho khách hàng thật — rủi ro cao nhất trong nhóm hồi sinh. Kiểm tra
    // thủ công (dry-run nếu lệnh hỗ trợ) trước khi bật cờ env này ở production.
    $schedule->command('trial:onboarding-emails')
        ->dailyAt('09:00')
        ->skip($restoreGuard('SCHEDULER_RESTORE_TRIAL_ONBOARDING_EMAILS'));

    $schedule->command('onboarding:sync')
        ->everyThirtyMinutes()
        ->skip($restoreGuard('SCHEDULER_RESTORE_ONBOARDING_SYNC'));

    $schedule->command('training:sync-compliance')
        ->hourly()
        ->skip($restoreGuard('SCHEDULER_RESTORE_TRAINING_COMPLIANCE'));
};
