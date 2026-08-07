<?php

namespace App\Http\Controllers;

use App\Events\Customer\ProductStockUpdated;
use App\Events\Kitchen\KitchenItemCancelled;
use App\Events\Kitchen\KitchenUpdated;
use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Services\InventoryAvailabilityService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'kitchen_display')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'kitchen_display',
                'feature_label' => 'Màn hình Bếp (Kitchen Display)',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $user->restaurant_id;
        $branchId = app(TenantContext::class)->activeBranchId();
        $todayStart = today()->startOfDay();
        $todayEnd = today()->endOfDay();

        // 1. Nhận đơn (Pending/Preparing items in active orders for today)
        $pendingItems = OrderItem::where('restaurant_id', $restaurantId)
            ->whereNull('prepared_at')
            ->where('status', '!=', 'cancelled')
            ->whereHas('order', function ($q) use ($branchId, $todayStart, $todayEnd) {
                // Payment completion and kitchen/service completion are
                // separate. Keep every unserved item in the queue.
                $q->where('status', '!=', 'cancelled')
                    ->currentTableOrder()
                    ->where(function ($dateQuery) use ($todayStart, $todayEnd) {
                        $dateQuery->whereBetween('created_at', [$todayStart, $todayEnd])
                            ->orWhereBetween('completed_at', [$todayStart, $todayEnd])
                            ->orWhereBetween('updated_at', [$todayStart, $todayEnd]);
                    });
                if ($branchId) {
                    $q->where(function ($bq) use ($branchId) {
                        $bq->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    });
                }
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
                'status' => $item->status,
                'creator_name' => $item->order->creator?->name ?? 'Hệ thống',
                'table_name' => $item->order->table?->name ?? 'Mang về',
                'table_id' => $item->order->table_id,
                // SLA riêng theo món: thời gian chuẩn bị chuẩn (phút), mặc định 10'
                'prep_minutes' => max(1, (int) ($item->product?->preparation_time_minutes ?? 10)),
            ]);

        // 2. Đơn đã làm xong (Completed but not served yet for today)
        // Payment status and kitchen status are independent. A paid
        // order must remain visible in kitchen until every item is served.
        $completedItems = OrderItem::where('restaurant_id', $restaurantId)
            ->whereNotNull('prepared_at')
            ->whereNull('served_at')
            ->where('status', '!=', 'cancelled')
            ->whereHas('order', function ($q) use ($branchId, $todayStart, $todayEnd) {
                $q->where('status', '!=', 'cancelled')
                    ->currentTableOrder()
                    ->where(function ($dateQuery) use ($todayStart, $todayEnd) {
                        $dateQuery->whereBetween('created_at', [$todayStart, $todayEnd])
                            ->orWhereBetween('completed_at', [$todayStart, $todayEnd])
                            ->orWhereBetween('updated_at', [$todayStart, $todayEnd]);
                    });
                if ($branchId) {
                    $q->where(function ($bq) use ($branchId) {
                        $bq->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    });
                }
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

        $products = Product::where('restaurant_id', $restaurantId)
            ->where(function ($query) use ($branchId): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->where('is_active', true)
            ->sellableMenu()
            ->with(['category', 'recipes.ingredient.unit'])
            ->get();

        $availabilityService = app(InventoryAvailabilityService::class);
        $availabilityService->refreshBranch($restaurantId, (int) $branchId, false);
        $availability = $availabilityService->forProducts($products, $restaurantId, (int) $branchId);

        $products = $products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => (float) $p->price,
            'category_name' => $p->category?->name ?? 'Món khác',
            'available_portions' => $availability->get($p->id)['available_portions'] ?? null,
            'paused_until' => $p->paused_until ? $p->paused_until->toIso8601String() : null,
            'out_of_stock_until' => $p->out_of_stock_until ? $p->out_of_stock_until->toIso8601String() : null,
            'is_paused' => (bool) ($p->paused_until && $p->paused_until->isFuture()),
            'is_out_of_stock' => (bool) (($availability->get($p->id)['is_sold_out'] ?? false)
                || ($p->out_of_stock_until && $p->out_of_stock_until->isFuture())),
        ])->all();

        $ingredients = Ingredient::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with('unit')
            ->orderBy('name')
            ->get()
            ->map(fn ($ing) => [
                'id' => $ing->id,
                'name' => $ing->name,
                'unit_symbol' => $ing->unit?->symbol ?? '',
            ])->all();

        return Inertia::render('kitchen/Dashboard', [
            'pendingItems' => $pendingItems,
            'completedItems' => $completedItems,
            'products' => $products,
            'ingredients' => $ingredients,
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

        if ($item->prepared_at !== null || $item->status === 'served') {
            return back()->with('success', 'Món ăn đã được chế biến trước đó.');
        }

        $item->update([
            'sent_to_kitchen_at' => $item->sent_to_kitchen_at ?? now(),
            'prepared_at' => now(),
            'prepared_by' => $user->id,
            // Keep `preparing` as the kitchen workflow state. prepared_at
            // distinguishes “đang làm” from “bếp đã làm xong”.
            'status' => 'preparing',
        ]);

        if ($item->order_id) {
            \App\Models\Order::where('id', $item->order_id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->update(['status' => 'preparing']);
        }

        $this->broadcastSafely(
            new KitchenUpdated($user->restaurant_id),
            'kitchen.updated',
        );

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

        $itemsToUpdate = OrderItem::whereIn('id', $validated['item_ids'])
            ->where('restaurant_id', $user->restaurant_id)
            ->whereNull('prepared_at')
            ->get();

        $orderIds = $itemsToUpdate->pluck('order_id')->unique()->filter();

        OrderItem::whereIn('id', $itemsToUpdate->pluck('id'))
            ->whereNull('sent_to_kitchen_at')
            ->update(['sent_to_kitchen_at' => now()]);

        $updatedCount = OrderItem::whereIn('id', $itemsToUpdate->pluck('id'))
            ->update([
                'prepared_at' => now(),
                'prepared_by' => $user->id,
                'status' => 'preparing',
            ]);

        if ($updatedCount > 0) {
            if ($orderIds->isNotEmpty()) {
                \App\Models\Order::whereIn('id', $orderIds)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->update(['status' => 'preparing']);
            }

            $this->broadcastSafely(
                new KitchenUpdated($user->restaurant_id),
                'kitchen.updated',
            );
        }

        return back()->with('success', 'Đã hoàn thành chuẩn bị các món!');
    }

    public function serve(Request $request, OrderItem $item): RedirectResponse
    {
        $user = $request->user();
        // Kitchen, cashier and waiter can confirm the hand-off. The owner can
        // also correct it from an operational screen when needed.
        abort_unless(
            $user->can('manage_kitchen')
                || $user->can('process_payments')
                || $user->can('manage_orders')
                || $user->can('create_orders'),
            403,
        );
        abort_if($item->restaurant_id !== $user->restaurant_id, 403);

        if ($item->served_at !== null || $item->status === 'served') {
            return back()->with('success', 'Món ăn đã được phục vụ trước đó.');
        }

        if ($item->prepared_at === null) {
            return back()->withErrors(['item' => 'Chỉ được xác nhận phục vụ sau khi bếp đã làm xong món.']);
        }

        $item->update([
            'served_at' => now(),
            'served_by' => $user->id,
            'status' => 'served', // final status
        ]);

        // Bàn chỉ được mở lại sau khi món cuối cùng đã phục vụ và không còn
        // đơn nào khác đang hoạt động trên cùng bàn.
        $item->loadMissing('order');
        $order = $item->order;
        if ($order?->table_id) {
            $table = RestaurantTable::where('id', $order->table_id)
                ->where('restaurant_id', $order->restaurant_id)
                ->where('branch_id', $order->branch_id)
                ->first();

            if ($table && ! $table->orders()->activeForService()->exists()) {
                $table->update(['status' => 'available']);
            }
        }

        $this->broadcastSafely(
            new KitchenUpdated($user->restaurant_id),
            'kitchen.updated',
        );

        return back()->with('success', 'Món ăn đã được phục vụ lấy đi!');
    }

    public function pause(Request $request, Product $product): RedirectResponse
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
            'out_of_stock_reason' => null,
        ]);

        $this->broadcastSafely(
            new ProductStockUpdated($user->restaurant_id),
            'product.stock_updated',
        );

        return back()->with('success', "Đã tạm dừng phục vụ món {$product->name} trong {$validated['minutes']} phút!");
    }

    public function markOutOfStock(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);

        $validated = $request->validate([
            'minutes' => ['required', 'integer', 'min:1'],
        ]);

        $product->update([
            'out_of_stock_until' => now()->addMinutes($validated['minutes']),
            'out_of_stock_reason' => null,
            'paused_until' => null,
        ]);

        $this->broadcastSafely(
            new ProductStockUpdated($user->restaurant_id),
            'product.stock_updated',
        );

        return back()->with('success', "Đã báo hết món {$product->name} trong {$validated['minutes']} phút!");
    }

    public function resume(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);

        $product->update([
            'paused_until' => null,
            'out_of_stock_until' => null,
            'out_of_stock_reason' => null,
        ]);

        $this->broadcastSafely(
            new ProductStockUpdated($user->restaurant_id),
            'product.stock_updated',
        );

        return back()->with('success', "Đã mở lại bán món {$product->name}!");
    }

    public function cancelItem(Request $request, OrderItem $item): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($item->restaurant_id !== $user->restaurant_id, 403);

        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:single,all_pending'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $scope = $validated['scope'];
        $reason = trim($validated['reason'] ?? '');
        $noteAppend = $reason !== '' ? "[Hủy bởi bếp: {$reason}]" : '[Hủy bởi bếp]';

        $productName = $item->product?->name ?? 'Món ăn';
        $tableName = $item->order?->table?->name ?? 'Mang về';
        $cancelledCount = 0;

        if ($scope === 'all_pending') {
            $pendingItemsToCancel = OrderItem::where('restaurant_id', $user->restaurant_id)
                ->where('product_id', $item->product_id)
                ->whereNull('prepared_at')
                ->where('status', '!=', 'cancelled')
                ->whereHas('order', function ($q) {
                    $q->whereNotIn('status', ['completed', 'cancelled']);
                })
                ->with('order')
                ->get();

            $cancelledCount = $pendingItemsToCancel->count();

            foreach ($pendingItemsToCancel as $pendingItem) {
                $pendingItem->update([
                    'status' => 'cancelled',
                    'notes' => $pendingItem->notes ? $pendingItem->notes.' '.$noteAppend : $noteAppend,
                ]);

                if ($pendingItem->order) {
                    $activeSubtotal = OrderItem::where('order_id', $pendingItem->order_id)
                        ->where('status', '!=', 'cancelled')
                        ->sum('line_total');

                    $pendingItem->order->update([
                        'subtotal' => $activeSubtotal,
                        'total_amount' => max(0, $activeSubtotal - $pendingItem->order->discount_amount),
                    ]);
                }
            }
        } else {
            $item->loadMissing(['order.table', 'product']);
            $item->update([
                'status' => 'cancelled',
                'notes' => $item->notes ? $item->notes.' '.$noteAppend : $noteAppend,
            ]);
            $cancelledCount = 1;

            if ($item->order) {
                $activeSubtotal = OrderItem::where('order_id', $item->order_id)
                    ->where('status', '!=', 'cancelled')
                    ->sum('line_total');

                $item->order->update([
                    'subtotal' => $activeSubtotal,
                    'total_amount' => max(0, $activeSubtotal - $item->order->discount_amount),
                ]);
            }
        }

        AuditLog::log('kitchen_item_cancelled', 'updated', $item, null, [
            'product_name' => $productName,
            'table_name' => $tableName,
            'scope' => $scope,
            'reason' => $reason,
            'cancelled_count' => $cancelledCount,
            'cancelled_by' => $user->name,
        ]);

        $this->broadcastSafely(
            new KitchenUpdated($user->restaurant_id),
            'kitchen.updated',
        );
        $this->broadcastSafely(
            new KitchenItemCancelled(
                restaurantId: $user->restaurant_id,
                productName: $productName,
                tableName: $tableName,
                quantity: (float) $item->quantity,
                scope: $scope,
                reason: $reason !== '' ? $reason : null,
                cancelledByName: $user->name,
                cancelledCount: $cancelledCount,
                orderId: $item->order_id,
            ),
            'kitchen.item_cancelled',
        );

        $msg = $scope === 'all_pending'
            ? "Đã hủy toàn bộ {$cancelledCount} phần món {$productName} trên các đơn chờ chế biến!"
            : "Đã hủy món {$productName} (Bàn {$tableName}) thành công!";

        return back()->with('success', $msg);
    }

    /**
     * Realtime tạm dừng không được làm hỏng thao tác nghiệp vụ ở màn hình bếp.
     */
    private function broadcastSafely(object $event, string $eventName): void
    {
        try {
            event($event);
        } catch (\Throwable $exception) {
            Log::warning('Realtime broadcast skipped from kitchen.', [
                'event' => $eventName,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
