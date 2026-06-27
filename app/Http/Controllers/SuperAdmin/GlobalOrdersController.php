<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GlobalOrdersController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['restaurant_id', 'status', 'payment_status', 'search', 'date_from', 'date_to']);

        $query = Order::withoutGlobalScopes();

        if (!empty($filters['restaurant_id'])) {
            $query->where('restaurant_id', $filters['restaurant_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['search'])) {
            $query->where('order_number', 'like', "%{$filters['search']}%");
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $restaurantCache = Restaurant::pluck('name', 'id')->toArray();
        $restaurantCodeCache = Restaurant::pluck('code', 'id')->toArray();

        $orders = $query->latest()->paginate(20)->through(fn (Order $o) => [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'restaurant' => $restaurantCache[$o->restaurant_id] ?? '—',
            'restaurant_code' => $restaurantCodeCache[$o->restaurant_id] ?? '',
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'total_amount' => number_format($o->total_amount ?? 0, 0, ',', '.'),
            'total_raw' => $o->total_amount ?? 0,
            'channel' => $o->channel ?? 'pos',
            'note' => $o->note,
            'created_at' => $o->created_at?->format('d/m/Y H:i'),
        ]);

        $today = now()->toDateString();
        $stats = [
            'total_today' => Order::withoutGlobalScopes()->whereDate('created_at', $today)->count(),
            'revenue_today' => Order::withoutGlobalScopes()->whereDate('created_at', $today)->where('status', 'completed')->sum('total_amount'),
            'completed_today' => Order::withoutGlobalScopes()->whereDate('created_at', $today)->where('status', 'completed')->count(),
            'cancelled_today' => Order::withoutGlobalScopes()->whereDate('created_at', $today)->where('status', 'cancelled')->count(),
        ];

        $restaurants = Restaurant::select('id', 'name', 'code')->orderBy('name')->get();

        return Inertia::render('super-admin/orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'restaurants' => $restaurants,
            'filters' => $filters,
        ]);
    }
}
