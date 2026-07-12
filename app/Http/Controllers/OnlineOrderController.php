<?php

namespace App\Http\Controllers;

use App\Models\OnlineStoreConfig;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\OnlineOrderService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Concerns\VerifiesTurnstile;
use App\Concerns\GeneratesSignedCaptcha;

class OnlineOrderController extends Controller
{
    use VerifiesTurnstile, GeneratesSignedCaptcha;
    public function __construct(
        private OnlineOrderService $orderService,
        private PaymentGatewayService $paymentService,
    ) {}

    public function storefront(string $slug): Response
    {
        $config = OnlineStoreConfig::withoutGlobalScopes()->where('slug', $slug)->firstOrFail();

        abort_unless($config->is_active, 404);

        $restaurant = Restaurant::find($config->restaurant_id);
        $menu = $this->orderService->getPublicMenu($restaurant);
        $gateways = $this->paymentService->getAvailableGateways($config->restaurant_id);

        $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: \App\Models\SystemSetting::get('turnstile_site_key');
        $captchaQuestion = null;
        $captchaToken = null;
        if (!$turnstileSiteKey) {
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
            $captchaToken = $this->generateCaptchaToken((string)$answer);
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
            ],
            'categories' => $menu['categories'],
            'products' => $menu['products'],
            'gateways' => $gateways,
            'tracking' => app(\App\Services\Integrations\TrackingService::class)->storefrontConfig($config->restaurant_id),
            'turnstileSiteKey' => $turnstileSiteKey,
            'captchaQuestion' => $captchaQuestion,
            'captchaToken' => $captchaToken,
        ]);
    }

    public function getMenu(string $slug): JsonResponse
    {
        $config = OnlineStoreConfig::withoutGlobalScopes()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $restaurant = Restaurant::find($config->restaurant_id);
        $menu = $this->orderService->getPublicMenu($restaurant);

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
        if (!app()->runningUnitTests()) {
            $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: \App\Models\SystemSetting::get('turnstile_site_key');
            if ($turnstileSiteKey) {
                $token = $request->input('cf-turnstile-response');
                if (!$token || !$this->verifyTurnstile($token)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Vui lòng hoàn thành xác minh bảo mật Cloudflare Turnstile.'
                    ], 422);
                }
            } else {
                $captchaAnswer = $request->input('captcha_answer');
                $captchaToken = $request->input('captcha_token');
                if (!$this->verifyCaptchaToken($captchaToken, $captchaAnswer)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Câu trả lời xác minh bảo mật không chính xác hoặc đã hết hạn.'
                    ], 422);
                }
            }
        }

        $config = OnlineStoreConfig::withoutGlobalScopes()->where('slug', $slug)->where('is_active', true)->firstOrFail();

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
        ]);

        try {
            $order = $this->orderService->createOnlineOrder($data, $config);

            $paymentUrl = null;
            if ($data['payment_method'] !== 'cod') {
                $returnUrl = url('/order/payment/return');
                $paymentUrl = $this->paymentService->createPayment($order, $data['payment_method'], $returnUrl);
            }

            $trackUrl = url("/order/track/{$order->order_number}");

            if ($order->customer_email) {
                try {
                    \Illuminate\Support\Facades\Mail::raw(
                        "Đơn hàng #{$order->order_number} đã được tạo.\n\nTheo dõi đơn hàng: {$trackUrl}\n\nCảm ơn bạn đã đặt hàng!",
                        fn ($msg) => $msg->to($order->customer_email)->subject("Đơn hàng #{$order->order_number} — Theo dõi đơn hàng")
                    );
                } catch (\Throwable) {}
            }

            return response()->json([
                'success' => true,
                'order_number' => $order->order_number,
                'total_amount' => (float) $order->total_amount,
                'payment_url' => $paymentUrl,
                'track_url' => $trackUrl,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
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
