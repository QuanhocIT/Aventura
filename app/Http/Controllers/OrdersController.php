<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $statusFilter = $request->get('status', 'all');
        $dateFilter   = $request->get('date', today()->toDateString());

        $query = Order::where('restaurant_id', $restaurantId)
            ->with(['table.area', 'items'])
            ->whereDate('created_at', $dateFilter)
            ->latest();

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $orders = $query->get()->map(fn ($o) => [
            'id'             => $o->id,
            'order_number'   => $o->order_number,
            'status'         => $o->status,
            'payment_status' => $o->payment_status,
            'channel'        => $o->channel,
            'table_name'     => $o->table?->name,
            'area_name'      => $o->table?->area?->name,
            'total_amount'   => (float) $o->total_amount,
            'items_count'    => $o->items->count(),
            'created_at'     => $o->created_at->format('H:i'),
            'completed_at'   => $o->completed_at?->format('H:i'),
        ]);

        $summary = [
            'total'     => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->count(),
            'pending'   => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'pending')->count(),
            'preparing' => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'preparing')->count(),
            'completed' => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'completed')->count(),
            'cancelled' => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'cancelled')->count(),
            'revenue'   => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'completed')->sum('total_amount'),
        ];

        return Inertia::render('orders/Index', [
            'orders'        => $orders,
            'summary'       => $summary,
            'filters'       => ['status' => $statusFilter, 'date' => $dateFilter],
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,preparing,completed,cancelled'],
        ]);

        $order->update([
            'status'       => $data['status'],
            'completed_at' => $data['status'] === 'completed' ? now() : $order->completed_at,
            'cancelled_at' => $data['status'] === 'cancelled' ? now() : $order->cancelled_at,
        ]);

        return back()->with('success', 'Đã cập nhật trạng thái đơn hàng.');
    }
}
