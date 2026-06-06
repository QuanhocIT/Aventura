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

        // Backward compatibility: ensure all existing tables have a qr_token
        RestaurantTable::where('restaurant_id', $restaurantId)
            ->whereNull('qr_token')
            ->get()
            ->each(function (RestaurantTable $t) {
                $t->update(['qr_token' => Str::random(32)]);
            });

        $tables = \Illuminate\Support\Facades\Cache::remember("restaurant_{$restaurantId}_tables", 3600, function () use ($restaurantId) {
            return RestaurantTable::where('restaurant_id', $restaurantId)
                ->with('area')
                ->whereNull('deleted_at')
                ->orderBy('area_id')
                ->orderBy('name')
                ->get()
                ->map(fn ($t) => [
                    'id'       => $t->id,
                    'name'     => $t->name,
                    'capacity' => $t->capacity,
                    'status'   => $t->status,
                    'area'     => $t->area ? ['id' => $t->area->id, 'name' => $t->area->name] : null,
                    'qr_code'  => $t->qr_code,
                    'qr_token' => $t->qr_token,
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
            'status'   => ['sometimes', 'in:available,occupied,reserved,inactive'],
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
