<?php

namespace App\Jobs;

use App\Models\Restaurant;
use App\Services\DailyReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Cập nhật RestaurantRevenueSummary trong ngày (intraday) để dashboard luôn
 * hiển thị dữ liệu tổng hợp mới nhất mà không cần query trực tiếp bảng orders.
 *
 * Được dispatch bởi Kernel schedule mỗi 15 phút.
 * Có thể dispatch cho 1 restaurant cụ thể (sau khi order completed) hoặc
 * toàn bộ nhà hàng active (batch job).
 */
class UpdateIntradaySummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Số lần retry tối đa */
    public int $tries = 3;

    /** Timeout tính bằng giây */
    public int $timeout = 120;

    /**
     * @param int|null $restaurantId  null = cập nhật tất cả nhà hàng active
     */
    public function __construct(
        public readonly ?int $restaurantId = null,
    ) {}

    public function handle(DailyReportService $reportService): void
    {
        $date = today()->toDateString();

        if ($this->restaurantId !== null) {
            $this->processOne($this->restaurantId, $date, $reportService);
            return;
        }

        // Batch: chạy cho tất cả nhà hàng active
        Restaurant::where('status', 'active')
            ->select('id')
            ->chunk(100, function ($restaurants) use ($date, $reportService) {
                foreach ($restaurants as $restaurant) {
                    $this->processOne($restaurant->id, $date, $reportService);
                }
            });
    }

    private function processOne(int $restaurantId, string $date, DailyReportService $service): void
    {
        try {
            $service->generateForRestaurant($restaurantId, $date);
        } catch (\Throwable $e) {
            Log::warning('UpdateIntradaySummaryJob: lỗi khi cập nhật summary', [
                'restaurant_id' => $restaurantId,
                'date'          => $date,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
