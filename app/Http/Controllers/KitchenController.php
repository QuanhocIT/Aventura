<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);

        $restaurant = $user->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'kitchen_display')) {
            return Inertia::render('FeatureGate', [
                'feature'       => 'kitchen_display',
                'feature_label' => 'Màn hình Bếp (Kitchen Display)',
                'plan_name'     => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $user->restaurant_id;

        // 1. Nhận đơn (Pending/Preparing items in active orders)
        // Grouped by table in frontend, so we just return the flat list ordered by time
        $pendingItems = OrderItem::where('restaurant_id', $restaurantId)
            ->whereNull('prepared_at')
            ->where('status', '!=', 'cancelled')
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', ['completed', 'cancelled']);
            })
            ->with(['order.table', 'order.creator', 'product'])
            ->oldest('created_at')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name ?? 'Món ăn',
                'quantity' => (float) $item->quantity,
                'notes' => $item->notes,
                'sent_to_kitchen_at' => $item->sent_to_kitchen_at ? $item->sent_to_kitchen_at->format('H:i') : $item->created_at->format('H:i'),
                'sent_to_kitchen_at_raw' => ($item->sent_to_kitchen_at ?? $item->created_at)->toIso8601String(),
                'creator_name' => $item->order->creator?->name ?? 'Hệ thống',
                'table_name' => $item->order->table?->name ?? 'Mang về',
                'table_id' => $item->order->table_id,
                // SLA riêng theo món: thời gian chuẩn bị chuẩn (phút), mặc định 10'
                'prep_minutes' => max(1, (int) ($item->product?->preparation_time_minutes ?? 10)),
            ]);

        // 2. Đơn đã làm xong (Completed but not served yet)
        $completedItems = OrderItem::where('restaurant_id', $restaurantId)
            ->whereNotNull('prepared_at')
            ->whereNull('served_at')
            ->where('status', '!=', 'cancelled')
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', ['completed', 'cancelled']);
            })
            ->with(['order.table', 'product', 'preparedBy'])
            ->latest('prepared_at')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name ?? 'Món ăn',
                'quantity' => (float) $item->quantity,
                'notes' => $item->notes,
                'prepared_at' => $item->prepared_at->format('H:i'),
                'prepared_by_name' => $item->preparedBy?->name ?? 'Bếp',
                'table_name' => $item->order->table?->name ?? 'Mang về',
            ]);

        $products = \App\Models\Product::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->with('category')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'category_name' => $p->category?->name ?? 'Món khác',
                'paused_until' => $p->paused_until ? $p->paused_until->toIso8601String() : null,
                'out_of_stock_until' => $p->out_of_stock_until ? $p->out_of_stock_until->toIso8601String() : null,
                'is_paused' => (bool) ($p->paused_until && $p->paused_until->isFuture()),
                'is_out_of_stock' => (bool) ($p->out_of_stock_until && $p->out_of_stock_until->isFuture()),
            ])->all();

        return Inertia::render('kitchen/Dashboard', [
            'pendingItems' => $pendingItems,
            'completedItems' => $completedItems,
            'products' => $products,
            'kitchenStats' => $this->buildKitchenStats($restaurantId),
        ]);
    }

    /**
     * Thống kê tốc độ bếp hôm nay: số món đã ra, thời gian chế biến trung bình,
     * món chậm nhất. Tính bằng PHP để tương thích mọi DB (test chạy SQLite).
     */
    private function buildKitchenStats(int $restaurantId): array
    {
        $preparedToday = OrderItem::where('restaurant_id', $restaurantId)
            ->whereNotNull('prepared_at')
            ->whereDate('prepared_at', now()->toDateString())
            ->get(['id', 'created_at', 'sent_to_kitchen_at', 'prepared_at']);

        $prepTimes = $preparedToday->map(function (OrderItem $item) {
            $start = $item->sent_to_kitchen_at ?? $item->created_at;

            return max(0, $start->diffInMinutes($item->prepared_at));
        });

        return [
            'done_today' => $preparedToday->count(),
            'avg_prep_minutes' => $prepTimes->isNotEmpty() ? round($prepTimes->avg(), 1) : null,
            'slowest_prep_minutes' => $prepTimes->isNotEmpty() ? (int) $prepTimes->max() : null,
        ];
    }

    public function prepare(Request $request, OrderItem $item): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($item->restaurant_id !== $user->restaurant_id, 403);

        if ($item->prepared_at !== null || $item->status === 'preparing' || $item->status === 'served') {
            return back()->with('success', 'Món ăn đã được chế biến trước đó.');
        }

        $item->update([
            'prepared_at' => now(),
            'prepared_by' => $user->id,
            'status' => 'preparing', // transition status
        ]);

        event(new \App\Events\Kitchen\KitchenUpdated($user->restaurant_id));

        return back()->with('success', 'Đã hoàn thành chuẩn bị món!');
    }

    public function prepareBulk(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);

        $validated = $request->validate([
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['integer'],
        ]);

        $updatedCount = OrderItem::whereIn('id', $validated['item_ids'])
            ->where('restaurant_id', $user->restaurant_id)
            ->whereNull('prepared_at')
            ->update([
                'prepared_at' => now(),
                'prepared_by' => $user->id,
                'status' => 'preparing',
            ]);

        if ($updatedCount > 0) {
            event(new \App\Events\Kitchen\KitchenUpdated($user->restaurant_id));
        }

        return back()->with('success', 'Đã hoàn thành chuẩn bị các món!');
    }

    public function serve(Request $request, OrderItem $item): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($item->restaurant_id !== $user->restaurant_id, 403);

        if ($item->served_at !== null || $item->status === 'served') {
            return back()->with('success', 'Món ăn đã được phục vụ trước đó.');
        }

        $item->update([
            'served_at' => now(),
            'served_by' => $user->id,
            'status' => 'served', // final status
        ]);

        event(new \App\Events\Kitchen\KitchenUpdated($user->restaurant_id));

        return back()->with('success', 'Món ăn đã được phục vụ lấy đi!');
    }

    public function pause(Request $request, \App\Models\Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);

        $validated = $request->validate([
            'minutes' => ['required', 'integer', 'min:1'],
        ]);

        $product->update([
            'paused_until' => now()->addMinutes($validated['minutes']),
            'out_of_stock_until' => null,
        ]);

        event(new \App\Events\Customer\ProductStockUpdated($user->restaurant_id));

        return back()->with('success', "Đã tạm dừng phục vụ món {$product->name} trong {$validated['minutes']} phút!");
    }

    public function markOutOfStock(Request $request, \App\Models\Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);

        $validated = $request->validate([
            'minutes' => ['required', 'integer', 'min:1'],
        ]);

        $product->update([
            'out_of_stock_until' => now()->addMinutes($validated['minutes']),
            'paused_until' => null,
        ]);

        event(new \App\Events\Customer\ProductStockUpdated($user->restaurant_id));

        return back()->with('success', "Đã báo hết món {$product->name} trong {$validated['minutes']} phút!");
    }

    public function resume(Request $request, \App\Models\Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);

        $product->update([
            'paused_until' => null,
            'out_of_stock_until' => null,
        ]);

        event(new \App\Events\Customer\ProductStockUpdated($user->restaurant_id));

        return back()->with('success', "Đã mở lại bán món {$product->name}!");
    }
}
