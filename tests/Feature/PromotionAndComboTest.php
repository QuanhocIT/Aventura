<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\Employee;
use App\Models\WorkShift;
use App\Models\ScheduleAssignment;
use App\Jobs\LogDiscountAppliedJob;
use App\Services\FraudDetectionService;
use App\Support\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromotionAndComboTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $manager;
    private User $cashier;
    private Employee $cashierEmp;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private Role $ownerRole;
    private Role $managerRole;
    private Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles Setup
        $this->ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $this->cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        // 2. Tenant & Owner Setup
        $this->owner = User::factory()->create(['status' => 'active']);
        $this->owner->assignRole($this->ownerRole);
        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['branch_id' => $this->branch->id]);

        // Thiết lập Tenant Context cho việc khởi tạo DB trong test setup
        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        // 3. Manager Setup
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active'
        ]);
        $this->manager->assignRole($this->managerRole);

        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->manager->id,
            'role_id' => $this->managerRole->id,
            'status' => 'active',
        ]);

        // 4. Cashier Setup (Employee + WorkShift + Schedule để vượt qua SetTenantContext)
        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active'
        ]);
        $this->cashier->assignRole($this->cashierRole);

        $this->cashierEmp = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->cashier->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Chieu',
            'code' => 'CA_CHIEU',
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'is_overnight' => false,
            'status' => 'active',
        ]);

        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->cashierEmp->id,
            'shift_id' => $shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);
    }

    /**
     * Test phân quyền bảo mật nghiêm ngặt cho trang promotions.
     */
    public function test_promotions_page_authorization(): void
    {
        // Cashier bị chặn truy cập (Trả về 403 Forbidden)
        $this->actingAs($this->cashier)
            ->get('/promotions')
            ->assertStatus(403);

        // Owner truy cập thành công
        $this->actingAs($this->owner)
            ->get('/promotions')
            ->assertStatus(200);

        // Manager truy cập thành công
        $this->actingAs($this->manager)
            ->get('/promotions')
            ->assertStatus(200);
    }

    /**
     * Test quy trình tạo và phê duyệt khuyến mãi.
     */
    public function test_promotion_creation_and_approval(): void
    {
        // 1. Owner tạo khuyến mãi -> tự động phê duyệt
        $this->actingAs($this->owner)
            ->post('/promotions', [
                'name' => 'Khuyến mãi hè của Owner',
                'code' => 'OWNER20',
                'type' => 'percent',
                'value' => 20,
                'min_order_amount' => 100000,
            ])
            ->assertRedirect();

        $promoOwner = Promotion::where('code', 'OWNER20')->first();
        $this->assertNotNull($promoOwner);
        $this->assertTrue($promoOwner->is_approved);

        // 2. Manager tạo khuyến mãi -> chờ duyệt (is_approved = false)
        $this->actingAs($this->manager)
            ->post('/promotions', [
                'name' => 'Khuyến mãi hè của Manager',
                'code' => 'MNGR15',
                'type' => 'percent',
                'value' => 15,
                'min_order_amount' => 50000,
            ])
            ->assertRedirect();

        $promoManager = Promotion::where('code', 'MNGR15')->first();
        $this->assertNotNull($promoManager);
        $this->assertFalse($promoManager->is_approved);

        // 3. Owner duyệt khuyến mãi của Manager
        $this->actingAs($this->owner)
            ->post("/promotions/{$promoManager->id}/approve")
            ->assertRedirect();

        $promoManager->refresh();
        $this->assertTrue($promoManager->is_approved);
    }

    /**
     * Test Model Observer bắt sự kiện và đẩy ngầm ghi log áp mã qua Queue.
     */
    public function test_discount_applied_observer_queues_job(): void
    {
        Queue::fake();

        // Tạo khuyến mãi được duyệt hoạt động
        $promotion = Promotion::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Giảm giá VIP',
            'code' => 'VIPGIAM',
            'type' => 'percent',
            'value' => 10,
            'min_order_amount' => 50000,
            'is_active' => true,
            'is_approved' => true,
        ]);

        // Tạo order
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-TEST-OBS',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total_amount' => 100000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        // Áp mã giảm giá từ Cashier qua API
        $this->actingAs($this->cashier)
            ->postJson('/api/promotions/apply', [
                'order_id' => $order->id,
                'code' => 'VIPGIAM',
            ])
            ->assertStatus(200);

        // Kiểm tra xem Observer đã bắt được sự kiện discount_amount thay đổi và dispatch Job thành công
        Queue::assertPushed(LogDiscountAppliedJob::class);
    }

    /**
     * Test thuật toán Fraud Detection phát hiện cashier áp mã liên tục bất thường.
     */
    public function test_fraud_detection_captures_anomalies(): void
    {
        // Đảm bảo TenantContext thiết lập đúng
        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-1',
            'subtotal' => 200000,
            'total_amount' => 160000,
            'discount_amount' => 40000,
        ]);

        // Ghi log 3 lần liên tục trong vòng 10 phút
        for ($i = 1; $i <= 3; $i++) {
            AuditLog::create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'user_id' => $this->cashier->id,
                'user_role' => 'cashier',
                'event' => 'updated',
                'action' => 'discount_applied',
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'old_values' => ['discount_amount' => 0],
                'new_values' => ['discount_amount' => 40000],
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => now()->subMinutes(15 - $i),
            ]);
        }

        // Chạy kiểm toán gian lận
        $fraudService = new FraudDetectionService(
            $this->restaurant->id,
            now()->subDays(1)->toDateString(),
            now()->toDateString()
        );

        $alerts = $fraudService->detectAiFraudAlerts();

        // Xác minh phát hiện cảnh báo áp voucher bất thường
        $voucherAlerts = collect($alerts)->filter(fn($a) => str_contains($a['violation_type'], 'voucher') || str_contains($a['violation_type'], 'Voucher'));
        $this->assertNotEmpty($voucherAlerts);

        $criticalAlert = $voucherAlerts->first();
        $this->assertEquals('critical', $criticalAlert['severity']);
        $this->assertGreaterThanOrEqual(95.0, $criticalAlert['risk_score']);
        $this->assertEquals($this->cashierEmp->id, $criticalAlert['employee_id']);
    }

    /**
     * Test API phân tích giỏ hàng (Market Basket Analysis) & Fallback dự phòng.
     */
    public function test_basket_analysis_and_fallback(): void
    {
        // Đảm bảo TenantContext được set đúng trước khi tạo dữ liệu
        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        // 1. Tạo dữ liệu các món ăn và hóa đơn
        $productA = Product::factory()->create(['name' => 'Lẩu gà Hỏa Đứng', 'restaurant_id' => $this->restaurant->id]);
        $productB = Product::factory()->create(['name' => 'Nước Cốt Sấu Hạt Chia', 'restaurant_id' => $this->restaurant->id]);

        // Tạo 3 đơn hàng hoàn thành chứa cả 2 món này để tạo liên kết
        for ($i = 0; $i < 3; $i++) {
            $order = Order::create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'order_number' => "ORD-MBA-$i",
                'status' => 'completed',
                'subtotal' => 200000,
                'total_amount' => 200000,
            ]);

            OrderItem::create([
                'restaurant_id' => $this->restaurant->id,
                'order_id' => $order->id,
                'product_id' => $productA->id,
                'quantity' => 1,
                'unit_price' => 150000,
                'line_total' => 150000,
            ]);

            OrderItem::create([
                'restaurant_id' => $this->restaurant->id,
                'order_id' => $order->id,
                'product_id' => $productB->id,
                'quantity' => 1,
                'unit_price' => 50000,
                'line_total' => 50000,
            ]);
        }

        // 2. Mock HTTP để giả định FastAPI microservice đang offline
        Http::fake([
            'http://localhost:8003/*' => Http::response(null, 500)
        ]);

        // 3. Gọi API phân tích và kiểm tra xem có tự động kích hoạt Fallback không
        $response = $this->actingAs($this->owner)
            ->get('/api/promotions/basket-analysis')
            ->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(3, $data['total_orders']);
        $this->assertNotEmpty($data['rules']);
        $this->assertEquals('Laravel Fallback Engine (Fail-safe Active)', $data['source']);

        $rule = $data['rules'][0];
        $this->assertEquals('Lẩu gà Hỏa Đứng', $rule['item_a']);
        $this->assertEquals('Nước Cốt Sấu Hạt Chia', $rule['item_b']);
        $this->assertEquals(1.0, $rule['support']);
        $this->assertEquals(1.0, $rule['confidence']);
    }

    /**
     * Test API gợi ý Upsell thông qua FastAPI Python Service thành công.
     */
    public function test_upsell_suggestion_via_fastapi(): void
    {
        // 1. Mock HTTP cho FastAPI hoạt động thành công
        Http::fake([
            'http://localhost:8003/api/analytics/upsell-suggestion' => Http::response([
                'suggestion' => 'AI đề xuất: Khách đang gọi Lẩu gà Hỏa Đứng, mời dùng thêm Nước Cốt Sấu Hạt Chia để được áp dụng combo giảm giá.',
                'recommended_item' => 'Nước Cốt Sấu Hạt Chia',
                'confidence' => 0.85,
                'lift' => 2.8
            ], 200)
        ]);

        // 2. Gọi API gợi ý từ Laravel
        $response = $this->actingAs($this->cashier)
            ->postJson('/api/promotions/upsell-suggestion', [
                'items' => ['Lẩu gà Hỏa Đứng']
            ])
            ->assertStatus(200);

        $data = $response->json();
        $this->assertStringContainsString('Python Service', $data['source']);
        $this->assertEquals('Nước Cốt Sấu Hạt Chia', $data['recommended_item']);
        $this->assertStringContainsString('Lẩu gà Hỏa Đứng', $data['suggestion']);
    }

    /**
     * Test API gợi ý Upsell tự động kích hoạt Fallback khi FastAPI offline.
     */
    public function test_upsell_suggestion_fallback_when_fastapi_offline(): void
    {
        // Thiết lập Tenant Context cho việc khởi tạo DB
        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        // Tạo dữ liệu sản phẩm & đơn hàng hoàn thành để Fallback Engine phân tích
        $productA = Product::factory()->create(['name' => 'Lẩu gà Hỏa Đứng', 'restaurant_id' => $this->restaurant->id]);
        $productB = Product::factory()->create(['name' => 'Nước Cốt Sấu Hạt Chia', 'restaurant_id' => $this->restaurant->id]);

        for ($i = 0; $i < 3; $i++) {
            $order = Order::create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'order_number' => "ORD-UP-$i",
                'status' => 'completed',
                'subtotal' => 200000,
                'total_amount' => 200000,
            ]);

            OrderItem::create([
                'restaurant_id' => $this->restaurant->id,
                'order_id' => $order->id,
                'product_id' => $productA->id,
                'quantity' => 1,
                'unit_price' => 150000,
                'line_total' => 150000,
            ]);

            OrderItem::create([
                'restaurant_id' => $this->restaurant->id,
                'order_id' => $order->id,
                'product_id' => $productB->id,
                'quantity' => 1,
                'unit_price' => 50000,
                'line_total' => 50000,
            ]);
        }

        // 1. Mock HTTP cho FastAPI bị lỗi 500
        Http::fake([
            'http://localhost:8003/*' => Http::response(null, 500)
        ]);

        // 2. Gọi API gợi ý từ Laravel và kiểm tra xem có chạy Fallback không
        $response = $this->actingAs($this->cashier)
            ->postJson('/api/promotions/upsell-suggestion', [
                'items' => ['Lẩu gà Hỏa Đứng']
            ])
            ->assertStatus(200);

        $data = $response->json();
        $this->assertEquals('Laravel Fallback Engine (Fail-safe Active)', $data['source']);
        $this->assertEquals('Nước Cốt Sấu Hạt Chia', $data['recommended_item']);
        $this->assertStringContainsString('Lẩu gà Hỏa Đứng', $data['suggestion']);
    }
}
