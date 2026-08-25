<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\TableReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API công khai v1 (chỉ đọc) — xác thực bằng X-Api-Key.
 * Dùng cho developer tích hợp ERP/BI bên ngoài với dữ liệu nhà hàng.
 */
class PublicApiController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $restaurantId = (int) $request->attributes->get('api_restaurant_id');

        $products = Product::withoutGlobalScopes()
            ->with('category:id,name')
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->sellableMenu()
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 50), 100));

        return response()->json([
            'data' => collect($products->items())->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'category' => $p->category?->name,
                'is_available' => (bool) $p->is_available,
            ]),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $restaurantId = (int) $request->attributes->get('api_restaurant_id');

        $query = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $orders = $query->paginate(min((int) $request->query('per_page', 50), 100));

        return response()->json([
            'data' => collect($orders->items())->map(fn (Order $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'channel' => $o->channel,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'subtotal' => (float) $o->subtotal,
                'discount_amount' => (float) $o->discount_amount,
                'total_amount' => (float) $o->total_amount,
                'created_at' => $o->created_at?->toIso8601String(),
                'completed_at' => $o->completed_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function reservations(Request $request): JsonResponse
    {
        $restaurantId = (int) $request->attributes->get('api_restaurant_id');

        $query = TableReservation::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->orderByDesc('reservation_date');

        if ($request->filled('date')) {
            $query->whereDate('reservation_date', $request->query('date'));
        }

        $reservations = $query->paginate(min((int) $request->query('per_page', 50), 100));

        return response()->json([
            'data' => collect($reservations->items())->map(fn (TableReservation $r) => [
                'id' => $r->id,
                'guest_name' => $r->guest_name,
                'guest_phone' => $r->guest_phone,
                'reservation_date' => $r->reservation_date->toDateString(),
                'reservation_time' => $r->reservation_time,
                'party_size' => (int) $r->party_size,
                'status' => $r->status,
                'deposit_status' => $r->deposit_status,
            ]),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'total' => $reservations->total(),
            ],
        ]);
    }
}
