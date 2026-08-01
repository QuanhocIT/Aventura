<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Services\BranchReportService;
use App\Services\ProfitLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_revenue_and_pnl_equal_the_sum_of_active_branches(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branchA = RestaurantBranch::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'A',
            'name' => 'Chi nhánh A',
            'status' => 'active',
        ]);
        $branchB = RestaurantBranch::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'B',
            'name' => 'Chi nhánh B',
            'status' => 'active',
        ]);

        $completedAt = now()->startOfMonth()->addDays(3);
        foreach ([[$branchA, 300000, 10000], [$branchB, 500000, 20000]] as [$branch, $total, $discount]) {
            Order::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'order_number' => 'BR-'.$branch->code,
                'channel' => 'dine_in',
                'status' => 'completed',
                'payment_status' => 'paid',
                'subtotal' => $total + $discount,
                'discount_amount' => $discount,
                'total_amount' => $total,
                'completed_at' => $completedAt,
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);
        }

        $reports = app(BranchReportService::class);
        $all = $reports->summarize($reports->dailyRevenue(
            $restaurant->id,
            $completedAt->copy()->startOfMonth(),
            $completedAt->copy()->endOfMonth(),
        ));
        $a = $reports->summarize($reports->dailyRevenue(
            $restaurant->id,
            $completedAt->copy()->startOfMonth(),
            $completedAt->copy()->endOfMonth(),
            $branchA->id,
        ));
        $b = $reports->summarize($reports->dailyRevenue(
            $restaurant->id,
            $completedAt->copy()->startOfMonth(),
            $completedAt->copy()->endOfMonth(),
            $branchB->id,
        ));

        $this->assertEquals($a['net_revenue'] + $b['net_revenue'], $all['net_revenue']);
        $this->assertSame($a['order_count'] + $b['order_count'], $all['order_count']);

        $pnl = app(ProfitLossService::class);
        $chainPnl = $pnl->buildWithComparison($restaurant->id, $completedAt->year, $completedAt->month);
        $branchAPnl = $pnl->buildMonthly($restaurant->id, $completedAt->year, $completedAt->month, $branchA->id);
        $branchBPnl = $pnl->buildMonthly($restaurant->id, $completedAt->year, $completedAt->month, $branchB->id);

        $this->assertEquals(
            $branchAPnl['revenue']['net_revenue'] + $branchBPnl['revenue']['net_revenue'],
            $chainPnl['current']['revenue']['net_revenue'],
        );
        $this->assertTrue($chainPnl['branch_reconciliation']['is_consistent']);
    }
}
