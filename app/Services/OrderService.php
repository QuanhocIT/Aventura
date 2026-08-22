<?php

namespace App\Services;

use App\Events\Customer\ProductStockUpdated;
use App\Events\Kitchen\KitchenUpdated;
use App\Jobs\ProcessPostPaymentActions;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Models\User;
use App\Repositories\OrderRepositoryInterface;
use App\Services\Integrations\TrackingService;
use App\Services\Integrations\WebhookDispatchService;
use App\Support\Tenant\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private InventoryService $inventoryService,
        private InventoryAvailabilityService $inventoryAvailabilityService,
    ) {}

    /**
     * Tạo đơn hàng mới từ giao diện POS.
     */
    public function createOrder(array $data, User $user, bool $mergeExistingTableOrder = true): Order
    {
        return DB::transaction(function () use ($data, $user, $mergeExistingTableOrder) {
            $restaurantId = $user->restaurant_id;
            $branchId = app(TenantContext::class)->activeBranchId();
            abort_if($branchId === null, 403, 'Đơn hàng phải được tạo trong một chi nhánh cụ thể.');
            // Fix #12: Str::ulid() thay uniqid() — đảm bảo unique dưới high-throughput
            $orderNumber = 'ORD-'.Str::ulid();

            // Pre-load các sản phẩm kèm recipes — tránh N+1 query trong loop bên dưới
            $productIds = array_column($data['items'], 'product_id');
            $products = Product::with('recipes')
                ->where('restaurant_id', $restaurantId)
                ->where(function ($query) use ($branchId) {
                    $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
                })
                ->whereIn('id', $productIds)
                ->sellableMenu()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count(array_unique($productIds))) {
                abort(422, 'Một hoặc nhiều món không thuộc thực đơn của chi nhánh hiện tại.');
            }

            $table = null;
            if (! empty($data['table_id'])) {
                $table = RestaurantTable::where('restaurant_id', $restaurantId)
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->find($data['table_id']);
                abort_unless($table, 422, 'Bàn không thuộc chi nhánh hiện tại.');
            }

            // Một bàn chỉ có một đơn đang phục vụ. Với đơn chưa thanh toán,
            // request từ POS/offline sync vẫn được gộp vào đơn hiện tại để
            // tránh tạo thêm order; với đơn đã thanh toán nhưng chưa phục vụ
            // hết thì phải chặn hoàn toàn.
            if (! empty($data['table_id']) && $mergeExistingTableOrder) {
                // Bảng đã được lock ở trên, nên cả hai thiết bị tạo đơn đồng
                // thời đều phải lần lượt kiểm tra cùng một trạng thái bàn.
                $existingActiveOrder = Order::where('restaurant_id', $restaurantId)
                    ->where('branch_id', $branchId)
                    ->where('table_id', $data['table_id'])
                    ->activeForService()
                    ->oldest('id')
                    ->lockForUpdate()
                    ->first();

                if ($existingActiveOrder) {
                    if ($existingActiveOrder->payment_status !== 'unpaid'
                        || in_array($existingActiveOrder->status, ['completed', 'cancelled'], true)) {
                        throw new \RuntimeException("Bàn {$table->name} đang có đơn {$existingActiveOrder->order_number} chưa phục vụ xong. Không thể tạo đơn mới cho bàn này.");
                    }

                    $reservationItems = collect($data['items'])
                        ->map(function (array $item) use ($existingActiveOrder): array {
                            if (! empty($item['client_item_id'])) {
                                $existingItem = OrderItem::where('order_id', $existingActiveOrder->id)
                                    ->where('client_item_id', $item['client_item_id'])
                                    ->first();
                                if ($existingItem && $existingItem->status === 'pending') {
                                    $item['quantity'] = max(0.0, (float) $item['quantity'] - (float) $existingItem->quantity);
                                }
                            }

                            return $item;
                        })
                        ->filter(fn (array $item): bool => (float) ($item['quantity'] ?? 0) > 0)
                        ->values()
                        ->all();
                    $this->inventoryAvailabilityService->assertItemsAvailable(
                        $restaurantId,
                        $branchId,
                        $reservationItems,
                        $existingActiveOrder->id,
                        false,
                        true,
                    );

                    foreach ($data['items'] as $itemData) {
                        $product = $products->get($itemData['product_id'])
                            ?? abort(422, 'Sản phẩm không tìm thấy: '.$itemData['product_id']);

                        $lineTotal = (float) $product->price * (float) $itemData['quantity'];

                        // Check if item already exists in the active order
                        $existingItem = null;
                        if (! empty($itemData['client_item_id'])) {
                            $existingItem = OrderItem::where('order_id', $existingActiveOrder->id)
                                ->where('client_item_id', $itemData['client_item_id'])
                                ->first();
                        }

                        if ($existingItem) {
                            // Idempotent check: if client_item_id is matched, this is a duplicate sync of the same item.
                            // We only update if status is pending and quantity increased.
                            if ($existingItem->status === 'pending') {
                                $diff = (float) $itemData['quantity'] - $existingItem->quantity;
                                if ($diff > 0) {
                                    $existingItem->increment('quantity', $diff);
                                    $existingItem->increment('line_total', (float) $product->price * $diff);

                                    // Reserve inventory (holding stock) for the diff
                                    if ($product->track_inventory) {
                                        foreach ($product->recipes as $recipe) {
                                            $totalUsed = $this->recipeUsageInInventoryUnit($recipe, (float) $diff);
                                            InventoryReservation::create([
                                                'restaurant_id' => $restaurantId,
                                                'branch_id' => $branchId,
                                                'order_id' => $existingActiveOrder->id,
                                                'ingredient_id' => $recipe->ingredient_id,
                                                'reserved_quantity' => $totalUsed,
                                                'status' => 'holding',
                                                'expires_at' => now()->addHours(4),
                                            ]);
                                        }
                                    }
                                }
                            }

                            continue;
                        }

                        // Fallback to old behavior for backward compatibility
                        $existingItem = OrderItem::where('order_id', $existingActiveOrder->id)
                            ->where('product_id', $product->id)
                            ->where('status', 'pending')
                            ->first();

                        if ($existingItem) {
                            $existingItem->increment('quantity', (float) $itemData['quantity']);
                            $existingItem->increment('line_total', $lineTotal);
                        } else {
                            OrderItem::create([
                                'restaurant_id' => $restaurantId,
                                'order_id' => $existingActiveOrder->id,
                                'product_id' => $product->id,
                                'quantity' => (float) $itemData['quantity'],
                                'unit_price' => (float) $product->price,
                                'discount_amount' => 0,
                                'line_total' => $lineTotal,
                                'status' => 'pending',
                                'sent_to_kitchen_at' => now(),
                                'notes' => ($itemData['notes'] ?? '').' (Offline Sync)',
                                'client_item_id' => $itemData['client_item_id'] ?? null,
                            ]);
                        }

                        // Reserve inventory (holding stock)
                        if ($product->track_inventory) {
                            foreach ($product->recipes as $recipe) {
                                $totalUsed = $this->recipeUsageInInventoryUnit($recipe, (float) $itemData['quantity']);
                                InventoryReservation::create([
                                    'restaurant_id' => $restaurantId,
                                    'branch_id' => $branchId,
                                    'order_id' => $existingActiveOrder->id,
                                    'ingredient_id' => $recipe->ingredient_id,
                                    'reserved_quantity' => $totalUsed,
                                    'status' => 'holding',
                                    'expires_at' => now()->addHours(4),
                                ]);
                            }
                        }
                    }

                    $this->syncHoldingReservations($existingActiveOrder);
                    $this->inventoryAvailabilityService->refreshBranch($restaurantId, $branchId);

                    // Recalculate totals
                    $newSubtotal = OrderItem::where('order_id', $existingActiveOrder->id)->sum('line_total');
                    $existingActiveOrder->update([
                        'subtotal' => $newSubtotal,
                        'total_amount' => max(0.00, $newSubtotal - $existingActiveOrder->discount_amount),
                        'note' => ($existingActiveOrder->note ? $existingActiveOrder->note.' ' : '').'[Gộp đơn trùng bàn do đồng bộ Offline]',
                    ]);

                    // P1: Debounce broadcast
                    $this->fireStockUpdatedDebounced($restaurantId);

                    AuditLog::log('order_merged', 'updated', $existingActiveOrder, null, [
                        'merged_items_count' => count($data['items']),
                    ]);

                    $this->broadcastSafely(
                        new KitchenUpdated($restaurantId),
                        'kitchen.updated',
                    );

                    return $existingActiveOrder;
                }
            }

            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $itemData) {
                $product = $products->get($itemData['product_id'])
                    ?? abort(422, 'Sản phẩm không tìm thấy: '.$itemData['product_id']);

                $lineTotal = (float) $product->price * (float) $itemData['quantity'];
                $subtotal += $lineTotal;

                $itemsToCreate[] = [
                    'restaurant_id' => $restaurantId,
                    'product_id' => $product->id,
                    'quantity' => (float) $itemData['quantity'],
                    'unit_price' => (float) $product->price,
                    'discount_amount' => 0,
                    'line_total' => $lineTotal,
                    'status' => 'pending',
                    'sent_to_kitchen_at' => now(),
                    'notes' => $itemData['notes'] ?? null,
                    'client_item_id' => $itemData['client_item_id'] ?? null,
                ];
            }

            // POS ghi nhận đúng số bán thực tế. Nếu kho không đủ, giao dịch
            // vẫn được tạo và InventoryService sẽ mở hồ sơ tồn âm để xử lý.
            $this->inventoryAvailabilityService->assertItemsAvailable(
                $restaurantId,
                $branchId,
                $data['items'],
                null,
                false,
                true,
            );

            $discountAmount = 0;
            $note = $data['note'] ?? null;
            if (! empty($data['customer_id'])) {
                $cust = Customer::find($data['customer_id']);
                if ($cust) {
                    $lvl = $cust->membership_level ?? 'silver';
                    if ($lvl === 'diamond') {
                        $discountAmount = round($subtotal * 0.10, 2);
                        $note = ($note ? $note.' ' : '').'[Ưu đãi Hội viên Kim Cương -10%]';
                    } elseif ($lvl === 'gold') {
                        $discountAmount = round($subtotal * 0.05, 2);
                        $note = ($note ? $note.' ' : '').'[Ưu đãi Hội viên Vàng -5%]';
                    }
                }
            }

            $order = $this->orderRepository->create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'table_id' => $data['table_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'created_by' => $user->id,
                'order_number' => $orderNumber,
                'channel' => $data['channel'] ?? ($data['table_id'] ? 'dine_in' : 'takeaway'),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount' => max(0.00, $subtotal - $discountAmount),
                'note' => $note,
            ]);

            foreach ($itemsToCreate as $item) {
                $item['order_id'] = $order->id;
                $createdItem = OrderItem::create($item);

                // Reserve inventory (holding stock)
                $product = $products->get($item['product_id']);
                if ($product && $product->track_inventory) {
                    foreach ($product->recipes as $recipe) {
                        $totalUsed = $this->recipeUsageInInventoryUnit($recipe, (float) $item['quantity']);
                        InventoryReservation::create([
                            'restaurant_id' => $restaurantId,
                            'branch_id' => $branchId,
                            'order_id' => $order->id,
                            'order_item_id' => $createdItem->id,
                            'ingredient_id' => $recipe->ingredient_id,
                            'reserved_quantity' => $totalUsed,
                            'status' => 'holding',
                            'expires_at' => now()->addHours(4), // default 4 hour holding time
                        ]);
                    }
                }
            }
            $this->inventoryAvailabilityService->refreshBranch($restaurantId, $branchId);
            // P1: Debounce broadcast — chỉ fire ProductStockUpdated tối đa 1 lần
            // mỗi 3 giây/nhà hàng. Tránh flood Reverb khi nhiều items trong cùng
            // 1 đơn, hoặc nhiều đơn tạo liên tiếp (giờ cao điểm nhiều nhà hàng).
            $this->fireStockUpdatedDebounced($restaurantId);

            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)
                    ->where('restaurant_id', $order->restaurant_id)
                    ->where('branch_id', $order->branch_id)
                    ->update(['status' => 'occupied']);
            }

            AuditLog::log('order_created', 'created', $order, null, [
                'total_amount' => (float) $order->total_amount,
                'items_count' => count($itemsToCreate),
            ]);

            $this->broadcastSafely(
                new KitchenUpdated($restaurantId),
                'kitchen.updated',
            );

            return $order;
        });
    }

    /**
     * Cập nhật trạng thái đơn hàng.
     */
    public function updateOrderStatus(Order $order, string $newStatus, User $user): void
    {
        DB::transaction(function () use ($order, $newStatus, $user) {
            // Khóa dòng order để tránh race condition khi cập nhật trạng thái từ nhiều luồng/thiết bị
            $order = Order::where('id', $order->id)
                ->where('restaurant_id', $user->restaurant_id)
                ->where('branch_id', $order->branch_id)
                ->lockForUpdate()
                ->firstOrFail();
            $oldStatus = $order->status;

            if ($oldStatus === $newStatus) {
                return;
            }

            if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                $this->inventoryService->deductInventoryForOrder($order, $user);
                $order->payment_status = 'paid';

                if ($order->customer_id) {
                    $customer = Customer::find($order->customer_id);
                    if ($customer) {
                        $customer->update(['last_order_at' => now()]);
                        // Earn loyalty points khi order hoàn thành (bất kể qua luồng nào)
                        $loyaltyService = app(LoyaltyService::class);
                        $loyaltyService->earnPoints($customer, $order, (float) $order->total_amount);
                        $loyaltyService->recalculateTier($customer);
                        CdpService::calculateRfmForCustomer($customer);
                    }
                }
            }

            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                if ($oldStatus === 'completed'
                    && ($order->payment_status === 'paid' || $order->payment_status === 'partial_refund')) {
                    throw new \RuntimeException(
                        'Đơn đã thanh toán không thể hủy trực tiếp. Hãy xử lý hoàn tiền để hoàn kho và ghi nhận nhật ký.'
                    );
                }

                $this->inventoryService->releaseInventoryReservations($order);
                AuditLog::log('order_cancelled', 'deleted', $order, ['status' => $oldStatus], ['status' => 'cancelled']);
            }

            $order->update([
                'status' => $newStatus,
                'completed_at' => $newStatus === 'completed' ? now() : $order->completed_at,
                'cancelled_at' => $newStatus === 'cancelled' ? now() : $order->cancelled_at,
                'payment_status' => $order->payment_status,
            ]);

            if (in_array($newStatus, ['completed', 'cancelled']) && $order->table_id) {
                RestaurantTable::where('id', $order->table_id)
                    ->where('restaurant_id', $order->restaurant_id)
                    ->where('branch_id', $order->branch_id)
                    ->update(['status' => 'available']);
            }

            $this->broadcastSafely(
                new KitchenUpdated($order->restaurant_id),
                'kitchen.updated',
            );
        });
    }

    /**
     * Tách đơn hàng hiện tại sang một bàn trống mới.
     */
    public function splitOrder(Order $order, array $data, User $user): Order
    {
        return DB::transaction(function () use ($order, $data, $user) {
            // Khóa dòng order gốc để tránh thay đổi đồng thời
            $order = Order::where('id', $order->id)
                ->where('restaurant_id', $user->restaurant_id)
                ->where('branch_id', $order->branch_id)
                ->lockForUpdate()
                ->firstOrFail();
            $oldAmount = (float) $order->total_amount;

            // 1. Tạo đơn hàng mới ở bàn trống
            $targetTable = RestaurantTable::where('restaurant_id', $order->restaurant_id)
                ->where('branch_id', $order->branch_id)
                ->where('status', 'available')
                ->find($data['table_id']);
            abort_unless($targetTable, 422, 'Bàn tách đơn phải thuộc cùng chi nhánh và đang trống.');

            $newOrder = $this->orderRepository->create([
                'restaurant_id' => $order->restaurant_id,
                'branch_id' => $order->branch_id,
                'table_id' => $data['table_id'],
                'created_by' => $user->id,
                'order_number' => $order->order_number.'-SPLIT-'.strtoupper(Str::random(4)),
                'channel' => $order->channel,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'is_split' => true,
                'split_from_order_id' => $order->id,
                'is_red_flagged' => true, // Đánh dấu đỏ lập tức
                'is_override_split_penalty' => false,
            ]);

            // 2. Chuyển các món ăn chỉ định sang đơn mới
            // Fix #3: Collect product_ids của items được move để di chuyển reservation
            $movedProductIds = [];
            foreach ($data['items'] as $itemData) {
                $originalItem = OrderItem::where('order_id', $order->id)
                    ->lockForUpdate() // Khóa dòng item gốc để tránh ghi đè đồng thời
                    ->findOrFail($itemData['order_item_id']);

                $splitQty = (float) $itemData['quantity'];
                $origQty = (float) $originalItem->quantity;

                if ($splitQty > $origQty) {
                    abort(422, 'Số lượng tách không thể lớn hơn số lượng món hiện có.');
                }

                if ($splitQty >= $origQty) {
                    $originalItem->update(['order_id' => $newOrder->id]);
                    $movedProductIds[] = $originalItem->product_id;
                } else {
                    $originalItem->update([
                        'quantity' => $origQty - $splitQty,
                        'line_total' => ($origQty - $splitQty) * $originalItem->unit_price,
                    ]);

                    OrderItem::create([
                        'restaurant_id' => $order->restaurant_id,
                        'order_id' => $newOrder->id,
                        'product_id' => $originalItem->product_id,
                        'quantity' => $splitQty,
                        'unit_price' => $originalItem->unit_price,
                        'line_total' => $splitQty * $originalItem->unit_price,
                        'status' => $originalItem->status,
                        'sent_to_kitchen_at' => $originalItem->sent_to_kitchen_at ?? now(),
                        'notes' => $originalItem->notes,
                    ]);
                }
            }

            // Fix #3: Tái phân bổ inventory reservations cho đơn mới
            // Release tất cả reservation cũ rồi re-sync cho cả 2 đơn
            $this->syncHoldingReservations($order);
            $this->syncHoldingReservations($newOrder);

            // 3. Tính toán lại subtotal & total_amount cho cả 2 đơn
            // Phân bổ discount theo tỷ lệ để tránh "Âm tiền"
            $origSub = (float) $order->items()->sum('line_total');
            $newSub = (float) $newOrder->items()->sum('line_total');
            $totalSub = $origSub + $newSub; // = subtotal ban đầu trước khi tách

            $discountTotal = (float) $order->discount_amount;
            if ($discountTotal > 0 && $totalSub > 0) {
                $origDiscountShare = round($discountTotal * ($origSub / $totalSub), 2);
                $newDiscountShare = round($discountTotal - $origDiscountShare, 2);
            } else {
                $origDiscountShare = 0.0;
                $newDiscountShare = 0.0;
            }

            $order->update([
                'subtotal' => $origSub,
                'discount_amount' => $origDiscountShare,
                'total_amount' => max(0.0, $origSub - $origDiscountShare),
            ]);

            $newOrder->update([
                'subtotal' => $newSub,
                'discount_amount' => $newDiscountShare,
                'total_amount' => max(0.0, $newSub - $newDiscountShare),
            ]);

            // Ghi Audit Log cho cả 2 đơn
            AuditLog::log('order_split', 'created', $newOrder, null, [
                'total_amount' => (float) $newOrder->total_amount,
                'split_from_order_id' => $order->id,
                'discount_distributed' => $newDiscountShare,
            ]);

            AuditLog::log('order_updated', 'updated', $order, [
                'total_amount' => $oldAmount,
            ], [
                'total_amount' => (float) $order->total_amount,
                'discount_distributed' => $origDiscountShare,
            ]);

            return $newOrder;
        });
    }

    /**
     * Sửa đổi thông tin đơn hàng / giá món / áp dụng giảm giá.
     */
    public function updateOrder(Order $order, array $data, User $user): void
    {
        DB::transaction(function () use ($order, $data) {
            $restaurantId = $order->restaurant_id;

            // Audit log tracking (Old values must be captured BEFORE database updates!)
            $oldValues = [
                'subtotal' => (float) $order->subtotal,
                'discount_amount' => (float) $order->discount_amount,
                'total_amount' => (float) $order->total_amount,
                'items' => $order->items->map(fn ($item) => ['id' => $item->id, 'unit_price' => (float) $item->unit_price, 'quantity' => (float) $item->quantity])->toArray(),
            ];

            if (isset($data['items'])) {
                $this->inventoryAvailabilityService->assertItemsAvailable(
                    $restaurantId,
                    (int) $order->branch_id,
                    $data['items'],
                    $order->id,
                    false,
                    true,
                );
                $payloadItemIds = collect($data['items'])->pluck('id')->filter()->toArray();

                // Mark omitted items as cancelled and release reservations
                $cancelledItems = OrderItem::where('order_id', $order->id)
                    ->whereNotIn('id', $payloadItemIds)
                    ->get();

                foreach ($cancelledItems as $cItem) {
                    $cItem->update(['status' => 'cancelled']);

                    $product = Product::with('recipes')
                        ->where('restaurant_id', $restaurantId)
                        ->where(function ($query) use ($order) {
                            $query->whereNull('branch_id')->orWhere('branch_id', $order->branch_id);
                        })
                        ->find($cItem->product_id);
                    if ($product && $product->track_inventory) {
                        foreach ($product->recipes as $recipe) {
                            InventoryReservation::where('order_id', $order->id)
                                ->where('branch_id', $order->branch_id)
                                ->where('ingredient_id', $recipe->ingredient_id)
                                ->where('status', 'holding')
                                ->update(['status' => 'released']);
                        }
                    }
                }

                foreach ($data['items'] as $itemData) {
                    if (! empty($itemData['id'])) {
                        $item = OrderItem::where('order_id', $order->id)
                            ->findOrFail($itemData['id']);

                        $item->update([
                            'unit_price' => $itemData['unit_price'] ?? $item->unit_price,
                            'quantity' => $itemData['quantity'],
                            'line_total' => ($itemData['unit_price'] ?? $item->unit_price) * $itemData['quantity'],
                            'notes' => $itemData['notes'] ?? $item->notes,
                        ]);
                    } else {
                        // Thêm món mới tinh
                        $product = Product::where('restaurant_id', $restaurantId)
                            ->where(function ($query) use ($order) {
                                $query->whereNull('branch_id')->orWhere('branch_id', $order->branch_id);
                            })
                            ->sellableMenu()
                            ->findOrFail($itemData['product_id']);

                        $unitPrice = $itemData['unit_price'] ?? (float) $product->price;
                        $lineTotal = $unitPrice * (float) $itemData['quantity'];

                        OrderItem::create([
                            'restaurant_id' => $restaurantId,
                            'order_id' => $order->id,
                            'product_id' => $product->id,
                            'quantity' => (float) $itemData['quantity'],
                            'unit_price' => $unitPrice,
                            'discount_amount' => 0,
                            'line_total' => $lineTotal,
                            'status' => 'pending',
                            'sent_to_kitchen_at' => now(),
                            'notes' => $itemData['notes'] ?? null,
                        ]);
                    }
                }

                $this->syncHoldingReservations($order);
            }

            $subtotal = $order->items()->where('status', '!=', 'cancelled')->sum('line_total');
            $discount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : $order->discount_amount;

            // Apply loyalty discount if customer is attached and discount is not manually specified
            if (! isset($data['discount_amount']) && $order->customer_id) {
                $cust = Customer::find($order->customer_id);
                if ($cust) {
                    $lvl = $cust->membership_level ?? 'silver';
                    if ($lvl === 'diamond') {
                        $discount = round($subtotal * 0.10, 2);
                    } elseif ($lvl === 'gold') {
                        $discount = round($subtotal * 0.05, 2);
                    }
                }
            }

            $discount = min(max(0.0, $discount), (float) $subtotal);

            $order->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => max(0.0, $subtotal - $discount),
                'note' => isset($data['note']) ? $data['note'] : $order->note,
            ]);

            $order->refresh();
            $order->load('items');

            $newValues = [
                'subtotal' => (float) $order->subtotal,
                'discount_amount' => (float) $order->discount_amount,
                'total_amount' => (float) $order->total_amount,
                'items' => $order->items->map(fn ($item) => ['id' => $item->id, 'unit_price' => (float) $item->unit_price, 'quantity' => (float) $item->quantity])->toArray(),
            ];

            $changed = false;

            if ($oldValues['discount_amount'] !== $newValues['discount_amount']) {
                $changed = true;
            }

            foreach ($oldValues['items'] as $oldItem) {
                $newItem = collect($newValues['items'])->firstWhere('id', $oldItem['id']);
                if ($newItem && $oldItem['unit_price'] !== $newItem['unit_price']) {
                    AuditLog::log('price_modified', 'updated', $order, [
                        'item_id' => $oldItem['id'],
                        'unit_price' => $oldItem['unit_price'],
                    ], [
                        'item_id' => $newItem['id'],
                        'unit_price' => $newItem['unit_price'],
                    ]);
                    $changed = true;
                }
            }

            if ($changed || $oldValues['subtotal'] !== $newValues['subtotal']) {
                AuditLog::log('order_updated', 'updated', $order, $oldValues, $newValues);
            }

            $this->broadcastSafely(
                new KitchenUpdated($order->restaurant_id),
                'kitchen.updated',
            );
        });
    }

    /**
     * Xác nhận đơn hàng QR từ khách hàng.
     */
    public function confirmQr(Order $order, User $user): void
    {
        DB::transaction(function () use ($order, $user) {
            $order->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'cashier_user_id' => $user->id,
            ]);

            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)
                    ->where('restaurant_id', $order->restaurant_id)
                    ->where('branch_id', $order->branch_id)
                    ->update(['status' => 'occupied']);
            }

            AuditLog::log('order_confirmed', 'updated', $order, ['status' => 'pending'], ['status' => 'confirmed']);
        });
    }

    /**
     * Thanh toán đơn hàng.
     */
    public function assertCanBePaid(Order $order): void
    {
        $hasUnservedItems = OrderItem::where('restaurant_id', $order->restaurant_id)
            ->where('order_id', $order->id)
            ->where('status', '!=', 'cancelled')
            ->whereNull('served_at')
            ->exists();

        if ($hasUnservedItems) {
            throw new \RuntimeException('Chưa thể thanh toán: đơn hàng vẫn còn món chưa được phục vụ.');
        }
    }

    public function payOrder(Order $order, array $data, User $user, bool $queuePostPayment = false): bool
    {
        $paid = DB::transaction(function () use ($order, $data, $user, $queuePostPayment) {
            // Luôn khóa bàn trước rồi mới khóa order, cùng thứ tự với luồng
            // tạo đơn, để thanh toán đồng thời không tạo deadlock.
            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)
                    ->where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $order->branch_id)
                    ->lockForUpdate()
                    ->first();
            }

            // Khóa row + đọc lại trạng thái mới nhất trong transaction để tránh 2 webhook
            // trùng lặp (retry gateway) cùng lúc vượt qua check payment_status và xử lý 2 lần.
            $order = Order::where('id', $order->id)
                ->where('restaurant_id', $user->restaurant_id)
                ->where('branch_id', $order->branch_id)
                ->lockForUpdate()
                ->first();

            if (! $order || $order->payment_status === 'paid') {
                return false;
            }

            $this->assertCanBePaid($order);

            // Cập nhật customer_id bên trong transaction an toàn
            if (isset($data['customer_id']) && $data['customer_id']) {
                $order->update(['customer_id' => $data['customer_id']]);
                $order->refresh();
            }

            $customer = $order->customer_id ? Customer::find($order->customer_id) : null;

            // Fix #7: Apply tier-based membership discount CHỈ KHI chưa có discount
            // từ createOrder (tránh stacking: createOrder -10% + payOrder -10% = -20%)
            if ($customer && ! str_contains($order->note ?? '', '[Ưu đãi Hội viên')) {
                $tier = $customer->loyaltyTier;
                $discountPct = $tier ? (float) $tier->discount_percent : 0;
                $tierName = $tier->name ?? null;

                if (! $tier) {
                    $lvl = $customer->membership_level ?? 'silver';
                    if ($lvl === 'diamond') {
                        $discountPct = 10;
                        $tierName = 'Kim Cương';
                    } elseif ($lvl === 'gold') {
                        $discountPct = 5;
                        $tierName = 'Vàng';
                    }
                }

                $loyaltyDiscount = $discountPct > 0 ? round($order->subtotal * ($discountPct / 100), 2) : 0;

                if ($loyaltyDiscount > 0) {
                    $tierName = $tierName ?? 'Thành viên';
                    $order->discount_amount = (float) $order->discount_amount + $loyaltyDiscount;
                    // Fix #7: Cap discount — không cho discount vượt subtotal
                    $order->discount_amount = min((float) $order->discount_amount, (float) $order->subtotal);
                    $order->total_amount = max(0.0, (float) $order->subtotal - (float) $order->discount_amount);
                    $order->note = ($order->note ? $order->note.' ' : '')."[Ưu đãi Hội viên {$tierName}: -".number_format($loyaltyDiscount).'đ]';
                    $order->save();
                }
            }

            $redeemedPoints = isset($data['redeem_points']) ? (int) $data['redeem_points'] : 0;
            $redeemDiscount = 0.0;

            if ($customer && $redeemedPoints > 0) {
                $loyaltyService = app(LoyaltyService::class);
                $redeemDiscount = $loyaltyService->redeemPoints($customer, $redeemedPoints, $order);

                if ($redeemDiscount > 0) {
                    $order->discount_amount = (float) $order->discount_amount + $redeemDiscount;
                    // Fix #7: Cap discount — không cho discount vượt subtotal
                    $order->discount_amount = min((float) $order->discount_amount, (float) $order->subtotal);
                    $order->total_amount = max(0.0, (float) $order->subtotal - (float) $order->discount_amount);
                    $order->note = ($order->note ? $order->note.' ' : '')."[Đã quy đổi {$redeemedPoints} điểm loyalty thưởng: -".number_format($redeemDiscount).'đ]';
                    $order->save();
                    $customer->refresh();
                }
            }

            // 1. Tạo Payment record (Hỗ trợ Thanh toán kết hợp - Multi-Tender)
            $cashAmountToRecord = 0.0;

            if (! empty($data['payments']) && is_array($data['payments'])) {
                foreach ($data['payments'] as $p) {
                    $pAmount = (float) ($p['amount'] ?? 0);
                    if ($pAmount <= 0) continue;

                    Payment::create([
                        'restaurant_id' => $order->restaurant_id,
                        'branch_id' => $order->branch_id,
                        'order_id' => $order->id,
                        'processed_by' => $user->id,
                        'payment_method' => $p['payment_method'] ?? 'cash',
                        'status' => 'paid',
                        'amount' => $pAmount,
                        'cash_received' => $p['cash_received'] ?? $pAmount,
                        'change_amount' => $p['change_amount'] ?? 0,
                        'paid_at' => now(),
                    ]);

                    if (($p['payment_method'] ?? '') === 'cash') {
                        $cashAmountToRecord += $pAmount;
                    }
                }
            } else {
                Payment::create([
                    'restaurant_id' => $order->restaurant_id,
                    'branch_id' => $order->branch_id,
                    'order_id' => $order->id,
                    'processed_by' => $user->id,
                    'payment_method' => $data['payment_method'],
                    'status' => 'paid',
                    'amount' => $order->total_amount,
                    'cash_received' => $data['cash_received'] ?? $order->total_amount,
                    'change_amount' => $data['change_amount'] ?? 0,
                    'paid_at' => now(),
                ]);

                if (($data['payment_method'] ?? '') === 'cash') {
                    $cashAmountToRecord = (float) $order->total_amount;
                }
            }

            // 3. Cập nhật Order status thành completed & payment_status paid
            $order->update([
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => $data['payment_method'] ?? 'cash',
                'completed_at' => now(),
                'cashier_user_id' => $user->id,
            ]);

            // 4. Chỉ giải phóng bàn khi toàn bộ món đã được phục vụ.
            // Thanh toán xong chưa đồng nghĩa với kết thúc phục vụ.
            $this->releaseTableIfNoActiveServiceOrder($order);

            // Ghi CashTransaction cho số tiền mặt thực tế nhận được (kể cả trong đơn thanh toán đa phương thức)
            if ($cashAmountToRecord > 0) {
                $cashRegister = CashRegister::where('restaurant_id', $order->restaurant_id)
                    ->where('branch_id', $order->branch_id)
                    ->where('status', 'open')
                    ->first();

                if ($cashRegister) {
                    CashTransaction::create([
                        'restaurant_id' => $order->restaurant_id,
                        'branch_id' => $order->branch_id,
                        'cash_register_id' => $cashRegister->id,
                        'type' => 'in',
                        'source' => 'order',
                        'amount' => $cashAmountToRecord,
                        'notes' => "Thanh toán đơn hàng {$order->order_number}",
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                        'created_by' => $user->id,
                        'occurred_at' => now(),
                    ]);

                    $cashRegister->increment('expected_closing_balance', $cashAmountToRecord);
                }
            }

            if ($queuePostPayment) {
                // Keep the queue for heavy loyalty/CDP work, but commit BOM
                // deduction in this transaction so paid revenue can never
                // exist without matching stock/COGS.
                $this->inventoryService->deductInventoryForOrder($order, $user);
                ProcessPostPaymentActions::dispatch($order->id, $user->id)->afterCommit();
            } else {
                // 2. Trừ kho nguyên liệu
                $this->inventoryService->deductInventoryForOrder($order, $user);

                // 5. Tích điểm loyalty + cập nhật RFM
                if ($customer) {
                    $customer->update(['last_order_at' => now()]);

                    $loyaltyService = app(LoyaltyService::class);
                    $loyaltyService->earnPoints($customer, $order, (float) $order->total_amount);
                    $loyaltyService->recalculateTier($customer);

                    CdpService::calculateRfmForCustomer($customer);
                }
            }

            AuditLog::log('order_paid', 'updated', $order, ['payment_status' => 'unpaid'], ['payment_status' => 'paid']);

            return true;
        });

        // Sau khi commit: bắn webhook developer + analytics server-side (GA4/FB CAPI).
        // Bọc try/catch — lỗi tích hợp không được phép ảnh hưởng luồng thanh toán.
        if ($paid) {
            try {
                $order->refresh();

                // Đơn cọc giữ bàn được thanh toán → tự động xác nhận đặt bàn
                if ($order->channel === 'reservation_deposit') {
                    TableReservation::withoutGlobalScopes()
                        ->where('deposit_order_id', $order->id)
                        ->where('deposit_status', 'pending')
                        ->update([
                            'deposit_status' => 'paid',
                            'status' => 'confirmed',
                            'confirmed_at' => now(),
                        ]);
                }

                app(WebhookDispatchService::class)->dispatch(
                    $order->restaurant_id,
                    'order.paid',
                    [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_amount' => (float) $order->total_amount,
                        'payment_method' => $data['payment_method'] ?? null,
                        'paid_at' => now()->toIso8601String(),
                    ]
                );

                app(TrackingService::class)->trackPurchase($order);
            } catch (\Throwable $e) {
                Log::warning('payOrder: lỗi post-payment integrations', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // false = đơn đã paid từ trước (webhook trùng lặp) — caller không được
        // bắn tiếp event/notification như thể vừa thanh toán lần đầu.
        return $paid;
    }

    /** Chuyển bàn về available chỉ khi không còn món chưa phục vụ. */
    private function releaseTableIfNoActiveServiceOrder(Order $order): void
    {
        if (! $order->table_id) {
            return;
        }

        $table = RestaurantTable::where('id', $order->table_id)
            ->where('restaurant_id', $order->restaurant_id)
            ->where('branch_id', $order->branch_id)
            ->lockForUpdate()
            ->first();

        if ($table && ! $table->orders()->activeForService()->exists()) {
            $table->update(['status' => 'available']);
        }
    }

    /**
     * Phê duyệt đối soát đơn bị tách để xóa phạt.
     */
    public function overrideSplitPenalty(Order $order, User $user): void
    {
        $order->update([
            'is_override_split_penalty' => true,
            'is_red_flagged' => false,
        ]);

        AuditLog::log('order_split_override', 'updated', $order, [
            'is_override_split_penalty' => false,
        ], [
            'is_override_split_penalty' => true,
        ]);
    }

    private function syncHoldingReservations(Order $order): void
    {
        InventoryReservation::where('order_id', $order->id)
            ->where('branch_id', $order->branch_id)
            ->where('status', 'holding')
            ->update(['status' => 'released']);

        $items = OrderItem::where('order_id', $order->id)
            ->where('status', '!=', 'cancelled')
            ->with('product.recipes')
            ->get();

        // Fix #11: Batch insert thay vì loop create() — giảm N+1 queries
        $reservationsToInsert = [];
        $now = now();
        $expiresAt = $now->copy()->addHours(4);

        foreach ($items as $item) {
            if (! $item->product?->track_inventory) {
                continue;
            }

            foreach ($item->product->recipes as $recipe) {
                $reservationsToInsert[] = [
                    'restaurant_id' => $order->restaurant_id,
                    'branch_id' => $order->branch_id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'ingredient_id' => $recipe->ingredient_id,
                    'reserved_quantity' => $this->recipeUsageInInventoryUnit($recipe, (float) $item->quantity),
                    'status' => 'holding',
                    'expires_at' => $expiresAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($reservationsToInsert)) {
            // Chunk để tránh query quá dài khi đơn lớn
            foreach (array_chunk($reservationsToInsert, 50) as $chunk) {
                InventoryReservation::insert($chunk);
            }
        }
    }

    /** Rebuild holding reservations after an item quantity changes in the kitchen. */
    public function refreshHoldingReservations(Order $order): void
    {
        $this->syncHoldingReservations($order);
        $this->inventoryAvailabilityService->refreshBranch(
            $order->restaurant_id,
            (int) $order->branch_id,
        );
    }

    private function recipeUsageInInventoryUnit($recipe, float $itemQuantity): float
    {
        return app(UnitConversionService::class)->recipeQuantityInIngredientUnit($recipe)
            * $itemQuantity
            * (1 + ((float) $recipe->waste_rate / 100));
    }

    /**
     * P1: Debounce ProductStockUpdated broadcast — tối đa 1 lần/nhà hàng/3 giây.
     *
     * Vấn đề: event này được fire bởi createOrder, splitOrder, InventoryService...
     * Nếu 1 đơn có 10 items, hoặc nhiều đơn tạo liên tiếp trong giờ cao điểm
     * với hệ thống 500+ nhà hàng → hàng nghìn broadcast/giây → Reverb bị flood.
     *
     * Giải pháp: dùng Cache::add() (atomic SET NX EX trên Redis) để chỉ fire
     * 1 lần/nhà hàng trong khoảng DEBOUNCE_TTL giây. Các lần fire tiếp theo
     * trong cùng khoảng thời gian bị bỏ qua (client vẫn nhận được đủ data
     * vì backend state không đổi trong khoảng thời gian đó).
     */
    private function fireStockUpdatedDebounced(int $restaurantId): void
    {
        /** @var int Giây tối thiểu giữa 2 lần broadcast cùng nhà hàng */
        $ttl = 3;
        $key = "stock_broadcast_lock:{$restaurantId}";

        // Cache::add() chỉ set nếu key chưa tồn tại → atomic, thread-safe trên Redis.
        // Nếu key đã tồn tại (đã fire gần đây) → skip broadcast.
        if (Cache::add($key, 1, $ttl)) {
            $this->broadcastSafely(
                new ProductStockUpdated($restaurantId),
                'product.stock_updated',
            );
        }
    }

    /**
     * Broadcast realtime không được làm hỏng giao dịch lưu đơn khi Reverb tạm dừng.
     */
    private function broadcastSafely(object $event, string $eventName): void
    {
        try {
            event($event);
        } catch (\Throwable $exception) {
            Log::warning('Realtime broadcast skipped.', [
                'event' => $eventName,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
