<?php

namespace App\Http\Controllers;

use App\Events\Customer\ProductStockUpdated;
use App\Events\Kitchen\KitchenItemCancelled;
use App\Events\Kitchen\KitchenUpdated;
use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductBranchPause;
use App\Models\RestaurantTable;
use App\Services\InventoryAvailabilityService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                    ->activeForService()
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
                'started_preparing_at' => $item->started_preparing_at?->toIso8601String(),
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
                    ->activeForService()
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

        // Tính tồn/khả dụng theo chi nhánh — CHỈ khi đang xem một chi nhánh cụ thể.
        // Chủ ở phạm vi "tất cả chi nhánh" có $branchId = null; TRƯỚC ĐÂY bị ép
        // (int) null = 0 khiến refreshBranch chèn product_branch_stock_statuses với
        // branch_id = 0 → vi phạm khóa ngoại (500). Không có chi nhánh thì bỏ qua
        // tính khả dụng (món vẫn hiển thị, chỉ không có số suất theo chi nhánh).
        $availabilityService = app(InventoryAvailabilityService::class);
        $availability = collect();
        // Tạm ngưng món theo RIÊNG chi nhánh hiện tại (cô lập, không dùng cờ chung).
        $branchPauses = collect();
        if ($branchId !== null && (int) $branchId > 0) {
            $availabilityService->refreshBranch($restaurantId, (int) $branchId, false);
            $availability = $availabilityService->forProducts($products, $restaurantId, (int) $branchId);
            $branchPauses = ProductBranchPause::where('restaurant_id', $restaurantId)
                ->where('branch_id', (int) $branchId)
                ->activePause()
                ->get()
                ->keyBy('product_id');
        }

        $products = $products->map(function ($p) use ($availability, $branchPauses) {
            $bp = $branchPauses->get($p->id);
            // Món coi là tạm ngưng nếu có cờ chung (cũ) HOẶC có tạm ngưng riêng chi nhánh.
            $globalPaused = (bool) ($p->paused_until && $p->paused_until->isFuture());

            return [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'category_name' => $p->category?->name ?? 'Món khác',
                'available_portions' => $availability->get($p->id)['available_portions'] ?? null,
                'paused_until' => $p->paused_until ? $p->paused_until->toIso8601String() : null,
                'pause_reason' => $bp?->reason ?? $p->pause_reason,
                'out_of_stock_until' => $p->out_of_stock_until ? $p->out_of_stock_until->toIso8601String() : null,
                'is_paused' => $globalPaused || $bp !== null,
                // Tạm ngưng riêng theo chi nhánh + trạng thái duyệt mở lại.
                'branch_paused' => $bp !== null,
                'reopen_requested' => $bp?->status === 'reopen_requested',
                'is_out_of_stock' => (bool) (($availability->get($p->id)['is_sold_out'] ?? false)
                    || ($p->out_of_stock_until && $p->out_of_stock_until->isFuture())),
            ];
        })->all();

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

    public function startPreparing(Request $request, OrderItem $item): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($item->restaurant_id !== $user->restaurant_id, 403);

        if ($item->prepared_at !== null || $item->status === 'served') {
            return back()->with('success', 'Món ăn đã hoàn tất, không cần đánh dấu bắt đầu nữa.');
        }

        $startedAt = $item->started_preparing_at ?? now();

        $item->update([
            'sent_to_kitchen_at' => $item->sent_to_kitchen_at ?? $startedAt,
            'started_preparing_at' => $startedAt,
            'status' => in_array($item->status, ['pending', 'sent'], true)
                ? 'preparing'
                : $item->status,
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

        return back()->with('success', 'Đã đánh dấu bếp bắt đầu chế biến món!');
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
            'started_preparing_at' => $item->started_preparing_at ?? now(),
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

        OrderItem::whereIn('id', $itemsToUpdate->pluck('id'))
            ->whereNull('started_preparing_at')
            ->update(['started_preparing_at' => now()]);

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
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $product->update([
            'paused_until' => now()->addMinutes($validated['minutes']),
            'pause_reason' => $validated['reason'] ?? null,
            'out_of_stock_until' => null,
            'out_of_stock_reason' => null,
        ]);

        // Ghi nhật ký tạm ngưng món để truy vết (ai, chi nhánh nào, lý do, bao lâu).
        AuditLog::log('kitchen_product_paused', 'updated', $product, null, [
            'branch_id' => app(TenantContext::class)->activeBranchId(),
            'minutes' => $validated['minutes'],
            'reason' => $validated['reason'] ?? null,
            'by' => $user->name,
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

        $wasPaused = $product->paused_until !== null || $product->out_of_stock_until !== null;

        $product->update([
            'paused_until' => null,
            'pause_reason' => null,
            'out_of_stock_until' => null,
            'out_of_stock_reason' => null,
        ]);

        // Mở lại bán món cũng được ghi nhật ký (truy vết "duyệt mở lại": ai mở, khi nào).
        if ($wasPaused) {
            AuditLog::log('kitchen_product_resumed', 'updated', $product, null, [
                'branch_id' => app(TenantContext::class)->activeBranchId(),
                'by' => $user->name,
            ]);
        }

        $this->broadcastSafely(
            new ProductStockUpdated($user->restaurant_id),
            'product.stock_updated',
        );

        return back()->with('success', "Đã mở lại bán món {$product->name}!");
    }

    // ── Tạm ngưng món theo TỪNG CHI NHÁNH + duyệt mở lại ──────────────────────

    /** Chi nhánh đang thao tác; bắt buộc có (tạm ngưng luôn thuộc một chi nhánh). */
    private function requirePauseBranch(): int
    {
        $branchId = app(TenantContext::class)->activeBranchId();
        abort_if($branchId === null, 422, 'Hãy chọn một chi nhánh cụ thể trước khi tạm ngưng món (không tạm ngưng ở phạm vi toàn chuỗi).');

        return (int) $branchId;
    }

    /** Tạm ngưng bán món CHỈ ở chi nhánh hiện tại. */
    public function pauseBranch(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);
        $branchId = $this->requirePauseBranch();

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        $existing = ProductBranchPause::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $branchId)->where('product_id', $product->id)
            ->activePause()->first();

        $pausedUntil = ! empty($data['minutes']) ? now()->addMinutes($data['minutes']) : null;

        if ($existing) {
            $existing->update(['reason' => $data['reason'], 'paused_until' => $pausedUntil, 'paused_by' => $user->id]);
            $pause = $existing;
        } else {
            $pause = ProductBranchPause::create([
                'restaurant_id' => $user->restaurant_id,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'reason' => $data['reason'],
                'paused_until' => $pausedUntil,
                'paused_by' => $user->id,
                'status' => 'active',
            ]);
        }

        AuditLog::log('kitchen_product_branch_paused', 'updated', $product, null, [
            'branch_id' => $branchId, 'reason' => $data['reason'], 'by' => $user->name,
        ]);
        $this->broadcastSafely(new ProductStockUpdated($user->restaurant_id), 'product.stock_updated');

        return back()->with('success', "Đã tạm ngưng bán món {$product->name} tại chi nhánh này.");
    }

    /** Nhân viên bếp ĐỀ NGHỊ mở lại (không tự mở) — chờ Quản lý/Chủ duyệt. */
    public function requestReopenBranch(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);
        $branchId = $this->requirePauseBranch();

        $pause = ProductBranchPause::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $branchId)->where('product_id', $product->id)
            ->where('status', 'active')->first();
        if (! $pause) {
            return back()->with('error', 'Món này không đang bị tạm ngưng tại chi nhánh.');
        }

        $pause->update(['status' => 'reopen_requested', 'reopen_requested_by' => $user->id, 'reopen_requested_at' => now()]);
        AuditLog::log('kitchen_reopen_requested', 'updated', $product, null, ['branch_id' => $branchId, 'by' => $user->name]);

        return back()->with('success', 'Đã gửi đề nghị mở lại món tới Quản lý/Chủ duyệt.');
    }

    /** Quản lý/Chủ DUYỆT mở lại món tại chi nhánh (gỡ tạm ngưng). */
    public function approveReopenBranch(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->isOwner() || $user->isBranchManager(), 403, 'Chỉ Quản lý/Chủ được duyệt mở lại món.');
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);
        $branchId = $this->requirePauseBranch();
        abort_unless($user->canAccessBranch($branchId), 403);

        $pause = ProductBranchPause::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $branchId)->where('product_id', $product->id)
            ->whereIn('status', ['active', 'reopen_requested'])->first();
        if (! $pause) {
            return back()->with('error', 'Không có tạm ngưng nào để mở lại.');
        }

        $pause->update(['status' => 'reopened', 'reopened_by' => $user->id, 'reopened_at' => now()]);
        AuditLog::log('kitchen_reopen_approved', 'updated', $product, null, ['branch_id' => $branchId, 'by' => $user->name]);
        $this->broadcastSafely(new ProductStockUpdated($user->restaurant_id), 'product.stock_updated');

        return back()->with('success', "Đã duyệt mở lại bán món {$product->name} tại chi nhánh.");
    }

    public function cancelItem(Request $request, OrderItem $item): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);
        abort_if($item->restaurant_id !== $user->restaurant_id, 403);

        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:single,all_pending'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $scope = $validated['scope'];
        $reason = trim($validated['reason'] ?? '');
        $noteAppend = $reason !== '' ? "[Hủy bởi bếp: {$reason}]" : '[Hủy bởi bếp]';

        $result = DB::transaction(function () use ($item, $user, $scope, $reason, $noteAppend, $validated) {
            $lockedItem = OrderItem::where('id', $item->id)
                ->where('restaurant_id', $user->restaurant_id)
                ->with(['order.table', 'product.recipes.unit', 'product.recipes.ingredient.unit'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->prepared_at !== null || $lockedItem->status === 'served') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'item' => 'Món đã được chế biến hoặc phục vụ, không thể hủy thêm.',
                ]);
            }

            $productName = $lockedItem->product?->name ?? 'Món ăn';
            $tableName = $lockedItem->order?->table?->name ?? 'Mang về';
            $cancelledCount = 0;
            $cancelledQuantity = 0.0;
            $affectedOrderIds = [];
            $auditItem = $lockedItem;

            if ($scope === 'all_pending') {
                $pendingItemsToCancel = OrderItem::where('restaurant_id', $user->restaurant_id)
                    ->where('product_id', $lockedItem->product_id)
                    ->whereNull('prepared_at')
                    ->where('status', '!=', 'cancelled')
                    ->whereHas('order', function ($q) {
                        $q->whereNotIn('status', ['completed', 'cancelled']);
                    })
                    ->with('order')
                    ->lockForUpdate()
                    ->get();

                $affectedOrderIds = $pendingItemsToCancel
                    ->pluck('order_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                foreach ($pendingItemsToCancel as $pendingItem) {
                    $cancelledQuantity += (float) $pendingItem->quantity;
                    $wasStarted = $pendingItem->started_preparing_at !== null
                        || $pendingItem->prepared_at !== null
                        || $pendingItem->status === 'preparing';
                    $pendingItem->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'cancelled_by' => $user->id,
                        'cancellation_reason' => $reason,
                        'notes' => $pendingItem->notes ? $pendingItem->notes.' '.$noteAppend : $noteAppend,
                    ]);

                    app(\App\Services\InventoryService::class)->handleCancelledItem(
                        $pendingItem->fresh(['order', 'product.recipes.unit', 'product.recipes.ingredient.unit']),
                        $user,
                        $wasStarted,
                        $reason,
                    );

                    if ($pendingItem->order) {
                        $this->recalculateCancelledOrder($pendingItem->order);
                        app(\App\Services\OrderService::class)
                            ->refreshHoldingReservations($pendingItem->order->fresh());
                    }
                }

                $cancelledCount = (int) round($cancelledQuantity);
            } else {
                $availableQuantity = (int) floor((float) $lockedItem->quantity);
                $requestedQuantity = (int) ($validated['quantity'] ?? $availableQuantity);

                if ($requestedQuantity < 1 || $requestedQuantity > $availableQuantity) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'quantity' => "Số lượng hủy phải từ 1 đến {$availableQuantity} phần.",
                    ]);
                }

                $affectedOrderIds = $lockedItem->order_id ? [$lockedItem->order_id] : [];
                $cancelledQuantity = (float) $requestedQuantity;
                $cancelledCount = $requestedQuantity;

                if ($requestedQuantity < $availableQuantity) {
                    $ratio = $requestedQuantity / $availableQuantity;
                    $cancelledItem = $lockedItem->replicate([
                        'quantity',
                        'discount_amount',
                        'line_total',
                        'status',
                        'notes',
                        'sent_to_kitchen_at',
                        'prepared_at',
                        'served_at',
                        'client_item_id',
                        'cancelled_at',
                        'cancelled_by',
                        'cancellation_reason',
                    ]);
                    $cancelledItem->quantity = $requestedQuantity;
                    $cancelledItem->discount_amount = round((float) $lockedItem->discount_amount * $ratio, 2);
                    $cancelledItem->line_total = round((float) $lockedItem->line_total * $ratio, 2);
                    $cancelledItem->status = 'cancelled';
                    $cancelledItem->cancelled_at = now();
                    $cancelledItem->cancelled_by = $user->id;
                    $cancelledItem->cancellation_reason = $reason;
                    $cancelledItem->notes = $lockedItem->notes
                        ? $lockedItem->notes.' '.$noteAppend
                        : $noteAppend;
                    $cancelledItem->save();

                    $lockedItem->quantity = $availableQuantity - $requestedQuantity;
                    $lockedItem->discount_amount = round(
                        (float) $lockedItem->discount_amount - (float) $cancelledItem->discount_amount,
                        2,
                    );
                    $lockedItem->line_total = round(
                        (float) $lockedItem->line_total - (float) $cancelledItem->line_total,
                        2,
                    );
                    $lockedItem->save();
                    $auditItem = $cancelledItem;
                } else {
                    $wasStarted = $lockedItem->started_preparing_at !== null
                        || $lockedItem->prepared_at !== null
                        || $lockedItem->status === 'preparing';
                    $lockedItem->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'cancelled_by' => $user->id,
                        'cancellation_reason' => $reason,
                        'notes' => $lockedItem->notes ? $lockedItem->notes.' '.$noteAppend : $noteAppend,
                    ]);

                    app(\App\Services\InventoryService::class)->handleCancelledItem(
                        $lockedItem->fresh(['order', 'product.recipes.unit', 'product.recipes.ingredient.unit']),
                        $user,
                        $wasStarted,
                        $reason,
                    );
                }

                if ($lockedItem->order) {
                    $this->recalculateCancelledOrder($lockedItem->order);
                    app(\App\Services\OrderService::class)
                        ->refreshHoldingReservations($lockedItem->order->fresh());
                }
            }

            AuditLog::log('kitchen_item_cancelled', 'updated', $auditItem, null, [
                'product_name' => $productName,
                'table_name' => $tableName,
                'scope' => $scope,
                'reason' => $reason,
                'cancelled_count' => $cancelledCount,
                'cancelled_quantity' => $cancelledQuantity,
                'cancelled_by' => $user->name,
            ]);

            return compact(
                'productName',
                'tableName',
                'cancelledCount',
                'cancelledQuantity',
                'affectedOrderIds',
                'lockedItem',
            );
        });

        $productName = $result['productName'];
        $tableName = $result['tableName'];
        $cancelledCount = $result['cancelledCount'];
        $cancelledQuantity = $result['cancelledQuantity'];
        $affectedOrderIds = $result['affectedOrderIds'];
        $item = $result['lockedItem'];

        $this->broadcastSafely(
            new KitchenUpdated($user->restaurant_id),
            'kitchen.updated',
        );
        $this->broadcastSafely(
            new KitchenItemCancelled(
                restaurantId: $user->restaurant_id,
                productName: $productName,
                tableName: $tableName,
                quantity: $cancelledQuantity,
                scope: $scope,
                reason: $reason !== '' ? $reason : null,
                cancelledByName: $user->name,
                cancelledCount: $cancelledCount,
                orderId: $item->order_id,
                orderIds: $affectedOrderIds,
            ),
            'kitchen.item_cancelled',
        );

        $msg = $scope === 'all_pending'
            ? "Đã hủy toàn bộ {$cancelledCount} phần món {$productName} trên các đơn chờ chế biến!"
            : "Đã hủy {$cancelledCount} phần món {$productName} (Bàn {$tableName}) thành công!";

        return back()->with('success', $msg);
    }

    private function recalculateCancelledOrder(\App\Models\Order $order): void
    {
        $activeSubtotal = OrderItem::where('order_id', $order->id)
            ->where('status', '!=', 'cancelled')
            ->sum('line_total');

        $order->update([
            'subtotal' => $activeSubtotal,
            'total_amount' => max(0, $activeSubtotal - $order->discount_amount),
        ]);

        $hasActiveItems = OrderItem::where('order_id', $order->id)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if (! $hasActiveItems
            && $order->payment_status === 'unpaid'
            && ! in_array($order->status, ['completed', 'cancelled'], true)) {
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'note' => trim(($order->note ? $order->note.' ' : '').'[Hủy bởi bếp]'),
            ]);

            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)
                    ->where('restaurant_id', $order->restaurant_id)
                    ->where('branch_id', $order->branch_id)
                    ->update(['status' => 'available']);
            }
        }
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
