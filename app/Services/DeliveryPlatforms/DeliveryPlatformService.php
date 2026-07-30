<?php

namespace App\Services\DeliveryPlatforms;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformOrder;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantIntegration;
use App\Services\Integrations\WebhookDispatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryPlatformService
{
    private array $drivers = [
        'grabfood' => GrabFoodDriver::class,
        'shopeefood' => ShopeeFoodDriver::class,
    ];

    public function __construct(private WebhookDispatchService $webhooks) {}

    public function resolveDriver(string $provider): DeliveryPlatformDriver
    {
        $class = $this->drivers[$provider] ?? null;

        if (! $class) {
            throw new \InvalidArgumentException("Nền tảng giao hàng '{$provider}' không được hỗ trợ.");
        }

        return app($class);
    }

    public function supportedProviders(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Nhận đơn từ nền tảng: idempotent theo (provider, platform_order_id).
     * Item được map với Product theo tên (không phân biệt hoa thường);
     * item không khớp vẫn được ghi nhận qua notes để nhân viên xử lý tay.
     */
    public function ingestOrder(int $restaurantId, string $provider, array $payload): PlatformOrder
    {
        $driver = $this->resolveDriver($provider);
        $parsed = $driver->parseOrder($payload);

        if ($parsed['platform_order_id'] === '' || empty($parsed['items'])) {
            throw new \InvalidArgumentException('Payload đơn hàng không hợp lệ: thiếu mã đơn hoặc danh sách món.');
        }

        // Idempotent — nền tảng có thể gửi lại webhook
        $existing = PlatformOrder::withoutGlobalScopes()
            ->where('provider', $provider)
            ->where('platform_order_id', $parsed['platform_order_id'])
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return $this->createFromParsedPayload($restaurantId, $provider, $payload, $parsed, $driver);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // 2 webhook trùng đến đồng thời: cả 2 vượt qua check $existing ở trên,
            // request thua cuộc vấp unique(provider, platform_order_id) — transaction
            // của nó rollback sạch (không rác Order/OrderItem), trả về đơn đã tạo
            // thay vì 500 để nền tảng không retry vô ích.
            return PlatformOrder::withoutGlobalScopes()
                ->where('provider', $provider)
                ->where('platform_order_id', $parsed['platform_order_id'])
                ->firstOrFail();
        }
    }

    /** @param array{platform_order_id: string, items: array, customer_phone: ?string, customer_name: ?string, note: ?string, total: float} $parsed */
    private function createFromParsedPayload(int $restaurantId, string $provider, array $payload, array $parsed, DeliveryPlatformDriver $driver): PlatformOrder
    {
        return DB::transaction(function () use ($restaurantId, $provider, $payload, $parsed, $driver) {
            $restaurant = Restaurant::find($restaurantId);

            $products = Product::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->get(['id', 'name', 'price']);

            $subtotal = 0;
            $lines = [];
            $unmatchedNotes = [];

            foreach ($parsed['items'] as $item) {
                $product = $products->first(
                    fn ($p) => mb_strtolower(trim($p->name)) === mb_strtolower(trim($item['name']))
                );

                $price = $item['price'] > 0 ? $item['price'] : (float) ($product?->price ?? 0);
                $lineTotal = $price * $item['quantity'];
                $subtotal += $lineTotal;

                if ($product) {
                    $lines[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $price,
                        'line_total' => $lineTotal,
                        'notes' => $item['note'],
                    ];
                } else {
                    $unmatchedNotes[] = "[{$driver->getDisplayName()}] {$item['quantity']}x {$item['name']} (".number_format($price).'đ) — món chưa có trong menu';
                }
            }

            $customer = null;
            if (! empty($parsed['customer_phone'])) {
                $customer = Customer::withoutGlobalScopes()->firstOrCreate(
                    ['restaurant_id' => $restaurantId, 'phone' => $parsed['customer_phone']],
                    ['full_name' => $parsed['customer_name']]
                );
            }

            $note = trim(implode("\n", array_filter([
                $parsed['note'],
                ...$unmatchedNotes,
            ])));

            $order = Order::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $restaurant?->branches()->first()?->id,
                'customer_id' => $customer?->id,
                'order_number' => strtoupper(substr($provider, 0, 2)).strtoupper(Str::random(8)),
                'channel' => 'delivery',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'service_charge' => 0,
                'total_amount' => $parsed['total'] > 0 ? $parsed['total'] : $subtotal,
                'note' => $note !== '' ? $note : "Đơn {$driver->getDisplayName()} #{$parsed['platform_order_id']}",
                'online_notes' => "Nguồn: {$driver->getDisplayName()} — mã đơn {$parsed['platform_order_id']}",
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'restaurant_id' => $restaurantId,
                    'order_id' => $order->id,
                    'status' => 'pending',
                    ...$line,
                ]);
            }

            $platformOrder = PlatformOrder::create([
                'restaurant_id' => $restaurantId,
                'provider' => $provider,
                'platform_order_id' => $parsed['platform_order_id'],
                'order_id' => $order->id,
                'raw_payload' => $payload,
                'status' => 'received',
            ]);

            $this->webhooks->dispatch($restaurantId, 'order.created', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'channel' => $provider,
                'total_amount' => (float) $order->total_amount,
                'platform_order_id' => $parsed['platform_order_id'],
            ]);

            return $platformOrder;
        });
    }

    /** Đẩy trạng thái đơn về nền tảng gốc (gọi khi nhà hàng xác nhận/hủy đơn). */
    public function pushStatus(PlatformOrder $platformOrder, string $status): bool
    {
        $integration = RestaurantIntegration::findEnabled($platformOrder->restaurant_id, $platformOrder->provider);

        if (! $integration) {
            return false;
        }

        $ok = $this->resolveDriver($platformOrder->provider)
            ->pushOrderStatus($integration, $platformOrder->platform_order_id, $status);

        if ($ok) {
            $platformOrder->update(['status' => $status]);
        }

        return $ok;
    }

    /**
     * Sinh payload đơn thử nghiệm từ menu thật của nhà hàng — demo luồng nhận đơn
     * mà không cần tài khoản đối tác Grab/Shopee thật.
     */
    public function buildSimulatedPayload(int $restaurantId, string $provider): array
    {
        $products = Product::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit(random_int(1, 3))
            ->get(['name', 'price']);

        if ($products->isEmpty()) {
            throw new \RuntimeException('Nhà hàng chưa có món nào để tạo đơn thử nghiệm.');
        }

        $total = 0;
        $grabItems = [];
        $shopeeItems = [];

        foreach ($products as $product) {
            $qty = random_int(1, 2);
            $total += (float) $product->price * $qty;

            $grabItems[] = ['name' => $product->name, 'quantity' => $qty, 'price' => (float) $product->price];
            $shopeeItems[] = ['item_name' => $product->name, 'quantity' => $qty, 'item_price' => (float) $product->price];
        }

        if ($provider === 'grabfood') {
            return [
                'orderID' => 'GF-SIM-'.strtoupper(Str::random(8)),
                'eater' => ['name' => 'Khách mô phỏng Grab', 'mobileNumber' => '09'.random_int(10000000, 99999999)],
                'items' => $grabItems,
                'orderTotal' => $total,
                'specialInstructions' => 'Đơn thử nghiệm từ trang Tích hợp',
            ];
        }

        return [
            'order' => [
                'order_sn' => 'SF-SIM-'.strtoupper(Str::random(8)),
                'buyer' => ['name' => 'Khách mô phỏng Shopee', 'phone' => '09'.random_int(10000000, 99999999)],
                'item_list' => $shopeeItems,
                'total_amount' => $total,
                'remark' => 'Đơn thử nghiệm từ trang Tích hợp',
            ],
        ];
    }
}
