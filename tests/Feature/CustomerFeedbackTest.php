<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\Employee;
use App\Models\WorkShift;
use App\Models\ScheduleAssignment;
use App\Models\CustomerFeedback;
use App\Support\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerFeedbackTest extends TestCase
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

        // Thiết lập Tenant Context
        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        // 3. Manager Setup
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active'
        ]);
        $this->manager->assignRole($this->managerRole);

        Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->manager->id,
            'role_id' => $this->managerRole->id,
            'status' => 'active',
            'employee_code' => 'EMP-MNGR',
            'full_name' => $this->manager->name,
            'email' => $this->manager->email,
            'phone' => '0987654322',
            'citizen_id_number' => '123456789013',
            'address' => 'Hà Nội',
            'hire_date' => today(),
            'base_salary' => 8000000,
            'job_title' => 'Quản lý',
            'employment_type' => 'full_time',
        ]);

        // 4. Cashier Setup
        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active'
        ]);
        $this->cashier->assignRole($this->cashierRole);

        $this->cashierEmp = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->cashier->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
            'employee_code' => 'EMP-CASH',
            'full_name' => $this->cashier->name,
            'email' => $this->cashier->email,
            'phone' => '0987654321',
            'citizen_id_number' => '123456789012',
            'address' => 'Hà Nội',
            'hire_date' => today(),
            'base_salary' => 5000000,
            'job_title' => 'Thu ngân',
            'employment_type' => 'full_time',
        ]);

        // Thiết lập ca trực cho Cashier
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Chieu',
            'code' => 'CA_CHIEU',
            'start_time' => '12:00:00',
            'end_time' => '18:00:00',
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
     * Test khách hàng gửi phản hồi công khai (Public Feedback) thành công.
     */
    public function test_guest_can_submit_public_feedback(): void
    {
        // 1. Tạo order làm bối cảnh đánh giá
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-FEED-1',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'completed',
        ]);

        // 2. Gửi request đánh giá (không ẩn danh)
        $response = $this->postJson('/feedback', [
            'rating' => 5,
            'content' => 'Món ăn ngon và phục vụ chu đáo!',
            'is_anonymous' => false,
            'submitted_by_name' => 'Nguyễn Văn Khách',
            'submitted_by_phone' => '0987654321',
            'order_id' => $order->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // 3. Kiểm tra DB
        $this->assertDatabaseHas('customer_feedback', [
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
            'submitted_by_name' => 'Nguyễn Văn Khách',
            'submitted_by_phone' => '0987654321',
            'is_anonymous' => false,
            'status' => 'new',
        ]);
    }

    /**
     * Test phân quyền bảo mật trang quản lý phản hồi.
     */
    public function test_feedback_page_authorization(): void
    {
        // Cashier bị chặn (403)
        $this->actingAs($this->cashier)
            ->get('/feedback')
            ->assertStatus(403);

        // Owner vào được (200)
        $this->actingAs($this->owner)
            ->get('/feedback')
            ->assertStatus(200);

        // Manager vào được (200)
        $this->actingAs($this->manager)
            ->get('/feedback')
            ->assertStatus(200);
    }

    /**
     * Test Quản lý xử lý đền bù Voucher và đổi trạng thái Feedback thành công.
     */
    public function test_admin_can_resolve_feedback_with_voucher_compensation(): void
    {
        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        // 1. Tạo sẵn voucher giảm giá hoạt động để đền bù
        Promotion::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Voucher Đền Bù VIP',
            'code' => 'SORRY50',
            'type' => 'fixed_amount',
            'value' => 50000,
            'is_active' => true,
            'is_approved' => true,
        ]);

        // 2. Tạo phản hồi mới từ khách hàng
        $feedback = CustomerFeedback::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 2,
            'content' => 'Nhân viên thái độ lồi lõm với khách!',
            'is_anonymous' => false,
            'submitted_by_name' => 'Lê Thị Giận Dữ',
            'submitted_by_phone' => '0912345678',
            'status' => 'new',
        ]);

        // 3. Admin xử lý đền bù sự cố
        $response = $this->actingAs($this->owner)
            ->post("/feedback/{$feedback->id}/resolve", [
                'compensation_voucher' => 'SORRY50',
                'resolution_notes' => 'Đã gọi điện xin lỗi trực tiếp và gửi voucher giảm 50k tri ân, khách đã hết giận.',
                'status' => 'resolved',
            ]);

        $response->assertRedirect();

        // 4. Kiểm tra trạng thái đã thay đổi trong DB
        $feedback->refresh();
        $this->assertEquals('resolved', $feedback->status);
        $this->assertEquals('SORRY50', $feedback->compensation_voucher);
        $this->assertStringContainsString('gửi voucher giảm 50k', $feedback->resolution_notes);

        // 5. Kiểm tra hệ thống có ghi Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $this->restaurant->id,
            'action' => 'feedback_resolved',
            'subject_type' => CustomerFeedback::class,
            'subject_id' => $feedback->id,
        ]);
    }

    /**
     * Test đối chiếu ca làm việc chịu trách nhiệm từ thời gian tạo hóa đơn sự cố.
     */
    public function test_feedback_incident_context_shift_attribution(): void
    {
        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        $product = Product::factory()->create(['name' => 'Súp Gà Ngô Non', 'restaurant_id' => $this->restaurant->id]);
        
        $area = \App\Models\Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Khu A',
            'code' => 'khu-a',
            'status' => 'active'
        ]);

        $table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $area->id,
            'name' => 'Bàn VIP 1',
            'status' => 'available',
        ]);

        // Tạo order phát sinh lúc 15:30 chiều (Nằm giữa 12:00:00 và 18:00:00 của Ca Chieu)
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $table->id,
            'order_number' => 'ORD-UP-99',
            'status' => 'completed',
            'created_at' => today()->setTime(15, 30, 0),
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 45000,
            'line_total' => 45000,
        ]);

        // Tạo feedback đánh giá 1 sao liên kết với hóa đơn này
        $feedback = CustomerFeedback::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'rating' => 1,
            'content' => 'Món súp quá dở, phục vụ chậm chạp!',
            'is_anonymous' => true,
            'status' => 'new',
            'created_at' => today()->setTime(15, 45, 0),
        ]);

        // Gọi API lấy danh sách phản hồi để kiểm tra dữ liệu đối chiếu ca trực được map động
        $response = $this->actingAs($this->owner)
            ->get('/feedback')
            ->assertStatus(200);

        // Kiểm tra xem dữ liệu map trả về trong Inertia render có chính xác ca chịu trách nhiệm
        $feedbacksData = $response->original->getData()['page']['props']['feedbacks'];
        
        $targetFb = collect($feedbacksData)->firstWhere('id', $feedback->id);
        $this->assertNotNull($targetFb);
        $this->assertEquals('Ca Chieu', $targetFb['responsible_shift']);
        $this->assertContains($this->cashier->name, $targetFb['responsible_staff']);
        $this->assertContains('Súp Gà Ngô Non', $targetFb['items']);
        $this->assertEquals('Bàn VIP 1', $targetFb['table_name']);
    }
}
