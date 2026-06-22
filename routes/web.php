<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\PaymentWebhookController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
// Chatbot API  public (rate limited)
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:30,1')->group(function () {
    Route::post('api/chatbot/message', [ChatbotController::class, 'message'])->name('chatbot.message');
    Route::get('api/chatbot/suggestions', [ChatbotController::class, 'suggestions'])->name('chatbot.suggestions');
    Route::post('api/chatbot/feedback', [ChatbotController::class, 'feedback'])->name('chatbot.feedback');
});

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

// Chức năng QR-Ordering dành cho khách hàng tại bàn
Route::middleware('throttle:60,1')->group(function () {
    Route::post('customer/order/call-staff/{restaurant}', [\App\Http\Controllers\Customer\QROrderController::class, 'callStaff'])->name('customer.qr-order.call-staff');
    Route::post('customer/order/payment-request/{restaurant}', [\App\Http\Controllers\Customer\QROrderController::class, 'paymentRequest'])->name('customer.qr-order.payment-request');
    Route::post('customer/order/feedback/{restaurant}', [\App\Http\Controllers\Customer\QROrderController::class, 'submitFeedback'])->name('customer.qr-order.feedback');
    Route::get('customer/order/{restaurant}/{token}', [\App\Http\Controllers\Customer\QROrderController::class, 'showMenu'])->name('customer.qr-order.show');
    Route::post('customer/order/{restaurant}/{token}', [\App\Http\Controllers\Customer\QROrderController::class, 'submitOrder'])->name('customer.qr-order.submit');
    Route::post('api/customer/track-behavior', [\App\Http\Controllers\CdpController::class, 'trackBehavior'])->name('api.customer.track-behavior');
    Route::get('api/orders/{order}/payment-qr', [\App\Http\Controllers\OrderPaymentQrController::class, 'paymentQr'])->name('api.orders.payment-qr');
    Route::get('api/orders/{order}/payment-status', [\App\Http\Controllers\OrderPaymentQrController::class, 'paymentStatus'])->name('api.orders.payment-status');

    // Public đặt bàn trước (khách quét QR đặt bàn)
    Route::post('r/{restaurantId}/reservations', [\App\Http\Controllers\TableReservationController::class, 'publicStore'])->name('reservations.public-store');

    // Member Dashboard, Loyalty & Reservation Portal Routes
    Route::get('customer/portal/dashboard/{restaurant}/{phone}', [\App\Http\Controllers\Customer\CustomerPortalController::class, 'showDashboard'])->name('customer.portal.dashboard');
    Route::post('customer/portal/redeem/{restaurant}/{phone}', [\App\Http\Controllers\Customer\CustomerPortalController::class, 'redeemReward'])->name('customer.portal.redeem');
    Route::post('customer/portal/reserve/{restaurant}', [\App\Http\Controllers\Customer\CustomerPortalController::class, 'createReservation'])->name('customer.portal.reserve');
});

// Xác thực lời mời nhận việc của nhân viên mới
Route::get('employees/verify/{user}', [EmployeeManagementController::class, 'verifyEmployee'])
    ->name('employees.verify')
    ->middleware('signed');

// Public System Status Page
Route::get('status', [\App\Http\Controllers\PublicStatusController::class, 'index'])->name('public.status');
Route::get('api/status-data', [\App\Http\Controllers\PublicStatusController::class, 'getStatusData'])->name('public.status.data');

require __DIR__.'/settings.php';

