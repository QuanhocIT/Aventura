<?php

namespace App\Jobs;

use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Mail\DashboardReportMail;
use App\Models\DashboardReportSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDashboardReportEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public readonly int $subscriptionId) {}

    public function handle(DashboardController $dashboard): void
    {
        $subscription = DashboardReportSubscription::with('user')->find($this->subscriptionId);

        if (! $subscription || ! $subscription->is_active || ! $subscription->user || ! $subscription->user->email) {
            Log::warning('SendDashboardReportEmail: bỏ qua do thiếu cấu hình hoặc email người nhận', [
                'subscription_id' => $this->subscriptionId,
            ]);

            return;
        }

        $now = now();
        $snapshot = $dashboard->buildReportSnapshot($now);

        Mail::to($subscription->user->email)->send(new DashboardReportMail($subscription->frequency, $now, $snapshot));

        $subscription->forceFill(['last_sent_at' => $now])->save();

        Log::info('SendDashboardReportEmail: đã gửi báo cáo định kỳ', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'frequency' => $subscription->frequency,
            'email' => $subscription->user->email,
        ]);
    }

    public function tags(): array
    {
        return ["dashboard-report:{$this->subscriptionId}"];
    }
}
