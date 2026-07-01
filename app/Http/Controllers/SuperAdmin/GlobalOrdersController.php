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

        $query = Order::with(['items.product', 'customer', 'table'])->withoutGlobalScopes();

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
            'total_raw' => (float)($o->total_amount ?? 0),
            'channel' => $o->channel ?? 'pos',
            'note' => $o->note,
            'created_at' => $o->created_at?->format('d/m/Y H:i'),
            'customer_name' => $o->customer?->name ?? 'Khách vãng lai',
            'customer_phone' => $o->customer?->phone ?? '—',
            'table_name' => $o->table?->name ?? '—',
            'items' => $o->items->map(fn($item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name ?? 'Sản phẩm đã xóa',
                'quantity' => $item->quantity,
                'price' => number_format($item->price ?? 0, 0, ',', '.'),
                'total' => number_format(($item->price * $item->quantity) ?? 0, 0, ',', '.'),
            ])->toArray(),
        ]);

        $today = now()->toDateString();
        
        $posToday = Order::withoutGlobalScopes()->whereDate('created_at', $today)->where('channel', 'pos')->count();
        $qrToday = Order::withoutGlobalScopes()->whereDate('created_at', $today)->where('channel', 'qr')->count();
        $onlineToday = Order::withoutGlobalScopes()->whereDate('created_at', $today)->where('channel', 'online')->count();
        $deliveryToday = Order::withoutGlobalScopes()->whereDate('created_at', $today)->where('channel', 'delivery')->count();

        $revenueTrend = collect(range(6, 0))->map(function($daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();
            $label = now()->subDays($daysAgo)->format('d/m');
            $revenue = Order::withoutGlobalScopes()
                ->whereDate('created_at', $date)
                ->where('status', 'completed')
                ->sum('total_amount') ?? 0;
            return [
                'date' => $label,
                'revenue' => (float)$revenue,
            ];
        })->toArray();

        $stats = [
            'total_today' => Order::withoutGlobalScopes()->whereDate('created_at', $today)->count(),
            'revenue_today' => (float)Order::withoutGlobalScopes()->whereDate('created_at', $today)->where('status', 'completed')->sum('total_amount'),
            'completed_today' => Order::withoutGlobalScopes()->whereDate('created_at', $today)->where('status', 'completed')->count(),
            'cancelled_today' => Order::withoutGlobalScopes()->whereDate('created_at', $today)->where('status', 'cancelled')->count(),
            
            'pos_today' => $posToday,
            'qr_today' => $qrToday,
            'online_today' => $onlineToday,
            'delivery_today' => $deliveryToday,
            'revenue_trend' => $revenueTrend,
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
