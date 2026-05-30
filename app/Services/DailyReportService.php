<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantRevenueSummary;
use App\Models\ShiftClosing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyReportService
{
    public function generateForAllRestaurants(string $date): array
    {
        $stats = ['processed' => 0, 'failed' => 0, 'skipped' => 0];

        Restaurant::where('status', 'active')
            ->with('owner')
            ->chunk(50, function ($restaurants) use ($date, &$stats) {
                foreach ($restaurants as $restaurant) {
                    try {
                        $this->generateForRestaurant($restaurant->id, $date);
                        $stats['processed']++;
                    } catch (\Throwable $e) {
                        $stats['failed']++;
                        Log::error('DailyReportService: lỗi khi tạo báo cáo', [
                            'restaurant_id' => $restaurant->id,
                            'date'          => $date,
                            'error'         => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $stats;
    }

    public function generateForRestaurant(int $restaurantId, string $date): array
    {
        $restaurant = Restaurant::with('owner')->findOrFail($restaurantId);

        $revenue    = $this->calculateRevenue($restaurantId, $date);
        $cogs       = $this->calculateCogs($restaurantId, $date);
        $products   = $this->getTopProducts($restaurantId, $date);
        $shifts     = $this->getShiftSummary($restaurantId, $date);
        $comparison = $this->getComparison($restaurantId, $date);
        $peakHour   = $this->getPeakHour($restaurantId, $date);

        $data = array_merge($revenue, [
            'restaurant_id'   => $restaurantId,
            'restaurant_name' => $restaurant->name,
            'owner_email'     => $restaurant->owner?->email ?? '',
            'report_date'     => $date,
            'report_date_vn'  => Carbon::parse($date)->format('d/m/Y'),
            'cogs_amount'     => $cogs,
            'gross_profit'    => $revenue['net_revenue'] - $cogs,
            'top_products'    => $products,
            'shift_summary'   => $shifts,
            'has_unconfirmed_shifts' => collect($shifts)->contains(
                fn ($s) => in_array($s['status'], ['draft', 'submitted'])
            ),
            'comparison'      => $comparison,
            'peak_hour'       => $peakHour,
            'calculated_at'   => now(),
        ]);

        $this->upsertSummary($restaurantId, $date, $data);

        return $data;
    }

    private function upsertSummary(int $restaurantId, string $date, array $data): RestaurantRevenueSummary
    {
        return RestaurantRevenueSummary::withoutGlobalScopes()->updateOrCreate(
            [
                'restaurant_id' => $restaurantId,
                'scope_key'     => 'restaurant',
                'summary_type'  => 'daily',
                'summary_date'  => $date,
            ],
            [
                'branch_id'               => null,
                'order_count'             => $data['order_count'],
                'completed_order_count'   => $data['completed_count'],
                'cancelled_order_count'   => $data['cancelled_count'],
                'gross_revenue'           => $data['gross_revenue'],
                'discount_total'          => $data['discount_total'],
                'service_charge_total'    => $data['service_charge'],
                'tax_total'               => $data['tax_total'],
                'net_revenue'             => $data['net_revenue'],
                'cogs_amount'             => $data['cogs_amount'],
                'gross_profit'            => $data['gross_profit'],
                'cash_revenue'            => $data['cash_revenue'],
                'bank_transfer_revenue'   => $data['bank_transfer_revenue'],
                'card_revenue'            => $data['card_revenue'],
                'ewallet_revenue'         => $data['ewallet_revenue'],
                'mixed_revenue'           => $data['mixed_revenue'],
                'average_order_value'     => $data['average_order_value'],
                'first_order_at'          => $data['first_order_at'],
                'last_order_at'           => $data['last_order_at'],
                'calculated_at'           => now(),
                'source'                  => 'system',
                'meta'                    => [
                    'peak_hour'    => $data['peak_hour'],
                    'top_products' => array_slice($data['top_products'], 0, 5),
                ],
            ]
        );
    }

    private function calculateRevenue(int $restaurantId, string $date): array
    {
        $start = Carbon::parse($date)->startOfDay();
        $end   = Carbon::parse($date)->endOfDay();

        $orders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->get(['id', 'total_amount', 'discount_amount', 'service_charge', 'tax_amount', 'completed_at']);

        $allOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $orderIds      = $orders->pluck('id');
        $grossRevenue  = $orders->sum('total_amount');
        $discountTotal = $orders->sum('discount_amount');
        $serviceCharge = $orders->sum('service_charge');
        $taxTotal      = $orders->sum('tax_amount');
        $netRevenue    = $grossRevenue - $discountTotal;
        $completedCount = $orders->count();
        $cancelledCount = (int) ($allOrders->get('cancelled', 0));
        $orderCount     = $allOrders->sum();

        $payments = Payment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->whereIn('order_id', $orderIds->all())
            ->get(['payment_method', 'amount']);

        return [
            'gross_revenue'           => (float) $grossRevenue,
            'discount_total'          => (float) $discountTotal,
            'service_charge'          => (float) $serviceCharge,
            'tax_total'               => (float) $taxTotal,
            'net_revenue'             => (float) $netRevenue,
            'order_count'             => (int) $orderCount,
            'completed_count'         => $completedCount,
            'cancelled_count'         => $cancelledCount,
            'average_order_value'     => $completedCount > 0 ? round($netRevenue / $completedCount, 2) : 0.0,
            'cash_revenue'            => (float) $payments->where('payment_method', 'cash')->sum('amount'),
            'bank_transfer_revenue'   => (float) $payments->where('payment_method', 'bank_transfer')->sum('amount'),
            'card_revenue'            => (float) $payments->where('payment_method', 'card')->sum('amount'),
            'ewallet_revenue'         => (float) $payments->where('payment_method', 'ewallet')->sum('amount'),
            'mixed_revenue'           => (float) $payments->where('payment_method', 'mixed')->sum('amount'),
            'first_order_at'          => $orders->min('completed_at'),
            'last_order_at'           => $orders->max('completed_at'),
        ];
    }

    private function getTopProducts(int $restaurantId, string $date): array
    {
        $start = Carbon::parse($date)->startOfDay();
        $end   = Carbon::parse($date)->endOfDay();

        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.completed_at', [$start, $end])
            ->select(
                'products.name',
                DB::raw('SUM(order_items.quantity) as qty'),
                DB::raw('SUM(order_items.line_total) as revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name'    => $r->name,
                'qty'     => (int) $r->qty,
                'revenue' => (float) $r->revenue,
            ])
            ->all();
    }

    private function getShiftSummary(int $restaurantId, string $date): array
    {
        return ShiftClosing::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('closing_date', $date)
            ->with('shift')
            ->get()
            ->map(fn (ShiftClosing $c) => [
                'shift_name'      => $c->shift?->name ?? '—',
                'status'          => $c->status,
                'expected_cash'   => (float) $c->expected_cash,
                'actual_cash'     => (float) $c->actual_cash,
                'cash_difference' => (float) $c->cash_difference,
            ])
            ->all();
    }

    private function getComparison(int $restaurantId, string $date): array
    {
        $yesterday = Carbon::parse($date)->subDay()->toDateString();

        $prev = RestaurantRevenueSummary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('scope_key', 'restaurant')
            ->where('summary_type', 'daily')
            ->whereDate('summary_date', $yesterday)
            ->first();

        $prevNet    = $prev ? (float) $prev->net_revenue : 0.0;
        $prevOrders = $prev ? (int) $prev->completed_order_count : 0;

        return [
            'yesterday_date'       => Carbon::parse($yesterday)->format('d/m/Y'),
            'yesterday_net_revenue'=> $prevNet,
            'yesterday_orders'     => $prevOrders,
            'revenue_delta'        => 0.0,   // được tính ở bước sau khi có net_revenue
            'revenue_delta_pct'    => 0.0,
            'order_delta'          => 0,
        ];
    }

    public function fillComparison(array $data): array
    {
        $prevNet    = $data['comparison']['yesterday_net_revenue'];
        $prevOrders = $data['comparison']['yesterday_orders'];
        $netRevenue = $data['net_revenue'];
        $completed  = $data['completed_count'];

        $revDelta    = $netRevenue - $prevNet;
        $revDeltaPct = $prevNet > 0 ? round(($revDelta / $prevNet) * 100, 1) : 0.0;
        $orderDelta  = $completed - $prevOrders;

        $data['comparison']['revenue_delta']     = $revDelta;
        $data['comparison']['revenue_delta_pct'] = $revDeltaPct;
        $data['comparison']['order_delta']        = $orderDelta;

        return $data;
    }

    private function calculateCogs(int $restaurantId, string $date): float
    {
        $start = Carbon::parse($date)->startOfDay();
        $end   = Carbon::parse($date)->endOfDay();

        $result = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('product_recipes', 'product_recipes.product_id', '=', 'order_items.product_id')
            ->join('ingredients', 'ingredients.id', '=', 'product_recipes.ingredient_id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.completed_at', [$start, $end])
            ->whereNull('ingredients.deleted_at')
            ->selectRaw('
                SUM(
                    order_items.quantity
                    * product_recipes.quantity
                    * (1 + product_recipes.waste_rate / 100)
                    * ingredients.average_cost
                ) as total_cogs
            ')
            ->value('total_cogs');

        return (float) ($result ?? 0.0);
    }

    private function getPeakHour(int $restaurantId, string $date): ?array
    {
        $start = Carbon::parse($date)->startOfDay();
        $end   = Carbon::parse($date)->endOfDay();

        $row = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->select(
                DB::raw('HOUR(completed_at) as hour'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as order_count')
            )
            ->groupBy(DB::raw('HOUR(completed_at)'))
            ->orderByDesc('revenue')
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'hour'        => (int) $row->hour,
            'label'       => sprintf('%02d:00 - %02d:59', $row->hour, $row->hour),
            'revenue'     => (float) $row->revenue,
            'order_count' => (int) $row->order_count,
        ];
    }
}
