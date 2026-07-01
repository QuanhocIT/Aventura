<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\PromotionAnalyticsService;
use Illuminate\Console\Command;

class CalculatePromotionAnalytics extends Command
{
    protected $signature = 'promotions:calculate-analytics {--date=}';
    protected $description = 'Calculate daily promotion analytics snapshots for all restaurants';

    public function handle(PromotionAnalyticsService $analytics): int
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();

        $restaurants = Restaurant::where('status', 'active')->pluck('id');
        $count = 0;

        foreach ($restaurants as $restaurantId) {
            $analytics->calculateDailySnapshot($restaurantId, $date);
            $count++;
        }

        $this->info("Calculated analytics for {$count} restaurants on {$date}.");

        return self::SUCCESS;
    }
}
