<?php

namespace App\Jobs;

use App\Services\DailyReportService;
use App\Services\EmailMicroserviceClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDailyReportEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(
        public readonly int $restaurantId,
        public readonly string $date,
    ) {}

    public function handle(DailyReportService $service, EmailMicroserviceClient $client): void
    {
        $data = $service->generateForRestaurant($this->restaurantId, $this->date);
        $data = $service->fillComparison($data);

        if (empty($data['owner_email'])) {
            Log::warning('SendDailyReportEmail: bỏ qua do không có owner email', [
                'restaurant_id' => $this->restaurantId,
                'date'          => $this->date,
            ]);
            return;
        }

        $sent = $client->sendDailyReport($data);

        Log::info('SendDailyReportEmail: ' . ($sent ? 'thành công' : 'thất bại'), [
            'restaurant_id' => $this->restaurantId,
            'date'          => $this->date,
            'email'         => $data['owner_email'],
        ]);
    }

    public function tags(): array
    {
        return ["restaurant:{$this->restaurantId}", "daily-report:{$this->date}"];
    }
}
