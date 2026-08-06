<?php

namespace App\Http\Controllers;

use App\Concerns\GeneratesSignedCaptcha;
use App\Concerns\VerifiesTurnstile;
use App\Models\OnlineStoreConfig;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\SystemSetting;
use App\Services\Integrations\TrackingService;
use App\Services\OnlineOrderService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OnlineOrderController extends Controller
{
    use GeneratesSignedCaptcha, VerifiesTurnstile;

    public function __construct(
        private OnlineOrderService $orderService,
        private PaymentGatewayService $paymentService,
    ) {}

    public function storefront(string $slug): Response
    {
        $config = OnlineStoreConfig::withoutGlobalScopes()->where('slug', $slug)->firstOrFail();

        abort_unless($config->is_active, 404);

        $restaurant = Restaurant::find($config->restaurant_id);
        $menu = $this->orderService->getPublicMenu($restaurant, $config->branch_id);
        $gateways = $this->paymentService->getAvailableGateways($config->restaurant_id);

        $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: SystemSetting::get('turnstile_site_key');
        $captchaQuestion = null;
        $captchaToken = null;
        if (! $turnstileSiteKey) {
            $num1 = rand(1, 10);
            $num2 = rand(1, 10);
            $operator = rand(0, 1) ? '+' : '-';
            if ($operator === '-') {
                if ($num1 < $num2) {
                    $temp = $num1;
                    $num1 = $num2;
                    $num2 = $temp;
                }
                $answer = $num1 - $num2;
            } else {
                $answer = $num1 + $num2;
            }
            $captchaToken = $this->generateCaptchaToken((string) $answer);
            $captchaQuestion = "{$num1} {$operator} {$num2} = ?";
        }

        return Inertia::render('online-order/Storefront', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'address' => $restaurant->address,
                'logo_url' => $restaurant->logo_url,
                'phone' => $restaurant->phone,
            ],
            'config' => [
                'slug' => $config->slug,
                'banner_url' => $config->banner_url,
                'description' => $config->description,
                'min_order_amount' => (float) $config->min_order_amount,
                'enable_takeaway' => $config->enable_takeaway,
                'enable_delivery' => $config->enable_delivery,
                'enable_preorder' => $config->enable_preorder,
                'is_open' => $config->isOpen(),
                'operating_hours' => $config->operating_hours,
                'branch_id' => $config->branch_id,
                'accepted_payments' => $config->accepted_payments,
            ],
            'categories' => $menu['categories'],
            'products' => $menu['products'],
            'gateways' => $gateways,
            'tracking' => app(TrackingService::class)->storefrontConfig($config->restaurant_id),
            'turnstileSiteKey' => $turnstileSiteKey,
            'captchaQuestion' => $captchaQuestion,
            'captchaToken' => $captchaToken,
        ]);
    }

    public function getMenu(string $slug): JsonResponse
    {
        $config = OnlineStoreConfig::withoutGlobalScopes()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $restaurant = Restaurant::find($config->restaurant_id);
        $menu = $this->orderService->getPublicMenu($restaurant, $config->branch_id);

        return response()->json($menu);
    }

    public function calculateFee(Request $request, string $slug): JsonResponse
    {
        $config = OnlineStoreConfig::withoutGlobalScopes()->where('slug', $slug)->firstOrFail();
        $restaurant = Restaurant::find($config->restaurant_id);

        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $result = $this->orderService->calculateDeliveryFee(
            (float) $data['latitude'],
            (float) $data['longitude'],
            $restaurant
        );

        return response()->json($result);
    }

    public function checkout(Request $request, string $slug): JsonResponse
    {
        if (! app()->runningUnitTests()) {
            $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: SystemSetting::get('turnstile_site_key');
            if ($turnstileSiteKey) {
                $token = $request->input('cf-turnstile-response');
                if (! $token || ! $this->verifyTurnstile($token)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Vui lòng hoàn thành xác minh bảo mật Cloudflare Turnstile.',
                    ], 422);
                }
            } else {
                $captchaAnswer = $request->input('captcha_answer');
                $captchaToken = $request->input('captcha_token');
                if (! $this->verifyCaptchaToken($captchaToken, $captchaAnswer)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Câu trả lời xác minh bảo mật không chính xác hoặc đã hết hạn.',
                    ], 422);
                }
            }
        }

        $config = OnlineStoreConfig::withoutGlobalScopes()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        $availablePaymentMethods = collect($this->paymentService->getAvailableGateways($config->restaurant_id))
            ->pluck('key');
        if (empty($config->accepted_payments) || in_array('cod', $config->accepted_payments, true)) {
            $availablePaymentMethods->push('cod');
        }
        $availablePaymentMethods = $availablePaymentMethods->unique()->values()->all();

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'channel' => ['required', 'in:takeaway,delivery'],
            'address' => ['nullable', 'required_if:channel,delivery', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'string', 'in:bank_transfer,vnpay,momo,zalopay,cod'],
            'note' => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'client_request_id' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            if (! in_array($data['payment_method'], $availablePaymentMethods, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phương thức thanh toán này hiện chưa được nhà hàng chấp nhận.',
                ], 422);
            }

            if ($data['channel'] === 'takeaway' && ! $config->enable_takeaway) {
                return response()->json(['success' => false, 'message' => 'Nhà hàng hiện không nhận đơn mang về.'], 422);
            }

            if ($data['channel'] === 'delivery' && ! $config->enable_delivery) {
                return response()->json(['success' => false, 'message' => 'Nhà hàng hiện không nhận đơn giao hàng.'], 422);
            }

            if (! empty($data['scheduled_at']) && ! $config->enable_preorder) {
                return response()->json(['success' => false, 'message' => 'Nhà hàng hiện không bật đặt trước.'], 422);
            }

            if (! $config->isOpen() && (empty($data['scheduled_at']) || ! $config->enable_preorder)) {
                return response()->json(['success' => false, 'message' => 'Cửa hàng đang ngoài giờ hoạt động. Vui lòng chọn thời gian đặt trước.'], 422);
            }

            if ($request->boolean('conflict_check')) {
                $conflicts = [];
                foreach ($data['items'] as $item) {
                    $product = Product::withoutGlobalScopes()
                        ->where('restaurant_id', $config->restaurant_id)
                        ->where(function ($q) use ($config): void {
                            if ($config->branch_id !== null) {
                                $q->whereNull('branch_id')->orWhere('branch_id', $config->branch_id);
                            }
                        })
                        ->whereKey($item['product_id'])
                        ->first();
                    if ($product && $product->out_of_stock_until?->isFuture()) {
                        $conflicts[] = [
                            'product_name' => $product->name,
                            'requested' => $item['quantity'],
                            'available_stock' => 0,
                        ];
                    }
                }
                if (! empty($conflicts)) {
                    return response()->json([
                        'message' => 'Tồn kho không đủ khi đồng bộ đơn hàng ngoại tuyến.',
                        'conflicts' => $conflicts,
                    ], 409);
                }
            }

            $order = $this->orderService->createOnlineOrder($data, $config);

            $paymentUrl = null;
            if ($data['payment_method'] !== 'cod') {
                try {
                    $returnUrl = url('/order/payment/return');
                    $paymentUrl = $this->paymentService->createPayment($order, $data['payment_method'], $returnUrl);
                } catch (\Throwable $e) {
                    Log::error('online checkout: payment URL creation failed', [
                        'order_id' => $order->id,
                        'payment_method' => $data['payment_method'],
                        'error' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Không thể khởi tạo thanh toán lúc này. Đơn đã được giữ lại, vui lòng thử lại.',
                        'order_number' => $order->order_number,
                        'track_url' => url("/order/track/{$order->order_number}"),
                    ], 503);
                }
            }

            $trackUrl = url("/order/track/{$order->order_number}");

            if ($order->customer_email) {
                try {
                    Mail::raw(
                        "Đơn hàng #{$order->order_number} đã được tạo.\n\nTheo dõi đơn hàng: {$trackUrl}\n\nCảm ơn bạn đã đặt hàng!",
                        fn ($msg) => $msg->to($order->customer_email)->subject("Đơn hàng #{$order->order_number} — Theo dõi đơn hàng")
                    );
                } catch (\Throwable $e) {
                    Log::warning('online checkout: confirmation email failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'order_number' => $order->order_number,
                'total_amount' => (float) $order->total_amount,
                'payment_url' => $paymentUrl,
                'track_url' => $trackUrl,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('online checkout: unexpected failure', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo đơn lúc này. Vui lòng thử lại sau.',
            ], 500);
        }
    }

    public function paymentReturn(Request $request): Response
    {
        return Inertia::render('online-order/OrderTracking', [
            'orderNumber' => $request->query('order_number', ''),
        ]);
    }

    public function trackOrder(string $orderNumber): Response
    {
        $order = Order::withoutGlobalScopes()
            ->where('order_number', $orderNumber)
            ->with('items.product')
            ->firstOrFail();

        $tracking = $this->orderService->getOrderTracking($order);

        return Inertia::render('online-order/OrderTracking', [
            'orderNumber' => $orderNumber,
            'tracking' => $tracking,
        ]);
    }

    public function orderStatus(string $orderNumber): JsonResponse
    {
        $order = Order::withoutGlobalScopes()
            ->where('order_number', $orderNumber)
            ->with('items.product')
            ->firstOrFail();

        return response()->json($this->orderService->getOrderTracking($order));
    }
}
