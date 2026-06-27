<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GlobalRevenueController extends Controller
{
    public function index(Request $request)
    {
        $range = $request->input('range', '30');
        $startDate = now()->subDays((int) $range)->startOfDay();

        $revenueByRestaurant = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->select('restaurant_id', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as orders_count'))
            ->groupBy('restaurant_id')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $restaurant = Restaurant::find($row->restaurant_id);
                return [
                    'id' => $row->restaurant_id,
                    'name' => $restaurant?->name ?? '—',
                    'code' => $restaurant?->code ?? '',
                    'revenue' => (float) $row->revenue,
                    'orders_count' => $row->orders_count,
                ];
            });

        $dailyRevenue = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as orders_count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'revenue' => (float) $row->revenue,
                'orders_count' => $row->orders_count,
            ]);

        $totalRevenue = Order::withoutGlobalScopes()->where('status', 'completed')->where('created_at', '>=', $startDate)->sum('total_amount');
        $totalOrders = Order::withoutGlobalScopes()->where('status', 'completed')->where('created_at', '>=', $startDate)->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        $activeRestaurants = Order::withoutGlobalScopes()->where('created_at', '>=', $startDate)->distinct('restaurant_id')->count('restaurant_id');

        return Inertia::render('super-admin/revenue/Index', [
            'revenueByRestaurant' => $revenueByRestaurant,
            'dailyRevenue' => $dailyRevenue,
            'stats' => [
                'total_revenue' => (float) $totalRevenue,
                'total_orders' => $totalOrders,
                'avg_order_value' => round($avgOrderValue),
                'active_restaurants' => $activeRestaurants,
            ],
            'filters' => ['range' => $range],
        ]);
    }
}
