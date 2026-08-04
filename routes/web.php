<?php

use App\Http\Controllers\Api\V1\PublicApiController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\TwoFactorChallengeQrController;
use App\Http\Controllers\Auth\TwoFactorEmailCodeController;
use App\Http\Controllers\Auth\VerifyEmailCodeController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\PaymentWebhookController;
use App\Http\Controllers\CdpController;
// Chatbot API — public (rate limited)
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\Customer\CouponWalletController;
use App\Http\Controllers\Customer\CustomerLoyaltyClaimController;
use App\Http\Controllers\Customer\CustomerPortalController;
use App\Http\Controllers\Customer\QROrderController;
use App\Http\Controllers\DeliveryPlatformWebhookController;
use App\Http\Controllers\DemoBookingController;
use App\Http\Controllers\EmployeeManagementController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\OnlineOrderController;
use App\Http\Controllers\OnlinePaymentWebhookController;
use App\Http\Controllers\OrderPaymentQrController;
use App\Http\Controllers\OrderPaymentWebhookController;
use App\Http\Controllers\PlatformFeedbackController;
use App\Http\Controllers\PosDeviceController;
use App\Http\Controllers\PublicStatusController;
use App\Http\Controllers\RestaurantChooserController;
use App\Http\Controllers\SuperAdmin\ImpersonateController;
use App\Http\Controllers\TableReservationController;
use App\Http\Middleware\ValidateForgotPasswordCaptcha;
use App\Models\NewsPost;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware(['web', 'guest', ValidateForgotPasswordCaptcha::class])
    ->name('password.email');

Route::middleware('throttle:30,1')->group(function () {
    Route::post('api/chatbot/message', [ChatbotController::class, 'message'])->name('chatbot.message');
    Route::get('api/chatbot/suggestions', [ChatbotController::class, 'suggestions'])->name('chatbot.suggestions');
    Route::post('api/chatbot/feedback', [ChatbotController::class, 'feedback'])->name('chatbot.feedback');
});

Route::post('api/demo-booking', [DemoBookingController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('api.demo-booking.store');

Route::get('sitemap.xml', function () {
    $posts = NewsPost::published()->latest('published_at')->take(100)->get();
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    $xml .= '<url><loc>'.url('/').'</loc><changefreq>daily</changefreq><priority>1.0</priority></url>';
    $xml .= '<url><loc>'.url('/tin-tuc').'</loc><changefreq>daily</changefreq><priority>0.8</priority></url>';
    foreach ($posts as $p) {
        $xml .= '<url><loc>'.url("/tin-tuc/{$p->slug}").'</loc><lastmod>'.$p->updated_at->toW3cString().'</lastmod><priority>0.6</priority></url>';
    }
    $xml .= '</urlset>';

    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

Route::get('api/admin/tenant-health', function (Request $request) {
    if (! $request->user()?->isSuperAdmin()
        || (! $request->user()->hasRole('super_admin') && ! $request->user()->hasPermissionTo('superadmin.tenant.view'))) {
        abort(403);
    }
    $restaurants = Restaurant::with('plan:id,code,name')
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
            'orders_30d' => Order::where('restaurant_id', $r->id)->where('created_at', '>=', now()->subDays(30))->count(),
        ]);

    return response()->json(['tenants' => $restaurants, 'total' => Restaurant::count()]);
})->middleware(['auth', 'verified'])->name('api.admin.tenant-health');

Route::get('api/docs', function () {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/'))
        ->map(fn ($r) => [
            'method' => implode('|', $r->methods()),
            'uri' => '/'.$r->uri(),
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
    try {
        DB::select('SELECT 1');
        $checks['database'] = true;
    } catch (Throwable) {
    }
    try {
        Cache::put('health_check', true, 5);
        $checks['cache'] = Cache::get('health_check') === true;
    } catch (Throwable) {
    }
    $healthy = $checks['app'] && $checks['database'] && $checks['cache'];

    return response()->json($checks, $healthy ? 200 : 503);
})->name('health');

Route::post('webhooks/payments', PaymentWebhookController::class)->name('billing.webhook');
Route::post('api/webhooks/payments/vietqr', OrderPaymentWebhookController::class)->name('api.webhooks.payments.vietqr');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('billing/checkout', CheckoutController::class)->name('billing.checkout');
    Route::get('billing/history', [CheckoutController::class, 'history'])->name('billing.history');
    Route::get('billing/pay/{code}', [CheckoutController::class, 'payPage'])->name('billing.pay');
    Route::get('api/billing/check/{code}', [CheckoutController::class, 'checkStatus']);
    Route::post('api/billing/apply-coupon', [CheckoutController::class, 'applyCoupon'])->name('billing.apply-coupon');

    // Multi-tenant restaurant selector
    Route::get('choose-restaurant', [RestaurantChooserController::class, 'chooseRestaurantPage'])->name('choose-restaurant');
    Route::post('choose-restaurant', [RestaurantChooserController::class, 'chooseRestaurant'])->name('choose-restaurant.select');

    // Impersonation Stop & Request Write Mode
    Route::post('impersonate/stop', [ImpersonateController::class, 'stop'])->name('impersonate.stop');
    Route::post('impersonate/request-write', [ImpersonateController::class, 'requestWrite'])->name('impersonate.request_write');

    // Platform / SaaS Service Feedback submission for store owners & subscribers
    Route::post('platform-feedback', [PlatformFeedbackController::class, 'store'])->name('platform-feedback.store');
});

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');

    Route::post('two-factor-challenge/send-email-code', [TwoFactorEmailCodeController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('two-factor.email-code.send');

    // Trả về QR code cho user đang ở trang two-factor-challenge (đã auth bằng password)
    Route::get('two-factor-challenge/setup-qr', TwoFactorChallengeQrController::class)
        ->middleware('throttle:10,1')
        ->name('two-factor.challenge.setup-qr');
});

Route::middleware(['auth'])->group(function () {
    Route::post('email/verify-code', [VerifyEmailCodeController::class, 'store'])
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
    Route::get('order/payment/return', [OnlineOrderController::class, 'paymentReturn'])->name('online.payment.return');
    Route::get('order/track/{orderNumber}', [OnlineOrderController::class, 'trackOrder'])->name('online.track');
    Route::get('order/{slug}', [OnlineOrderController::class, 'storefront'])->name('online.storefront');
    Route::get('api/online/{slug}/menu', [OnlineOrderController::class, 'getMenu'])->name('online.menu');
    Route::post('api/online/{slug}/delivery-fee', [OnlineOrderController::class, 'calculateFee'])->name('online.delivery-fee');
    Route::post('api/online/{slug}/checkout', [OnlineOrderController::class, 'checkout'])->name('online.checkout');
    Route::get('api/online/order/{orderNumber}/status', [OnlineOrderController::class, 'orderStatus'])->name('online.order-status');
});

// Payment gateway webhooks (CSRF exempt via ValidateCsrfTokens)
// VNPay IPN gửi bằng GET theo tài liệu chính thức, không phải POST như Momo/ZaloPay.
Route::match(['get', 'post'], 'api/webhooks/payments/vnpay', [OnlinePaymentWebhookController::class, 'handleVnpay'])->name('webhooks.vnpay');
Route::post('api/webhooks/payments/momo', [OnlinePaymentWebhookController::class, 'handleMomo'])->name('webhooks.momo');
Route::post('api/webhooks/payments/zalopay', [OnlinePaymentWebhookController::class, 'handleZalopay'])->name('webhooks.zalopay');

// Webhook nhận đơn từ nền tảng giao đồ ăn (GrabFood/ShopeeFood) — verify HMAC trong controller
Route::post('api/webhooks/delivery/{provider}/{restaurantId}', [DeliveryPlatformWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.delivery');

// API cho app máy POS: ghép nối thiết bị + heartbeat
Route::post('api/pos/pair', [PosDeviceController::class, 'pair'])->middleware('throttle:10,1')->name('api.pos.pair');
Route::post('api/pos/heartbeat', [PosDeviceController::class, 'heartbeat'])->middleware('throttle:120,1')->name('api.pos.heartbeat');

// REST API công khai v1 (chỉ đọc) — xác thực bằng header X-Api-Key
Route::prefix('api/v1')->middleware(['auth.apikey', 'throttle:120,1'])->name('api.v1.')->group(function () {
    Route::get('products', [PublicApiController::class, 'products'])->name('products');
    Route::get('orders', [PublicApiController::class, 'orders'])->name('orders');
    Route::get('reservations', [PublicApiController::class, 'reservations'])->name('reservations');
});

// Chức năng QR-Ordering dành cho khách hàng tại bàn & Tích điểm hóa đơn
Route::middleware('throttle:60,1')->group(function () {
    Route::get('customer/loyalty/order-summary/{order}', [CustomerLoyaltyClaimController::class, 'getOrderPointsSummary'])->name('customer.loyalty.order-summary');
    Route::post('customer/loyalty/claim-points', [CustomerLoyaltyClaimController::class, 'claimPoints'])->name('customer.loyalty.claim-points');
    Route::post('customer/order/call-staff/{restaurant}', [QROrderController::class, 'callStaff'])->name('customer.qr-order.call-staff');
    Route::post('customer/order/payment-request/{restaurant}', [QROrderController::class, 'paymentRequest'])->name('customer.qr-order.payment-request');
    Route::post('customer/order/feedback/{restaurant}', [QROrderController::class, 'submitFeedback'])->name('customer.qr-order.feedback');
    Route::get('customer/order/{restaurant}/{token}', [QROrderController::class, 'showMenu'])->name('customer.qr-order.show');
    Route::post('customer/order/{restaurant}/{token}', [QROrderController::class, 'submitOrder'])->middleware('throttle:qr_order_submit')->name('customer.qr-order.submit');
    Route::post('api/customer/track-behavior', [CdpController::class, 'trackBehavior'])->name('api.customer.track-behavior');
    Route::get('api/orders/{order}/payment-qr', [OrderPaymentQrController::class, 'paymentQr'])->name('api.orders.payment-qr');
    Route::get('api/orders/{order}/payment-status', [OrderPaymentQrController::class, 'paymentStatus'])->name('api.orders.payment-status');

    // Public đặt bàn trước (khách quét QR đặt bàn)
    Route::post('r/{restaurantId}/reservations', [TableReservationController::class, 'publicStore'])->name('reservations.public-store');

    // Member Dashboard, Loyalty & Reservation Portal Routes
    // Bảo mật: KHÔNG phát token công khai. Khách yêu cầu link → gửi link đã ký qua
    // SMS/Zalo tới chính SĐT đó (chỉ chủ SĐT nhận được).
    Route::post('customer/portal/request-link/{restaurant}', [CustomerPortalController::class, 'requestAccessLink'])
        ->middleware('throttle:6,1')->name('customer.portal.request-link');
    Route::get('customer/portal/dashboard/{restaurant}/{phone}', [CustomerPortalController::class, 'showDashboard'])->name('customer.portal.dashboard');
    Route::post('customer/portal/redeem/{restaurant}/{phone}', [CustomerPortalController::class, 'redeemReward'])->name('customer.portal.redeem');
    Route::post('customer/portal/reserve/{restaurant}', [CustomerPortalController::class, 'createReservation'])->name('customer.portal.reserve');

    // Customer Coupon Wallet
    Route::get('customer/coupons/{restaurant}/{phone}', [CouponWalletController::class, 'showWallet'])->name('customer.coupons.wallet');
    Route::post('customer/coupons/{restaurant}/{phone}/claim', [CouponWalletController::class, 'claimCoupon'])->name('customer.coupons.claim');
});

// Xác thực lời mời nhận việc của nhân viên mới
Route::match(['get', 'post'], 'employees/verify/{user}', [EmployeeManagementController::class, 'verifyEmployee'])
    ->name('employees.verify')
    ->middleware(['signed', 'throttle:6,1']);

// Public System Status Page
Route::get('status', [PublicStatusController::class, 'index'])->name('public.status');
Route::get('api/status-data', [PublicStatusController::class, 'getStatusData'])->name('public.status.data');

require __DIR__.'/settings.php';
