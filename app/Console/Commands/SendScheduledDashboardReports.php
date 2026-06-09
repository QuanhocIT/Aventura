<?php

namespace App\Console\Commands;

use App\Jobs\SendDashboardReportEmail;
use App\Models\DashboardReportSubscription;
use Illuminate\Console\Command;

class SendScheduledDashboardReports extends Command
{
    protected $signature = 'dashboard:send-scheduled-reports';

    protected $description = 'Gửi báo cáo tổng quan SuperAdmin Dashboard định kỳ (tuần/tháng) cho các tài khoản đã đăng ký';

    public function handle(): int
    {
        $now = now();

        $dueSubscriptions = DashboardReportSubscription::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (DashboardReportSubscription $subscription) => $subscription->isDue($now));

        if ($dueSubscriptions->isEmpty()) {
            $this->info('Không có báo cáo định kỳ nào đến hạn gửi.');

            return Command::SUCCESS;
        }

        foreach ($dueSubscriptions as $subscription) {
            SendDashboardReportEmail::dispatch($subscription->id);
            $this->line("→ Đã đưa vào hàng đợi: subscription #{$subscription->id} (user #{$subscription->user_id}, {$subscription->frequency})");
        }

        $this->info("Đã đưa vào hàng đợi {$dueSubscriptions->count()} báo cáo định kỳ.");

        return Command::SUCCESS;
    }
}
