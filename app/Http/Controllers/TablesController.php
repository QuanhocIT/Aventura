<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TablesController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $areas = \Illuminate\Support\Facades\Cache::remember("restaurant_{$restaurantId}_areas", 3600, function () use ($restaurantId) {
            return Area::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->orderBy('display_order')
                ->withCount('tables')
                ->get()
                ->map(fn ($a) => [
                    'id'     => $a->id,
                    'name'   => $a->name,
                    'code'   => $a->code,
                    'tables_count' => $a->tables_count,
                ])->toArray();
        });

        $tables = \Illuminate\Support\Facades\Cache::remember("restaurant_{$restaurantId}_tables", 3600, function () use ($restaurantId) {
            return RestaurantTable::where('restaurant_id', $restaurantId)
                ->with(['area', 'activeOrder.creator', 'activeOrder.items.product'])
                ->whereNull('deleted_at')
                ->orderBy('area_id')
                ->orderBy('name')
                ->get()
                ->map(fn ($t) => [
                    'id'            => $t->id,
                    'restaurant_id' => $t->restaurant_id,
                    'name'          => $t->name,
                    'capacity'      => $t->capacity,
                    'status'        => $t->status,
                    'x_pos'         => (int) $t->x_pos,
                    'y_pos'         => (int) $t->y_pos,
                    'area'          => $t->area ? ['id' => $t->area->id, 'name' => $t->area->name] : null,
                    'qr_code'       => $t->qr_code,
                    'qr_token'      => $t->qr_token,
                    'active_order'  => $t->activeOrder ? [
                        'id' => $t->activeOrder->id,
                        'order_number' => $t->activeOrder->order_number,
                        'waiter_name' => $t->activeOrder->creator?->name ?? 'Khách đặt (QR)',
                        'total_amount' => (float) $t->activeOrder->total_amount,
                        'elapsed_minutes' => $t->activeOrder->created_at ? now()->diffInMinutes($t->activeOrder->created_at) : 0,
                        'pending_items' => $t->activeOrder->items->filter(fn($item) => is_null($item->served_at) && $item->status !== 'cancelled')->map(fn($item) => [
                            'name' => $item->product?->name ?? 'Món ăn',
                            'quantity' => (float) $item->quantity,
                            'sent_at' => $item->sent_to_kitchen_at ? $item->sent_to_kitchen_at->diffForHumans() : null,
                            'is_late' => $item->sent_to_kitchen_at ? now()->diffInMinutes($item->sent_to_kitchen_at) >= 15 : false,
                        ])->values()->toArray()
                    ] : null
                ])->toArray();
        });

        return Inertia::render('tables/Index', [
            'areas'  => $areas,
            'tables' => $tables,
        ]);
    }

    public function storeArea(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        Area::create([
            'restaurant_id' => $user->restaurant_id,
            'name'          => $data['name'],
            'code'          => Str::slug($data['name']) . '-' . Str::lower(Str::random(4)),
            'display_order' => Area::where('restaurant_id', $user->restaurant_id)->count() + 1,
            'status'        => 'active',
        ]);

        return back()->with('success', 'Đã thêm khu vực mới.');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:50'],
            'area_id'  => ['required', 'exists:areas,id'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        RestaurantTable::create([
            'restaurant_id' => $user->restaurant_id,
            'area_id'       => $data['area_id'],
            'name'          => $data['name'],
            'capacity'      => $data['capacity'],
            'status'        => 'available',
            'qr_token'      => Str::random(32),
        ]);

        return back()->with('success', 'Đã thêm bàn mới.');
    }

    public function update(Request $request, RestaurantTable $table): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        abort_if($table->restaurant_id !== $request->user()->restaurant_id, 403);

        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:50'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'status'   => ['sometimes', 'in:available,occupied,reserved,inactive,cleaning'],
            'x_pos'    => ['sometimes', 'integer', 'min:0', 'max:100'],
            'y_pos'    => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        $table->update($data);

        return back()->with('success', 'Đã cập nhật bàn.');
    }

    public function destroy(Request $request, RestaurantTable $table): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        abort_if($table->restaurant_id !== $request->user()->restaurant_id, 403);

        $table->delete();

        return back()->with('success', 'Đã xóa bàn.');
    }

    public function regenerateQr(Request $request, RestaurantTable $table): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($table->restaurant_id !== $request->user()->restaurant_id, 403);

        $table->update([
            'qr_token' => Str::random(32),
        ]);

        return back()->with('success', 'Đã tạo lại mã QR mới cho bàn.');
    }
}
