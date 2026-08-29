<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Nguồn sự thật cho chính sách gói cước.
 *
 * Rà soát ngày 25/08/2026 cho thấy chỉ 32 trên 89 controller render Inertia có
 * gọi QuotaService::hasFeature(). Vấn đề không nằm ở con số — nhiều trang thuộc
 * gói miễn phí một cách hợp lệ — mà ở chỗ KHÔNG CÓ TÀI LIỆU NÀO nói trang nào
 * thuộc gói nào, nên không phân biệt được "miễn phí có chủ đích" với "quên gate".
 *
 * Bảng dưới đây là tài liệu đó, ở dạng chạy được. Thêm trang mới thì phải khai
 * báo vào một trong hai danh sách, nếu không test đỏ.
 */
class FeatureGatePolicyTest extends TestCase
{
    // Bảng route dựng middleware của controller, trong đó có nhánh tra cứu
    // bảng permissions — nên test này cần schema thật.
    use RefreshDatabase;

    /**
     * Controller BẮT BUỘC phải kiểm hasFeature() vì thuộc gói trả phí.
     *
     * @var array<string, string>
     */
    private array $paidControllers = [
        'BIDashboardController' => 'advanced_analytics',
        'BestSellerController' => 'advanced_analytics',
        'BranchClosingController' => 'inventory_basic',
        'BusinessGoalController' => 'advanced_analytics',
        'CashFlowController' => 'advanced_analytics',
        'CdpController' => 'advanced_analytics',
        'ChatbotController' => 'ai_advisor',
        'CustomerController' => 'advanced_analytics',
        'DebtController' => 'advanced_analytics',
        'Delivery\\DeliveryManagementController' => 'advanced_analytics',
        'EmployeeManagementController' => 'hr_full',
        'BonusController' => 'hr_full',
        'OvertimeController' => 'hr_timekeeping',
        'EnterpriseCommandCenterController' => 'advanced_analytics',
        'ExpenseController' => 'advanced_analytics',
        'FraudController' => 'fraud_detection',
        'GeoAnalyticsController' => 'advanced_analytics',
        'InventoryManagementController' => 'inventory_basic',
        'MaterialClosingController' => 'inventory_basic',
        'KitchenController' => 'kitchen_display',
        'KpiController' => 'advanced_analytics',
        'LoyaltyController' => 'advanced_analytics',
        'MenuEngineeringController' => 'advanced_analytics',
        'OnlineStoreSettingsController' => 'qr_ordering',
        'PromotionAnalyticsController' => 'advanced_analytics',
        'PromotionController' => 'advanced_analytics',
        'PromotionTriggerController' => 'advanced_analytics',
        'ReportsController' => 'advanced_analytics',
        'RfpController' => 'supplier_portal',
        'SalaryController' => 'hr_full',
        'ScheduleController' => 'hr_timekeeping',
        'ShiftClosingController' => 'advanced_analytics',
        'TrainingController' => 'hr_full',
        'ViolationReportController' => 'hr_full',
        'WasteManagementController' => 'inventory_basic',
        'DashboardController' => 'advanced_analytics',
    ];

    public function test_paid_controllers_actually_check_the_feature_gate(): void
    {
        $missing = [];

        foreach ($this->paidControllers as $short => $feature) {
            $class = 'App\\Http\\Controllers\\'.$short;

            if (! class_exists($class)) {
                $missing[] = "{$short} — controller không còn tồn tại, xoá khỏi bảng chính sách.";

                continue;
            }

            $source = file_get_contents((new \ReflectionClass($class))->getFileName());

            if (! str_contains($source, 'hasFeature')) {
                $missing[] = "{$short} — thuộc gói '{$feature}' nhưng không kiểm hasFeature().";
            }
        }

        $this->assertSame([], $missing, sprintf(
            "Chính sách gói cước bị vi phạm:\n\n%s\n\n".
            "Cách xử lý: thêm kiểm tra QuotaService::hasFeature() và trả về\n".
            "Inertia::render('FeatureGate', [...]), hoặc chuyển controller sang\n".
            "diện miễn phí bằng cách xoá khỏi \$paidControllers.\n",
            implode("\n", array_map(fn ($m) => '  - '.$m, $missing)),
        ));
    }

    public function test_every_inertia_controller_has_a_declared_plan_tier(): void
    {
        // Trang mới phải được xếp gói ngay khi ra đời, thay vì mặc định lọt vào
        // gói miễn phí chỉ vì không ai nhớ đặt gate.
        $undeclared = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if (! str_starts_with($action, 'App\\Http\\Controllers\\') || ! str_contains($action, '@')) {
                continue;
            }
            if (str_contains($action, '\\SuperAdmin\\') || str_contains($action, '\\Auth\\')) {
                continue;
            }

            [$class] = explode('@', $action);
            $short = str_replace('App\\Http\\Controllers\\', '', $class);

            if (
                isset($this->paidControllers[$short])
                || in_array($short, $this->freeControllers, true)
                || in_array($short, $this->pendingPolicyDecision, true)
            ) {
                continue;
            }

            $file = (new \ReflectionClass($class))->getFileName();
            if ($file === false || ! str_contains(file_get_contents($file), 'Inertia::render')) {
                continue;
            }

            $undeclared[$short] = $short;
        }

        $this->assertSame([], array_values($undeclared), sprintf(
            "Có %d controller render trang nhưng chưa được xếp vào gói cước nào:\n\n%s\n\n".
            "Cách xử lý: thêm vào \$paidControllers (kèm tên feature) nếu là tính năng\n".
            "trả phí, hoặc vào \$freeControllers nếu thuộc gói miễn phí.\n",
            count($undeclared),
            implode("\n", array_map(fn ($m) => '  - '.$m, $undeclared)),
        ));
    }

    public function test_the_pending_policy_list_does_not_grow(): void
    {
        // Danh sách chờ quyết định là nợ, không phải chỗ chứa. Nó được phép
        // ngắn đi, không được dài ra: tính năng mới phải xếp gói ngay từ đầu.
        $this->assertLessThanOrEqual(
            33,
            count($this->pendingPolicyDecision),
            'Danh sách chờ quyết định gói cước đang dài ra. '.
            'Tính năng mới phải được xếp vào $paidControllers hoặc $freeControllers ngay khi ra đời.',
        );
    }

    /**
     * Gói miễn phí một cách có chủ đích: nghiệp vụ lõi mà mọi nhà hàng phải dùng
     * được dù trả tiền hay không, trang tài khoản của chính người dùng, trang
     * công khai, và cổng dành cho khách hàng cuối.
     *
     * Chặn tính phí ở những trang này là chặn chính việc nhà hàng bán hàng.
     *
     * @var list<string>
     */
    private array $freeControllers = [
        // Bán hàng lõi — không có mấy thứ này thì phần mềm vô dụng
        'OrdersController',
        'TablesController',
        'ProductManagementController',
        'KitchenMenuControlController',
        'FeedbackController',

        // Tài khoản & cấu hình của chính người dùng
        'Settings\\ProfileController',
        'Settings\\RestaurantController',
        'Settings\\BranchController',
        'RestaurantChooserController',
        'OnboardingController',
        'SupportController',
        'MyRequestsController',
        'EmployeePortalController',

        // Thanh toán gói cước — tính phí ở đây thì khách không nâng cấp được
        'Billing\\CheckoutController',

        // Trang công khai
        'HomeController',
        'NewsController',
        'PublicStatusController',

        // Cổng dành cho khách hàng cuối và tài xế: người dùng ở đây không phải
        // là người trả tiền, chặn họ là chặn doanh thu của chính nhà hàng
        'OnlineOrderController',
        'Customer\\QROrderController',
        'Customer\\CustomerPortalController',
        'Customer\\CouponWalletController',
        'Delivery\\ShipperPwaController',
        'Delivery\\ShipperController',
    ];

    /**
     * CHƯA CHỐT — cần Chủ sản phẩm quyết định, không phải quyết định kỹ thuật.
     *
     * Đây là những module mà việc xếp gói là câu hỏi kinh doanh thật sự: một nhà
     * hàng nhỏ có được dùng đối soát ngân hàng miễn phí không? Kiểm kê kho có
     * phải tính năng trả phí không? Tôi không tự đặt giá thay bạn.
     *
     * Cách dùng: chốt xong thì chuyển dòng đó sang $paidControllers (kèm tên
     * feature) hoặc $freeControllers, rồi xoá khỏi đây. Danh sách này rỗng dần
     * chính là tiến độ của việc chuẩn hoá chính sách gói cước.
     *
     * @var list<string>
     */
    private array $pendingPolicyDecision = [
        // Tài chính — nhóm cần chốt sớm nhất vì đây là giá trị bán được
        'ProfitLossController',
        'FinancialController',
        'IngredientSpendController',
        'FinancialBudgetController',
        'FixedAssetController',
        'BankReconciliationController',
        'PayrollBudgetController',

        // Kho & chuỗi cung ứng nâng cao (InventoryManagement đã là inventory_basic)
        'SupplierController',
        'SupplierPortalController',
        'SupplyRequestController',
        'StockTransferRequestController',
        'InventoryCountController',
        'InventoryNegativeStockController',
        'CentralWarehouseTeamController',
        'CentralWarehousePriceController',
        'CentralKitchenController',
        'WarehouseStaffController',
        'WarehouseGovernanceController',
        'WarehouseReverseLogisticsController',
        'DeliveryManifestController',
        'BatchRecallController',

        // Vận hành & tuân thủ
        'EquipmentController',
        'OperationsChecklistController',
        'ShiftHandoverController',
        'OperationalAuditController',
        'IncidentController',
        'ApprovalController',
        'ApprovalPolicyController',
        'OperationPolicyController',
        'CompanyPolicyController',
        'AuditLogController',
        'IntegrationSettingsController',
        'TableReservationController',
    ];
}
