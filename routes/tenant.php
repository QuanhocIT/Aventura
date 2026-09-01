<?php

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalPolicyController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BatchRecallController;
use App\Http\Controllers\BestSellerController;
use App\Http\Controllers\BIDashboardController;
use App\Http\Controllers\BranchClosingController;
use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\BusinessGoalController;
use App\Http\Controllers\CashFlowController;
use App\Http\Controllers\CashHandoverController;
use App\Http\Controllers\CdpController;
use App\Http\Controllers\CentralKitchenController;
use App\Http\Controllers\CentralWarehousePriceController;
use App\Http\Controllers\CentralWarehouseSupplyChainController;
use App\Http\Controllers\CentralWarehouseTeamController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CompanyPolicyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\Delivery\DeliveryManagementController;
use App\Http\Controllers\Delivery\ShipperController;
use App\Http\Controllers\Delivery\ShipperPwaController;
use App\Http\Controllers\DeliveryManifestController;
use App\Http\Controllers\EInvoiceController;
use App\Http\Controllers\EmployeeManagementController;
use App\Http\Controllers\EmployeePortalController;
use App\Http\Controllers\EnterpriseCommandCenterController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FinancialBudgetController;
use App\Http\Controllers\FinancialController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\FraudController;
use App\Http\Controllers\GeoAnalyticsController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IngredientSpendController;
use App\Http\Controllers\IntegrationSettingsController;
use App\Http\Controllers\InternalTransferController;
use App\Http\Controllers\InventoryCountController;
use App\Http\Controllers\MaterialClosingController;
use App\Http\Controllers\InventoryManagementController;
use App\Http\Controllers\InventoryNegativeStockController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\KitchenMenuControlController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\LeaveScheduleController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\MenuEngineeringController;
use App\Http\Controllers\MenuInsightController;
use App\Http\Controllers\MyRequestsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\OnlineStoreSettingsController;
use App\Http\Controllers\OperationalAuditController;
use App\Http\Controllers\OperationPolicyController;
use App\Http\Controllers\OperationsChecklistController;
use App\Http\Controllers\OrderActionsController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PayrollBudgetController;
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
use App\Http\Controllers\SecureFileController;
use App\Http\Controllers\ShiftClosingController;
use App\Http\Controllers\ShiftHandoverController;
use App\Http\Controllers\ShiftSwapController;
use App\Http\Controllers\StaffQROrderController;
use App\Http\Controllers\StockTransferRequestController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPortalController;
use App\Http\Controllers\SupplyRequestController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TableReservationController;
use App\Http\Controllers\TablesController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\ViolationReportController;
use App\Http\Controllers\WarehouseFraudCaseController;
use App\Http\Controllers\WarehouseGovernanceController;
use App\Http\Controllers\WarehouseLocationController;
use App\Http\Controllers\WarehouseReverseLogisticsController;
use App\Http\Controllers\WarehouseStaffController;
use App\Http\Controllers\WarehouseTaskController;
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
    Route::get('enterprise/command-center', [EnterpriseCommandCenterController::class, 'index'])->name('enterprise.command-center');

    // Quản lý Đội ngũ Kho Tổng (Trưởng Kho Tổng)
    Route::get('warehouse/team', [CentralWarehouseTeamController::class, 'index'])->name('warehouse.team.index');
    Route::post('warehouse/team/assign-supervisor', [CentralWarehouseTeamController::class, 'assignSupervisor'])->name('warehouse.team.assign-supervisor');
    Route::post('warehouse/team/toggle-status', [CentralWarehouseTeamController::class, 'toggleTaskStatus'])->name('warehouse.team.toggle-status');
    Route::post('warehouse/team/tasks/assign', [CentralWarehouseTeamController::class, 'assignTask'])->name('warehouse.team.tasks.assign');
    Route::post('warehouse/team/tasks/{task}/reassign', [CentralWarehouseTeamController::class, 'reassignTask'])->name('warehouse.team.tasks.reassign');
    Route::post('warehouse/team/leave/{leave}/approve', [CentralWarehouseTeamController::class, 'approveLeave'])->name('warehouse.team.leave.approve');
    Route::get('api/warehouse/team/kpi', [CentralWarehouseTeamController::class, 'kpiReport'])->name('api.warehouse.team.kpi');

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
    Route::put('inventory/ingredients/{id}', [InventoryManagementController::class, 'updateIngredient'])->name('inventory.ingredients.update');
    Route::post('inventory/recipes', [InventoryManagementController::class, 'storeRecipe'])->name('inventory.recipes.store');
    Route::delete('inventory/recipes/{id}', [InventoryManagementController::class, 'deleteRecipe'])->name('inventory.recipes.delete');
    Route::post('inventory/purchases', [InventoryManagementController::class, 'storePurchase'])->name('inventory.purchases.store');
    Route::post('inventory/waste', [InventoryManagementController::class, 'storeWaste'])->name('inventory.waste.store');
    Route::post('inventory/reconcile', [InventoryManagementController::class, 'reconcile'])->name('inventory.reconcile');
    Route::get('inventory/negative-stock', [InventoryNegativeStockController::class, 'page'])
        ->middleware('role_or_permission:owner|super_admin|manager|inventory_staff|warehouse_manager|warehouse_staff')
        ->name('inventory.negative-stock');
    Route::get('api/inventory/negative-stock-cases', [InventoryNegativeStockController::class, 'index'])
        ->middleware('role_or_permission:owner|super_admin|manager|inventory_staff|warehouse_manager|warehouse_staff')
        ->name('inventory.negative-stock-cases.index');
    Route::get('api/inventory/negative-stock-cases/{id}', [InventoryNegativeStockController::class, 'show'])
        ->middleware('role_or_permission:owner|super_admin|manager|inventory_staff|warehouse_manager|warehouse_staff')
        ->name('inventory.negative-stock-cases.show');
    Route::post('api/inventory/negative-stock-cases/{id}/plan', [InventoryNegativeStockController::class, 'updatePlan'])
        ->middleware('role_or_permission:owner|super_admin|manager|warehouse_manager')
        ->name('inventory.negative-stock-cases.plan');
    Route::post('api/inventory/negative-stock-cases/{id}/approve', [InventoryNegativeStockController::class, 'approve'])
        ->middleware('role_or_permission:owner|super_admin')
        ->name('inventory.negative-stock-cases.approve');
    Route::post('api/inventory/negative-stock-cases/{id}/resolve', [InventoryNegativeStockController::class, 'resolve'])
        ->middleware('role_or_permission:owner|super_admin|manager|warehouse_manager')
        ->name('inventory.negative-stock-cases.resolve');
    Route::post('api/inventory/negative-stock-cases/{id}/submit-verification', [InventoryNegativeStockController::class, 'submitVerification'])
        ->middleware('role_or_permission:owner|super_admin|manager|warehouse_manager')
        ->name('inventory.negative-stock-cases.submit-verification');
    Route::post('api/inventory/negative-stock-cases/{id}/verify', [InventoryNegativeStockController::class, 'verify'])
        ->middleware('role_or_permission:owner|super_admin|manager|warehouse_manager')
        ->name('inventory.negative-stock-cases.verify');
    // Khóa lô / mở khóa / yêu cầu kho thu hồi.
    Route::post('inventory/batches/{batch}/lock', [InventoryManagementController::class, 'lockBatch'])->name('inventory.batches.lock');
    Route::post('inventory/batches/{batch}/unlock', [InventoryManagementController::class, 'unlockBatch'])->name('inventory.batches.unlock');
    Route::post('inventory/batches/{batch}/recall', [InventoryManagementController::class, 'requestBatchRecall'])->name('inventory.batches.recall');
    Route::post('inventory/auto-po/generate', [PurchaseOrderController::class, 'generateAutoPo'])->name('inventory.auto-po.generate');

    Route::get('employees', [EmployeeManagementController::class, 'employeesPage'])->name('employees.index');
    Route::get('bonuses', [BonusController::class, 'index'])->name('bonuses.index');
    Route::post('bonuses', [EmployeeManagementController::class, 'storeBonus'])->name('bonuses.store');
    Route::post('employees', [EmployeeManagementController::class, 'storeEmployee'])->name('employees.store')->middleware('tenant.quota:employees');
    Route::patch('employees/{employee}', [EmployeeManagementController::class, 'updateEmployee'])->name('employees.update');
    Route::patch('employees/{employee}/toggle-status', [EmployeeManagementController::class, 'toggleEmployeeStatus'])->name('employees.toggle-status');
    Route::get('employees/{employee}/export-profile', [EmployeeManagementController::class, 'exportEmployeeProfile'])->name('employees.export-profile');
    Route::get('employees/{employee}/citizen-id/{side}', [EmployeeManagementController::class, 'citizenIdImage'])->name('employees.citizen-id');
    Route::post('employees/shifts/sync', [EmployeeManagementController::class, 'syncShifts'])->name('employees.shifts.sync');

    // ── [SECURITY P0] Xếp ca: chỉ Owner / Manager / Trưởng kho được tạo/xóa/tự động ──
    // Nhân viên thường KHÔNG được gọi các route này. Phân quyền 2 lớp: route-level +
    // controller-level (ScheduleAssignmentService::storeAssignment cũng tự kiểm tra).
    Route::middleware('role:owner|manager|warehouse_manager')->group(function () {
        Route::post('employees/schedules', [LeaveScheduleController::class, 'storeAssignment'])->name('employees.schedules.store');
        Route::post('employees/schedules/delete', [LeaveScheduleController::class, 'destroyAssignment'])->name('employees.schedules.destroy');
        Route::post('employees/schedules/toggle-auto', [LeaveScheduleController::class, 'toggleAutoSchedule'])->name('employees.schedules.toggle-auto');
        Route::post('employees/schedules/quick-auto', [LeaveScheduleController::class, 'quickAutoSchedule'])->name('employees.schedules.quick-auto');
        Route::post('employees/schedules/copy-last-week', [LeaveScheduleController::class, 'copyLastWeekSchedules'])->name('employees.schedules.copy-last-week');
        // Thay ca khẩn cấp (nghỉ đột xuất) — quản lý xếp người thay, không tự xếp mình.
        Route::post('employees/schedules/emergency-replace', [LeaveScheduleController::class, 'emergencyReplace'])->name('employees.schedules.emergency-replace');
        Route::patch('employees/leaves/{leave}/approve', [LeaveScheduleController::class, 'approveLeaveRequest'])->name('employees.leaves.approve');
        Route::patch('employees/leaves/{leave}/reject', [LeaveScheduleController::class, 'rejectLeaveRequest'])->name('employees.leaves.reject');
    });

    // Nộp đơn nghỉ phép / xem gợi ý thay ca — tất cả nhân viên có tài khoản.
    Route::post('employees/leaves', [LeaveScheduleController::class, 'storeLeaveRequest'])->name('employees.leaves.store');
    Route::get('employees/leaves/{leave}/replacements', [LeaveScheduleController::class, 'getReplacementSuggestions'])->name('employees.leaves.replacements');

    // Chấm công & Lịch biểu
    Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::get('overtime-requests', [OvertimeController::class, 'index'])->name('overtime.index');
    Route::post('overtime-requests', [OvertimeController::class, 'store'])->name('overtime.store');
    Route::patch('overtime-requests/{overtimeRequest}/approve', [OvertimeController::class, 'approve'])->name('overtime.approve');
    Route::patch('overtime-requests/{overtimeRequest}/reject', [OvertimeController::class, 'reject'])->name('overtime.reject');
    Route::patch('overtime-requests/{overtimeRequest}/accept', [OvertimeController::class, 'accept'])->name('overtime.accept');
    Route::patch('overtime-requests/{overtimeRequest}/decline', [OvertimeController::class, 'decline'])->name('overtime.decline');
    Route::post('overtime-requests/{overtimeRequest}/check-in', [OvertimeController::class, 'checkIn'])->name('overtime.check-in');
    Route::post('overtime-requests/{overtimeRequest}/check-out', [OvertimeController::class, 'checkOut'])->name('overtime.check-out');
    Route::patch('overtime-requests/{overtimeRequest}/withdraw', [OvertimeController::class, 'withdraw'])->name('overtime.withdraw');
    Route::patch('overtime-requests/{overtimeRequest}/cancel', [OvertimeController::class, 'cancel'])->name('overtime.cancel');
    Route::patch('overtime-requests/{overtimeRequest}/reconcile', [OvertimeController::class, 'reconcile'])->name('overtime.reconcile');
    Route::get('overtime-requests/export', [OvertimeController::class, 'export'])->name('overtime.export');
    Route::post('overtime-policies', [OvertimeController::class, 'updatePolicy'])->name('overtime.policies.update');
    Route::post('overtime-holidays', [OvertimeController::class, 'storeHoliday'])->name('overtime.holidays.store');
    Route::delete('overtime-holidays/{overtimeHoliday}', [OvertimeController::class, 'destroyHoliday'])->name('overtime.holidays.destroy');
    Route::post('schedules/check-in', [AttendanceController::class, 'checkIn'])->name('schedules.check-in');
    Route::post('schedules/request-check-in', [AttendanceController::class, 'requestCheckIn'])->name('schedules.request-check-in');
    Route::post('schedules/request-check-out', [AttendanceController::class, 'requestCheckOut'])->name('schedules.request-check-out');
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
        Route::post('/', [EquipmentController::class, 'store'])
            ->middleware('role_or_permission:owner|super_admin|equipment.manage')
            ->name('store');
        Route::patch('/{equipment}', [EquipmentController::class, 'update'])
            ->middleware('role_or_permission:owner|super_admin|equipment.manage')
            ->name('update');
        Route::delete('/{equipment}', [EquipmentController::class, 'destroy'])
            ->middleware('role_or_permission:owner|super_admin|equipment.manage')
            ->name('destroy');
        Route::post('/report-issue', [EquipmentController::class, 'reportIssue'])
            ->middleware('role_or_permission:owner|super_admin|equipment.report|equipment.manage')
            ->name('report');
        Route::post('/logs/{log}/complete', [EquipmentController::class, 'completeLog'])
            ->middleware('role_or_permission:owner|super_admin|equipment.manage')
            ->name('logs.complete');
    });

    // Đào Tạo & Onboarding Nhân Viên
    Route::prefix('training')->name('training.')->group(function () {
        Route::get('/', [TrainingController::class, 'index'])->name('index');
        Route::post('/courses', [TrainingController::class, 'storeCourse'])
            ->middleware('role_or_permission:owner|super_admin|training.manage')
            ->name('courses.store');
        Route::delete('/courses/{course}', [TrainingController::class, 'destroyCourse'])
            ->middleware('role_or_permission:owner|super_admin|training.manage')
            ->name('courses.destroy');
        Route::post('/courses/{course}/lessons', [TrainingController::class, 'storeLesson'])
            ->middleware('role_or_permission:owner|super_admin|training.manage')
            ->name('lessons.store');
        Route::post('/courses/{course}/quizzes', [TrainingController::class, 'storeQuiz'])
            ->middleware('role_or_permission:owner|super_admin|training.manage')
            ->name('quizzes.store');
        Route::post('/enroll', [TrainingController::class, 'enrollEmployee'])
            ->middleware('role_or_permission:owner|super_admin|training.manage')
            ->name('enroll');
        Route::get('/courses/{course}/content', [TrainingController::class, 'courseContent'])
            ->name('courses.content');
        Route::post('/enrollments/{enrollment}/approve', [TrainingController::class, 'approveEnrollment'])
            ->middleware('role_or_permission:owner|super_admin|training.manage')
            ->name('enrollments.approve');
        Route::patch('/enrollments/{enrollment}', [TrainingController::class, 'syncEnrollment'])
            ->middleware('role_or_permission:owner|super_admin|training.manage')
            ->name('enrollments.update');
        Route::post('/complete-lesson', [TrainingController::class, 'completeLesson'])->name('complete-lesson');
        Route::post('/submit-quiz', [TrainingController::class, 'submitQuiz'])->name('submit-quiz');
    });

    // Checklist Vận Hành Hàng Ngày
    Route::prefix('operations-checklist')->name('checklist.')->group(function () {
        Route::get('/', [OperationsChecklistController::class, 'index'])->name('index');
        Route::post('/complete', [OperationsChecklistController::class, 'completeItem'])->name('complete');
        Route::post('/uncomplete', [OperationsChecklistController::class, 'uncompleteItem'])->name('uncomplete');
        Route::post('/templates', [OperationsChecklistController::class, 'storeTemplate'])->name('templates.store');
        Route::put('/templates/{template}', [OperationsChecklistController::class, 'updateTemplate'])->name('templates.update');
        Route::delete('/templates/{template}', [OperationsChecklistController::class, 'destroyTemplate'])->name('templates.destroy');
        Route::get('/api/weekly-report', [OperationsChecklistController::class, 'weeklyReport'])->name('weekly-report');
    });

    // Bàn giao ca: tiền, hàng, thiết bị, sự cố, việc tồn trong một phiên.
    Route::prefix('shift-handovers')->name('shift-handovers.')->group(function () {
        Route::get('/', [ShiftHandoverController::class, 'index'])->name('index');
        Route::post('/', [ShiftHandoverController::class, 'store'])->name('store');
        Route::post('/{handover}/check', [ShiftHandoverController::class, 'checkItem'])->name('check');
        Route::patch('/{handover}/submit', [ShiftHandoverController::class, 'submit'])->name('submit');
        Route::patch('/{handover}/accept', [ShiftHandoverController::class, 'accept'])->name('accept');
        Route::patch('/{handover}/dispute', [ShiftHandoverController::class, 'dispute'])->name('dispute');
    });

    // Quản Lý Hao Hụt & Lãng Phí (Waste Management)
    Route::prefix('waste-management')->name('waste.')->group(function () {
        Route::get('/', [WasteManagementController::class, 'index'])->name('index');
        Route::post('/record', [InventoryManagementController::class, 'storeWaste'])->name('record');
        Route::get('/api/dashboard', [WasteManagementController::class, 'apiDashboard'])->name('dashboard');
        Route::get('/api/trend', [WasteManagementController::class, 'apiTrend'])->name('trend');
        Route::get('/api/suggestions', [WasteManagementController::class, 'apiSuggestions'])->name('suggestions');
        Route::get('/api/expiring', [WasteManagementController::class, 'apiExpiring'])->name('expiring');
    });

    // Menu Engineering Nâng Cao
    Route::prefix('menu-engineering')->name('menu-engineering.')->group(function () {
        Route::get('/', [MenuEngineeringController::class, 'index'])->name('index');
        Route::get('/api/scoring', [MenuEngineeringController::class, 'scoring'])->name('scoring');
        Route::get('/api/behavior', [MenuEngineeringController::class, 'behaviorAnalytics'])->name('behavior');
        Route::post('/display-order', [MenuEngineeringController::class, 'updateDisplayOrder'])->name('display-order');
        Route::patch('/products/{product}/time-slot', [MenuEngineeringController::class, 'updateTimeSlot'])->name('time-slot');
        Route::post('/price-tests', [MenuEngineeringController::class, 'storePriceTest'])->name('price-tests.store');
        Route::post('/price-tests/{test}/complete', [MenuEngineeringController::class, 'completePriceTest'])->name('price-tests.complete');
        Route::post('/price-tests/{test}/cancel', [MenuEngineeringController::class, 'cancelPriceTest'])->name('price-tests.cancel');
    });

    // Phân tích Món Bán Chạy (Best-Seller Analytics)
    Route::prefix('best-sellers')->name('best-sellers.')->group(function () {
        Route::get('/', [BestSellerController::class, 'index'])->name('index');
        Route::get('/api/analytics', [BestSellerController::class, 'analytics'])->name('analytics');
        Route::get('/api/dishes/{product}', [BestSellerController::class, 'dish'])
            ->whereNumber('product')
            ->name('dish');
        Route::get('/export', [BestSellerController::class, 'export'])->name('export');
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

    // Tách bill / gộp đơn / chuyển bàn / gọi thanh toán
    Route::get('api/orders/{order}/items', [OrderActionsController::class, 'items'])->name('orders.items');
    Route::get('api/orders/available-tables', [OrderActionsController::class, 'availableTables'])->name('orders.available-tables');
    Route::post('orders/{order}/split', [OrderActionsController::class, 'split'])->name('orders.split');
    Route::post('orders/{order}/merge', [OrderActionsController::class, 'merge'])->name('orders.merge');
    Route::post('orders/{order}/move-table', [OrderActionsController::class, 'moveTable'])->name('orders.move-table');
    Route::post('orders/{order}/request-payment', [OrderActionsController::class, 'requestPayment'])->name('orders.request-payment');
    Route::post('orders/{order}/convert-to-takeaway', [OrderActionsController::class, 'convertToTakeaway'])->name('orders.convert-to-takeaway');
    Route::post('orders/{order}/call-waiter', [OrderActionsController::class, 'callWaiter'])->name('orders.call-waiter');

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
    Route::get('api/promotions/available', [PromotionController::class, 'availableForCashier'])->name('promotions.available');
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
    Route::post('promotions/triggers/{trigger}/test-fire', [PromotionTriggerController::class, 'testFire'])->name('promotions.triggers.test-fire');
    Route::put('promotions/triggers/{trigger}', [PromotionTriggerController::class, 'update'])->name('promotions.triggers.update');
    Route::delete('promotions/triggers/{trigger}', [PromotionTriggerController::class, 'destroy'])->name('promotions.triggers.destroy');
    Route::patch('promotions/triggers/{trigger}/toggle', [PromotionTriggerController::class, 'toggleActive'])->name('promotions.triggers.toggle');

    // Promotion Analytics
    Route::get('promotions/analytics', [PromotionAnalyticsController::class, 'index'])->name('promotions.analytics.index');
    Route::post('promotions/analytics/recalculate', [PromotionAnalyticsController::class, 'recalculate'])->name('promotions.analytics.recalculate');

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
    Route::get('kitchen/menu-control', [KitchenMenuControlController::class, 'index'])->name('kitchen.menu-control.index');
    Route::post('kitchen/menu-control/activate', [KitchenMenuControlController::class, 'activate'])->name('kitchen.menu-control.activate');
    Route::post('kitchen/items/prepare-bulk', [KitchenController::class, 'prepareBulk'])->name('kitchen.prepare-bulk');
    Route::post('kitchen/items/{item}/start', [KitchenController::class, 'startPreparing'])->name('kitchen.start-preparing');
    Route::post('kitchen/items/{item}/prepare', [KitchenController::class, 'prepare'])->name('kitchen.prepare');
    Route::post('kitchen/items/{item}/cancel', [KitchenController::class, 'cancelItem'])->name('kitchen.cancel-item');
    Route::post('kitchen/items/{item}/serve', [KitchenController::class, 'serve'])->name('kitchen.serve');
    Route::post('kitchen/products/{product}/pause', [KitchenController::class, 'pause'])->name('kitchen.products.pause');
    Route::post('kitchen/products/{product}/out-of-stock', [KitchenController::class, 'markOutOfStock'])->name('kitchen.products.out-of-stock');
    Route::post('kitchen/products/{product}/resume', [KitchenController::class, 'resume'])->name('kitchen.products.resume');
    // Tạm ngưng món theo RIÊNG chi nhánh + duyệt mở lại.
    Route::post('kitchen/products/{product}/pause-branch', [KitchenController::class, 'pauseBranch'])->name('kitchen.products.pause-branch');
    Route::post('kitchen/products/{product}/request-reopen', [KitchenController::class, 'requestReopenBranch'])->name('kitchen.products.request-reopen');
    Route::post('kitchen/products/{product}/approve-reopen', [KitchenController::class, 'approveReopenBranch'])->name('kitchen.products.approve-reopen');

    // Orders management
    Route::get('orders/create', [OrdersController::class, 'create'])->name('orders.create');
    Route::post('orders', [OrdersController::class, 'store'])->name('orders.store');
    Route::get('orders', [OrdersController::class, 'index'])->name('orders.index');
    Route::post('orders/{order}/items/{item}/cancel', [OrdersController::class, 'cancelItem'])->name('orders.items.cancel');
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
        Route::post('/', [TableReservationController::class, 'store'])->name('store');
        Route::post('{reservation}/confirm', [TableReservationController::class, 'confirm'])->name('confirm');
        Route::post('{reservation}/seat', [TableReservationController::class, 'seat'])->name('seat');
        Route::post('{reservation}/complete', [TableReservationController::class, 'complete'])->name('complete');
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

    // Financial journal, trial balance and accounting periods.
    Route::get('finance', [FinancialController::class, 'index'])->name('finance.index');
    Route::get('finance/ingredient-spend', [IngredientSpendController::class, 'index'])->name('finance.ingredient-spend.index');
    Route::post('finance/accounts', [FinancialController::class, 'storeAccount'])->name('finance.accounts.store');
    Route::post('finance/entries', [FinancialController::class, 'storeEntry'])->name('finance.entries.store');
    Route::get('finance/periods/{period}/checklist', [FinancialController::class, 'closeChecklist'])->name('finance.periods.checklist');
    Route::patch('finance/periods/{period}/close', [FinancialController::class, 'closePeriod'])->name('finance.periods.close');
    Route::patch('finance/periods/{period}/reopen', [FinancialController::class, 'reopenPeriod'])->name('finance.periods.reopen');
    Route::post('finance/entries/{entry}/reverse', [FinancialController::class, 'reverseEntry'])->name('finance.entries.reverse');
    Route::get('financial-budgets', [FinancialBudgetController::class, 'index'])->name('financial-budgets.index');
    Route::post('financial-budgets', [FinancialBudgetController::class, 'store'])->name('financial-budgets.store');
    Route::patch('financial-budgets/{budget}/approve', [FinancialBudgetController::class, 'approve'])->name('financial-budgets.approve');
    Route::get('fixed-assets', [FixedAssetController::class, 'index'])->name('fixed-assets.index');
    Route::post('fixed-assets', [FixedAssetController::class, 'store'])->name('fixed-assets.store');
    Route::patch('fixed-assets/{asset}', [FixedAssetController::class, 'update'])->name('fixed-assets.update');
    Route::post('fixed-assets/{asset}/dispose', [FixedAssetController::class, 'dispose'])->name('fixed-assets.dispose');
    Route::post('fixed-assets/{asset}/handovers', [FixedAssetController::class, 'storeHandover'])->name('fixed-assets.handovers.store');
    Route::post('fixed-asset-handovers/{handover}/accept', [FixedAssetController::class, 'acceptHandover'])->name('fixed-asset-handovers.accept');
    Route::post('fixed-asset-handovers/{handover}/reject', [FixedAssetController::class, 'rejectHandover'])->name('fixed-asset-handovers.reject');
    Route::post('fixed-assets/{asset}/inspections', [FixedAssetController::class, 'inspect'])->name('fixed-assets.inspections.store');

    Route::prefix('bank-reconciliation')->name('bank-reconciliation.')->group(function () {
        Route::get('/', [BankReconciliationController::class, 'index'])->name('index');
        Route::post('/payments/{payment}/reconcile', [BankReconciliationController::class, 'reconcile'])->name('payments.reconcile');
        Route::post('/payments/{payment}/unreconcile', [BankReconciliationController::class, 'unreconcile'])->name('payments.unreconcile');
        Route::post('/batch-reconcile', [BankReconciliationController::class, 'batchReconcile'])->name('batch-reconcile');
        Route::post('/accounts', [BankReconciliationController::class, 'storeAccount'])->name('accounts.store');
        Route::post('/import', [BankReconciliationController::class, 'import'])->name('import');
        Route::post('/sync-sepay', [BankReconciliationController::class, 'syncSepay'])->name('sync-sepay');
        Route::patch('/lines/{line}/match', [BankReconciliationController::class, 'match'])->name('lines.match');
        Route::patch('/lines/{line}/unmatch', [BankReconciliationController::class, 'unmatch'])->name('lines.unmatch');
        Route::post('/lines/{line}/adjustment', [BankReconciliationController::class, 'createAdjustment'])->name('lines.adjustment');
    });

    // Expenses / OPEX Tracker
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::patch('/{expense}', [ExpenseController::class, 'update'])->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
        Route::patch('/{expense}/approve', [ExpenseController::class, 'approveExpense'])->name('approve');
        Route::patch('/{expense}/reject', [ExpenseController::class, 'rejectExpense'])->name('reject');
        Route::patch('/{expense}/pay', [ExpenseController::class, 'payExpense'])->name('pay');

        Route::post('/recurring', [ExpenseController::class, 'storeRecurring'])->name('recurring.store');
        Route::patch('/recurring/{recurring}', [ExpenseController::class, 'updateRecurring'])->name('recurring.update');
        Route::delete('/recurring/{recurring}', [ExpenseController::class, 'destroyRecurring'])->name('recurring.destroy');

        Route::post('/categories', [ExpenseController::class, 'storeCategory'])->name('categories.store');
        Route::delete('/categories/{category}', [ExpenseController::class, 'destroyCategory'])->name('categories.destroy');
        // Chủ đặt hạn mức chi tiêu tháng theo chi nhánh.
        Route::post('/branch-budget', [ExpenseController::class, 'storeBranchBudget'])->name('branch-budget.store');
    });

    // Quản lý Công nợ (Accounts Receivable / Payable)
    Route::prefix('debts')->name('debts.')->group(function () {
        Route::get('/', [DebtController::class, 'index'])->name('index');
        Route::post('/payables/{payable}/pay', [DebtController::class, 'paySupplier'])->name('payables.pay');
        Route::post('/payables/{payable}/write-off', [DebtController::class, 'writeOffPayable'])->name('payables.write-off');
        Route::post('/receivables/{receivable}/collect', [DebtController::class, 'collectCustomer'])->name('receivables.collect');
        Route::post('/receivables/{receivable}/write-off', [DebtController::class, 'writeOffReceivable'])->name('receivables.write-off');
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

    // Quỹ lương & bậc lương theo chi nhánh (chỉ Chủ doanh nghiệp)
    Route::prefix('payroll-budget')->name('payroll-budget.')->group(function () {
        Route::get('/', [PayrollBudgetController::class, 'index'])->name('index');
        Route::post('/budget', [PayrollBudgetController::class, 'storeBudget'])->name('budget.store');
        Route::post('/wage-tiers', [PayrollBudgetController::class, 'storeWageTier'])->name('wage-tiers.store');
        Route::put('/wage-tiers/{wageTier}', [PayrollBudgetController::class, 'updateWageTier'])->name('wage-tiers.update');
        Route::patch('/wage-tiers/{wageTier}/toggle', [PayrollBudgetController::class, 'toggleWageTier'])->name('wage-tiers.toggle');
        Route::delete('/wage-tiers/{wageTier}', [PayrollBudgetController::class, 'destroyWageTier'])->name('wage-tiers.destroy');
    });

    // Shift Closings — Chốt ca & Doanh thu gộp
    Route::get('shift-closings', [ShiftClosingController::class, 'index'])->name('shift-closings.index');
    // Chủ cấu hình kiểm soát tiền mặt cuối ca (đếm mù, ngưỡng giải trình/ảnh, bàn giao).
    Route::post('shift-closings/cash-control', [ShiftClosingController::class, 'updateCashControl'])->name('shift-closings.cash-control');
    Route::get('shift-closings/preview', [ShiftClosingController::class, 'preview'])->name('shift-closings.preview');
    // Đếm tiền mù: nộp phiếu đếm rồi hệ thống mới lộ số kỳ vọng.
    Route::post('shift-closings/count', [ShiftClosingController::class, 'countCash'])->name('shift-closings.count');
    // Bàn giao tiền có chữ ký hai bên.
    Route::post('cash-handovers', [CashHandoverController::class, 'store'])->name('cash-handovers.store');
    Route::patch('cash-handovers/{handover}/acknowledge', [CashHandoverController::class, 'acknowledge'])->name('cash-handovers.acknowledge');
    Route::patch('cash-handovers/{handover}/dispute', [CashHandoverController::class, 'dispute'])->name('cash-handovers.dispute');
    Route::post('shift-closings', [ShiftClosingController::class, 'store'])->name('shift-closings.store');
    Route::patch('shift-closings/{closing}/confirm', [ShiftClosingController::class, 'confirm'])->name('shift-closings.confirm');
    Route::patch('shift-closings/{closing}/dispute', [ShiftClosingController::class, 'dispute'])->name('shift-closings.dispute');
    // Thùng rác: chỉ draft mới được trash; tự xóa sau 7 ngày bởi scheduler.
    Route::get('shift-closings/trash', [ShiftClosingController::class, 'trashIndex'])->name('shift-closings.trash.index');
    Route::post('shift-closings/{id}/trash', [ShiftClosingController::class, 'trash'])->name('shift-closings.trash');
    Route::post('shift-closings/{id}/restore', [ShiftClosingController::class, 'restore'])->name('shift-closings.restore');

    // Cash Flow Management
    Route::get('cash-flow', [CashFlowController::class, 'index'])->name('cash-flow.index');
    Route::post('cash-flow/registers', [CashFlowController::class, 'openRegister'])->name('cash-flow.registers.open');
    Route::post('cash-flow/registers/{register}/reconcile-opening', [CashFlowController::class, 'reconcileOpening'])->name('cash-flow.registers.reconcile-opening');
    Route::post('cash-flow/transactions', [CashFlowController::class, 'storeTransaction'])->name('cash-flow.transactions.store');
    Route::post('cash-flow/transactions/{transaction}/reversal', [CashFlowController::class, 'reversalTransaction'])->name('cash-flow.transactions.reversal');
    Route::get('cash-flow/forecast', [CashFlowController::class, 'getForecast'])->name('cash-flow.forecast');

    // Secure File Downloads
    Route::get('secure-files/download', [SecureFileController::class, 'download'])->name('secure-files.download');

    // Support booking demo
    Route::post('support/bookings', [SupportController::class, 'storeBooking'])->name('support.bookings.store');

    // Kiểm toán gian lận
    Route::get('fraud', [FraudController::class, 'index'])->name('fraud.index');
    Route::post('fraud/violation', [FraudController::class, 'createViolation'])->name('fraud.violation.store');
    Route::post('fraud/verify-pin', [FraudController::class, 'verifyManagerPin'])->name('fraud.pin.verify');

    // Yêu cầu của tôi — mọi vai trò đều xem được trạng thái yêu cầu mình đã gửi.
    Route::get('my-requests', [MyRequestsController::class, 'index'])->name('my-requests.index');

    // Kiểm duyệt chéo (Cross-review)
    Route::middleware('role_or_permission:owner|manager|super_admin|approve_requests')->group(function () {
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::patch('approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::patch('approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    });

    // Sổ phê duyệt & ma trận thẩm quyền — chỉ Chủ doanh nghiệp.
    Route::middleware('role:owner|super_admin')->group(function () {
        Route::get('approvals/ledger', [ApprovalController::class, 'ledger'])->name('approvals.ledger');
        Route::patch('approvals/decisions/{decision}/acknowledge', [ApprovalController::class, 'acknowledge'])->name('approvals.decisions.acknowledge');
        Route::get('approvals/policies', [ApprovalPolicyController::class, 'index'])->name('approvals.policies.index');
        Route::put('approvals/policies', [ApprovalPolicyController::class, 'update'])->name('approvals.policies.update');
        Route::delete('approvals/policies/{policy}', [ApprovalPolicyController::class, 'destroy'])->name('approvals.policies.destroy');
        Route::post('approvals/delegations', [ApprovalPolicyController::class, 'storeDelegation'])->name('approvals.delegations.store');
        Route::delete('approvals/delegations/{delegation}', [ApprovalPolicyController::class, 'destroyDelegation'])->name('approvals.delegations.destroy');
    });

    // Quản lý phản hồi khách hàng (Owner & Manager)
    Route::get('feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::post('feedback/{feedback}/resolve', [FeedbackController::class, 'resolve'])->name('feedback.resolve');

    // Quản lý Tố cáo Nội bộ & Sai phạm (Owner & Manager)
    Route::get('violations', [ViolationReportController::class, 'index'])->name('violations.index');
    Route::post('violations', [ViolationReportController::class, 'store'])->name('violations.store');
    Route::post('violations/{report}/resolve', [ViolationReportController::class, 'resolve'])->name('violations.resolve');
    // Kháng cáo: nhân viên gửi đơn; Chủ xét duyệt.
    Route::post('violations/{report}/appeal', [ViolationReportController::class, 'appeal'])->name('violations.appeal');
    Route::post('violations/{report}/appeal/review', [ViolationReportController::class, 'reviewAppeal'])->name('violations.appeal.review');

    // Sự cố khẩn cấp: mọi nhân viên được báo; Quản lý/Chủ tiếp nhận & đóng.
    Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::get('incidents/{incident}/photo', [IncidentController::class, 'photo'])->name('incidents.photo');
    Route::post('incidents/{incident}/acknowledge', [IncidentController::class, 'acknowledge'])->name('incidents.acknowledge');
    Route::post('incidents/{incident}/escalate', [IncidentController::class, 'escalate'])->name('incidents.escalate');
    Route::post('incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->name('incidents.resolve');

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

    // Điều chuyển liên chi nhánh có định tuyến của Chủ + bàn giao hai bước.
    Route::middleware('role_or_permission:owner|super_admin|manager|warehouse_manager')->group(function () {
        Route::get('inventory/transfers', [StockTransferRequestController::class, 'index'])->name('inventory.transfers');
        Route::post('inventory/transfers', [StockTransferRequestController::class, 'store'])->name('inventory.transfers.store');
        Route::post('inventory/transfers/{transfer}/cancel', [StockTransferRequestController::class, 'cancel'])->name('inventory.transfers.cancel');
        Route::post('inventory/transfers/batch-cancel', [StockTransferRequestController::class, 'batchCancel'])->name('inventory.transfers.batch-cancel');

        Route::post('inventory/transfers/{transfer}/dispatch', [StockTransferRequestController::class, 'dispatch'])->name('inventory.transfers.dispatch');
        Route::post('inventory/transfers/{transfer}/receive', [StockTransferRequestController::class, 'receive'])->name('inventory.transfers.receive');
        Route::post('inventory/transfers/{transfer}/resolve-discrepancy', [StockTransferRequestController::class, 'resolveDiscrepancy'])->name('inventory.transfers.resolve-discrepancy');

        // Các bước phê duyệt định tuyến và từ chối chỉ dành cho Chủ hoặc Trưởng kho Tổng
        Route::middleware('role_or_permission:owner|super_admin|warehouse_manager')->group(function () {
            Route::post('inventory/transfers/batch-route', [StockTransferRequestController::class, 'batchRoute'])->name('inventory.transfers.batch-route');
            Route::post('inventory/transfers/batch-reject', [StockTransferRequestController::class, 'batchReject'])->name('inventory.transfers.batch-reject');
            Route::post('inventory/transfers/{transfer}/route', [StockTransferRequestController::class, 'route'])->name('inventory.transfers.route');
            Route::post('inventory/transfers/{transfer}/reject', [StockTransferRequestController::class, 'reject'])->name('inventory.transfers.reject');
        });
    });

    // Quản lý Tổng Kho & Yêu cầu cấp phát hàng hóa
    Route::middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_staff|warehouse_manager')->group(function () {
        Route::get('inventory/central-warehouse', [SupplyRequestController::class, 'centralWarehousePage'])->name('inventory.central-warehouse');
        Route::get('inventory/central-warehouse/stock', [SupplyRequestController::class, 'centralWarehouseInventoryPage'])->name('inventory.central-warehouse.stock');
        Route::get('inventory/central-warehouse/requests', [SupplyRequestController::class, 'centralWarehouseRequestsPage'])->name('inventory.central-warehouse.requests');
        Route::get('inventory/central-warehouse/receiving', [SupplyRequestController::class, 'centralWarehouseReceivingPage'])->name('inventory.central-warehouse.receiving');
        Route::get('inventory/reverse-logistics', [WarehouseReverseLogisticsController::class, 'page'])->name('inventory.reverse-logistics');
        Route::get('inventory/central-warehouse/prices', [CentralWarehousePriceController::class, 'centralWarehousePricesPage'])
            ->middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_manager')
            ->name('inventory.central-warehouse.prices');
        Route::get('inventory/central-warehouse/export', [SupplyRequestController::class, 'export'])
            ->middleware('role_or_permission:owner|super_admin|warehouse.report|warehouse_manager')
            ->name('inventory.central-warehouse.export');
        Route::get('inventory/central-kitchen', [CentralKitchenController::class, 'page'])->name('inventory.central-kitchen');
        Route::get('inventory/delivery-manifests', [DeliveryManifestController::class, 'page'])->name('inventory.delivery-manifests');
        Route::get('inventory/batch-recalls', [BatchRecallController::class, 'page'])->name('inventory.batch-recalls');
        Route::get('api/supply-requests', [SupplyRequestController::class, 'index'])->name('supply-requests.index');
        Route::get('api/supply-requests/task-board', [WarehouseTaskController::class, 'taskBoardData'])->name('supply-requests.task-board');
        Route::post('api/warehouse/tasks/assign', [WarehouseTaskController::class, 'assignWarehouseTask'])->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse.task.assign')->name('warehouse.tasks.assign');
        Route::post('api/warehouse/tasks/{id}/status', [WarehouseTaskController::class, 'updateWarehouseTaskStatus'])->name('warehouse.tasks.status');
    });

    // ── Portal Nhân Viên Kho Tổng (tách riêng khỏi trang Trưởng kho) ─────────
    Route::middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_staff|warehouse_manager')->prefix('inventory')->group(function () {
        // Trang portal dành riêng cho nhân viên
        Route::get('staff-portal', [WarehouseStaffController::class, 'staffPortalPage'])->name('inventory.staff-portal');
    });

    Route::middleware('role_or_permission:owner|super_admin|warehouse_staff|warehouse_manager|warehouse.receive.submit|warehouse.scan')->prefix('api/warehouse')->group(function () {
        // Task cá nhân
        Route::get('my-tasks', [WarehouseStaffController::class, 'myTasks'])->name('warehouse.my-tasks');
        Route::post('tasks/{id}/start', [WarehouseStaffController::class, 'startTask'])->name('warehouse.tasks.start');
        Route::post('tasks/{id}/complete', [WarehouseStaffController::class, 'completeTask'])->name('warehouse.tasks.complete');
        Route::get('tasks/{id}/evidence/{index}', [WarehouseStaffController::class, 'viewTaskEvidence'])->name('warehouse.tasks.evidence');
        // Phiếu nhận hàng GRN
        Route::post('receiving-vouchers', [WarehouseStaffController::class, 'storeReceivingVoucher'])->middleware('role_or_permission:owner|super_admin|warehouse.receiving.create|warehouse_staff|warehouse_manager|warehouse.manage')->name('warehouse.receiving-vouchers.store');
        Route::post('receiving-vouchers/{id}/confirm', [WarehouseStaffController::class, 'confirmReceiving'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.manage')->name('warehouse.receiving-vouchers.confirm');
        Route::post('receiving-vouchers/{id}/dispose', [WarehouseStaffController::class, 'disposeReceiving'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.manage')->name('warehouse.receiving-vouchers.dispose');
        Route::post('receiving-vouchers/{id}/submit', [WarehouseStaffController::class, 'submitReceiving'])->middleware('role_or_permission:owner|super_admin|warehouse.receiving.create|warehouse_staff|warehouse_manager|warehouse.manage')->name('warehouse.receiving-vouchers.submit');
        Route::post('receiving-vouchers/{id}/reject', [WarehouseStaffController::class, 'rejectReceiving'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.manage')->name('warehouse.receiving-vouchers.reject');
        Route::get('receiving-vouchers/{id}/documents/{document}', [WarehouseStaffController::class, 'viewReceivingDocument'])->name('warehouse.receiving-vouchers.documents.view');
        Route::get('receiving-vouchers/{id}/evidence/{index}', [WarehouseStaffController::class, 'viewReceivingEvidence'])->name('warehouse.receiving-vouchers.evidence.view');
        Route::post('receiving-vouchers/{id}/discrepancy', [WarehouseStaffController::class, 'reportDiscrepancy'])->middleware('role_or_permission:owner|super_admin|warehouse.incident.report|warehouse_staff')->name('warehouse.receiving-vouchers.discrepancy');
        // Cất hàng
        Route::post('tasks/{taskId}/putaway-confirm', [WarehouseStaffController::class, 'confirmPutaway'])->name('warehouse.putaway.confirm');
        // Báo sự cố
        Route::post('incidents', [WarehouseStaffController::class, 'reportIncident'])->middleware('role_or_permission:owner|super_admin|warehouse.incident.report|warehouse_staff')->name('warehouse.incidents.store');
        // Bàn giao ca
        Route::get('my-shift-handover', [WarehouseStaffController::class, 'myShiftHandover'])->name('warehouse.my-shift-handover');
        Route::post('shift-handover', [WarehouseStaffController::class, 'submitShiftHandover'])->middleware('role_or_permission:owner|super_admin|warehouse.shift.handover|warehouse_staff')->name('warehouse.shift-handover.submit');
        Route::post('shift-handover/{id}/confirm', [WarehouseStaffController::class, 'confirmShiftHandover'])->middleware('role_or_permission:owner|super_admin|warehouse.shift.handover|warehouse_staff')->name('warehouse.shift-handover.confirm');
        // Lịch sử của tôi
        Route::get('my-history', [WarehouseStaffController::class, 'myHistory'])->name('warehouse.my-history');
        // Quét mã QR/barcode
        Route::post('scan', [WarehouseStaffController::class, 'scanCode'])->middleware('role_or_permission:owner|super_admin|warehouse.scan|warehouse_staff')->name('warehouse.scan');
    });

    Route::get('inventory/branch-requisition', [SupplyRequestController::class, 'branchRequisitionPage'])->middleware('role_or_permission:owner|super_admin|supply_requests.create|supply_requests.receive')->name('inventory.branch-requisition');
    Route::post('api/supply-requests', [SupplyRequestController::class, 'store'])->middleware('role_or_permission:owner|super_admin|supply_requests.create')->name('supply-requests.store');
    Route::post('api/supply-requests/{id}/approve', [SupplyRequestController::class, 'approve'])->middleware(['role_or_permission:owner|super_admin|warehouse_manager|supply_requests.approve', 'prevent_self_approval:approve'])->name('supply-requests.approve');
    Route::post('api/supply-requests/{id}/prepare', [SupplyRequestController::class, 'prepare'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.pack|warehouse.pick|supply_requests.dispatch')->name('supply-requests.prepare');
    Route::post('api/supply-requests/{id}/approve-dispatch', [SupplyRequestController::class, 'approveDispatch'])->middleware(['role_or_permission:owner|super_admin|warehouse_manager|supply_requests.dispatch_approve|supply_requests.dispatch', 'prevent_self_approval:dispatch_approve'])->name('supply-requests.approve-dispatch');
    Route::post('api/supply-requests/{id}/dispatch', [SupplyRequestController::class, 'dispatch'])->middleware(['role_or_permission:owner|super_admin|warehouse_manager|warehouse.handover|supply_requests.dispatch', 'prevent_self_approval:dispatch'])->name('supply-requests.dispatch');
    Route::post('api/supply-requests/{id}/transporter', [SupplyRequestController::class, 'assignTransporter'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.handover|supply_requests.dispatch')->name('supply-requests.transporter.assign');
    Route::get('api/supply-requests/{id}/proof/{type}', [WarehouseTaskController::class, 'viewProof'])->name('supply-requests.proof');
    Route::post('api/supply-requests/{id}/receive', [SupplyRequestController::class, 'receive'])->middleware(['role_or_permission:owner|super_admin|supply_requests.receive', 'prevent_self_approval:receive'])->name('supply-requests.receive');
    Route::post('api/supply-requests/{id}/receiving-report/confirm', [SupplyRequestController::class, 'confirmReceivingReport'])->middleware(['role_or_permission:owner|super_admin|supply_requests.receive', 'prevent_self_approval:receive'])->name('supply-requests.receiving-report.confirm');
    Route::post('api/receiving-reports/{id}/driver-confirm', [SupplyRequestController::class, 'confirmReceivingReportByDriver'])->middleware('role_or_permission:owner|super_admin|warehouse_staff|warehouse_manager')->name('supply-requests.receiving-report.driver-confirm');
    Route::post('api/receiving-reports/{id}/review', [SupplyRequestController::class, 'reviewReceivingReport'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse_governance.manage')->name('supply-requests.receiving-report.review');
    Route::post('api/supply-requests/{id}/reject', [SupplyRequestController::class, 'reject'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|supply_requests.approve')->name('supply-requests.reject');
    Route::post('api/supply-requests/{id}/cancel', [SupplyRequestController::class, 'cancel'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|supply_requests.cancel')->name('supply-requests.cancel');
    Route::post('api/supply-requests/set-central-branch', [SupplyRequestController::class, 'setCentralBranch'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.manage')->name('supply-requests.set-central-branch');
    Route::post('api/supply-requests/smart-allocation', [SupplyRequestController::class, 'smartAllocation'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.manage|supply_requests.approve')->name('supply-requests.smart-allocation');
    Route::post('api/supply-requests/{id}/create-backorder', [SupplyRequestController::class, 'createBackorder'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.manage|supply_requests.dispatch')->name('supply-requests.create-backorder');
    Route::post('api/warehouse/ingredient-prices', [CentralWarehousePriceController::class, 'updateIngredientPrices'])
        ->middleware('role_or_permission:owner|super_admin')
        ->name('warehouse.ingredient-prices.update');
    Route::post('api/warehouse/ingredient-prices/propose', [CentralWarehousePriceController::class, 'proposeIngredientPrices'])
        ->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.manage')
        ->name('warehouse.ingredient-prices.propose');

    // Chuỗi cung ứng Kho Tổng: cảnh báo nguồn cung, đối soát tồn và NCC dự phòng.
    Route::get('api/warehouse/supply-chain/alerts', [CentralWarehouseSupplyChainController::class, 'alerts'])
        ->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.view')
        ->name('warehouse.supply-chain.alerts');
    Route::get('api/warehouse/ai-recommendations', [SupplyRequestController::class, 'aiRecommendations'])
        ->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.view')
        ->name('warehouse.ai-recommendations');
    Route::get('api/warehouse/supply-chain/reconciliation', [CentralWarehouseSupplyChainController::class, 'reconciliation'])
        ->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.view')
        ->name('warehouse.supply-chain.reconciliation');
    Route::get('api/warehouse/ingredients/{ingredientId}/suppliers', [CentralWarehouseSupplyChainController::class, 'supplierOptions'])
        ->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.view')
        ->name('warehouse.ingredients.suppliers');
    Route::post('api/warehouse/ingredients/suppliers', [CentralWarehouseSupplyChainController::class, 'syncSupplierOptions'])
        ->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.manage')
        ->name('warehouse.ingredients.suppliers.sync');

    // Cách ly, hoàn trả và khiếu nại chuỗi cung ứng.
    Route::get('api/warehouse/reverse-logistics/quarantines', [WarehouseReverseLogisticsController::class, 'quarantines'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_manager|warehouse_staff|manager')
        ->name('warehouse.reverse-logistics.quarantines');
    Route::get('api/warehouse/reverse-logistics/returns', [WarehouseReverseLogisticsController::class, 'returns'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_manager|warehouse_staff|manager')
        ->name('warehouse.reverse-logistics.returns');
    Route::post('api/warehouse/reverse-logistics/quarantines/{id}/return', [WarehouseReverseLogisticsController::class, 'requestReturn'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_manager|warehouse_staff|manager')
        ->name('warehouse.reverse-logistics.quarantines.return');
    Route::post('api/warehouse/reverse-logistics/quarantines/{id}/destroy', [WarehouseReverseLogisticsController::class, 'destroyQuarantine'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_manager')
        ->name('warehouse.reverse-logistics.quarantines.destroy');
    Route::post('api/warehouse/reverse-logistics/returns/{id}/approve', [WarehouseReverseLogisticsController::class, 'approveReturn'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_manager')
        ->name('warehouse.reverse-logistics.returns.approve');
    Route::post('api/warehouse/reverse-logistics/returns/{id}/complete', [WarehouseReverseLogisticsController::class, 'completeReturn'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_manager')
        ->name('warehouse.reverse-logistics.returns.complete');
    Route::get('api/warehouse/reverse-logistics/claims', [WarehouseReverseLogisticsController::class, 'claims'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_manager|warehouse_staff|manager')
        ->name('warehouse.reverse-logistics.claims');
    Route::post('api/warehouse/reverse-logistics/claims', [WarehouseReverseLogisticsController::class, 'storeClaim'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_manager|warehouse_staff|manager')
        ->name('warehouse.reverse-logistics.claims.store');
    Route::post('api/warehouse/reverse-logistics/claims/{id}/resolve', [WarehouseReverseLogisticsController::class, 'resolveClaim'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_manager|manager')
        ->name('warehouse.reverse-logistics.claims.resolve');

    // Central Kitchen (Sơ chế & Sản xuất Trung tâm)
    Route::get('api/central-kitchen/boms', [CentralKitchenController::class, 'getBoms'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_manager')
        ->name('central-kitchen.boms');
    Route::post('api/central-kitchen/boms', [CentralKitchenController::class, 'storeBom'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_manager')
        ->name('central-kitchen.boms.store');
    Route::get('api/central-kitchen/work-orders', [CentralKitchenController::class, 'getWorkOrders'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_manager')
        ->name('central-kitchen.work-orders');
    Route::post('api/central-kitchen/work-orders', [CentralKitchenController::class, 'storeWorkOrder'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_manager')
        ->name('central-kitchen.work-orders.store');
    Route::post('api/central-kitchen/work-orders/{id}/execute', [CentralKitchenController::class, 'executeWorkOrder'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_manager')
        ->name('central-kitchen.work-orders.execute');

    // Delivery Manifests (Chuyến xe Giao hàng & Master Packing List)
    Route::get('api/delivery-manifests', [DeliveryManifestController::class, 'index'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_manager|warehouse_staff')
        ->name('delivery-manifests.index');
    Route::post('api/delivery-manifests', [DeliveryManifestController::class, 'store'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_manager')
        ->name('delivery-manifests.store');
    Route::get('api/delivery-manifests/{id}/packing-list', [DeliveryManifestController::class, 'packingList'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_manager|warehouse_staff')
        ->name('delivery-manifests.packing-list');
    Route::post('api/delivery-manifests/{id}/dispatch', [DeliveryManifestController::class, 'dispatch'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.handover|warehouse.manage|warehouse_manager')
        ->name('delivery-manifests.dispatch');
    Route::post('api/delivery-manifests/{id}/complete', [DeliveryManifestController::class, 'complete'])
        ->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse_staff|supply_requests.receive')
        ->name('delivery-manifests.complete');

    // Batch Recall Orders (Lệnh Thu hồi Lô Khẩn cấp 1-Click)
    Route::get('api/batch-recalls', [BatchRecallController::class, 'index'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_governance.view|warehouse_manager')
        ->name('batch-recalls.index');
    Route::post('api/batch-recalls/initiate', [BatchRecallController::class, 'initiate'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_governance.manage|warehouse_manager')
        ->name('batch-recalls.initiate');
    Route::post('api/batch-recalls/{id}/complete', [BatchRecallController::class, 'complete'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.manage|warehouse_governance.manage|warehouse_manager')
        ->name('batch-recalls.complete');

    // Quản lý vị trí kho (Zones, Racks, Bins, Cold storage, Quarantine)
    Route::get('api/warehouse-locations', [WarehouseLocationController::class, 'index'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.view|warehouse_manager|warehouse_staff')
        ->name('warehouse-locations.index');
    Route::post('api/warehouse-locations', [WarehouseLocationController::class, 'store'])
        ->middleware('role_or_permission:owner|super_admin|warehouse.location.manage|warehouse.manage|warehouse_manager')
        ->name('warehouse-locations.store');

    // Quản lý Hồ sơ Cảnh báo Gian lận (Warehouse Fraud Cases)
    Route::get('api/warehouse-fraud-cases', [WarehouseFraudCaseController::class, 'index'])
        ->middleware('role_or_permission:owner|super_admin|warehouse_governance.view|warehouse_manager')
        ->name('warehouse-fraud-cases.index');
    Route::post('api/warehouse-fraud-cases/{id}/assign', [WarehouseFraudCaseController::class, 'assign'])
        ->middleware('role_or_permission:owner|super_admin|warehouse_governance.manage|warehouse_manager')
        ->name('warehouse-fraud-cases.assign');
    Route::post('api/warehouse-fraud-cases/{id}/status', [WarehouseFraudCaseController::class, 'updateStatus'])
        ->middleware('role_or_permission:owner|super_admin|warehouse_governance.manage|warehouse_manager')
        ->name('warehouse-fraud-cases.update-status');

    // Bộ Quy Tắc Siết Chặt Quản Lý Tài Chính & Quy Trách Nhiệm Kho (Dành cho Trưởng Kho)
    Route::get('inventory/warehouse-governance', [WarehouseGovernanceController::class, 'page'])->middleware('role_or_permission:owner|super_admin|warehouse_governance.view')->name('inventory.warehouse-governance');
    Route::post('api/warehouse-governance/rules', [WarehouseGovernanceController::class, 'updateRules'])->middleware('role_or_permission:owner|super_admin|warehouse_governance.manage')->name('warehouse-governance.update-rules');
    Route::post('api/warehouse-governance/disputes/{id}/resolve', [WarehouseGovernanceController::class, 'resolveDispute'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse_governance.manage')->name('warehouse-governance.resolve-dispute');
    Route::post('api/warehouse-governance/disputes/{id}/respond', [WarehouseGovernanceController::class, 'respondDispute'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse_staff|manager')->name('warehouse-governance.respond-dispute');

    // Kiểm kê tồn kho nâng cao (Periodic, Spot check, Blind count)
    Route::get('inventory/branch-closing', [BranchClosingController::class, 'page'])->middleware('role_or_permission:owner|super_admin|manager|inventory.count')->name('inventory.branch-closing');
    Route::post('api/inventory/branch-closing', [BranchClosingController::class, 'store'])->middleware('role_or_permission:owner|super_admin|manager|inventory.count')->name('inventory.branch-closing.store');
    Route::post('api/inventory/branch-closing/{id}/assign', [BranchClosingController::class, 'assign'])->middleware('role_or_permission:owner|super_admin|manager|inventory.count')->name('inventory.branch-closing.assign');

    Route::get('inventory/central-warehouse/material-closing', [MaterialClosingController::class, 'page'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse_staff|inventory.count|inventory.count.execute')->name('inventory.material-closing');
    Route::post('api/inventory/central-warehouse/material-closing', [MaterialClosingController::class, 'store'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.count|inventory.count.execute')->name('inventory.material-closing.store');
    Route::post('api/inventory/central-warehouse/material-closing/{id}/assign', [MaterialClosingController::class, 'assign'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse.manage')->name('inventory.material-closing.assign');
    Route::post('api/inventory/central-warehouse/material-closing/{id}/counts', [MaterialClosingController::class, 'submitCounts'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|warehouse_staff|inventory.count|inventory.count.execute')->name('inventory.material-closing.counts');

    Route::get('inventory/count-sessions', [InventoryCountController::class, 'page'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.count|inventory.count.execute|inventory.adjust.approve')->name('inventory.count-sessions');
    Route::post('api/inventory/count-sessions', [InventoryCountController::class, 'store'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.count|inventory.count.execute')->name('inventory.count-sessions.store');
    Route::post('api/inventory/count-sessions/{id}/counts', [InventoryCountController::class, 'submitCounts'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.count|inventory.count.execute')->name('inventory.count-sessions.counts');
    Route::post('api/inventory/count-sessions/{id}/second-counter', [InventoryCountController::class, 'assignSecondCounter'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.count|inventory.count.execute')->name('inventory.count-sessions.second-counter');
    Route::post('api/inventory/count-sessions/{id}/items/{itemId}/reconcile', [InventoryCountController::class, 'reconcileItem'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.count|inventory.count.execute')->name('inventory.count-sessions.items.reconcile');
    Route::post('api/inventory/count-sessions/{id}/submit-approval', [InventoryCountController::class, 'submitForApproval'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.count|inventory.count.execute')->name('inventory.count-sessions.submit-approval');
    Route::post('api/inventory/count-sessions/{id}/upload-proof', [InventoryCountController::class, 'uploadVarianceProof'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.count|inventory.count.execute')->name('inventory.count-sessions.upload-proof');
    Route::get('api/inventory/count-sessions/{id}/proof', [InventoryCountController::class, 'viewVarianceProof'])->name('inventory.count-sessions.proof');
    Route::post('api/inventory/count-sessions/{id}/reject', [InventoryCountController::class, 'reject'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.adjust.approve')->name('inventory.count-sessions.reject');
    Route::post('api/inventory/count-sessions/{id}/reopen', [InventoryCountController::class, 'reopen'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.count')->name('inventory.count-sessions.reopen');
    Route::post('api/inventory/count-sessions/{id}/cancel', [InventoryCountController::class, 'cancel'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.count')->name('inventory.count-sessions.cancel');
    Route::post('api/inventory/count-sessions/{id}/approve', [InventoryCountController::class, 'approve'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.adjust.approve')->name('inventory.count-sessions.approve');

    // Bộ Quy Định & Tiêu Chuẩn Vận Hành Toàn Hệ Thống
    Route::get('operations/company-policies', [CompanyPolicyController::class, 'page'])->middleware('role_or_permission:owner|super_admin|company_policies.view|company_policies.manage')->name('operations.company-policies');
    // Nhân viên được phép tra cứu các quy định đã ban hành; quyền quản trị vẫn
    // được giữ riêng cho các route tạo, sửa và xóa bên dưới.
    Route::get('api/company-policies', [CompanyPolicyController::class, 'index'])->name('company-policies.index');
    Route::post('api/company-policies', [CompanyPolicyController::class, 'store'])->middleware('role_or_permission:owner|super_admin|company_policies.manage')->name('company-policies.store');
    Route::put('api/company-policies/{id}', [CompanyPolicyController::class, 'update'])->middleware('role_or_permission:owner|super_admin|company_policies.manage')->name('company-policies.update');
    Route::delete('api/company-policies/{id}', [CompanyPolicyController::class, 'destroy'])->middleware('role_or_permission:owner|super_admin|company_policies.manage')->name('company-policies.destroy');
    Route::post('api/company-policy-categories', [CompanyPolicyController::class, 'storeCategory'])->middleware('role_or_permission:owner|super_admin|company_policies.manage')->name('company-policy-categories.store');
    Route::put('api/company-policy-categories/{id}', [CompanyPolicyController::class, 'updateCategory'])->middleware('role_or_permission:owner|super_admin|company_policies.manage')->name('company-policy-categories.update');
    Route::delete('api/company-policy-categories/{id}', [CompanyPolicyController::class, 'destroyCategory'])->middleware('role_or_permission:owner|super_admin|company_policies.manage')->name('company-policy-categories.destroy');

    // Tổng quan thanh tra phải có route riêng. Không dùng /dashboard vì
    // DashboardController chủ động chuyển inspector về trang vận hành.
    Route::get('operations/audit/overview', [OperationalAuditController::class, 'overview'])->middleware('role_or_permission:owner|super_admin|operational_audit.view|operational_audit.approve')->name('operations.audit.overview');
    // Giám Sát Vận Hành & Lập/Duyệt Biên Bản Vi Phạm Xử Phạt
    Route::get('operations/audit', [OperationalAuditController::class, 'page'])->middleware('role_or_permission:owner|super_admin|operational_audit.view|operational_audit.approve')->name('operations.audit');
    Route::get('operations/inspection-workspace', [OperationalAuditController::class, 'inspectionWorkspace'])->middleware('role_or_permission:owner|super_admin|operational_inspection.view|operational_inspection.create|operational_audit.report')->name('operations.inspection-workspace');
    Route::get('api/operational-audit/inspections/{id}', [OperationalAuditController::class, 'inspectionDetails'])->middleware('role_or_permission:owner|super_admin|operational_inspection.view|operational_inspection.execute|operational_audit.report')->name('operational-audit.inspections.show');
    Route::post('api/operational-audit/reports', [OperationalAuditController::class, 'storeReport'])->middleware('role_or_permission:owner|super_admin|operational_audit.report')->name('operational-audit.reports.store');
    Route::post('api/operational-audit/reports/{id}/approve', [OperationalAuditController::class, 'approveReport'])->middleware('role_or_permission:owner|super_admin|operational_audit.approve')->name('operational-audit.reports.approve');
    Route::post('api/operational-audit/reports/{id}/reject', [OperationalAuditController::class, 'rejectReport'])->middleware('role_or_permission:owner|super_admin|operational_audit.approve')->name('operational-audit.reports.reject');
    Route::post('api/operational-audit/reports/{id}/assign', [OperationalAuditController::class, 'assignReport'])->middleware('role:owner|super_admin')->name('operational-audit.reports.assign');
    // Người được giao có thể nộp bằng chứng khắc phục; controller khóa theo assigned_to.
    Route::post('api/operational-audit/reports/{id}/remediation', [OperationalAuditController::class, 'submitRemediation'])->name('operational-audit.reports.remediation');
    Route::post('api/operational-audit/reports/{id}/reinspect', [OperationalAuditController::class, 'reinspectReport'])->middleware('role_or_permission:owner|super_admin|operational_audit.reinspect|inspection.close')->name('operational-audit.reports.reinspect');
    Route::get('api/operational-audit/export', [OperationalAuditController::class, 'export'])->middleware('role_or_permission:owner|super_admin|operational_audit.view|operational_audit.report')->name('operational-audit.export');
    Route::post('api/operational-audit/inspection-plans', [OperationalAuditController::class, 'storeInspectionPlan'])->middleware('role_or_permission:owner|super_admin|operational_audit.manage|operational_audit.report')->name('operational-audit.inspection-plans.store');
    Route::post('api/operational-audit/inspection-plans/{id}/start', [OperationalAuditController::class, 'startInspectionPlan'])->middleware('role_or_permission:owner|super_admin|operational_audit.manage|operational_audit.report')->name('operational-audit.inspection-plans.start');
    Route::post('api/operational-audit/inspection-plans/{id}/complete', [OperationalAuditController::class, 'completeInspectionPlan'])->middleware('role_or_permission:owner|super_admin|operational_audit.manage|operational_audit.report')->name('operational-audit.inspection-plans.complete');
    Route::post('api/operational-audit/inspection-plans/{id}/cancel', [OperationalAuditController::class, 'cancelInspectionPlan'])->middleware('role_or_permission:owner|super_admin|operational_audit.manage|operational_audit.report')->name('operational-audit.inspection-plans.cancel');
    Route::post('api/operational-audit/inspections', [OperationalAuditController::class, 'storeInspection'])->middleware('role_or_permission:owner|super_admin|operational_inspection.create|operational_inspection.manage|operational_audit.report')->name('operational-audit.inspections.store');
    Route::post('api/operational-audit/inspections/{id}/start', [OperationalAuditController::class, 'startInspection'])->middleware('role_or_permission:owner|super_admin|operational_inspection.execute|operational_audit.report')->name('operational-audit.inspections.start');
    Route::post('api/operational-audit/inspections/{id}/complete', [OperationalAuditController::class, 'completeInspection'])->middleware('role_or_permission:owner|super_admin|operational_inspection.execute|operational_audit.report')->name('operational-audit.inspections.complete');
    Route::post('api/operational-audit/inspections/{id}/checklist', [OperationalAuditController::class, 'recordChecklistResult'])->middleware('role_or_permission:owner|super_admin|operational_inspection.execute|operational_audit.report')->name('operational-audit.inspections.checklist');
    Route::post('api/operational-audit/reports/{id}/assignment/accept', [OperationalAuditController::class, 'acceptAssignment'])->name('operational-audit.reports.assignment.accept');
    Route::post('api/operational-audit/reports/{id}/assignment/reject', [OperationalAuditController::class, 'rejectAssignment'])->name('operational-audit.reports.assignment.reject');
    Route::post('api/operational-audit/reports/{id}/acknowledge', [OperationalAuditController::class, 'acknowledgeReport'])->middleware('role_or_permission:owner|super_admin|manager|operational_audit.branch_acknowledge')->name('operational-audit.reports.acknowledge');
    Route::post('api/operational-audit/reports/{id}/actions', [OperationalAuditController::class, 'storeCorrectiveAction'])->middleware('role:owner|super_admin')->name('operational-audit.reports.actions.store');
    Route::post('api/operational-audit/inspections/{id}/actions', [OperationalAuditController::class, 'storeInspectionCorrectiveAction'])->middleware('role:owner|super_admin')->name('operational-audit.inspections.actions.store');
    Route::patch('api/operational-audit/actions/{id}', [OperationalAuditController::class, 'updateCorrectiveAction'])->name('operational-audit.actions.update');
    Route::post('api/operational-audit/evidence', [OperationalAuditController::class, 'storeEvidence'])->middleware('role_or_permission:owner|super_admin|operational_audit.evidence.upload|operational_audit.report')->name('operational-audit.evidence.store');
    Route::get('api/operational-audit/evidence/{id}', [OperationalAuditController::class, 'downloadEvidence'])->name('operational-audit.evidence.download');
    Route::post('api/operational-audit/links', [OperationalAuditController::class, 'storeCaseLink'])->middleware('role_or_permission:owner|super_admin|operational_audit.report')->name('operational-audit.links.store');

    // Quản lý Đấu thầu RFP (Dành cho nhà hàng)
    Route::middleware('supplier.portal')->group(function () {
        Route::get('rfps', [RfpController::class, 'index'])->name('rfps.index');
        Route::post('rfps', [RfpController::class, 'store'])->name('rfps.store');
        Route::post('rfps/{rfp}/close', [RfpController::class, 'close'])->name('rfps.close');
        Route::post('rfps/bids/{bid}/accept', [RfpController::class, 'acceptBid'])->name('rfps.bids.accept');
    });

    // Portal Chuỗi cung ứng (Dành cho nhà cung cấp)
    Route::prefix('supplier')->name('supplier.')->middleware(['role:supplier', 'supplier.portal'])->group(function () {
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
    Route::patch('api/temporary-orders/{temporaryOrder}/revise', [StaffQROrderController::class, 'requestRevision'])->name('temporary-orders.revise');
    Route::post('api/temporary-orders/{temporaryOrder}/cancel', [StaffQROrderController::class, 'cancel'])->name('temporary-orders.cancel');
    Route::get('api/temporary-orders/rejected-logs', [StaffQROrderController::class, 'rejectedLogs'])->name('temporary-orders.rejected-logs');

    // Trợ lý AI Chiến lược (AI Advisor)
    Route::get('ai-advisor', [ChatbotController::class, 'advisorIndex'])->name('ai-advisor.index');
    Route::get('inventory/central-warehouse/ai-advisor', [ChatbotController::class, 'centralWarehouseAdvisorIndex'])
        ->middleware('role_or_permission:owner|super_admin|warehouse_manager')
        ->name('inventory.central-warehouse.ai-advisor');
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
            Route::get('shipper/current', [ShipperPwaController::class, 'current'])->name('shipper.current');
            Route::post('shipper/batches/{batch}/accept', [ShipperPwaController::class, 'acceptBatch'])->name('shipper.batch.accept');
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
            Route::get('/profile', [EmployeePortalController::class, 'profile'])->name('profile');
            Route::get('/data', [EmployeePortalController::class, 'getDashboardData'])->name('data');
            Route::get('/salaries', [EmployeePortalController::class, 'getSalaries'])->name('salaries');
            Route::get('/overtime', [OvertimeController::class, 'portalIndex'])->name('overtime');
            Route::post('/overtime', [OvertimeController::class, 'portalStore'])->name('overtime.store');
            Route::post('/overtime/{overtimeRequest}/respond', [OvertimeController::class, 'portalRespond'])->name('overtime.respond');
            Route::get('/leaves', [EmployeePortalController::class, 'getLeaves'])->name('leaves');
            Route::post('/leaves', [EmployeePortalController::class, 'storeLeaveRequest'])->name('leaves.store');
            Route::get('/swaps', [EmployeePortalController::class, 'getSwaps'])->name('swaps');
            Route::post('/swaps/request', [EmployeePortalController::class, 'requestSwap'])->name('swaps.request');
            Route::post('/swaps/{swap}/respond', [EmployeePortalController::class, 'respondSwap'])->name('swaps.respond');
            Route::post('/notifications/read-all', [EmployeePortalController::class, 'readAllNotifications'])->name('notifications.read-all');
        });

    // Quick Actions API Routes (Batch 1)
    Route::post('reservations/{reservation}/auto-assign', [TableReservationController::class, 'autoAssignTable'])->name('reservations.auto-assign');
    Route::post('orders/batch-approve-qr', [OrdersController::class, 'batchApproveQrOrders'])->name('orders.batch-approve-qr');
    Route::post('inventory/counts/quick-preset', [InventoryCountController::class, 'quickCountPreset'])->middleware('role_or_permission:owner|super_admin|warehouse_manager|inventory.count|inventory.count.execute')->name('inventory.counts.quick-preset');
    Route::post('supply-requests/quick-recommended', [SupplyRequestController::class, 'quickRecommendedRequest'])->middleware('role_or_permission:owner|super_admin|supply_requests.create')->name('supply-requests.quick-recommended');
    Route::post('kitchen/items/{item}/notify-waiter', [KitchenController::class, 'notifyWaiterOverdue'])->name('kitchen.items.notify-waiter');
    Route::post('approvals/batch-approve-low-risk', [ApprovalController::class, 'batchApproveLowRisk'])->name('approvals.batch-approve-low-risk');

    // Quick Actions API Routes (Batch 2)
    Route::post('attendance/batch-approve-normal', [AttendanceController::class, 'batchApproveNormal'])->name('attendance.batch-approve-normal');
    Route::post('warehouse/tasks/quick-auto-assign', [WarehouseStaffController::class, 'quickAutoAssignTasks'])->name('warehouse.tasks.quick-auto-assign');
    Route::post('delivery/batches/{batch}/mark-all-picked-up', [DeliveryManagementController::class, 'markAllPickedUp'])->name('delivery.batches.mark-all-picked-up');
    Route::post('products/toggle-out-of-stock-ingredients', [ProductManagementController::class, 'pauseProductsWithLowStockIngredients'])->name('products.toggle-out-of-stock-ingredients');
    Route::post('feedback/{feedback}/quick-template-reply', [FeedbackController::class, 'quickTemplateReply'])->name('feedback.quick-template-reply');
    Route::post('purchase-orders/send-delivery-reminder', [PurchaseOrderController::class, 'sendDeliveryReminder'])->name('purchase-orders.send-delivery-reminder');

    // Chi nhánh làm việc
    Route::post('branch/switch', [BranchSwitchController::class, 'switchBranch'])->name('branch.switch');
});
