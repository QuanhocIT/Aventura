<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\OperatingExpense;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\ProfitLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitLossTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restaurant = Restaurant::factory()->create();
    }

    public function test_monthly_pnl_aggregates_revenue_and_expenses(): void
    {
        // 2 đơn hoàn thành trong tháng
        foreach ([['PL1', 300000, 20000], ['PL2', 200000, 0]] as [$number, $total, $discount]) {
            Order::create([
                'restaurant_id' => $this->restaurant->id,
                'order_number' => $number,
                'channel' => 'dine_in',
                'status' => 'completed',
                'payment_status' => 'paid',
                'subtotal' => $total + $discount,
                'discount_amount' => $discount,
                'total_amount' => $total,
                'completed_at' => now()->startOfMonth()->addDays(5),
            ]);
        }

        // 1 đơn tháng trước — không được tính vào kỳ này
        Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => 'PL-OLD',
            'channel' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 999999,
            'discount_amount' => 0,
            'total_amount' => 999999,
            'completed_at' => now()->subMonth(),
        ]);

        $category = ExpenseCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Tiền điện',
        ]);

        OperatingExpense::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'description' => 'Hóa đơn điện tháng này',
            'amount' => 150000,
            'expense_date' => now()->startOfMonth()->addDays(10)->toDateString(),
        ]);

        $report = app(ProfitLossService::class)->buildMonthly(
            $this->restaurant->id,
            now()->year,
            now()->month
        );

        $this->assertSame(2, $report['revenue']['order_count']);
        $this->assertEquals(500000, $report['revenue']['net_revenue']);
        $this->assertEquals(20000, $report['revenue']['discount_total']);
        $this->assertEquals(150000, $report['operating_expenses']['total']);
        $this->assertEquals(150000, $report['operating_expenses']['by_category']['Tiền điện']);
        // Không có BOM/lương → gross = net revenue, net = gross - opex
        $this->assertEquals(500000, $report['gross_profit']);
        $this->assertEquals(350000, $report['net_profit']);
        $this->assertEquals(70.0, $report['net_margin']);
    }

    public function test_pnl_page_renders_for_owner(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('owner');

        $this->actingAs($user)
            ->get('/reports/profit-loss')
            ->assertOk();
    }

    public function test_comparison_includes_six_month_trend(): void
    {
        $result = app(ProfitLossService::class)->buildWithComparison(
            $this->restaurant->id,
            now()->year,
            now()->month
        );

        $this->assertCount(6, $result['trend']);
        $this->assertArrayHasKey('net_revenue', $result['trend'][0]);
        $this->assertArrayHasKey('previous', $result);
    }
}
