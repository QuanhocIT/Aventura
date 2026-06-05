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
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('billing/checkout', CheckoutController::class)->name('billing.checkout');
    Route::get('billing/pay/{code}', [CheckoutController::class, 'payPage'])->name('billing.pay');
    Route::get('api/billing/check/{code}', [CheckoutController::class, 'checkStatus']);

    // Multi-tenant restaurant selector
    Route::get('choose-restaurant', [\App\Http\Controllers\SupportController::class, 'chooseRestaurantPage'])->name('choose-restaurant');
    Route::post('choose-restaurant', [\App\Http\Controllers\SupportController::class, 'chooseRestaurant'])->name('choose-restaurant.select');

    // Impersonation Stop
    Route::post('impersonate/stop', [\App\Http\Controllers\SuperAdmin\ImpersonateController::class, 'stop'])->name('impersonate.stop');
});

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tin-tuc', [NewsController::class, 'index'])->name('news.index');
Route::get('/tin-tuc/{slug}', [NewsController::class, 'show'])->name('news.show');

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ViolationReportController;

Route::middleware(['auth', 'verified', 'tenant.subscription', 'tenant.ratelimit'])->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Onboarding API
    Route::post('api/onboarding/update', [OnboardingController::class, 'updateProgress'])->name('onboarding.update');
    Route::post('api/onboarding/reset', [OnboardingController::class, 'resetProgress'])->name('onboarding.reset');

    // Support Portal
    Route::get('support', [SupportController::class, 'index'])->name('support.index');
    Route::post('support/tickets', [SupportController::class, 'storeTicket'])->name('support.tickets.store');
    Route::post('support/tickets/{ticket}/replies', [SupportController::class, 'storeReply'])->name('support.tickets.replies.store');

    // Functional Pages for Guided Tours
    Route::get('products', [SupportController::class, 'productsPage'])->name('products.index');
    Route::get('api/products/menu-insights', [\App\Http\Controllers\MenuInsightController::class, 'index'])->name('products.menu-insights');
    Route::post('products', [SupportController::class, 'storeProduct'])->name('products.store');
    Route::patch('products/{product}', [SupportController::class, 'updateProduct'])->name('products.update');
    Route::delete('products/{product}', [SupportController::class, 'destroyProduct'])->name('products.destroy');
    Route::post('product-categories', [SupportController::class, 'storeCategory'])->name('product-categories.store');
    Route::delete('product-categories/{category}', [SupportController::class, 'destroyCategory'])->name('product-categories.destroy');

    Route::get('inventory', [SupportController::class, 'inventoryPage'])->name('inventory.index');
    Route::get('api/inventory/ai-forecast', [SupportController::class, 'aiForecast'])->name('inventory.ai-forecast');
    Route::post('inventory/ingredients', [SupportController::class, 'storeIngredient'])->name('inventory.ingredients.store');
    Route::post('inventory/recipes', [SupportController::class, 'storeRecipe'])->name('inventory.recipes.store');
    Route::post('inventory/purchases', [SupportController::class, 'storePurchase'])->name('inventory.purchases.store');
    Route::post('inventory/waste', [SupportController::class, 'storeWaste'])->name('inventory.waste.store');

    Route::get('employees', [SupportController::class, 'employeesPage'])->name('employees.index');
    Route::post('employees', [SupportController::class, 'storeEmployee'])->name('employees.store');
    Route::patch('employees/{employee}', [SupportController::class, 'updateEmployee'])->name('employees.update');
    Route::patch('employees/{employee}/toggle-status', [SupportController::class, 'toggleEmployeeStatus'])->name('employees.toggle-status');
    Route::get('employees/{employee}/export-profile', [SupportController::class, 'exportEmployeeProfile'])->name('employees.export-profile');
    Route::post('employees/shifts/sync', [SupportController::class, 'syncShifts'])->name('employees.shifts.sync');
    Route::post('employees/schedules', [SupportController::class, 'storeAssignment'])->name('employees.schedules.store');
    Route::post('employees/schedules/delete', [SupportController::class, 'destroyAssignment'])->name('employees.schedules.destroy');
    Route::post('employees/schedules/toggle-auto', [SupportController::class, 'toggleAutoSchedule'])->name('employees.schedules.toggle-auto');
    Route::post('employees/leaves', [SupportController::class, 'storeLeaveRequest'])->name('employees.leaves.store');
    Route::get('employees/leaves/{leave}/replacements', [SupportController::class, 'getReplacementSuggestions'])->name('employees.leaves.replacements');
    Route::patch('employees/leaves/{leave}/approve', [SupportController::class, 'approveLeaveRequest'])->name('employees.leaves.approve');
    Route::patch('employees/leaves/{leave}/reject', [SupportController::class, 'rejectLeaveRequest'])->name('employees.leaves.reject');

    // Chấm công & Lịch biểu
    Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('schedules/check-in', [ScheduleController::class, 'checkIn'])->name('schedules.check-in');
    Route::post('schedules/check-out', [ScheduleController::class, 'checkOut'])->name('schedules.check-out');
    Route::post('schedules/check-in-employee', [ScheduleController::class, 'checkInEmployee'])->name('schedules.check-in-employee');
    Route::post('schedules/check-out-employee', [ScheduleController::class, 'checkOutEmployee'])->name('schedules.check-out-employee');
    Route::post('schedules/absent-employee', [ScheduleController::class, 'markAbsentEmployee'])->name('schedules.absent-employee');
    Route::post('schedules/register', [ScheduleController::class, 'register'])->name('schedules.register');

    // Quản lý Khách hàng (CRM Mini) & Bảo mật tài sản số
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::get('api/customers/search', [CustomerController::class, 'search'])->name('customers.search');

    // Thiết lập Khuyến mãi & Chiến lược cấu hình Combo thông minh
    Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');
    Route::patch('promotions/{promotion}/toggle', [PromotionController::class, 'toggleActive'])->name('promotions.toggle');
    Route::post('promotions/{promotion}/approve', [PromotionController::class, 'approve'])->name('promotions.approve');
    Route::post('api/promotions/apply', [PromotionController::class, 'apply'])->name('promotions.apply');
    Route::get('api/promotions/basket-analysis', [PromotionController::class, 'getBasketAnalysis'])->name('promotions.basket-analysis');
    Route::post('api/promotions/upsell-suggestion', [PromotionController::class, 'getUpsellSuggestion'])->name('promotions.upsell-suggestion');

    // Tables management
    Route::get('tables', [\App\Http\Controllers\TablesController::class, 'index'])->name('tables.index');
    Route::post('tables/areas', [\App\Http\Controllers\TablesController::class, 'storeArea'])->name('tables.areas.store');
    Route::post('tables', [\App\Http\Controllers\TablesController::class, 'store'])->name('tables.store');
    Route::patch('tables/{table}', [\App\Http\Controllers\TablesController::class, 'update'])->name('tables.update');
    Route::delete('tables/{table}', [\App\Http\Controllers\TablesController::class, 'destroy'])->name('tables.destroy');

    // Kitchen management
    Route::get('kitchen', [\App\Http\Controllers\KitchenController::class, 'index'])->name('kitchen.index');
    Route::post('kitchen/items/{item}/prepare', [\App\Http\Controllers\KitchenController::class, 'prepare'])->name('kitchen.prepare');
    Route::post('kitchen/items/{item}/serve', [\App\Http\Controllers\KitchenController::class, 'serve'])->name('kitchen.serve');

    // Orders management
    Route::get('orders/create', [\App\Http\Controllers\OrdersController::class, 'create'])->name('orders.create');
    Route::post('orders', [\App\Http\Controllers\OrdersController::class, 'store'])->name('orders.store');
    Route::get('orders', [\App\Http\Controllers\OrdersController::class, 'index'])->name('orders.index');
    Route::patch('orders/{order}/status', [\App\Http\Controllers\OrdersController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('orders/{order}/split', [\App\Http\Controllers\OrdersController::class, 'split'])->name('orders.split');
    Route::patch('orders/{order}/override-split-penalty', [\App\Http\Controllers\OrdersController::class, 'overrideSplitPenalty'])->name('orders.override-split-penalty');
    Route::patch('orders/{order}', [\App\Http\Controllers\OrdersController::class, 'update'])->name('orders.update');
    Route::post('orders/{order}/pay', [\App\Http\Controllers\OrdersController::class, 'pay'])->name('orders.pay');
    Route::post('orders/{order}/confirm-qr', [\App\Http\Controllers\OrdersController::class, 'confirmQr'])->name('orders.confirm-qr');
    Route::post('settings/toggle-auto-pay', [\App\Http\Controllers\OrdersController::class, 'toggleAutoPaySetting'])->name('settings.toggle-auto-pay');

    // Audit Logs (Owner & Manager — chỉ xem log của nhà hàng mình)
    Route::get('audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');

    // Revenue / Reports
    Route::get('reports', [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::post('reports/generate', [\App\Http\Controllers\ReportsController::class, 'generate'])->name('reports.generate');
    Route::post('reports/send-email', [\App\Http\Controllers\ReportsController::class, 'sendReport'])->name('reports.send-email');

    // Bảng lương
    Route::get('salaries', [\App\Http\Controllers\SalaryController::class, 'index'])->name('salaries.index');
    Route::post('salaries/generate', [\App\Http\Controllers\SalaryController::class, 'generate'])->name('salaries.generate');
    Route::post('salaries/adjustments/bulk', [\App\Http\Controllers\SalaryController::class, 'storeBulkAdjustment'])->name('salaries.adjustments.bulk');
    Route::patch('salaries/adjustments/{adjustment}/dispute', [\App\Http\Controllers\SalaryController::class, 'disputeAdjustment'])->name('salaries.adjustments.dispute');
    Route::patch('salaries/{salary}/approve', [\App\Http\Controllers\SalaryController::class, 'approve'])->name('salaries.approve');
    Route::patch('salaries/{salary}/paid', [\App\Http\Controllers\SalaryController::class, 'markPaid'])->name('salaries.paid');
    Route::post('salaries/{salary}/adjustments', [\App\Http\Controllers\SalaryController::class, 'storeAdjustment'])->name('salaries.adjustments.store');

    // Shift Closings — Chốt ca & Doanh thu gộp
    Route::get('shift-closings', [\App\Http\Controllers\ShiftClosingController::class, 'index'])->name('shift-closings.index');
    Route::get('shift-closings/preview', [\App\Http\Controllers\ShiftClosingController::class, 'preview'])->name('shift-closings.preview');
    Route::post('shift-closings', [\App\Http\Controllers\ShiftClosingController::class, 'store'])->name('shift-closings.store');
    Route::patch('shift-closings/{closing}/confirm', [\App\Http\Controllers\ShiftClosingController::class, 'confirm'])->name('shift-closings.confirm');
    Route::patch('shift-closings/{closing}/dispute', [\App\Http\Controllers\ShiftClosingController::class, 'dispute'])->name('shift-closings.dispute');

    // Support booking demo
    Route::post('support/bookings', [SupportController::class, 'storeBooking'])->name('support.bookings.store');

    // Kiểm toán gian lận
    Route::get('fraud', [\App\Http\Controllers\FraudController::class, 'index'])->name('fraud.index');
    Route::post('fraud/violation', [\App\Http\Controllers\FraudController::class, 'createViolation'])->name('fraud.violation.store');

    // Kiểm duyệt chéo (Cross-review)
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::patch('approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::patch('approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

    // Quản lý phản hồi khách hàng (Owner & Manager)
    Route::get('feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::post('feedback/{feedback}/resolve', [FeedbackController::class, 'resolve'])->name('feedback.resolve');

    // Quản lý Tố cáo Nội bộ & Sai phạm (Owner & Manager)
    Route::get('violations', [ViolationReportController::class, 'index'])->name('violations.index');
    Route::post('violations', [ViolationReportController::class, 'store'])->name('violations.store');
    Route::post('violations/{report}/resolve', [ViolationReportController::class, 'resolve'])->name('violations.resolve');

    // Quản lý Nhà cung cấp & Đơn PO (Dành cho nhà hàng)
    Route::get('suppliers', [\App\Http\Controllers\SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('suppliers', [\App\Http\Controllers\SupplierController::class, 'store'])->name('suppliers.store');
    Route::patch('suppliers/{supplier}', [\App\Http\Controllers\SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('suppliers/{supplier}', [\App\Http\Controllers\SupplierController::class, 'destroy'])->name('suppliers.destroy');
    Route::post('suppliers/{supplier}/place-order', [\App\Http\Controllers\SupplierController::class, 'placeOrder'])->name('suppliers.place-order');
    Route::post('suppliers/orders/{purchaseOrder}/approve', [\App\Http\Controllers\SupplierController::class, 'approveOrder'])->name('suppliers.orders.approve');
    Route::post('suppliers/orders/{purchaseOrder}/verify', [\App\Http\Controllers\SupplierController::class, 'verifyOrder'])->name('suppliers.orders.verify');
    Route::post('suppliers/orders/{purchaseOrder}/release-escrow', [\App\Http\Controllers\SupplierController::class, 'releaseEscrow'])->name('suppliers.orders.release-escrow');
    Route::post('suppliers/orders/{purchaseOrder}/refund-escrow', [\App\Http\Controllers\SupplierController::class, 'refundEscrow'])->name('suppliers.orders.refund-escrow');
    Route::get('suppliers/{supplier}/ingredients/{ingredient}/price-analytics', [\App\Http\Controllers\SupplierController::class, 'priceAnalytics'])->name('suppliers.price-analytics');
    Route::get('suppliers/{supplier}/sla', [\App\Http\Controllers\SupplierController::class, 'getSlaMetrics'])->name('suppliers.sla');
    Route::post('suppliers/auto-replenish', [\App\Http\Controllers\SupplierController::class, 'triggerAutoReplenish'])->name('suppliers.auto-replenish');
    Route::post('suppliers/ocr-invoice', [\App\Http\Controllers\SupplierController::class, 'ocrInvoice'])->name('suppliers.ocr-invoice');

    // Quản lý Đấu thầu RFP (Dành cho nhà hàng)
    Route::get('rfps', [\App\Http\Controllers\RfpController::class, 'index'])->name('rfps.index');
    Route::post('rfps', [\App\Http\Controllers\RfpController::class, 'store'])->name('rfps.store');
    Route::post('rfps/{rfp}/close', [\App\Http\Controllers\RfpController::class, 'close'])->name('rfps.close');
    Route::post('rfps/bids/{bid}/accept', [\App\Http\Controllers\RfpController::class, 'acceptBid'])->name('rfps.bids.accept');

    // Portal Chuỗi cung ứng (Dành cho nhà cung cấp)
    Route::prefix('supplier')->name('supplier.')->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\SupplierController::class, 'supplierDashboard'])->name('dashboard');
        Route::get('catalog', [\App\Http\Controllers\SupplierController::class, 'supplierCatalog'])->name('catalog');
        Route::post('catalog', [\App\Http\Controllers\SupplierController::class, 'storeCatalogItem'])->name('catalog.store');
        Route::get('orders', [\App\Http\Controllers\SupplierController::class, 'supplierOrders'])->name('orders');
        Route::post('orders/{purchaseOrder}/status', [\App\Http\Controllers\SupplierController::class, 'updateOrderStatus'])->name('orders.update-status');
        Route::get('rfps', [\App\Http\Controllers\RfpController::class, 'supplierIndex'])->name('rfps');
        Route::post('rfps/{rfp}/bid', [\App\Http\Controllers\RfpController::class, 'supplierSubmitBid'])->name('rfps.bid');
    });
});

// Biểu mẫu gửi đánh giá công khai (Dành cho Khách hàng quét mã QR, giới hạn 15 request/phút)
Route::middleware('throttle:15,1')->group(function () {
    Route::get('feedback/new', [FeedbackController::class, 'publicCreate'])->name('feedback.new');
    Route::post('feedback', [FeedbackController::class, 'store'])->name('feedback.store');
});

// Xác thực lời mời nhận việc của nhân viên mới
Route::get('employees/verify/{user}', [SupportController::class, 'verifyEmployee'])
    ->name('employees.verify')
    ->middleware('signed');

require __DIR__.'/settings.php';

