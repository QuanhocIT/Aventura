<?php

namespace Tests\Unit;

use App\Services\CentralWarehouseAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralWarehouseAiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prioritizes_operational_risks_without_mutating_data(): void
    {
        $assessment = (new CentralWarehouseAiService)->analyze([
            'supplyAnalytics' => [
                'period_days' => 28,
                'summary' => [
                    'urgent_recommendations' => 2,
                    'open_requests' => 6,
                    'overdue_requests' => 3,
                    'disputed_requests' => 1,
                    'fill_rate_percent' => 80,
                    'last7_requests' => 4,
                ],
                'recommendations' => [[
                    'name' => 'Thịt bò',
                    'trend_percent' => 25,
                    'priority' => 'urgent',
                ]],
            ],
            'inventorySummary' => [
                'low_stock_count' => 2,
                'zero_stock_count' => 1,
                'expired_batch_count' => 1,
                'expiring_soon_count' => 2,
            ],
            'receivingSummary' => [
                'discrepancy_vouchers' => 2,
                'discrepancy_quantity' => 3,
            ],
            'centralWarehouseAnalytics' => [
                'otif_percent' => 82,
            ],
            'warehouseTasks' => [[
                'status' => 'assigned',
                'due_at' => now()->subHour()->toISOString(),
            ]],
        ]);

        $this->assertSame('critical', $assessment['level']);
        $this->assertSame(0, $assessment['score']);
        $this->assertGreaterThanOrEqual(5, $assessment['signal_count']);
        $this->assertSame('inventory_forecast', $assessment['signals'][0]['source']);
        $this->assertSame(0.86, $assessment['confidence']);
    }
}
