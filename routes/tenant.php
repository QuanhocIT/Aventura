<?php

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BIDashboardController;
use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\BusinessGoalController;
use App\Http\Controllers\CashFlowController;
use App\Http\Controllers\CdpController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\Delivery\DeliveryManagementController;
use App\Http\Controllers\Delivery\ShipperController;
use App\Http\Controllers\Delivery\ShipperPwaController;
use App\Http\Controllers\EInvoiceController;
use App\Http\Controllers\EmployeeManagementController;
use App\Http\Controllers\EmployeePortalController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FraudController;
use App\Http\Controllers\GeoAnalyticsController;
use App\Http\Controllers\IntegrationSettingsController;
use App\Http\Controllers\InternalTransferController;
use App\Http\Controllers\InventoryManagementController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\LeaveScheduleController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\MenuEngineeringController;
use App\Http\Controllers\MenuInsightController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OnlineStoreSettingsController;
use App\Http\Controllers\OperationPolicyController;
use App\Http\Controllers\OperationsChecklistController;
use App\Http\Controllers\OrderActionsController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PosDeviceController;
use App\Http\Controllers\ProductManagementController;
use App\Http\Controllers\ProfitLossController;
use App\Http\Controllers\PromotionAnalyticsController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PromotionTriggerController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReceiptPrintController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RfpController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ShiftClosingController;
use App\Http\Controllers\ShiftSwapController;
use App\Http\Controllers\StaffQROrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPortalController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TableReservationController;
use App\Http\Controllers\TablesController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\ViolationReportController;
use App\Http\Controllers\WasteManagementController;
use App\Http\Controllers\WeatherForecastController;
use App\Http\Controllers\WebhookEndpointController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tenant.subscription', 'tenant.ratelimit', 'tenant.branch'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Onboarding API
    Route::post('api/onboarding/update', [OnboardingController::class, 'updateProgress'])->name('onboarding.update');
    Route::post('api/onboarding/reset', [OnboardingController::class, 'resetProgress'])->name('onboarding.reset');

    // Support Portal
    Route::get('support', [SupportController::class, 'index'])->name('support.index');
    Route::post('support/tickets', [SupportController::class, 'storeTicket'])->name('support.tickets.store');
    Route::post('support/tickets/{ticket}/replies', [SupportController::class, 'storeReply'])->name('support.tickets.replies.store');

    // Functional Pages for Guided Tours
    Route::get('products', [ProductManagementController::class, 'productsPage'])->name('products.index');
    Route::get('api/products/menu-insights', [MenuInsightController::class, 'index'])->name('products.menu-insights');
    Route::get('api/analytics/weather-menu-forecast', [WeatherForecastController::class, 'index'])->name('analytics.weather-forecast');
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
    Route::post('inventory/auto-po/generate', [PurchaseOrderController::class, 'generateAutoPo'])->name('inventory.auto-po.generate');

    Route::get('employees', [EmployeeManagementController::class, 'employeesPage'])->name('employees.index');
    Route::post('employees', [EmployeeManagementController::class, 'storeEmployee'])->name('employees.store')->middleware('tenant.quota:employees');
    Route::patch('employees/{employee}', [EmployeeManagementController::class, 'updateEmployee'])->name('employees.update');
    Route::patch('employees/{employee}/toggle-status', [EmployeeManagementController::class, 'toggleEmployeeStatus'])->name('employees.toggle-status');
    Route::get('employees/{employee}/export-profile', [EmployeeManagementController::class, 'exportEmployeeProfile'])->name('employees.export-profile');
    Route::get('employees/{employee}/citizen-id/{side}', [EmployeeManagementController::class, 'citizenIdImage'])->name('employees.citizen-id');
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
    Route::get('customers/cdp', [CdpController::class, 'index'])->name('customers.cdp');
    Route::post('customers/cdp/recalculate', [CdpController::class, 'recalculate'])->name('customers.cdp.recalculate');
    Route::get('customers/cdp/segment/{segment}', [CdpController::class, 'segment'])->name('customers.cdp.segment');
    Route::post('customers/cdp/campaigns', [CdpController::class, 'storeCampaign'])->name('customers.cdp.campaigns');

    // Business Intelligence Dashboard
    Route::get('bi-dashboard', [BIDashboardController::class, 'index'])->name('bi-dashboard.index');

    // Phân Tích Địa Lý & Vùng Phục Vụ
    Route::get('geo-analytics', [GeoAnalyticsController::class, 'index'])->name('geo-analytics.index');
    Route::get('api/geo-analytics/heatmap', [GeoAnalyticsController::class, 'apiHeatmap'])->name('geo-analytics.heatmap');

    // Mục Tiêu & OKR Doanh Nghiệp
    Route::prefix('business-goals')->name('goals.')->group(function () {
        Route::get('/', [BusinessGoalController::class, 'index'])->name('index');
        Route::post('/', [BusinessGoalController::class, 'store'])->name('store');
        Route::delete('/{goal}', [BusinessGoalController::class, 'destroy'])->name('destroy');
        Route::post('/{goal}/actions', [BusinessGoalController::class, 'storeAction'])->name('actions.store');
        Route::patch('/actions/{action}/toggle', [BusinessGoalController::class, 'toggleAction'])->name('actions.toggle');
        Route::patch('/{goal}/value', [BusinessGoalController::class, 'updateCustomValue'])->name('value');
    });

    // Phân Quyền Chi Tiết & Giới Hạn Thao Tác
    Route::get('operation-policies', [OperationPolicyController::class, 'index'])->name('operation-policies.index');
    Route::post('operation-policies', [OperationPolicyController::class, 'update'])->name('operation-policies.update');
    Route::post('api/operation-policies/check', [OperationPolicyController::class, 'checkPermission'])->name('operation-policies.check');

    // Quản Lý Thiết Bị & Bảo Trì
    Route::prefix('equipment')->name('equipment.')->group(function () {
        Route::get('/', [EquipmentController::class, 'index'])->name('index');
        Route::post('/', [EquipmentController::class, 'store'])->name('store');
        Route::patch('/{equipment}', [EquipmentController::class, 'update'])->name('update');
        Route::delete('/{equipment}', [EquipmentController::class, 'destroy'])->name('destroy');
        Route::post('/report-issue', [EquipmentController::class, 'reportIssue'])->name('report');
        Route::post('/logs/{log}/complete', [EquipmentController::class, 'completeLog'])->name('logs.complete');
    });

    // Đào Tạo & Onboarding Nhân Viên
    Route::prefix('training')->name('training.')->group(function () {
        Route::get('/', [TrainingController::class, 'index'])->name('index');
        Route::post('/courses', [TrainingController::class, 'storeCourse'])->name('courses.store');
        Route::delete('/courses/{course}', [TrainingController::class, 'destroyCourse'])->name('courses.destroy');
        Route::post('/courses/{course}/lessons', [TrainingController::class, 'storeLesson'])->name('lessons.store');
        Route::post('/courses/{course}/quizzes', [TrainingController::class, 'storeQuiz'])->name('quizzes.store');
        Route::post('/enroll', [TrainingController::class, 'enrollEmployee'])->name('enroll');
        Route::post('/complete-lesson', [TrainingController::class, 'completeLesson'])->name('complete-lesson');
        Route::post('/submit-quiz', [TrainingController::class, 'submitQuiz'])->name('submit-quiz');
    });

    // Checklist Vận Hành Hàng Ngày
    Route::prefix('operations-checklist')->name('checklist.')->group(function () {
        Route::get('/', [OperationsChecklistController::class, 'index'])->name('index');
        Route::post('/complete', [OperationsChecklistController::class, 'completeItem'])->name('complete');
        Route::post('/uncomplete', [OperationsChecklistController::class, 'uncompleteItem'])->name('uncomplete');
        Route::post('/templates', [OperationsChecklistController::class, 'storeTemplate'])->name('templates.store');
        Route::delete('/templates/{template}', [OperationsChecklistController::class, 'destroyTemplate'])->name('templates.destroy');
        Route::get('/api/weekly-report', [OperationsChecklistController::class, 'weeklyReport'])->name('weekly-report');
    });

    // Quản Lý Hao Hụt & Lãng Phí (Waste Management)
    Route::prefix('waste-management')->name('waste.')->group(function () {
        Route::get('/', [WasteManagementController::class, 'index'])->name('index');
        Route::get('/api/dashboard', [WasteManagementController::class, 'apiDashboard'])->name('dashboard');
        Route::get('/api/trend', [WasteManagementController::class, 'apiTrend'])->name('trend');
        Route::get('/api/suggestions', [WasteManagementController::class, 'apiSuggestions'])->name('suggestions');
        Route::get('/api/expiring', [WasteManagementController::class, 'apiExpiring'])->name('expiring');
    });

    // Menu Engineering Nâng Cao
    Route::prefix('menu-engineering')->name('menu-engineering.')->group(function () {
        Route::get('/', [MenuEngineeringController::class, 'index'])->name('index');
        Route::get('/api/scoring', [MenuEngineeringController::class, 'scoring'])->name('scoring');
        Route::post('/display-order', [MenuEngineeringController::class, 'updateDisplayOrder'])->name('display-order');
        Route::patch('/products/{product}/time-slot', [MenuEngineeringController::class, 'updateTimeSlot'])->name('time-slot');
        Route::post('/price-tests', [MenuEngineeringController::class, 'storePriceTest'])->name('price-tests.store');
        Route::post('/price-tests/{test}/complete', [MenuEngineeringController::class, 'completePriceTest'])->name('price-tests.complete');
        Route::post('/price-tests/{test}/cancel', [MenuEngineeringController::class, 'cancelPriceTest'])->name('price-tests.cancel');
    });

    // Cấu hình Cửa hàng Online (Digital Ordering Hub)
    Route::get('online-store', [OnlineStoreSettingsController::class, 'index'])->name('online-store.index');
    Route::post('online-store', [OnlineStoreSettingsController::class, 'update'])->name('online-store.update');

    // Trung tâm Tích hợp (Integration Hub)
    Route::prefix('settings/integrations')->name('integrations.')->group(function () {
        Route::get('/', [IntegrationSettingsController::class, 'index'])->name('index');
        Route::post('/simulate-order', [IntegrationSettingsController::class, 'simulateOrder'])->name('simulate-order');
        Route::post('/test-zalo', [IntegrationSettingsController::class, 'testZalo'])->name('test-zalo');
        Route::get('/misa/export', [IntegrationSettingsController::class, 'misaExport'])->name('misa.export');
        Route::post('/devices', [PosDeviceController::class, 'store'])->name('devices.store');
        Route::delete('/devices/{device}', [PosDeviceController::class, 'destroy'])->name('devices.destroy');
        Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::patch('/api-keys/{apiKey}/toggle', [ApiKeyController::class, 'toggle'])->name('api-keys.toggle');
        Route::post('/api-keys/{apiKey}/rotate', [ApiKeyController::class, 'rotate'])->name('api-keys.rotate');
        Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
        Route::post('/webhooks', [WebhookEndpointController::class, 'store'])->name('webhooks.store');
        Route::patch('/webhooks/{endpoint}/toggle', [WebhookEndpointController::class, 'toggle'])->name('webhooks.toggle');
        Route::post('/webhooks/{endpoint}/test', [WebhookEndpointController::class, 'test'])->name('webhooks.test');
        Route::delete('/webhooks/{endpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhooks.destroy');
        Route::post('/{provider}/demo', [IntegrationSettingsController::class, 'connectDemo'])->name('demo');
        Route::post('/{provider}', [IntegrationSettingsController::class, 'update'])->name('update');
        Route::patch('/{provider}/toggle', [IntegrationSettingsController::class, 'toggle'])->name('toggle');
    });

    // In hóa đơn qua máy in nhiệt
    Route::post('orders/{order}/print-receipt', [ReceiptPrintController::class, 'print'])->name('orders.print-receipt');
    Route::get('orders/{order}/receipt.bin', [ReceiptPrintController::class, 'download'])->name('orders.receipt-download');

    // Hóa đơn điện tử (XML chuẩn TT78) cho đơn đã thanh toán
    Route::get('orders/{order}/e-invoice.xml', [EInvoiceController::class, 'download'])->name('orders.e-invoice');

    // Tách bill / gộp đơn / chuyển bàn
    Route::get('api/orders/{order}/items', [OrderActionsController::class, 'items'])->name('orders.items');
    Route::get('api/orders/available-tables', [OrderActionsController::class, 'availableTables'])->name('orders.available-tables');
    Route::post('orders/{order}/split', [OrderActionsController::class, 'split'])->name('orders.split');
    Route::post('orders/{order}/merge', [OrderActionsController::class, 'merge'])->name('orders.merge');
    Route::post('orders/{order}/move-table', [OrderActionsController::class, 'moveTable'])->name('orders.move-table');

    // Chương trình Khách hàng Thân thiết (Loyalty Program)
    Route::prefix('loyalty')->name('loyalty.')->group(function () {
        Route::get('/', [LoyaltyController::class, 'index'])->name('index');
        Route::get('/settings', [LoyaltyController::class, 'settings'])->name('settings');
        Route::post('/settings', [LoyaltyController::class, 'updateSettings'])->name('settings.update');
        Route::post('/tiers', [LoyaltyController::class, 'storeTier'])->name('tiers.store');
        Route::patch('/tiers/{tier}', [LoyaltyController::class, 'updateTier'])->name('tiers.update');
        Route::delete('/tiers/{tier}', [LoyaltyController::class, 'destroyTier'])->name('tiers.destroy');
        Route::post('/rewards', [LoyaltyController::class, 'storeReward'])->name('rewards.store');
        Route::patch('/rewards/{reward}', [LoyaltyController::class, 'updateReward'])->name('rewards.update');
        Route::delete('/rewards/{reward}', [LoyaltyController::class, 'destroyReward'])->name('rewards.destroy');
        Route::patch('/rewards/{reward}/toggle', [LoyaltyController::class, 'toggleReward'])->name('rewards.toggle');
        Route::post('/adjust-points', [LoyaltyController::class, 'adjustPoints'])->name('adjust-points');
        Route::get('/transactions', [LoyaltyController::class, 'transactions'])->name('transactions');
        Route::get('/qr/{customer}', [LoyaltyController::class, 'customerLoyaltyQr'])->name('qr');
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
    Route::get('promotions/triggers', [PromotionTriggerController::class, 'index'])->name('promotions.triggers.index');
    Route::post('promotions/triggers', [PromotionTriggerController::class, 'store'])->name('promotions.triggers.store');
    Route::put('promotions/triggers/{trigger}', [PromotionTriggerController::class, 'update'])->name('promotions.triggers.update');
    Route::delete('promotions/triggers/{trigger}', [PromotionTriggerController::class, 'destroy'])->name('promotions.triggers.destroy');
    Route::patch('promotions/triggers/{trigger}/toggle', [PromotionTriggerController::class, 'toggleActive'])->name('promotions.triggers.toggle');

    // Promotion Analytics
    Route::get('promotions/analytics', [PromotionAnalyticsController::class, 'index'])->name('promotions.analytics.index');

    // Tables management
    Route::get('tables', [TablesController::class, 'index'])->name('tables.index');
    Route::post('tables/areas', [TablesController::class, 'storeArea'])->name('tables.areas.store');
    Route::delete('tables/areas/{area}', [TablesController::class, 'destroyArea'])->name('tables.areas.destroy');
    Route::post('tables', [TablesController::class, 'store'])->name('tables.store')->middleware('tenant.quota:tables');
    Route::patch('tables/{table}', [TablesController::class, 'update'])->name('tables.update');
    Route::delete('tables/{table}', [TablesController::class, 'destroy'])->name('tables.destroy');
    Route::post('tables/{table}/regenerate-qr', [TablesController::class, 'regenerateQr'])->name('tables.regenerate-qr');

    // Kitchen management
    Route::get('kitchen', [KitchenController::class, 'index'])->name('kitchen.index');
    Route::post('kitchen/items/prepare-bulk', [KitchenController::class, 'prepareBulk'])->name('kitchen.prepare-bulk');
    Route::post('kitchen/items/{item}/prepare', [KitchenController::class, 'prepare'])->name('kitchen.prepare');
    Route::post('kitchen/items/{item}/serve', [KitchenController::class, 'serve'])->name('kitchen.serve');
    Route::post('kitchen/products/{product}/pause', [KitchenController::class, 'pause'])->name('kitchen.products.pause');
    Route::post('kitchen/products/{product}/out-of-stock', [KitchenController::class, 'markOutOfStock'])->name('kitchen.products.out-of-stock');
    Route::post('kitchen/products/{product}/resume', [KitchenController::class, 'resume'])->name('kitchen.products.resume');

    // Orders management
    Route::get('orders/create', [OrdersController::class, 'create'])->name('orders.create');
    Route::post('orders', [OrdersController::class, 'store'])->name('orders.store');
    Route::get('orders', [OrdersController::class, 'index'])->name('orders.index');
    Route::patch('orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('orders/{order}/split', [OrdersController::class, 'split'])->name('orders.split');
    Route::patch('orders/{order}/override-split-penalty', [OrdersController::class, 'overrideSplitPenalty'])->name('orders.override-split-penalty');
    Route::patch('orders/{order}', [OrdersController::class, 'update'])->name('orders.update');
    Route::post('orders/{order}/pay', [OrdersController::class, 'pay'])->name('orders.pay');
    Route::post('orders/{order}/confirm-qr', [OrdersController::class, 'confirmQr'])->name('orders.confirm-qr');
    Route::post('orders/{order}/refund', [OrdersController::class, 'refund'])->name('orders.refund');
    Route::post('settings/toggle-auto-pay', [OrdersController::class, 'toggleAutoPaySetting'])->name('settings.toggle-auto-pay');

    // ── Đặt Bàn Trước (Table Reservations) ──────────────────────────────────
    Route::prefix('reservations')->name('reservations.')->group(function () {
        Route::get('/', [TableReservationController::class, 'index'])->name('index');
        Route::post('{reservation}/confirm', [TableReservationController::class, 'confirm'])->name('confirm');
        Route::post('{reservation}/seat', [TableReservationController::class, 'seat'])->name('seat');
        Route::post('{reservation}/cancel', [TableReservationController::class, 'cancel'])->name('cancel');
        Route::post('{reservation}/no-show', [TableReservationController::class, 'noShow'])->name('no-show');
    });

    // Audit Logs (Owner & Manager — chỉ xem log của nhà hàng mình)
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
    if (app()->environment('local', 'staging')) {
        Route::post('audit-logs/seed-demo', [AuditLogController::class, 'seedDemo'])->name('audit-logs.seed-demo');
    }

    // Revenue / Reports
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/profit-loss', [ProfitLossController::class, 'index'])->name('reports.profit-loss');
    Route::post('reports/generate', [ReportsController::class, 'generate'])->name('reports.generate');
    Route::post('reports/send-email', [ReportsController::class, 'sendReport'])->name('reports.send-email');
    Route::get('reports/export-pdf', [ReportsController::class, 'exportPdf'])->name('reports.export-pdf');
    Route::get('reports/export-csv', [ReportsController::class, 'exportCsv'])->name('reports.export-csv');
    Route::get('reports/export-excel', [ReportsController::class, 'exportExcel'])->name('reports.export-excel');
    Route::get('reports/reconciliation', [ReportsController::class, 'crossReconciliation'])->name('reports.reconciliation');

    // Expenses / OPEX Tracker
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::patch('/{expense}', [ExpenseController::class, 'update'])->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');

        Route::post('/recurring', [ExpenseController::class, 'storeRecurring'])->name('recurring.store');
        Route::patch('/recurring/{recurring}', [ExpenseController::class, 'updateRecurring'])->name('recurring.update');
        Route::delete('/recurring/{recurring}', [ExpenseController::class, 'destroyRecurring'])->name('recurring.destroy');

        Route::post('/categories', [ExpenseController::class, 'storeCategory'])->name('categories.store');
        Route::delete('/categories/{category}', [ExpenseController::class, 'destroyCategory'])->name('categories.destroy');
    });

    // Quản lý Công nợ (Accounts Receivable / Payable)
    Route::prefix('debts')->name('debts.')->group(function () {
        Route::get('/', [DebtController::class, 'index'])->name('index');
        Route::post('/payables/{payable}/pay', [DebtController::class, 'paySupplier'])->name('payables.pay');
        Route::post('/receivables/{receivable}/collect', [DebtController::class, 'collectCustomer'])->name('receivables.collect');
        Route::post('/customers/{customer}/credit', [DebtController::class, 'updateCustomerCredit'])->name('customers.credit');
    });

    // KPI & Đánh giá hiệu suất nhân sự
    Route::prefix('kpis')->name('kpis.')->group(function () {
        Route::get('/', [KpiController::class, 'index'])->name('index');
        Route::post('/recalculate', [KpiController::class, 'recalculate'])->name('recalculate');
        Route::post('/{kpi}/finalize', [KpiController::class, 'finalize'])->name('finalize');
        Route::post('/reviews', [KpiController::class, 'storeReview'])->name('reviews.store');
        Route::post('/metrics/{metric}', [KpiController::class, 'updateMetricConfig'])->name('metrics.update');
    });

    // Bảng lương
    Route::get('salaries', [SalaryController::class, 'index'])->name('salaries.index');
    Route::post('salaries/generate', [SalaryController::class, 'generate'])->name('salaries.generate');
    Route::post('salaries/approve-bulk', [SalaryController::class, 'bulkApprove'])->name('salaries.approve-bulk');
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

    // Cash Flow Management
    Route::get('cash-flow', [CashFlowController::class, 'index'])->name('cash-flow.index');
    Route::post('cash-flow/registers', [CashFlowController::class, 'openRegister'])->name('cash-flow.registers.open');
    Route::post('cash-flow/transactions', [CashFlowController::class, 'storeTransaction'])->name('cash-flow.transactions.store');
    Route::get('cash-flow/forecast', [CashFlowController::class, 'getForecast'])->name('cash-flow.forecast');

    // Support booking demo
    Route::post('support/bookings', [SupportController::class, 'storeBooking'])->name('support.bookings.store');

    // Kiểm toán gian lận
    Route::get('fraud', [FraudController::class, 'index'])->name('fraud.index');
    Route::post('fraud/violation', [FraudController::class, 'createViolation'])->name('fraud.violation.store');
    Route::post('fraud/verify-pin', [FraudController::class, 'verifyManagerPin'])->name('fraud.pin.verify');

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
    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::patch('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    Route::post('suppliers/{supplier}/place-order', [SupplierController::class, 'placeOrder'])->name('suppliers.place-order');
    Route::post('suppliers/orders/{purchaseOrder}/approve', [SupplierController::class, 'approveOrder'])->name('suppliers.orders.approve');
    Route::post('suppliers/orders/{purchaseOrder}/verify', [SupplierController::class, 'verifyOrder'])->name('suppliers.orders.verify');
    Route::post('suppliers/orders/{purchaseOrder}/release-escrow', [SupplierController::class, 'releaseEscrow'])->name('suppliers.orders.release-escrow');
    Route::post('suppliers/orders/{purchaseOrder}/refund-escrow', [SupplierController::class, 'refundEscrow'])->name('suppliers.orders.refund-escrow');
    Route::get('suppliers/{supplier}/ingredients/{ingredient}/price-analytics', [SupplierController::class, 'priceAnalytics'])->name('suppliers.price-analytics');
    Route::get('suppliers/{supplier}/sla', [SupplierPortalController::class, 'getSlaMetrics'])->name('suppliers.sla');
    Route::post('suppliers/auto-replenish', [SupplierController::class, 'triggerAutoReplenish'])->name('suppliers.auto-replenish');
    Route::post('suppliers/ocr-invoice', [SupplierController::class, 'ocrInvoice'])->name('suppliers.ocr-invoice');
    Route::get('api/suppliers/replenish-cockpit', [SupplierController::class, 'getReplenishCockpit'])->name('suppliers.replenish-cockpit');
    Route::post('api/suppliers/draft-po-bulk', [SupplierController::class, 'draftPoBulk'])->name('suppliers.draft-po-bulk');
    Route::get('api/suppliers/sla-dashboard', [SupplierController::class, 'getSlaDashboard'])->name('suppliers.sla-dashboard');

    // Điều phối và chuyển kho nội bộ liên chi nhánh
    Route::get('api/inventory/transfer-recommendations', [InternalTransferController::class, 'transferRecommendations'])->name('inventory.transfer-recommendations');
    Route::post('api/inventory/internal-transfers', [InternalTransferController::class, 'storeInternalTransfer'])->name('inventory.internal-transfers');
    Route::get('api/inventory/internal-transfers', [InternalTransferController::class, 'listInternalTransfers'])->name('inventory.internal-transfers.list');

    // Quản lý Đấu thầu RFP (Dành cho nhà hàng)
    Route::get('rfps', [RfpController::class, 'index'])->name('rfps.index');
    Route::post('rfps', [RfpController::class, 'store'])->name('rfps.store');
    Route::post('rfps/{rfp}/close', [RfpController::class, 'close'])->name('rfps.close');
    Route::post('rfps/bids/{bid}/accept', [RfpController::class, 'acceptBid'])->name('rfps.bids.accept');

    // Portal Chuỗi cung ứng (Dành cho nhà cung cấp)
    Route::prefix('supplier')->name('supplier.')->middleware('role:supplier|owner|manager')->group(function () {
        Route::get('dashboard', [SupplierPortalController::class, 'supplierDashboard'])->name('dashboard');
        Route::get('catalog', [SupplierPortalController::class, 'supplierCatalog'])->name('catalog');
        Route::post('catalog', [SupplierPortalController::class, 'storeCatalogItem'])->name('catalog.store');
        Route::get('orders', [SupplierPortalController::class, 'supplierOrders'])->name('orders');
        Route::post('orders/{purchaseOrder}/status', [SupplierPortalController::class, 'updateOrderStatus'])->name('orders.update-status');
        Route::get('rfps', [RfpController::class, 'supplierIndex'])->name('rfps');
        Route::post('rfps/{rfp}/bid', [RfpController::class, 'supplierSubmitBid'])->name('rfps.bid');
    });

    // Quản lý đơn đệm QR và Nhật ký hủy (Staff & Manager)
    Route::get('api/temporary-orders', [StaffQROrderController::class, 'index'])->name('temporary-orders.index');
    Route::post('api/temporary-orders/{temporaryOrder}/confirm', [StaffQROrderController::class, 'confirm'])->name('temporary-orders.confirm');
    Route::post('api/temporary-orders/{temporaryOrder}/cancel', [StaffQROrderController::class, 'cancel'])->name('temporary-orders.cancel');
    Route::get('api/temporary-orders/rejected-logs', [StaffQROrderController::class, 'rejectedLogs'])->name('temporary-orders.rejected-logs');

    // Trợ lý AI Chiến lược (AI Advisor)
    Route::get('ai-advisor', [ChatbotController::class, 'advisorIndex'])->name('ai-advisor.index');
    Route::post('api/chatbot/advisor-message', [ChatbotController::class, 'advisorMessage'])->name('chatbot.advisor-message');
    Route::get('api/chatbot/advisor-history', [ChatbotController::class, 'advisorHistory'])->name('chatbot.advisor-history');

    // ── Smart Routing & Dispatch ────────────────────────────────────────────
    Route::prefix('delivery')->name('delivery.')->group(function () {
        // Manager dashboard
        Route::get('/', [DeliveryManagementController::class, 'index'])->name('index');

        // Manager API
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('unassigned-orders', [DeliveryManagementController::class, 'unassignedOrders'])->name('unassigned-orders');
            Route::get('active-shippers', [DeliveryManagementController::class, 'activeShippers'])->name('active-shippers');
            Route::get('stats', [DeliveryManagementController::class, 'stats'])->name('stats');
            Route::post('optimize-route', [DeliveryManagementController::class, 'optimizeRoute'])->name('optimize-route');
            Route::post('suggest-shippers', [DeliveryManagementController::class, 'suggestShippers'])->name('suggest-shippers');
            Route::post('suggest-batches', [DeliveryManagementController::class, 'suggestBatches'])->name('suggest-batches');
            Route::post('batches', [DeliveryManagementController::class, 'createBatch'])->name('batches.create');
            Route::post('batches/{batch}/dispatch', [DeliveryManagementController::class, 'dispatchBatch'])->name('batches.dispatch');
            Route::post('batches/{batch}/complete', [DeliveryManagementController::class, 'completeBatch'])->name('batches.complete');
            Route::post('batches/{batch}/cancel', [DeliveryManagementController::class, 'cancelBatch'])->name('batches.cancel');

            // Shipper PWA API
            Route::post('shipper/location', [ShipperPwaController::class, 'updateLocation'])->name('shipper.location');
            Route::post('shipper/location/batch', [ShipperPwaController::class, 'updateLocationBatch'])->name('shipper.location.batch');
            Route::post('shipper/items/{item}/status', [ShipperPwaController::class, 'updateItemStatus'])->name('shipper.item.status');
        });

        // Shipper PWA page
        Route::get('shipper', [ShipperPwaController::class, 'app'])->name('shipper.app');

        // Shippers CRUD
        Route::get('shippers', [ShipperController::class, 'index'])->name('shippers.index');
        Route::post('shippers', [ShipperController::class, 'store'])->name('shippers.store');
        Route::patch('shippers/{shipper}', [ShipperController::class, 'update'])->name('shippers.update');
        Route::delete('shippers/{shipper}', [ShipperController::class, 'destroy'])->name('shippers.destroy');
    });

    // Employee Self-Service Portal
    Route::prefix('employee-portal')
        ->middleware(['throttle:employee_portal'])
        ->name('employee-portal.')
        ->group(function () {
            Route::get('/', [EmployeePortalController::class, 'index'])->name('index');
            Route::get('/data', [EmployeePortalController::class, 'getDashboardData'])->name('data');
            Route::get('/salaries', [EmployeePortalController::class, 'getSalaries'])->name('salaries');
            Route::get('/leaves', [EmployeePortalController::class, 'getLeaves'])->name('leaves');
            Route::post('/leaves', [EmployeePortalController::class, 'storeLeaveRequest'])->name('leaves.store');
            Route::get('/swaps', [EmployeePortalController::class, 'getSwaps'])->name('swaps');
            Route::post('/swaps/request', [EmployeePortalController::class, 'requestSwap'])->name('swaps.request');
            Route::post('/swaps/{swap}/respond', [EmployeePortalController::class, 'respondSwap'])->name('swaps.respond');
            Route::post('/notifications/read-all', [EmployeePortalController::class, 'readAllNotifications'])->name('notifications.read-all');
        });

    // Chi nhánh làm việc
    Route::post('branch/switch', [BranchSwitchController::class, 'switchBranch'])->name('branch.switch');
});
