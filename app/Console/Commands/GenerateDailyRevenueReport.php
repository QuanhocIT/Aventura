<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyReportEmail;
use App\Models\Restaurant;
use App\Services\DailyReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyRevenueReport extends Command
{
    protected $signature = 'reports:generate-daily
                            {date? : Ngày cần tạo báo cáo (YYYY-MM-DD), mặc định hôm nay}
                            {--restaurant= : Chỉ chạy cho 1 restaurant_id cụ thể}
                            {--no-email : Tính toán và lưu DB nhưng không gửi email}';

    protected $description = 'Tạo báo cáo doanh thu ngày và gửi email tự động cho chủ nhà hàng';

    public function handle(DailyReportService $service): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))->toDateString()
            : today()->toDateString();

        $noEmail      = $this->option('no-email');
        $restaurantId = $this->option('restaurant');

        $this->info("Bắt đầu tạo báo cáo ngày {$date}...");

        if ($restaurantId) {
            return $this->processSingle((int) $restaurantId, $date, $noEmail, $service);
        }

        return $this->processAll($date, $noEmail, $service);
    }

    private function processSingle(int $restaurantId, string $date, bool $noEmail, DailyReportService $service): int
    {
        try {
            $data = $service->generateForRestaurant($restaurantId, $date);
            $data = $service->fillComparison($data);
            $this->info("✓ Restaurant #{$restaurantId} ({$data['restaurant_name']}): net_revenue = " . number_format($data['net_revenue'], 0, ',', '.') . 'đ');

            if (! $noEmail && ! empty($data['owner_email'])) {
                SendDailyReportEmail::dispatch($restaurantId, $date);
                $this->line("  → Email sẽ gửi tới {$data['owner_email']}");
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("✗ Restaurant #{$restaurantId}: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function processAll(string $date, bool $noEmail, DailyReportService $service): int
    {
        $processed = 0;
        $failed    = 0;
        $skipped   = 0;

        Restaurant::where('status', 'active')
            ->with('owner')
            ->chunk(50, function ($restaurants) use ($date, $noEmail, $service, &$processed, &$failed, &$skipped) {
                foreach ($restaurants as $restaurant) {
                    if (empty($restaurant->owner?->email)) {
                        $this->line("  - Skip #{$restaurant->id} ({$restaurant->name}): không có owner email");
                        $skipped++;
                        continue;
                    }

                    try {
                        $data = $service->generateForRestaurant($restaurant->id, $date);
                        $data = $service->fillComparison($data);

                        $this->line(sprintf(
                            '  ✓ #%d %s — %sđ (%d đơn)',
                            $restaurant->id,
                            $restaurant->name,
                            number_format($data['net_revenue'], 0, ',', '.'),
                            $data['completed_count']
                        ));

                        if (! $noEmail) {
                            SendDailyReportEmail::dispatch($restaurant->id, $date);
                        }

                        $processed++;
                    } catch (\Throwable $e) {
                        $this->error("  ✗ #{$restaurant->id} {$restaurant->name}: {$e->getMessage()}");
                        $failed++;
                    }
                }
            });

        $this->newLine();
        $this->info("Hoàn tất: {$processed} thành công, {$failed} lỗi, {$skipped} bỏ qua.");

        if (! $noEmail && $processed > 0) {
            $this->info("Email được dispatch vào queue.");
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
