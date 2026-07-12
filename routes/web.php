<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\PaymentWebhookController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\EmployeeManagementController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
// Chatbot API — public (rate limited)
use Illuminate\Support\Facades\Route;

use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use App\Http\Middleware\ValidateForgotPasswordCaptcha;

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware(['web', 'guest', ValidateForgotPasswordCaptcha::class])
    ->name('password.email');

Route::middleware('throttle:30,1')->group(function () {
    Route::post('api/chatbot/message', [ChatbotController::class, 'message'])->name('chatbot.message');
    Route::get('api/chatbot/suggestions', [ChatbotController::class, 'suggestions'])->name('chatbot.suggestions');
    Route::post('api/chatbot/feedback', [ChatbotController::class, 'feedback'])->name('chatbot.feedback');
});

Route::get('sitemap.xml', function () {
    $posts = \App\Models\NewsPost::published()->latest('published_at')->take(100)->get();
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    $xml .= '<url><loc>' . url('/') . '</loc><changefreq>daily</changefreq><priority>1.0</priority></url>';
    $xml .= '<url><loc>' . url('/tin-tuc') . '</loc><changefreq>daily</changefreq><priority>0.8</priority></url>';
    foreach ($posts as $p) {
        $xml .= '<url><loc>' . url("/tin-tuc/{$p->slug}") . '</loc><lastmod>' . $p->updated_at->toW3cString() . '</lastmod><priority>0.6</priority></url>';
    }
    $xml .= '</urlset>';
    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

Route::get('api/admin/tenant-health', function (\Illuminate\Http\Request $request) {
    if (!$request->user()?->hasRole('super_admin')) {
        abort(403);
    }
    $restaurants = \App\Models\Restaurant::with('plan:id,code,name')
        ->select('id', 'name', 'plan_id', 'status', 'subscription_ends_at', 'trial_ends_at')
        ->latest('id')
        ->take(50)
        ->get()
        ->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'plan' => $r->plan?->name ?? 'N/A',
            'status' => $r->status,
            'expires' => $r->subscription_ends_at?->toDateString(),
            'orders_30d' => \App\Models\Order::where('restaurant_id', $r->id)->where('created_at', '>=', now()->subDays(30))->count(),
        ]);
    return response()->json(['tenants' => $restaurants, 'total' => \App\Models\Restaurant::count()]);
})->middleware(['auth', 'verified'])->name('api.admin.tenant-health');

Route::get('api/docs', function () {
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/'))
        ->map(fn ($r) => [
            'method' => implode('|', $r->methods()),
            'uri' => '/' . $r->uri(),
            'name' => $r->getName(),
            'middleware' => implode(', ', $r->middleware()),
        ])
        ->values();
    return response()->json([
        'app' => 'Aventura API',
        'version' => '1.0.0',
        'endpoints_count' => $routes->count(),
        'endpoints' => $routes,
    ]);
})->name('api.docs');

Route::get('api/health', function () {
    $checks = [
        'app' => true,
        'database' => false,
        'cache' => false,
        'queue' => config('queue.default'),
        'time' => now()->toIso8601String(),
        'version' => app()->version(),
    ];
    try { \Illuminate\Support\Facades\DB::select('SELECT 1'); $checks['database'] = true; } catch (\Throwable) {}
    try { \Illuminate\Support\Facades\Cache::put('health_check', true, 5); $checks['cache'] = \Illuminate\Support\Facades\Cache::get('health_check') === true; } catch (\Throwable) {}
    $healthy = $checks['app'] && $checks['database'] && $checks['cache'];
    return response()->json($checks, $healthy ? 200 : 503);
})->name('health');

Route::post('webhooks/payments', PaymentWebhookController::class)->name('billing.webhook');
Route::post('api/webhooks/payments/vietqr', \App\Http\Controllers\OrderPaymentWebhookController::class)->name('api.webhooks.payments.vietqr');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('billing/checkout', CheckoutController::class)->name('billing.checkout');
    Route::get('billing/history', [CheckoutController::class, 'history'])->name('billing.history');
    Route::get('billing/pay/{code}', [CheckoutController::class, 'payPage'])->name('billing.pay');
    Route::get('api/billing/check/{code}', [CheckoutController::class, 'checkStatus']);
    Route::post('api/billing/apply-coupon', [CheckoutController::class, 'applyCoupon'])->name('billing.apply-coupon');

    // Multi-tenant restaurant selector
    Route::get('choose-restaurant', [\App\Http\Controllers\RestaurantChooserController::class, 'chooseRestaurantPage'])->name('choose-restaurant');
    Route::post('choose-restaurant', [\App\Http\Controllers\RestaurantChooserController::class, 'chooseRestaurant'])->name('choose-restaurant.select');

    // Impersonation Stop
    Route::post('impersonate/stop', [\App\Http\Controllers\SuperAdmin\ImpersonateController::class, 'stop'])->name('impersonate.stop');
});

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');

    Route::post('two-factor-challenge/send-email-code', [\App\Http\Controllers\Auth\TwoFactorEmailCodeController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('two-factor.email-code.send');

    // Trả về QR code cho user đang ở trang two-factor-challenge (đã auth bằng password)
    Route::get('two-factor-challenge/setup-qr', \App\Http\Controllers\Auth\TwoFactorChallengeQrController::class)
        ->middleware('throttle:10,1')
        ->name('two-factor.challenge.setup-qr');
});

Route::middleware(['auth'])->group(function () {
    Route::post('email/verify-code', [\App\Http\Controllers\Auth\VerifyEmailCodeController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.verify-code');
});

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tin-tuc', [NewsController::class, 'index'])->name('news.index');
Route::get('/tin-tuc/{slug}', [NewsController::class, 'show'])->name('news.show');

require __DIR__.'/tenant.php';


// Biểu mẫu gửi đánh giá công khai (Dành cho Khách hàng quét mã QR, giới hạn 15 request/phút)
Route::middleware('throttle:15,1')->group(function () {
    Route::get('feedback/new', [FeedbackController::class, 'publicCreate'])->name('feedback.new');
    Route::post('feedback', [FeedbackController::class, 'store'])->name('feedback.store');
});

// Kênh Đặt Hàng Online (Digital Ordering Hub)
Route::middleware('throttle:60,1')->group(function () {
    Route::get('order/payment/return', [\App\Http\Controllers\OnlineOrderController::class, 'paymentReturn'])->name('online.payment.return');
    Route::get('order/track/{orderNumber}', [\App\Http\Controllers\OnlineOrderController::class, 'trackOrder'])->name('online.track');
    Route::get('order/{slug}', [\App\Http\Controllers\OnlineOrderController::class, 'storefront'])->name('online.storefront');
    Route::get('api/online/{slug}/menu', [\App\Http\Controllers\OnlineOrderController::class, 'getMenu'])->name('online.menu');
    Route::post('api/online/{slug}/delivery-fee', [\App\Http\Controllers\OnlineOrderController::class, 'calculateFee'])->name('online.delivery-fee');
    Route::post('api/online/{slug}/checkout', [\App\Http\Controllers\OnlineOrderController::class, 'checkout'])->name('online.checkout');
    Route::get('api/online/order/{orderNumber}/status', [\App\Http\Controllers\OnlineOrderController::class, 'orderStatus'])->name('online.order-status');
});

// Payment gateway webhooks (CSRF exempt via ValidateCsrfTokens)
// VNPay IPN gửi bằng GET theo tài liệu chính thức, không phải POST như Momo/ZaloPay.
Route::match(['get', 'post'], 'api/webhooks/payments/vnpay', [\App\Http\Controllers\OnlinePaymentWebhookController::class, 'handleVnpay'])->name('webhooks.vnpay');
Route::post('api/webhooks/payments/momo', [\App\Http\Controllers\OnlinePaymentWebhookController::class, 'handleMomo'])->name('webhooks.momo');
Route::post('api/webhooks/payments/zalopay', [\App\Http\Controllers\OnlinePaymentWebhookController::class, 'handleZalopay'])->name('webhooks.zalopay');

// Webhook nhận đơn từ nền tảng giao đồ ăn (GrabFood/ShopeeFood) — verify HMAC trong controller
Route::post('api/webhooks/delivery/{provider}/{restaurantId}', [\App\Http\Controllers\DeliveryPlatformWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.delivery');

// API cho app máy POS: ghép nối thiết bị + heartbeat
Route::post('api/pos/pair', [\App\Http\Controllers\PosDeviceController::class, 'pair'])->middleware('throttle:10,1')->name('api.pos.pair');
Route::post('api/pos/heartbeat', [\App\Http\Controllers\PosDeviceController::class, 'heartbeat'])->middleware('throttle:120,1')->name('api.pos.heartbeat');

// REST API công khai v1 (chỉ đọc) — xác thực bằng header X-Api-Key
Route::prefix('api/v1')->middleware(['auth.apikey', 'throttle:120,1'])->name('api.v1.')->group(function () {
    Route::get('products', [\App\Http\Controllers\Api\V1\PublicApiController::class, 'products'])->name('products');
    Route::get('orders', [\App\Http\Controllers\Api\V1\PublicApiController::class, 'orders'])->name('orders');
    Route::get('reservations', [\App\Http\Controllers\Api\V1\PublicApiController::class, 'reservations'])->name('reservations');
});

// Chức năng QR-Ordering dành cho khách hàng tại bàn
Route::middleware('throttle:60,1')->group(function () {
    Route::post('customer/order/call-staff/{restaurant}', [\App\Http\Controllers\Customer\QROrderController::class, 'callStaff'])->name('customer.qr-order.call-staff');
    Route::post('customer/order/payment-request/{restaurant}', [\App\Http\Controllers\Customer\QROrderController::class, 'paymentRequest'])->name('customer.qr-order.payment-request');
    Route::post('customer/order/feedback/{restaurant}', [\App\Http\Controllers\Customer\QROrderController::class, 'submitFeedback'])->name('customer.qr-order.feedback');
    Route::get('customer/order/{restaurant}/{token}', [\App\Http\Controllers\Customer\QROrderController::class, 'showMenu'])->name('customer.qr-order.show');
    Route::post('customer/order/{restaurant}/{token}', [\App\Http\Controllers\Customer\QROrderController::class, 'submitOrder'])->middleware('throttle:qr_order_submit')->name('customer.qr-order.submit');
    Route::post('api/customer/track-behavior', [\App\Http\Controllers\CdpController::class, 'trackBehavior'])->name('api.customer.track-behavior');
    Route::get('api/orders/{order}/payment-qr', [\App\Http\Controllers\OrderPaymentQrController::class, 'paymentQr'])->name('api.orders.payment-qr');
    Route::get('api/orders/{order}/payment-status', [\App\Http\Controllers\OrderPaymentQrController::class, 'paymentStatus'])->name('api.orders.payment-status');

    // Public đặt bàn trước (khách quét QR đặt bàn)
    Route::post('r/{restaurantId}/reservations', [\App\Http\Controllers\TableReservationController::class, 'publicStore'])->name('reservations.public-store');

    // Member Dashboard, Loyalty & Reservation Portal Routes
    // Bảo mật: KHÔNG phát token công khai. Khách yêu cầu link → gửi link đã ký qua
    // SMS/Zalo tới chính SĐT đó (chỉ chủ SĐT nhận được).
    Route::post('customer/portal/request-link/{restaurant}', [\App\Http\Controllers\Customer\CustomerPortalController::class, 'requestAccessLink'])
        ->middleware('throttle:6,1')->name('customer.portal.request-link');
    Route::get('customer/portal/dashboard/{restaurant}/{phone}', [\App\Http\Controllers\Customer\CustomerPortalController::class, 'showDashboard'])->name('customer.portal.dashboard');
    Route::post('customer/portal/redeem/{restaurant}/{phone}', [\App\Http\Controllers\Customer\CustomerPortalController::class, 'redeemReward'])->name('customer.portal.redeem');
    Route::post('customer/portal/reserve/{restaurant}', [\App\Http\Controllers\Customer\CustomerPortalController::class, 'createReservation'])->name('customer.portal.reserve');

    // Customer Coupon Wallet
    Route::get('customer/coupons/{restaurant}/{phone}', [\App\Http\Controllers\Customer\CouponWalletController::class, 'showWallet'])->name('customer.coupons.wallet');
    Route::post('customer/coupons/{restaurant}/{phone}/claim', [\App\Http\Controllers\Customer\CouponWalletController::class, 'claimCoupon'])->name('customer.coupons.claim');
});

// Xác thực lời mời nhận việc của nhân viên mới
Route::get('employees/verify/{user}', [EmployeeManagementController::class, 'verifyEmployee'])
    ->name('employees.verify')
    ->middleware('signed');

// Public System Status Page
Route::get('status', [\App\Http\Controllers\PublicStatusController::class, 'index'])->name('public.status');
Route::get('api/status-data', [\App\Http\Controllers\PublicStatusController::class, 'getStatusData'])->name('public.status.data');

require __DIR__.'/settings.php';

