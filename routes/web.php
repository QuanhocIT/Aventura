<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\PaymentWebhookController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FraudController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MenuInsightController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\QrOrderController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ShiftClosingController;
use App\Http\Controllers\SuperAdmin\ImpersonateController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TablesController;
use App\Http\Controllers\ViolationReportController;
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
    Route::get('choose-restaurant', [SupportController::class, 'chooseRestaurantPage'])->name('choose-restaurant');
    Route::post('choose-restaurant', [SupportController::class, 'chooseRestaurant'])->name('choose-restaurant.select');

    // Impersonation Stop
    Route::post('impersonate/stop', [ImpersonateController::class, 'stop'])->name('impersonate.stop');
});

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tin-tuc', [NewsController::class, 'index'])->name('news.index');
Route::get('/tin-tuc/{slug}', [NewsController::class, 'show'])->name('news.show');

Route::middleware(['auth', 'verified', 'tenant.subscription'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Onboarding API
    Route::post('api/onboarding/update', [OnboardingController::class, 'updateProgress'])->name('onboarding.update');
    Route::post('api/onboarding/reset', [OnboardingController::class, 'resetProgress'])->name('onboarding.reset');

    // Support Portal
    Route::get('support', [SupportController::class, 'index'])->name('support.index');
    Route::post('support/tickets', [SupportController::class, 'storeTicket'])->name('support.tickets.store');
    Route::post('support/tickets/{ticket}/replies', [SupportController::class, 'storeReply'])->name('support.tickets.replies.store');

    // Functional Pages for Guided Tours
    Route::get('products', [SupportController::class, 'productsPage'])->name('products.index');
    Route::get('api/products/menu-insights', [MenuInsightController::class, 'index'])->name('products.menu-insights');
    Route::post('products', [SupportController::class, 'storeProduct'])->name('products.store');
    Route::patch('products/{product}', [SupportController::class, 'updateProduct'])->name('products.update');
    Route::delete('products/{product}', [SupportController::class, 'destroyProduct'])->name('products.destroy');
    Route::post('product-categories', [SupportController::class, 'storeCategory'])->name('product-categories.store');
    Route::delete('product-categories/{category}', [SupportController::class, 'destroyCategory'])->name('product-categories.destroy');

    Route::get('inventory', [SupportController::class, 'inventoryPage'])->name('inventory.index');
    Route::get('api/inventory/ai-forecast', [SupportController::class, 'aiForecast'])->name('inventory.ai-forecast');
    Route::post('inventory/ingredients', [SupportController::class, 'storeIngredient'])->name('inventory.ingredients.store');
    Route::patch('inventory/ingredients/{ingredient}', [SupportController::class, 'updateIngredient'])->name('inventory.ingredients.update');
    Route::post('inventory/recipes', [SupportController::class, 'storeRecipe'])->name('inventory.recipes.store');
    Route::post('inventory/purchases', [SupportController::class, 'storePurchase'])->name('inventory.purchases.store');
    Route::post('inventory/waste', [SupportController::class, 'storeWaste'])->name('inventory.waste.store');

    // Nhà cung cấp & PO Fulfillment
    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::patch('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    Route::post('purchase-orders', [SupplierController::class, 'storePO'])->name('purchase-orders.store');
    Route::patch('purchase-orders/{purchaseOrder}/status', [SupplierController::class, 'updatePOStatus'])->name('purchase-orders.update-status');

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
    Route::patch('employees/leaves/{leave}/approve', [SupportController::class, 'approveLeaveRequest'])->name('employees.leaves.approve');
    Route::patch('employees/leaves/{leave}/reject', [SupportController::class, 'rejectLeaveRequest'])->name('employees.leaves.reject');

    // Chấm công & Lịch biểu
    Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('schedules/register', [ScheduleController::class, 'register'])->name('schedules.register');
    Route::post('schedules/check-in', [ScheduleController::class, 'checkIn'])->name('schedules.check-in');
    Route::post('schedules/check-out', [ScheduleController::class, 'checkOut'])->name('schedules.check-out');
    Route::post('schedules/check-in-employee', [ScheduleController::class, 'checkInEmployee'])->name('schedules.check-in-employee');
    Route::post('schedules/check-out-employee', [ScheduleController::class, 'checkOutEmployee'])->name('schedules.check-out-employee');
    Route::post('schedules/absent-employee', [ScheduleController::class, 'markAbsentEmployee'])->name('schedules.absent-employee');

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
    Route::get('tables', [TablesController::class, 'index'])->name('tables.index');
    Route::post('tables/areas', [TablesController::class, 'storeArea'])->name('tables.areas.store');
    Route::post('tables', [TablesController::class, 'store'])->name('tables.store');
    Route::patch('tables/{table}', [TablesController::class, 'update'])->name('tables.update');
    Route::delete('tables/{table}', [TablesController::class, 'destroy'])->name('tables.destroy');

    // Orders management
    Route::get('orders/create', [OrdersController::class, 'create'])->name('orders.create');
    Route::post('orders', [OrdersController::class, 'store'])->name('orders.store');
    Route::get('orders', [OrdersController::class, 'index'])->name('orders.index');
    Route::patch('orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('orders/{order}/split', [OrdersController::class, 'split'])->name('orders.split');
    Route::patch('orders/{order}/override-split-penalty', [OrdersController::class, 'overrideSplitPenalty'])->name('orders.override-split-penalty');
    Route::patch('orders/{order}', [OrdersController::class, 'update'])->name('orders.update');
    Route::patch('orders/items/{item}/status', [OrdersController::class, 'updateItemStatus'])->name('orders.items.update-status');
    Route::post('api/orders/third-party/simulate', [OrdersController::class, 'simulateThirdPartyOrder'])->name('orders.third-party.simulate');

    // Audit Logs (Owner & Manager — chỉ xem log của nhà hàng mình)
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Revenue / Reports
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::post('reports/generate', [ReportsController::class, 'generate'])->name('reports.generate');
    Route::post('reports/send-email', [ReportsController::class, 'sendReport'])->name('reports.send-email');

    // Bảng lương
    Route::get('salaries', [SalaryController::class, 'index'])->name('salaries.index');
    Route::post('salaries/generate', [SalaryController::class, 'generate'])->name('salaries.generate');
    Route::post('salaries/adjustments/bulk', [SalaryController::class, 'storeBulkAdjustment'])->name('salaries.adjustments.bulk');
    Route::patch('salaries/adjustments/{adjustment}/dispute', [SalaryController::class, 'disputeAdjustment'])->name('salaries.adjustments.dispute');
    Route::patch('salaries/{salary}/approve', [SalaryController::class, 'approve'])->name('salaries.approve');
    Route::patch('salaries/{salary}/paid', [SalaryController::class, 'markPaid'])->name('salaries.paid');
    Route::post('salaries/{salary}/adjustments', [SalaryController::class, 'storeAdjustment'])->name('salaries.adjustments.store');

    // Shift Closings — Chốt ca & Doanh thu gộp
    Route::get('shift-closings', [ShiftClosingController::class, 'index'])->name('shift-closings.index');
    Route::get('shift-closings/preview', [ShiftClosingController::class, 'preview'])->name('shift-closings.preview');
    Route::post('shift-closings', [ShiftClosingController::class, 'store'])->name('shift-closings.store');
    Route::patch('shift-closings/{closing}/confirm', [ShiftClosingController::class, 'confirm'])->name('shift-closings.confirm');
    Route::patch('shift-closings/{closing}/dispute', [ShiftClosingController::class, 'dispute'])->name('shift-closings.dispute');

    // Support booking demo
    Route::post('support/bookings', [SupportController::class, 'storeBooking'])->name('support.bookings.store');

    // Kiểm toán gian lận
    Route::get('fraud', [FraudController::class, 'index'])->name('fraud.index');
    Route::post('fraud/violation', [FraudController::class, 'createViolation'])->name('fraud.violation.store');

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
});

// Biểu mẫu gửi đánh giá công khai (Dành cho Khách hàng quét mã QR)
Route::get('feedback/new', [FeedbackController::class, 'publicCreate'])->name('feedback.new');
Route::post('feedback', [FeedbackController::class, 'store'])->name('feedback.store');

// Trải nghiệm gọi món qua QR dành cho Khách hàng tại bàn
Route::get('order/{restaurant}/{table_token}', [QrOrderController::class, 'showMenu'])->name('qr.order.show');
Route::post('order/{restaurant}/{table_token}', [QrOrderController::class, 'submitOrder'])->name('qr.order.submit');
Route::post('order/{restaurant}/{table_token}/call-staff', [QrOrderController::class, 'callStaff'])->name('qr.order.call-staff');
Route::post('order/{restaurant}/{table_token}/request-payment', [QrOrderController::class, 'requestPayment'])->name('qr.order.request-payment');

// Xác thực lời mời nhận việc của nhân viên mới
Route::get('employees/verify/{user}', [SupportController::class, 'verifyEmployee'])
    ->name('employees.verify')
    ->middleware('signed');

require __DIR__.'/settings.php';
