<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\InventoryReservation;
use App\Repositories\OrderRepositoryInterface;
use App\Events\Kitchen\KitchenUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private InventoryService $inventoryService
    ) {}

    /**
     * Tạo đơn hàng mới từ giao diện POS.
     */
    public function createOrder(array $data, \App\Models\User $user): Order
    {
        return DB::transaction(function () use ($data, $user) {
            $restaurantId = $user->restaurant_id;
            $branchId = $user->branch_id;
            $orderNumber = 'ORD-' . strtoupper(uniqid());

            // Pre-load các sản phẩm
            $productIds = array_column($data['items'], 'product_id');
            $products = Product::where('restaurant_id', $restaurantId)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $itemData) {
                $product = $products->get($itemData['product_id'])
                    ?? abort(422, 'Sản phẩm không tìm thấy: ' . $itemData['product_id']);
                
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
                    'notes' => $itemData['notes'] ?? null,
                ];
            }

            $discountAmount = 0;
            $note = $data['note'] ?? null;
            if (!empty($data['customer_id'])) {
                $cust = \App\Models\Customer::find($data['customer_id']);
                if ($cust) {
                    $lvl = $cust->membership_level ?? 'silver';
                    if ($lvl === 'diamond') {
                        $discountAmount = round($subtotal * 0.10, 2);
                        $note = ($note ? $note . ' ' : '') . "[Ưu đãi Hội viên Kim Cương -10%]";
                    } elseif ($lvl === 'gold') {
                        $discountAmount = round($subtotal * 0.05, 2);
                        $note = ($note ? $note . ' ' : '') . "[Ưu đãi Hội viên Vàng -5%]";
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
                OrderItem::create($item);

                // Reserve inventory (holding stock)
                $product = Product::with('recipes')->find($item['product_id']);
                if ($product && $product->track_inventory) {
                    foreach ($product->recipes as $recipe) {
                        $totalUsed = ($recipe->quantity * $item['quantity']) * (1 + ($recipe->waste_rate / 100));
                        InventoryReservation::create([
                            'restaurant_id' => $restaurantId,
                            'order_id' => $order->id,
                            'ingredient_id' => $recipe->ingredient_id,
                            'reserved_quantity' => $totalUsed,
                            'status' => 'holding',
                            'expires_at' => now()->addHours(4), // default 4 hour holding time
                        ]);
                    }
                }
            }
            event(new \App\Events\Customer\ProductStockUpdated($restaurantId));

            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)->update(['status' => 'occupied']);
            }

            AuditLog::log('order_created', 'created', $order, null, [
                'total_amount' => (float) $order->total_amount,
                'items_count' => count($itemsToCreate)
            ]);

            event(new KitchenUpdated($restaurantId));

            return $order;
        });
    }

    /**
     * Cập nhật trạng thái đơn hàng.
     */
    public function updateOrderStatus(Order $order, string $newStatus, \App\Models\User $user): void
    {
        DB::transaction(function () use ($order, $newStatus, $user) {
            $oldStatus = $order->status;

            if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                $this->inventoryService->deductInventoryForOrder($order, $user);
                $order->payment_status = 'paid';

                if ($order->customer_id) {
                    $customer = \App\Models\Customer::find($order->customer_id);
                    if ($customer) {
                        $customer->update(['last_order_at' => now()]);
                        \App\Services\CdpService::calculateRfmForCustomer($customer);
                    }
                }
            }

            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                $this->inventoryService->releaseInventoryReservations($order);
                AuditLog::log('order_cancelled', 'deleted', $order, ['status' => $oldStatus], ['status' => 'cancelled']);
            }

            $order->update([
                'status'       => $newStatus,
                'completed_at' => $newStatus === 'completed' ? now() : $order->completed_at,
                'cancelled_at' => $newStatus === 'cancelled' ? now() : $order->cancelled_at,
                'payment_status' => $order->payment_status,
            ]);

            if (in_array($newStatus, ['completed', 'cancelled']) && $order->table_id) {
                RestaurantTable::where('id', $order->table_id)->update(['status' => 'available']);
            }

            event(new KitchenUpdated($order->restaurant_id));
        });
    }

    /**
     * Tách đơn hàng hiện tại sang một bàn trống mới.
     */
    public function splitOrder(Order $order, array $data, \App\Models\User $user): Order
    {
        return DB::transaction(function () use ($order, $data, $user) {
            $oldAmount = (float) $order->total_amount;

            // 1. Tạo đơn hàng mới ở bàn trống
            $newOrder = $this->orderRepository->create([
                'restaurant_id' => $order->restaurant_id,
                'branch_id' => $order->branch_id,
                'table_id' => $data['table_id'],
                'created_by' => $user->id,
                'order_number' => $order->order_number . '-SPLIT-' . strtoupper(Str::random(4)),
                'channel' => $order->channel,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'is_split' => true,
                'split_from_order_id' => $order->id,
                'is_red_flagged' => true, // Đánh dấu đỏ lập tức
                'is_override_split_penalty' => false,
            ]);

            // 2. Chuyển các món ăn chỉ định sang đơn mới
            foreach ($data['items'] as $itemData) {
                $originalItem = OrderItem::where('order_id', $order->id)
                    ->findOrFail($itemData['order_item_id']);

                $splitQty = (float) $itemData['quantity'];
                $origQty = (float) $originalItem->quantity;

                if ($splitQty >= $origQty) {
                    $originalItem->update(['order_id' => $newOrder->id]);
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
                        'notes' => $originalItem->notes,
                    ]);
                }
            }

            // 3. Tính toán lại subtotal & total_amount cho cả 2 đơn
            // Phân bổ discount theo tỷ lệ để tránh "Âm tiền"
            $origSub = (float) $order->items()->sum('line_total');
            $newSub  = (float) $newOrder->items()->sum('line_total');
            $totalSub = $origSub + $newSub; // = subtotal ban đầu trước khi tách

            $discountTotal = (float) $order->discount_amount;
            if ($discountTotal > 0 && $totalSub > 0) {
                $origDiscountShare = round($discountTotal * ($origSub / $totalSub), 2);
                $newDiscountShare  = round($discountTotal - $origDiscountShare, 2);
            } else {
                $origDiscountShare = 0.0;
                $newDiscountShare  = 0.0;
            }

            $order->update([
                'subtotal'        => $origSub,
                'discount_amount' => $origDiscountShare,
                'total_amount'    => max(0.0, $origSub - $origDiscountShare),
            ]);

            $newOrder->update([
                'subtotal'        => $newSub,
                'discount_amount' => $newDiscountShare,
                'total_amount'    => max(0.0, $newSub - $newDiscountShare),
            ]);

            // Ghi Audit Log cho cả 2 đơn
            AuditLog::log('order_split', 'created', $newOrder, null, [
                'total_amount'         => (float) $newOrder->total_amount,
                'split_from_order_id'  => $order->id,
                'discount_distributed' => $newDiscountShare,
            ]);

            AuditLog::log('order_updated', 'updated', $order, [
                'total_amount' => $oldAmount
            ], [
                'total_amount'         => (float) $order->total_amount,
                'discount_distributed' => $origDiscountShare,
            ]);

            return $newOrder;
        });
    }

    /**
     * Sửa đổi thông tin đơn hàng / giá món / áp dụng giảm giá.
     */
    public function updateOrder(Order $order, array $data, \App\Models\User $user): void
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
                foreach ($data['items'] as $itemData) {
                    if (!empty($itemData['id'])) {
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
                            'notes' => $itemData['notes'] ?? null,
                        ]);
                    }
                }
            }

            $subtotal = $order->items()->sum('line_total');
            $discount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : $order->discount_amount;

            // Apply loyalty discount if customer is attached and discount is not manually specified
            if (!isset($data['discount_amount']) && $order->customer_id) {
                $cust = \App\Models\Customer::find($order->customer_id);
                if ($cust) {
                    $lvl = $cust->membership_level ?? 'silver';
                    if ($lvl === 'diamond') {
                        $discount = round($subtotal * 0.10, 2);
                    } elseif ($lvl === 'gold') {
                        $discount = round($subtotal * 0.05, 2);
                    }
                }
            }

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
                        'unit_price' => $oldItem['unit_price']
                    ], [
                        'item_id' => $newItem['id'],
                        'unit_price' => $newItem['unit_price']
                    ]);
                    $changed = true;
                }
            }

            if ($changed || $oldValues['subtotal'] !== $newValues['subtotal']) {
                AuditLog::log('order_updated', 'updated', $order, $oldValues, $newValues);
            }

            event(new KitchenUpdated($order->restaurant_id));
        });
    }

    /**
     * Xác nhận đơn hàng QR từ khách hàng.
     */
    public function confirmQr(Order $order, \App\Models\User $user): void
    {
        DB::transaction(function () use ($order, $user) {
            $order->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'cashier_user_id' => $user->id,
            ]);

            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)->update(['status' => 'occupied']);
            }

            AuditLog::log('order_confirmed', 'updated', $order, ['status' => 'pending'], ['status' => 'confirmed']);
        });
    }

    /**
     * Thanh toán đơn hàng.
     */
    public function payOrder(Order $order, array $data, \App\Models\User $user): void
    {
        DB::transaction(function () use ($order, $data, $user) {
            $customer = $order->customer_id ? \App\Models\Customer::find($order->customer_id) : null;

            // Apply tier-based membership discount
            if ($customer && !str_contains($order->note ?? '', '[Ưu đãi Hội viên')) {
                $tier = $customer->loyaltyTier;
                $discountPct = $tier ? (float) $tier->discount_percent : 0;
                $tierName = $tier->name ?? null;

                if (!$tier) {
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
                    $order->total_amount = max(0.0, (float) $order->subtotal - (float) $order->discount_amount);
                    $order->note = ($order->note ? $order->note . ' ' : '') . "[Ưu đãi Hội viên {$tierName}: -" . number_format($loyaltyDiscount) . "đ]";
                    $order->save();
                }
            }

            $redeemedPoints = isset($data['redeem_points']) ? (int) $data['redeem_points'] : 0;
            $redeemDiscount = 0.0;

            if ($customer && $redeemedPoints > 0) {
                $loyaltyService = app(\App\Services\LoyaltyService::class);
                $redeemDiscount = $loyaltyService->redeemPoints($customer, $redeemedPoints, $order);

                if ($redeemDiscount > 0) {
                    $order->discount_amount = (float) $order->discount_amount + $redeemDiscount;
                    $order->total_amount = max(0.0, (float) $order->subtotal - (float) $order->discount_amount);
                    $order->note = ($order->note ? $order->note . ' ' : '') . "[Đã quy đổi {$redeemedPoints} điểm loyalty thưởng: -" . number_format($redeemDiscount) . "đ]";
                    $order->save();
                    $customer->refresh();
                }
            }

            // 1. Tạo Payment record
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

            // 2. Trừ kho nguyên liệu
            $this->inventoryService->deductInventoryForOrder($order, $user);

            // 3. Cập nhật Order status thành completed & payment_status paid
            $order->update([
                'status' => 'completed',
                'payment_status' => 'paid',
                'completed_at' => now(),
                'cashier_user_id' => $user->id,
            ]);

            // 4. Giải phóng bàn
            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)->update(['status' => 'available']);
            }

            // 5. Tích điểm loyalty + cập nhật RFM
            if ($customer) {
                $customer->update(['last_order_at' => now()]);

                $loyaltyService = app(\App\Services\LoyaltyService::class);
                $loyaltyService->earnPoints($customer, $order, (float) $order->total_amount);
                $loyaltyService->recalculateTier($customer);

                \App\Services\CdpService::calculateRfmForCustomer($customer);
            }

            AuditLog::log('order_paid', 'updated', $order, ['payment_status' => 'unpaid'], ['payment_status' => 'paid']);
        });
    }

    /**
     * Phê duyệt đối soát đơn bị tách để xóa phạt.
     */
    public function overrideSplitPenalty(Order $order, \App\Models\User $user): void
    {
        $order->update([
            'is_override_split_penalty' => true,
            'is_red_flagged' => false,
        ]);

        AuditLog::log('order_split_override', 'updated', $order, [
            'is_override_split_penalty' => false
        ], [
            'is_override_split_penalty' => true
        ]);
    }
}
