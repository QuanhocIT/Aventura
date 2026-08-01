<?php

namespace App\Jobs;

use App\Models\Restaurant;
use App\Services\EmployeeTrustScoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * RecalculateTrustScoresJob
 *
 * Chạy định kỳ đêm (02:00 AM) để tính toán lại điểm tín nhiệm
 * cho tất cả nhân viên của mọi nhà hàng trong hệ thống.
 */
class RecalculateTrustScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $restaurantId = null
    ) {}

    public function handle(EmployeeTrustScoreService $service): void
    {
        if ($this->restaurantId) {
            $service->recalculateAll($this->restaurantId);
            Log::info("Recalculated trust scores for restaurant #{$this->restaurantId}");

            return;
        }

        // Tính lại cho tất cả nhà hàng active
        $restaurants = Restaurant::pluck('id');
        $count = 0;

        foreach ($restaurants as $rId) {
            $service->recalculateAll($rId);
            $count++;
        }

        Log::info("Recalculated employee trust scores across {$count} restaurants.");
    }
}
