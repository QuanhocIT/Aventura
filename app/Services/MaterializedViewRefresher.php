<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Support\MaterializedViews\MaterializedView;
use App\Support\MaterializedViews\MaterializedViewRegistry;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Tổng quát hoá DailyReportService::generateForAllRestaurants() (cùng shape:
 * chunk nhà hàng active, try/catch per-restaurant, Log::error khi lỗi) cho MỌI
 * materialized view khai báo trong config/materialized_views.php, không riêng
 * doanh thu.
 */
class MaterializedViewRefresher
{
    public function __construct(
        private readonly MaterializedViewRegistry $registry,
    ) {}

    /**
     * @return array{processed: int, failed: int, skipped: int, ms: float}
     */
    public function refresh(string $viewName, ?int $restaurantId = null, ?CarbonInterface $date = null): array
    {
        $start = microtime(true);
        $view = $this->registry->get($viewName);
        $date ??= Carbon::today();

        $stats = ['processed' => 0, 'failed' => 0, 'skipped' => 0];

        if ($restaurantId !== null) {
            $this->processOne($view, $restaurantId, $date, $stats);

            return $stats + ['ms' => round((microtime(true) - $start) * 1000, 1)];
        }

        Restaurant::where('status', 'active')
            ->select('id')
            ->chunk($view->chunk, function ($restaurants) use ($view, $date, &$stats) {
                foreach ($restaurants as $restaurant) {
                    $this->processOne($view, $restaurant->id, $date, $stats);
                }
            });

        return $stats + ['ms' => round((microtime(true) - $start) * 1000, 1)];
    }

    private function processOne(MaterializedView $view, int $restaurantId, CarbonInterface $date, array &$stats): void
    {
        try {
            $builder = $view->builder();
            $store = $view->store();

            $scopeKey = $builder->scopeKey(null);
            $payload = $builder->build($restaurantId, null, $date);
            $store->upsert($view, $restaurantId, null, $scopeKey, $date, $payload);

            if ($view->branchScoped) {
                $restaurant = Restaurant::withoutGlobalScopes()->with('branches:id,restaurant_id')->find($restaurantId);

                foreach ($restaurant?->branches ?? [] as $branch) {
                    $branchScopeKey = $builder->scopeKey($branch->id);
                    $branchPayload = $builder->build($restaurantId, $branch->id, $date);
                    $store->upsert($view, $restaurantId, $branch->id, $branchScopeKey, $date, $branchPayload);
                }
            }

            $stats['processed']++;
        } catch (\Throwable $e) {
            $stats['failed']++;
            Log::error('MaterializedViewRefresher: lỗi khi refresh view', [
                'view' => $view->name,
                'restaurant_id' => $restaurantId,
                'date' => $date->toDateString(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
