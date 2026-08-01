<?php

namespace App\Console\Commands;

use App\Models\SystemMaintenanceSchedule;
use App\Models\User;
use App\Services\EmailMicroserviceClient;
use App\Services\ServiceMonitorService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CheckMaintenanceSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:check-maintenance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activate/deactivate scheduled maintenance windows and notify restaurant owners';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $now = Carbon::now();
        $monitor = app(ServiceMonitorService::class);
        $emailClient = app(EmailMicroserviceClient::class);

        // 1. Activate scheduled maintenance
        $toActivate = SystemMaintenanceSchedule::where('status', 'scheduled')
            ->where('downtime_start', '<=', $now)
            ->where('downtime_end', '>', $now)
            ->get();

        foreach ($toActivate as $schedule) {
            $schedule->update(['status' => 'active']);
            $services = $schedule->services;
            $msg = $schedule->banner_message ?: 'Hệ thống đang được bảo trì theo lịch.';

            foreach ($services as $service) {
                $monitor->setMaintenance($service, true, $msg);
            }

            Log::info("Activated maintenance schedule ID: {$schedule->id}. Services put in maintenance: ".implode(', ', $services));
            $this->info("Activated maintenance schedule ID: {$schedule->id}");
        }

        // 2. Deactivate completed maintenance
        $toDeactivate = SystemMaintenanceSchedule::where('status', 'active')
            ->where('downtime_end', '<=', $now)
            ->get();

        foreach ($toDeactivate as $schedule) {
            $schedule->update(['status' => 'completed']);
            $services = $schedule->services;

            foreach ($services as $service) {
                $monitor->setMaintenance($service, false);
            }

            Log::info("Completed and deactivated maintenance schedule ID: {$schedule->id}");
            $this->info("Completed maintenance schedule ID: {$schedule->id}");
        }

        // 3. Email notifications (24h warning)
        $warn24h = SystemMaintenanceSchedule::where('status', 'scheduled')
            ->where('downtime_start', '<=', $now->copy()->addHours(24))
            ->where('notified_24h', false)
            ->get();

        foreach ($warn24h as $schedule) {
            $schedule->update(['notified_24h' => true]);
            $this->sendNotificationToOwners($emailClient, $schedule, '24 giờ');
        }

        // 4. Email notifications (1h warning)
        $warn1h = SystemMaintenanceSchedule::where('status', 'scheduled')
            ->where('downtime_start', '<=', $now->copy()->addHours(1))
            ->where('notified_1h', false)
            ->get();

        foreach ($warn1h as $schedule) {
            $schedule->update(['notified_1h' => true]);
            $this->sendNotificationToOwners($emailClient, $schedule, '1 giờ');
        }
    }

    private function sendNotificationToOwners(EmailMicroserviceClient $emailClient, SystemMaintenanceSchedule $schedule, string $timeLeft): void
    {
        $owners = User::whereHas('roles', function ($q) {
            $q->where('name', 'owner');
        })->get();

        $startStr = $schedule->downtime_start->format('H:i d/m/Y');
        $endStr = $schedule->downtime_end->format('H:i d/m/Y');
        $servicesStr = implode(', ', array_map(fn ($s) => $s === 'mysql' ? 'Hệ thống chính' : $s, $schedule->services));

        $title = '🚨 Thông báo bảo trì hệ thống Aventura sắp diễn ra';
        $content = 'Kính gửi quý đối tác,<br><br>'.
            'Hệ thống Aventura xin thông báo sẽ tiến hành bảo trì nâng cấp định kỳ:<br>'.
            '<ul>'.
            "<li><strong>Thời gian bắt đầu:</strong> {$startStr}</li>".
            "<li><strong>Thời gian hoàn tất dự kiến:</strong> {$endStr}</li>".
            "<li><strong>Dịch vụ bị ảnh hưởng:</strong> {$servicesStr}</li>".
            '</ul>'.
            'Hệ thống sẽ tạm thời không khả dụng trong thời gian bảo trì. Xin quý khách vui lòng lưu lại dữ liệu trước thời gian trên.<br>'.
            'Trân trọng,<br>Đội ngũ Aventura.';

        foreach ($owners as $owner) {
            $emailClient->sendCampaignEmail([
                'email' => $owner->email,
                'name' => $owner->name,
                'title' => $title,
                'content' => $content,
            ]);
        }

        Log::info("Sent {$timeLeft} maintenance warning email for schedule ID: {$schedule->id} to ".count($owners).' owners');
    }
}
