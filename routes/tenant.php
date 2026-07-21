<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\ProductManagementController;
use App\Http\Controllers\InventoryManagementController;
use App\Http\Controllers\EmployeeManagementController;
use App\Http\Controllers\LeaveScheduleController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ShiftSwapController;
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
    Route::get('products', [ProductManagementController::class, 'productsPage'])->name('products.index');
    Route::get('api/products/menu-insights', [\App\Http\Controllers\MenuInsightController::class, 'index'])->name('products.menu-insights');
    Route::get('api/analytics/weather-menu-forecast', [\App\Http\Controllers\WeatherForecastController::class, 'index'])->name('analytics.weather-forecast');
    Route::post('products', [ProductManagementController::class, 'storeProduct'])->name('products.store')->middleware('tenant.quota:dishes');
    Route::patch('products/{product}', [ProductManagementController::class, 'updateProduct'])->name('products.update');
    Route::delete('products/{product}', [ProductManagementController::class, 'destroyProduct'])->name('products.destroy');
    Route::post('product-categories', [ProductManagementController::class, 'storeCategory'])->name('product-categories.store');
    Route::delete('product-categories/{category}', [ProductManagementController::class, 'destroyCategory'])->name('product-categories.destroy');

    Route::get('inventory', [InventoryManagementController::class, 'inventoryPage'])->name('inventory.index');
    Route::get('api/inventory/ai-forecast', [InventoryManagementController::class, 'aiForecast'])->name('inventory.ai-forecast');
    Route::post('inventory/ingredients', [InventoryManagementController::class, 'storeIngredient'])->name('inventory.ingredients.store');
    Route::post('inventory/recipes', [InventoryManagementController::class, 'storeRecipe'])->name('inventory.recipes.store');
    Route::delete('inventory/recipes/{id}', [InventoryManagementController::class, 'deleteRecipe'])->name('inventory.recipes.delete');
    Route::post('inventory/purchases', [InventoryManagementController::class, 'storePurchase'])->name('inventory.purchases.store');
    Route::post('inventory/waste', [InventoryManagementController::class, 'storeWaste'])->name('inventory.waste.store');
    Route::post('inventory/reconcile', [InventoryManagementController::class, 'reconcile'])->name('inventory.reconcile');

    Route::get('employees', [EmployeeManagementController::class, 'employeesPage'])->name('employees.index');
    Route::post('employees', [EmployeeManagementController::class, 'storeEmployee'])->name('employees.store')->middleware('tenant.quota:employees');
    Route::patch('employees/{employee}', [EmployeeManagementController::class, 'updateEmployee'])->name('employees.update');
    Route::patch('employees/{employee}/toggle-status', [EmployeeManagementController::class, 'toggleEmployeeStatus'])->name('employees.toggle-status');
    Route::get('employees/{employee}/export-profile', [EmployeeManagementController::class, 'exportEmployeeProfile'])->name('employees.export-profile');
    Route::post('employees/shifts/sync', [EmployeeManagementController::class, 'syncShifts'])->name('employees.shifts.sync');
    Route::post('employees/schedules', [LeaveScheduleController::class, 'storeAssignment'])->name('employees.schedules.store');
    Route::post('employees/schedules/delete', [LeaveScheduleController::class, 'destroyAssignment'])->name('employees.schedules.destroy');
    Route::post('employees/schedules/toggle-auto', [LeaveScheduleController::class, 'toggleAutoSchedule'])->name('employees.schedules.toggle-auto');
    Route::post('employees/schedules/copy-last-week', [LeaveScheduleController::class, 'copyLastWeekSchedules'])->name('employees.schedules.copy-last-week');
    Route::post('employees/leaves', [LeaveScheduleController::class, 'storeLeaveRequest'])->name('employees.leaves.store');
    Route::get('employees/leaves/{leave}/replacements', [LeaveScheduleController::class, 'getReplacementSuggestions'])->name('employees.leaves.replacements');
    Route::patch('employees/leaves/{leave}/approve', [LeaveScheduleController::class, 'approveLeaveRequest'])->name('employees.leaves.approve');
    Route::patch('employees/leaves/{leave}/reject', [LeaveScheduleController::class, 'rejectLeaveRequest'])->name('employees.leaves.reject');

    // Chấm công & Lịch biểu
    Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('schedules/check-in', [AttendanceController::class, 'checkIn'])->name('schedules.check-in');
    Route::post('schedules/check-out', [AttendanceController::class, 'checkOut'])->name('schedules.check-out');
    Route::post('schedules/check-in-employee', [AttendanceController::class, 'checkInEmployee'])->name('schedules.check-in-employee');
    Route::post('schedules/check-out-employee', [AttendanceController::class, 'checkOutEmployee'])->name('schedules.check-out-employee');
    Route::post('schedules/absent-employee', [AttendanceController::class, 'markAbsentEmployee'])->name('schedules.absent-employee');
    Route::post('schedules/register', [ScheduleController::class, 'register'])->name('schedules.register');
    Route::post('schedules/toggle-leader', [ScheduleController::class, 'toggleShiftLeader'])->name('schedules.toggle-leader');
    Route::post('schedules/settings', [ScheduleController::class, 'updateSettings'])->name('schedules.update-settings');
    Route::post('schedules/settings/generate-qr', [ScheduleController::class, 'generateDailyQR'])->name('schedules.settings.generate-qr');
    Route::get('schedules/dynamic-qr', [ScheduleController::class, 'getDynamicQR'])->name('schedules.dynamic-qr');
    Route::post('schedules/approve-registration', [ScheduleController::class, 'approveRegistration'])->name('schedules.approve-registration');
    Route::get('schedules/export', [ScheduleController::class, 'export'])->name('schedules.export');

    // Shift Swapping
    Route::post('schedules/swap/request', [ShiftSwapController::class, 'requestSwap'])->name('schedules.swap.request');
    Route::post('schedules/swap/{swap}/accept', [ShiftSwapController::class, 'acceptSwap'])->name('schedules.swap.accept');
    Route::post('schedules/swap/{swap}/cancel', [ShiftSwapController::class, 'cancelSwap'])->name('schedules.swap.cancel');
    Route::patch('schedules/swap/{swap}/approve', [LeaveScheduleController::class, 'approveSwap'])->name('schedules.swap.approve');
    Route::patch('schedules/swap/{swap}/reject', [LeaveScheduleController::class, 'rejectSwap'])->name('schedules.swap.reject');
    Route::get('schedules/swap-suggestions', [ShiftSwapController::class, 'getSwapSuggestions'])->name('schedules.swap-suggestions');
    Route::get('notifications', [ShiftSwapController::class, 'getNotifications'])->name('notifications.index');
    Route::post('notifications/{id}/read', [ShiftSwapController::class, 'markNotificationAsRead'])->name('notifications.read');


    // Quản lý Khách hàng (CRM Mini) & Bảo mật tài sản số
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::get('api/customers/search', [CustomerController::class, 'search'])->name('customers.search');

    // Customer Data Platform (CDP) & RFM Analysis
    Route::get('customers/cdp', [\App\Http\Controllers\CdpController::class, 'index'])->name('customers.cdp');
    Route::post('customers/cdp/recalculate', [\App\Http\Controllers\CdpController::class, 'recalculate'])->name('customers.cdp.recalculate');
    Route::get('customers/cdp/segment/{segment}', [\App\Http\Controllers\CdpController::class, 'segment'])->name('customers.cdp.segment');
    Route::post('customers/cdp/campaigns', [\App\Http\Controllers\CdpController::class, 'storeCampaign'])->name('customers.cdp.campaigns');

    // Business Intelligence Dashboard
    Route::get('bi-dashboard', [\App\Http\Controllers\BIDashboardController::class, 'index'])->name('bi-dashboard.index');

    // Phân Tích Địa Lý & Vùng Phục Vụ
    Route::get('geo-analytics', [\App\Http\Controllers\GeoAnalyticsController::class, 'index'])->name('geo-analytics.index');
    Route::get('api/geo-analytics/heatmap', [\App\Http\Controllers\GeoAnalyticsController::class, 'apiHeatmap'])->name('geo-analytics.heatmap');

    // Mục Tiêu & OKR Doanh Nghiệp
    Route::prefix('business-goals')->name('goals.')->group(function () {
        Route::get('/', [\App\Http\Controllers\BusinessGoalController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\BusinessGoalController::class, 'store'])->name('store');
        Route::delete('/{goal}', [\App\Http\Controllers\BusinessGoalController::class, 'destroy'])->name('destroy');
        Route::post('/{goal}/actions', [\App\Http\Controllers\BusinessGoalController::class, 'storeAction'])->name('actions.store');
        Route::patch('/actions/{action}/toggle', [\App\Http\Controllers\BusinessGoalController::class, 'toggleAction'])->name('actions.toggle');
        Route::patch('/{goal}/value', [\App\Http\Controllers\BusinessGoalController::class, 'updateCustomValue'])->name('value');
    });

    // Phân Quyền Chi Tiết & Giới Hạn Thao Tác
    Route::get('operation-policies', [\App\Http\Controllers\OperationPolicyController::class, 'index'])->name('operation-policies.index');
    Route::post('operation-policies', [\App\Http\Controllers\OperationPolicyController::class, 'update'])->name('operation-policies.update');
    Route::post('api/operation-policies/check', [\App\Http\Controllers\OperationPolicyController::class, 'checkPermission'])->name('operation-policies.check');

    // Quản Lý Thiết Bị & Bảo Trì
    Route::prefix('equipment')->name('equipment.')->group(function () {
        Route::get('/', [\App\Http\Controllers\EquipmentController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\EquipmentController::class, 'store'])->name('store');
        Route::patch('/{equipment}', [\App\Http\Controllers\EquipmentController::class, 'update'])->name('update');
        Route::delete('/{equipment}', [\App\Http\Controllers\EquipmentController::class, 'destroy'])->name('destroy');
        Route::post('/report-issue', [\App\Http\Controllers\EquipmentController::class, 'reportIssue'])->name('report');
        Route::post('/logs/{log}/complete', [\App\Http\Controllers\EquipmentController::class, 'completeLog'])->name('logs.complete');
    });

    // Đào Tạo & Onboarding Nhân Viên
    Route::prefix('training')->name('training.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TrainingController::class, 'index'])->name('index');
        Route::post('/courses', [\App\Http\Controllers\TrainingController::class, 'storeCourse'])->name('courses.store');
        Route::delete('/courses/{course}', [\App\Http\Controllers\TrainingController::class, 'destroyCourse'])->name('courses.destroy');
        Route::post('/courses/{course}/lessons', [\App\Http\Controllers\TrainingController::class, 'storeLesson'])->name('lessons.store');
        Route::post('/courses/{course}/quizzes', [\App\Http\Controllers\TrainingController::class, 'storeQuiz'])->name('quizzes.store');
        Route::post('/enroll', [\App\Http\Controllers\TrainingController::class, 'enrollEmployee'])->name('enroll');
        Route::post('/complete-lesson', [\App\Http\Controllers\TrainingController::class, 'completeLesson'])->name('complete-lesson');
        Route::post('/submit-quiz', [\App\Http\Controllers\TrainingController::class, 'submitQuiz'])->name('submit-quiz');
    });

    // Checklist Vận Hành Hàng Ngày
    Route::prefix('operations-checklist')->name('checklist.')->group(function () {
        Route::get('/', [\App\Http\Controllers\OperationsChecklistController::class, 'index'])->name('index');
        Route::post('/complete', [\App\Http\Controllers\OperationsChecklistController::class, 'completeItem'])->name('complete');
        Route::post('/uncomplete', [\App\Http\Controllers\OperationsChecklistController::class, 'uncompleteItem'])->name('uncomplete');
        Route::post('/templates', [\App\Http\Controllers\OperationsChecklistController::class, 'storeTemplate'])->name('templates.store');
        Route::delete('/templates/{template}', [\App\Http\Controllers\OperationsChecklistController::class, 'destroyTemplate'])->name('templates.destroy');
        Route::get('/api/weekly-report', [\App\Http\Controllers\OperationsChecklistController::class, 'weeklyReport'])->name('weekly-report');
    });

    // Quản Lý Hao Hụt & Lãng Phí (Waste Management)
    Route::prefix('waste-management')->name('waste.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WasteManagementController::class, 'index'])->name('index');
        Route::get('/api/dashboard', [\App\Http\Controllers\WasteManagementController::class, 'apiDashboard'])->name('dashboard');
        Route::get('/api/trend', [\App\Http\Controllers\WasteManagementController::class, 'apiTrend'])->name('trend');
        Route::get('/api/suggestions', [\App\Http\Controllers\WasteManagementController::class, 'apiSuggestions'])->name('suggestions');
        Route::get('/api/expiring', [\App\Http\Controllers\WasteManagementController::class, 'apiExpiring'])->name('expiring');
    });

    // Menu Engineering Nâng Cao
    Route::prefix('menu-engineering')->name('menu-engineering.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MenuEngineeringController::class, 'index'])->name('index');
        Route::get('/api/scoring', [\App\Http\Controllers\MenuEngineeringController::class, 'scoring'])->name('scoring');
        Route::post('/display-order', [\App\Http\Controllers\MenuEngineeringController::class, 'updateDisplayOrder'])->name('display-order');
        Route::patch('/products/{product}/time-slot', [\App\Http\Controllers\MenuEngineeringController::class, 'updateTimeSlot'])->name('time-slot');
        Route::post('/price-tests', [\App\Http\Controllers\MenuEngineeringController::class, 'storePriceTest'])->name('price-tests.store');
        Route::post('/price-tests/{test}/complete', [\App\Http\Controllers\MenuEngineeringController::class, 'completePriceTest'])->name('price-tests.complete');
        Route::post('/price-tests/{test}/cancel', [\App\Http\Controllers\MenuEngineeringController::class, 'cancelPriceTest'])->name('price-tests.cancel');
    });

    // Cấu hình Cửa hàng Online (Digital Ordering Hub)
    Route::get('online-store', [\App\Http\Controllers\OnlineStoreSettingsController::class, 'index'])->name('online-store.index');
    Route::post('online-store', [\App\Http\Controllers\OnlineStoreSettingsController::class, 'update'])->name('online-store.update');

    // Trung tâm Tích hợp (Integration Hub)
    Route::prefix('settings/integrations')->name('integrations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\IntegrationSettingsController::class, 'index'])->name('index');
        Route::post('/simulate-order', [\App\Http\Controllers\IntegrationSettingsController::class, 'simulateOrder'])->name('simulate-order');
        Route::post('/test-zalo', [\App\Http\Controllers\IntegrationSettingsController::class, 'testZalo'])->name('test-zalo');
        Route::get('/misa/export', [\App\Http\Controllers\IntegrationSettingsController::class, 'misaExport'])->name('misa.export');
        Route::post('/devices', [\App\Http\Controllers\PosDeviceController::class, 'store'])->name('devices.store');
        Route::delete('/devices/{device}', [\App\Http\Controllers\PosDeviceController::class, 'destroy'])->name('devices.destroy');
        Route::post('/api-keys', [\App\Http\Controllers\ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::patch('/api-keys/{apiKey}/toggle', [\App\Http\Controllers\ApiKeyController::class, 'toggle'])->name('api-keys.toggle');
        Route::delete('/api-keys/{apiKey}', [\App\Http\Controllers\ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
        Route::post('/webhooks', [\App\Http\Controllers\WebhookEndpointController::class, 'store'])->name('webhooks.store');
        Route::patch('/webhooks/{endpoint}/toggle', [\App\Http\Controllers\WebhookEndpointController::class, 'toggle'])->name('webhooks.toggle');
        Route::post('/webhooks/{endpoint}/test', [\App\Http\Controllers\WebhookEndpointController::class, 'test'])->name('webhooks.test');
        Route::delete('/webhooks/{endpoint}', [\App\Http\Controllers\WebhookEndpointController::class, 'destroy'])->name('webhooks.destroy');
        Route::post('/{provider}/demo', [\App\Http\Controllers\IntegrationSettingsController::class, 'connectDemo'])->name('demo');
        Route::post('/{provider}', [\App\Http\Controllers\IntegrationSettingsController::class, 'update'])->name('update');
        Route::patch('/{provider}/toggle', [\App\Http\Controllers\IntegrationSettingsController::class, 'toggle'])->name('toggle');
    });

    // In hóa đơn qua máy in nhiệt
    Route::post('orders/{order}/print-receipt', [\App\Http\Controllers\ReceiptPrintController::class, 'print'])->name('orders.print-receipt');
    Route::get('orders/{order}/receipt.bin', [\App\Http\Controllers\ReceiptPrintController::class, 'download'])->name('orders.receipt-download');

    // Hóa đơn điện tử (XML chuẩn TT78) cho đơn đã thanh toán
    Route::get('orders/{order}/e-invoice.xml', [\App\Http\Controllers\EInvoiceController::class, 'download'])->name('orders.e-invoice');

    // Tách bill / gộp đơn / chuyển bàn
    Route::get('api/orders/{order}/items', [\App\Http\Controllers\OrderActionsController::class, 'items'])->name('orders.items');
    Route::get('api/orders/available-tables', [\App\Http\Controllers\OrderActionsController::class, 'availableTables'])->name('orders.available-tables');
    Route::post('orders/{order}/split', [\App\Http\Controllers\OrderActionsController::class, 'split'])->name('orders.split');
    Route::post('orders/{order}/merge', [\App\Http\Controllers\OrderActionsController::class, 'merge'])->name('orders.merge');
    Route::post('orders/{order}/move-table', [\App\Http\Controllers\OrderActionsController::class, 'moveTable'])->name('orders.move-table');

    // Chương trình Khách hàng Thân thiết (Loyalty Program)
    Route::prefix('loyalty')->name('loyalty.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LoyaltyController::class, 'index'])->name('index');
        Route::get('/settings', [\App\Http\Controllers\LoyaltyController::class, 'settings'])->name('settings');
        Route::post('/settings', [\App\Http\Controllers\LoyaltyController::class, 'updateSettings'])->name('settings.update');
        Route::post('/tiers', [\App\Http\Controllers\LoyaltyController::class, 'storeTier'])->name('tiers.store');
        Route::patch('/tiers/{tier}', [\App\Http\Controllers\LoyaltyController::class, 'updateTier'])->name('tiers.update');
        Route::delete('/tiers/{tier}', [\App\Http\Controllers\LoyaltyController::class, 'destroyTier'])->name('tiers.destroy');
        Route::post('/rewards', [\App\Http\Controllers\LoyaltyController::class, 'storeReward'])->name('rewards.store');
        Route::patch('/rewards/{reward}', [\App\Http\Controllers\LoyaltyController::class, 'updateReward'])->name('rewards.update');
        Route::delete('/rewards/{reward}', [\App\Http\Controllers\LoyaltyController::class, 'destroyReward'])->name('rewards.destroy');
        Route::patch('/rewards/{reward}/toggle', [\App\Http\Controllers\LoyaltyController::class, 'toggleReward'])->name('rewards.toggle');
        Route::post('/adjust-points', [\App\Http\Controllers\LoyaltyController::class, 'adjustPoints'])->name('adjust-points');
        Route::get('/transactions', [\App\Http\Controllers\LoyaltyController::class, 'transactions'])->name('transactions');
        Route::get('/qr/{customer}', [\App\Http\Controllers\LoyaltyController::class, 'customerLoyaltyQr'])->name('qr');
    });

    // Thiết lập Khuyến mãi & Chiến lược cấu hình Combo thông minh
    Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');
    Route::put('promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
    Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy');
    Route::patch('promotions/{promotion}/toggle', [PromotionController::class, 'toggleActive'])->name('promotions.toggle');
    Route::post('promotions/{promotion}/approve', [PromotionController::class, 'approve'])->name('promotions.approve');
    Route::post('promotions/combos', [PromotionController::class, 'storeCombo'])->name('promotions.combos.store');
    Route::post('api/promotions/apply', [PromotionController::class, 'apply'])->middleware('throttle:voucher_apply')->name('promotions.apply');
    Route::post('api/promotions/validate', [PromotionController::class, 'validatePromotion'])->name('promotions.validate');
    Route::get('api/promotions/basket-analysis', [PromotionController::class, 'getBasketAnalysis'])->name('promotions.basket-analysis');
    Route::post('api/promotions/upsell-suggestion', [PromotionController::class, 'getUpsellSuggestion'])->name('promotions.upsell-suggestion');

    // Promotion QR Codes
    Route::get('promotions/{promotion}/qr', [PromotionController::class, 'generateQr'])->name('promotions.qr');
    Route::post('promotions/print-qr-sheet', [PromotionController::class, 'printQrSheet'])->name('promotions.print-qr');

    // Promotion Triggers
    Route::get('promotions/triggers', [\App\Http\Controllers\PromotionTriggerController::class, 'index'])->name('promotions.triggers.index');
    Route::post('promotions/triggers', [\App\Http\Controllers\PromotionTriggerController::class, 'store'])->name('promotions.triggers.store');
    Route::put('promotions/triggers/{trigger}', [\App\Http\Controllers\PromotionTriggerController::class, 'update'])->name('promotions.triggers.update');
    Route::delete('promotions/triggers/{trigger}', [\App\Http\Controllers\PromotionTriggerController::class, 'destroy'])->name('promotions.triggers.destroy');
    Route::patch('promotions/triggers/{trigger}/toggle', [\App\Http\Controllers\PromotionTriggerController::class, 'toggleActive'])->name('promotions.triggers.toggle');

    // Promotion Analytics
    Route::get('promotions/analytics', [\App\Http\Controllers\PromotionAnalyticsController::class, 'index'])->name('promotions.analytics.index');

    // Tables management
    Route::get('tables', [\App\Http\Controllers\TablesController::class, 'index'])->name('tables.index');
    Route::post('tables/areas', [\App\Http\Controllers\TablesController::class, 'storeArea'])->name('tables.areas.store');
    Route::post('tables', [\App\Http\Controllers\TablesController::class, 'store'])->name('tables.store')->middleware('tenant.quota:tables');
    Route::patch('tables/{table}', [\App\Http\Controllers\TablesController::class, 'update'])->name('tables.update');
    Route::delete('tables/{table}', [\App\Http\Controllers\TablesController::class, 'destroy'])->name('tables.destroy');
    Route::post('tables/{table}/regenerate-qr', [\App\Http\Controllers\TablesController::class, 'regenerateQr'])->name('tables.regenerate-qr');

    // Kitchen management
    Route::get('kitchen', [\App\Http\Controllers\KitchenController::class, 'index'])->name('kitchen.index');
    Route::post('kitchen/items/prepare-bulk', [\App\Http\Controllers\KitchenController::class, 'prepareBulk'])->name('kitchen.prepare-bulk');
    Route::post('kitchen/items/{item}/prepare', [\App\Http\Controllers\KitchenController::class, 'prepare'])->name('kitchen.prepare');
    Route::post('kitchen/items/{item}/serve', [\App\Http\Controllers\KitchenController::class, 'serve'])->name('kitchen.serve');
    Route::post('kitchen/products/{product}/pause', [\App\Http\Controllers\KitchenController::class, 'pause'])->name('kitchen.products.pause');
    Route::post('kitchen/products/{product}/out-of-stock', [\App\Http\Controllers\KitchenController::class, 'markOutOfStock'])->name('kitchen.products.out-of-stock');
    Route::post('kitchen/products/{product}/resume', [\App\Http\Controllers\KitchenController::class, 'resume'])->name('kitchen.products.resume');

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
    Route::post('orders/{order}/refund', [\App\Http\Controllers\OrdersController::class, 'refund'])->name('orders.refund');
    Route::post('settings/toggle-auto-pay', [\App\Http\Controllers\OrdersController::class, 'toggleAutoPaySetting'])->name('settings.toggle-auto-pay');

    // ── Đặt Bàn Trước (Table Reservations) ──────────────────────────────────
    Route::prefix('reservations')->name('reservations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TableReservationController::class, 'index'])->name('index');
        Route::post('{reservation}/confirm', [\App\Http\Controllers\TableReservationController::class, 'confirm'])->name('confirm');
        Route::post('{reservation}/seat', [\App\Http\Controllers\TableReservationController::class, 'seat'])->name('seat');
        Route::post('{reservation}/cancel', [\App\Http\Controllers\TableReservationController::class, 'cancel'])->name('cancel');
        Route::post('{reservation}/no-show', [\App\Http\Controllers\TableReservationController::class, 'noShow'])->name('no-show');
    });

    // Audit Logs (Owner & Manager — chỉ xem log của nhà hàng mình)
    Route::get('audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/export', [\App\Http\Controllers\AuditLogController::class, 'export'])->name('audit-logs.export');
    if (app()->environment('local', 'staging')) {
        Route::post('audit-logs/seed-demo', [\App\Http\Controllers\AuditLogController::class, 'seedDemo'])->name('audit-logs.seed-demo');
    }

    // Revenue / Reports
    Route::get('reports', [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/profit-loss', [\App\Http\Controllers\ProfitLossController::class, 'index'])->name('reports.profit-loss');
    Route::post('reports/generate', [\App\Http\Controllers\ReportsController::class, 'generate'])->name('reports.generate');
    Route::post('reports/send-email', [\App\Http\Controllers\ReportsController::class, 'sendReport'])->name('reports.send-email');
    Route::get('reports/export-pdf', [\App\Http\Controllers\ReportsController::class, 'exportPdf'])->name('reports.export-pdf');
    Route::get('reports/export-csv', [\App\Http\Controllers\ReportsController::class, 'exportCsv'])->name('reports.export-csv');
    Route::get('reports/reconciliation', [\App\Http\Controllers\ReportsController::class, 'crossReconciliation'])->name('reports.reconciliation');

    // Expenses / OPEX Tracker
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ExpenseController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\ExpenseController::class, 'store'])->name('store');
        Route::patch('/{expense}', [\App\Http\Controllers\ExpenseController::class, 'update'])->name('update');
        Route::delete('/{expense}', [\App\Http\Controllers\ExpenseController::class, 'destroy'])->name('destroy');

        Route::post('/recurring', [\App\Http\Controllers\ExpenseController::class, 'storeRecurring'])->name('recurring.store');
        Route::patch('/recurring/{recurring}', [\App\Http\Controllers\ExpenseController::class, 'updateRecurring'])->name('recurring.update');
        Route::delete('/recurring/{recurring}', [\App\Http\Controllers\ExpenseController::class, 'destroyRecurring'])->name('recurring.destroy');

        Route::post('/categories', [\App\Http\Controllers\ExpenseController::class, 'storeCategory'])->name('categories.store');
        Route::delete('/categories/{category}', [\App\Http\Controllers\ExpenseController::class, 'destroyCategory'])->name('categories.destroy');
    });

    // Quản lý Công nợ (Accounts Receivable / Payable)
    Route::prefix('debts')->name('debts.')->group(function () {
        Route::get('/', [\App\Http\Controllers\DebtController::class, 'index'])->name('index');
        Route::post('/payables/{payable}/pay', [\App\Http\Controllers\DebtController::class, 'paySupplier'])->name('payables.pay');
        Route::post('/receivables/{receivable}/collect', [\App\Http\Controllers\DebtController::class, 'collectCustomer'])->name('receivables.collect');
        Route::post('/customers/{customer}/credit', [\App\Http\Controllers\DebtController::class, 'updateCustomerCredit'])->name('customers.credit');
    });

    // KPI & Đánh giá hiệu suất nhân sự
    Route::prefix('kpis')->name('kpis.')->group(function () {
        Route::get('/', [\App\Http\Controllers\KpiController::class, 'index'])->name('index');
        Route::post('/recalculate', [\App\Http\Controllers\KpiController::class, 'recalculate'])->name('recalculate');
        Route::post('/{kpi}/finalize', [\App\Http\Controllers\KpiController::class, 'finalize'])->name('finalize');
        Route::post('/reviews', [\App\Http\Controllers\KpiController::class, 'storeReview'])->name('reviews.store');
        Route::post('/metrics/{metric}', [\App\Http\Controllers\KpiController::class, 'updateMetricConfig'])->name('metrics.update');
    });

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

    // Cash Flow Management
    Route::get('cash-flow', [\App\Http\Controllers\CashFlowController::class, 'index'])->name('cash-flow.index');
    Route::post('cash-flow/registers', [\App\Http\Controllers\CashFlowController::class, 'openRegister'])->name('cash-flow.registers.open');
    Route::post('cash-flow/transactions', [\App\Http\Controllers\CashFlowController::class, 'storeTransaction'])->name('cash-flow.transactions.store');
    Route::get('cash-flow/forecast', [\App\Http\Controllers\CashFlowController::class, 'getForecast'])->name('cash-flow.forecast');

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
    Route::get('suppliers/{supplier}/sla', [\App\Http\Controllers\SupplierPortalController::class, 'getSlaMetrics'])->name('suppliers.sla');
    Route::post('suppliers/auto-replenish', [\App\Http\Controllers\SupplierController::class, 'triggerAutoReplenish'])->name('suppliers.auto-replenish');
    Route::post('suppliers/ocr-invoice', [\App\Http\Controllers\SupplierController::class, 'ocrInvoice'])->name('suppliers.ocr-invoice');
    Route::get('api/suppliers/replenish-cockpit', [\App\Http\Controllers\SupplierController::class, 'getReplenishCockpit'])->name('suppliers.replenish-cockpit');
    Route::post('api/suppliers/draft-po-bulk', [\App\Http\Controllers\SupplierController::class, 'draftPoBulk'])->name('suppliers.draft-po-bulk');
    Route::get('api/suppliers/sla-dashboard', [\App\Http\Controllers\SupplierController::class, 'getSlaDashboard'])->name('suppliers.sla-dashboard');

    // Điều phối và chuyển kho nội bộ liên chi nhánh
    Route::get('api/inventory/transfer-recommendations', [\App\Http\Controllers\InternalTransferController::class, 'transferRecommendations'])->name('inventory.transfer-recommendations');
    Route::post('api/inventory/internal-transfers', [\App\Http\Controllers\InternalTransferController::class, 'storeInternalTransfer'])->name('inventory.internal-transfers');
    Route::get('api/inventory/internal-transfers', [\App\Http\Controllers\InternalTransferController::class, 'listInternalTransfers'])->name('inventory.internal-transfers.list');

    // Quản lý Đấu thầu RFP (Dành cho nhà hàng)
    Route::get('rfps', [\App\Http\Controllers\RfpController::class, 'index'])->name('rfps.index');
    Route::post('rfps', [\App\Http\Controllers\RfpController::class, 'store'])->name('rfps.store');
    Route::post('rfps/{rfp}/close', [\App\Http\Controllers\RfpController::class, 'close'])->name('rfps.close');
    Route::post('rfps/bids/{bid}/accept', [\App\Http\Controllers\RfpController::class, 'acceptBid'])->name('rfps.bids.accept');

    // Portal Chuỗi cung ứng (Dành cho nhà cung cấp)
    Route::prefix('supplier')->name('supplier.')->middleware('role:supplier|owner|manager')->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\SupplierPortalController::class, 'supplierDashboard'])->name('dashboard');
        Route::get('catalog', [\App\Http\Controllers\SupplierPortalController::class, 'supplierCatalog'])->name('catalog');
        Route::post('catalog', [\App\Http\Controllers\SupplierPortalController::class, 'storeCatalogItem'])->name('catalog.store');
        Route::get('orders', [\App\Http\Controllers\SupplierPortalController::class, 'supplierOrders'])->name('orders');
        Route::post('orders/{purchaseOrder}/status', [\App\Http\Controllers\SupplierPortalController::class, 'updateOrderStatus'])->name('orders.update-status');
        Route::get('rfps', [\App\Http\Controllers\RfpController::class, 'supplierIndex'])->name('rfps');
        Route::post('rfps/{rfp}/bid', [\App\Http\Controllers\RfpController::class, 'supplierSubmitBid'])->name('rfps.bid');
    });

    // Quản lý đơn đệm QR và Nhật ký hủy (Staff & Manager)
    Route::get('api/temporary-orders', [\App\Http\Controllers\StaffQROrderController::class, 'index'])->name('temporary-orders.index');
    Route::post('api/temporary-orders/{temporaryOrder}/confirm', [\App\Http\Controllers\StaffQROrderController::class, 'confirm'])->name('temporary-orders.confirm');
    Route::post('api/temporary-orders/{temporaryOrder}/cancel', [\App\Http\Controllers\StaffQROrderController::class, 'cancel'])->name('temporary-orders.cancel');
    Route::get('api/temporary-orders/rejected-logs', [\App\Http\Controllers\StaffQROrderController::class, 'rejectedLogs'])->name('temporary-orders.rejected-logs');

    // Trợ lý AI Chiến lược (AI Advisor)
    Route::get('ai-advisor', [\App\Http\Controllers\ChatbotController::class, 'advisorIndex'])->name('ai-advisor.index');
    Route::post('api/chatbot/advisor-message', [\App\Http\Controllers\ChatbotController::class, 'advisorMessage'])->name('chatbot.advisor-message');
    Route::get('api/chatbot/advisor-history', [\App\Http\Controllers\ChatbotController::class, 'advisorHistory'])->name('chatbot.advisor-history');

    // ── Smart Routing & Dispatch ────────────────────────────────────────────
    Route::prefix('delivery')->name('delivery.')->group(function () {
        // Manager dashboard
        Route::get('/', [\App\Http\Controllers\Delivery\DeliveryManagementController::class, 'index'])->name('index');

        // Manager API
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('unassigned-orders',  [\App\Http\Controllers\Delivery\DeliveryManagementController::class, 'unassignedOrders'])->name('unassigned-orders');
            Route::get('active-shippers',    [\App\Http\Controllers\Delivery\DeliveryManagementController::class, 'activeShippers'])->name('active-shippers');
            Route::get('stats',              [\App\Http\Controllers\Delivery\DeliveryManagementController::class, 'stats'])->name('stats');
            Route::post('optimize-route',    [\App\Http\Controllers\Delivery\DeliveryManagementController::class, 'optimizeRoute'])->name('optimize-route');
            Route::post('suggest-shippers',  [\App\Http\Controllers\Delivery\DeliveryManagementController::class, 'suggestShippers'])->name('suggest-shippers');
            Route::post('suggest-batches',   [\App\Http\Controllers\Delivery\DeliveryManagementController::class, 'suggestBatches'])->name('suggest-batches');
            Route::post('batches',           [\App\Http\Controllers\Delivery\DeliveryManagementController::class, 'createBatch'])->name('batches.create');
            Route::post('batches/{batch}/dispatch', [\App\Http\Controllers\Delivery\DeliveryManagementController::class, 'dispatchBatch'])->name('batches.dispatch');
            Route::post('batches/{batch}/complete', [\App\Http\Controllers\Delivery\DeliveryManagementController::class, 'completeBatch'])->name('batches.complete');
            Route::post('batches/{batch}/cancel',   [\App\Http\Controllers\Delivery\DeliveryManagementController::class, 'cancelBatch'])->name('batches.cancel');

            // Shipper PWA API
            Route::post('shipper/location',        [\App\Http\Controllers\Delivery\ShipperPwaController::class, 'updateLocation'])->name('shipper.location');
            Route::post('shipper/location/batch',  [\App\Http\Controllers\Delivery\ShipperPwaController::class, 'updateLocationBatch'])->name('shipper.location.batch');
            Route::post('shipper/items/{item}/status', [\App\Http\Controllers\Delivery\ShipperPwaController::class, 'updateItemStatus'])->name('shipper.item.status');
        });

        // Shipper PWA page
        Route::get('shipper', [\App\Http\Controllers\Delivery\ShipperPwaController::class, 'app'])->name('shipper.app');

        // Shippers CRUD
        Route::get('shippers',          [\App\Http\Controllers\Delivery\ShipperController::class, 'index'])->name('shippers.index');
        Route::post('shippers',         [\App\Http\Controllers\Delivery\ShipperController::class, 'store'])->name('shippers.store');
        Route::patch('shippers/{shipper}', [\App\Http\Controllers\Delivery\ShipperController::class, 'update'])->name('shippers.update');
        Route::delete('shippers/{shipper}', [\App\Http\Controllers\Delivery\ShipperController::class, 'destroy'])->name('shippers.destroy');
    });

    // Employee Self-Service Portal
    Route::prefix('employee-portal')
        ->middleware(['throttle:employee_portal'])
        ->name('employee-portal.')
        ->group(function () {
        Route::get('/', [\App\Http\Controllers\EmployeePortalController::class, 'index'])->name('index');
        Route::get('/data', [\App\Http\Controllers\EmployeePortalController::class, 'getDashboardData'])->name('data');
        Route::get('/salaries', [\App\Http\Controllers\EmployeePortalController::class, 'getSalaries'])->name('salaries');
        Route::get('/leaves', [\App\Http\Controllers\EmployeePortalController::class, 'getLeaves'])->name('leaves');
        Route::post('/leaves', [\App\Http\Controllers\EmployeePortalController::class, 'storeLeaveRequest'])->name('leaves.store');
        Route::get('/swaps', [\App\Http\Controllers\EmployeePortalController::class, 'getSwaps'])->name('swaps');
        Route::post('/swaps/request', [\App\Http\Controllers\EmployeePortalController::class, 'requestSwap'])->name('swaps.request');
        Route::post('/swaps/{swap}/respond', [\App\Http\Controllers\EmployeePortalController::class, 'respondSwap'])->name('swaps.respond');
        Route::post('/notifications/read-all', [\App\Http\Controllers\EmployeePortalController::class, 'readAllNotifications'])->name('notifications.read-all');
    });

    // Chi nhánh làm việc
    Route::post('branch/switch', [\App\Http\Controllers\BranchSwitchController::class, 'switchBranch'])->name('branch.switch');
});

