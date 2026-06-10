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
            ]);

        // 2. Đơn đã làm xong (Completed but not served yet)
        $completedItems = OrderItem::where('restaurant_id', $restaurantId)
            ->whereNotNull('prepared_at')
            ->whereNull('served_at')
            ->where('status', '!=', 'cancelled')
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', ['completed', 'cancelled']);
            })
            ->with(['order.table', 'product'])
            ->latest('prepared_at')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name ?? 'Món ăn',
                'quantity' => (float) $item->quantity,
                'notes' => $item->notes,
                'prepared_at' => $item->prepared_at->format('H:i'),
                'table_name' => $item->order->table?->name ?? 'Mang về',
            ]);

        return Inertia::render('kitchen/Dashboard', [
            'pendingItems' => $pendingItems,
            'completedItems' => $completedItems,
        ]);
    }

    public function prepare(Request $request, OrderItem $item): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($item->restaurant_id !== $user->restaurant_id, 403);

        $item->update([
            'prepared_at' => now(),
            'status' => 'preparing', // transition status
        ]);

        event(new \App\Events\Kitchen\KitchenUpdated($user->restaurant_id));

        return back()->with('success', 'Đã hoàn thành chuẩn bị món!');
    }

    public function serve(Request $request, OrderItem $item): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($item->restaurant_id !== $user->restaurant_id, 403);

        $item->update([
            'served_at' => now(),
            'status' => 'served', // final status
        ]);

        event(new \App\Events\Kitchen\KitchenUpdated($user->restaurant_id));

        return back()->with('success', 'Món ăn đã được phục vụ lấy đi!');
    }
}
