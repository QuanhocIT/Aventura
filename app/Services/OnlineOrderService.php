<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Delivery\DeliveryDetail;
use App\Models\OnlineStoreConfig;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnlineOrderService
{
    public function getPublicMenu(Restaurant $restaurant): array
    {
        $categories = ProductCategory::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'active')
            ->orderBy('display_order')
            ->get(['id', 'name', 'slug']);

        $products = Product::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->where('is_available', true)
            ->where(function ($q) {
                $q->whereNull('paused_until')->orWhere('paused_until', '<', now());
            })
            ->where(function ($q) {
                $q->whereNull('out_of_stock_until')->orWhere('out_of_stock_until', '<', now());
            })
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'price', 'image_url', 'category_id']);

        return [
            'categories' => $categories,
            'products' => $products->groupBy('category_id'),
        ];
    }

    public function createOnlineOrder(array $data, OnlineStoreConfig $config): Order
    {
        $restaurant = Restaurant::find($config->restaurant_id);

        if (! $config->is_active) {
            throw ValidationException::withMessages(['store' => 'Cửa hàng online hiện đang tạm ngừng.']);
        }

        return DB::transaction(function () use ($data, $config, $restaurant) {
            $channel = $data['channel'] ?? 'takeaway';
            $items = $data['items'] ?? [];

            $subtotal = 0;
            $orderItems = [];

            foreach ($items as $item) {
                $product = Product::withoutGlobalScopes()
                    ->where('restaurant_id', $config->restaurant_id)
                    ->where('id', $item['product_id'])
                    ->where('is_active', true)
                    ->where('is_available', true)
                    ->first();

                if (! $product) {
                    throw ValidationException::withMessages(['items' => "Sản phẩm #{$item['product_id']} không khả dụng."]);
                }

                $lineTotal = (float) $product->price * (int) $item['quantity'];
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $product->price,
                    'line_total' => $lineTotal,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $deliveryFee = 0;
            if ($channel === 'delivery' && isset($data['latitude'], $data['longitude'])) {
                $feeResult = $this->calculateDeliveryFee(
                    (float) $data['latitude'],
                    (float) $data['longitude'],
                    $restaurant
                );

                if (! $feeResult['deliverable']) {
                    throw ValidationException::withMessages(['address' => $feeResult['reason']]);
                }

                $deliveryFee = $feeResult['fee'];
            }

            $totalAmount = $subtotal + $deliveryFee;

            if ($config->min_order_amount > 0 && $subtotal < (float) $config->min_order_amount) {
                throw ValidationException::withMessages([
                    'total' => 'Đơn hàng tối thiểu ' . number_format($config->min_order_amount) . 'đ.',
                ]);
            }

            $customer = null;
            if (! empty($data['phone'])) {
                $customer = Customer::withoutGlobalScopes()->firstOrCreate(
                    ['restaurant_id' => $config->restaurant_id, 'phone' => $data['phone']],
                    ['full_name' => $data['customer_name'] ?? 'Khách online']
                );
            }

            $order = Order::create([
                'restaurant_id' => $config->restaurant_id,
                'branch_id' => $restaurant->branches()->first()?->id,
                'customer_id' => $customer?->id,
                'order_number' => 'ON' . strtoupper(Str::random(8)),
                'channel' => $channel === 'delivery' ? 'delivery' : 'takeaway',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'service_charge' => $deliveryFee,
                'total_amount' => $totalAmount,
                'note' => $data['note'] ?? null,
                'online_notes' => $data['address'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
            ]);

            foreach ($orderItems as $item) {
                OrderItem::create([
                    'restaurant_id' => $config->restaurant_id,
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'notes' => $item['notes'],
                    'status' => 'pending',
                ]);
            }

            if ($channel === 'delivery') {
                DeliveryDetail::create([
                    'order_id' => $order->id,
                    'restaurant_id' => $config->restaurant_id,
                    'customer_name' => $data['customer_name'] ?? 'Khách',
                    'phone' => $data['phone'] ?? '',
                    'address' => $data['address'] ?? '',
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'delivery_fee' => $deliveryFee,
                    'cod_amount' => ($data['payment_method'] ?? '') === 'cod' ? $totalAmount : 0,
                    'delivery_status' => 'pending',
                ]);
            }

            return $order;
        });
    }

    public function calculateDeliveryFee(float $lat, float $lng, Restaurant $restaurant): array
    {
        $config = OnlineStoreConfig::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (! $config) {
            return ['deliverable' => false, 'reason' => 'Nhà hàng chưa cấu hình giao hàng.'];
        }

        $rLat = (float) ($restaurant->latitude ?? 0);
        $rLng = (float) ($restaurant->longitude ?? 0);

        if ($rLat === 0.0 || $rLng === 0.0) {
            return ['deliverable' => false, 'reason' => 'Nhà hàng chưa cập nhật tọa độ.'];
        }

        $distanceKm = $this->haversine($rLat, $rLng, $lat, $lng);

        if ($distanceKm > (float) $config->max_delivery_km) {
            return [
                'deliverable' => false,
                'reason' => "Ngoài phạm vi giao hàng ({$config->max_delivery_km} km).",
                'distance_km' => round($distanceKm, 1),
            ];
        }

        return [
            'deliverable' => true,
            'distance_km' => round($distanceKm, 1),
            'fee' => $config->calculateDeliveryFee($distanceKm),
        ];
    }

    public function getOrderTracking(Order $order): array
    {
        $timeline = [];

        $timeline[] = ['status' => 'created', 'label' => 'Đặt hàng', 'at' => $order->created_at?->toIso8601String(), 'done' => true];
        $timeline[] = ['status' => 'confirmed', 'label' => 'Xác nhận', 'at' => $order->confirmed_at?->toIso8601String(), 'done' => $order->confirmed_at !== null];
        $timeline[] = ['status' => 'preparing', 'label' => 'Đang chuẩn bị', 'at' => null, 'done' => in_array($order->status, ['preparing', 'completed'])];

        if ($order->channel === 'delivery') {
            $delivery = $order->deliveryDetail;
            $timeline[] = ['status' => 'delivering', 'label' => 'Đang giao', 'at' => null, 'done' => $delivery && in_array($delivery->delivery_status, ['in_transit', 'delivered'])];
        }

        $timeline[] = ['status' => 'completed', 'label' => 'Hoàn thành', 'at' => $order->completed_at?->toIso8601String(), 'done' => $order->status === 'completed'];

        return [
            'order' => [
                'order_number' => $order->order_number,
                'channel' => $order->channel,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount' => (float) $order->total_amount,
                'created_at' => $order->created_at?->toIso8601String(),
            ],
            'items' => $order->items->map(fn ($i) => [
                'name' => $i->product_name ?? $i->product?->name,
                'quantity' => (int) $i->quantity,
                'price' => (float) $i->unit_price,
            ]),
            'timeline' => $timeline,
        ];
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
