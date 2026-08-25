<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\ViolationReport;
use App\Models\WorkShift;
use App\Support\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InternalIntegrityTest extends TestCase
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
            'status' => 'active',
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
            'status' => 'active',
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
     * Test nhân sự có thể gửi vé tố cáo nội bộ ẩn danh.
     */
    public function test_staff_can_submit_anonymous_whistleblowing(): void
    {
        // Tố cáo phải nhắm vào NGƯỜI KHÁC: ViolationReportController chặn tự lập
        // biên bản cho chính mình để không ai thao túng được lương của bản thân.
        $targetEmp = Employee::where('restaurant_id', $this->restaurant->id)
            ->where('employee_code', 'EMP-MNGR')
            ->firstOrFail();

        $response = $this->actingAs($this->cashier)
            ->post('/violations', [
                'employee_id' => $targetEmp->id,
                'violation_type' => 'Bớt xén nguyên vật liệu kho',
                'description' => 'Đã phát hiện nhân viên bòn rút 2kg thịt bò mang về nhà.',
                'is_anonymous' => true,
                'occurred_at' => today()->toDateString(),
            ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Kiểm tra database lưu đúng cờ ẩn danh
        $this->assertDatabaseHas('violation_reports', [
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $targetEmp->id,
            'reported_by' => $this->cashier->id,
            'is_anonymous' => true,
            'violation_type' => 'Bớt xén nguyên vật liệu kho',
            'status' => 'open',
        ]);
    }

    /**
     * Test phân quyền bảo mật trang quản lý tố cáo và xử lý kỷ luật.
     */
    public function test_violations_authorization(): void
    {
        // 1. Tạo một report mẫu
        $report = ViolationReport::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->cashierEmp->id,
            'reported_by' => $this->manager->id,
            'is_anonymous' => true,
            'violation_type' => 'Gian lận quỹ',
            'description' => 'Nghi ngờ thu ngân bỏ túi tiền thối thồi.',
            'occurred_at' => today(),
            'status' => 'open',
        ]);

        // 2. Manager chỉ được xem trang chứ không được resolve (Trả về 403)
        $this->actingAs($this->manager)
            ->post("/violations/{$report->id}/resolve", [
                'severity' => 'critical',
                'penalty_amount' => 500000,
                'status' => 'resolved',
                'resolution_notes' => 'Quyết định sa thải!',
            ])
            ->assertStatus(403);

        // 3. Owner truy cập và resolve thành công (Trả về redirect)
        $response = $this->actingAs($this->owner)
            ->post("/violations/{$report->id}/resolve", [
                'severity' => 'critical',
                'penalty_amount' => 500000,
                'status' => 'resolved',
                'resolution_notes' => 'Cảnh cáo và trừ lương 500k.',
            ]);

        $response->assertRedirect();
    }

    /**
     * Test tích hợp cấn trừ phạt trực tiếp thời gian thực vào bảng lương nhân sự vi phạm.
     */
    public function test_resolved_violation_automatically_deducts_salary(): void
    {
        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        // 1. Tạo vé tố cáo nhân sự thu ngân vi phạm
        $report = ViolationReport::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->cashierEmp->id,
            'reported_by' => $this->owner->id,
            'is_anonymous' => false,
            'violation_type' => 'Làm thất thoát tài sản',
            'description' => 'Làm vỡ tivi phòng VIP trị giá 2 triệu đồng.',
            'occurred_at' => today(),
            'status' => 'open',
        ]);

        // 2. Owner quyết định xử lý vi phạm resolved và phạt tiền 1,000,000 VND
        $response = $this->actingAs($this->owner)
            ->post("/violations/{$report->id}/resolve", [
                'severity' => 'high',
                'penalty_amount' => 1000000,
                'status' => 'resolved',
                'resolution_notes' => 'Khấu trừ 1 triệu vào lương tháng này để đền bù tivi vỡ.',
            ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // 3. Kiểm tra vé tố cáo đã resolved trong DB
        $report->refresh();
        $this->assertEquals('resolved', $report->status);
        $this->assertEquals(1000000.0, (float) $report->penalty_amount);

        // 4. Kiểm tra xem hệ thống đã tự động tạo bảng lương (Salary) cho Cashier trong tháng hiện tại
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $salary = Salary::where('employee_id', $this->cashierEmp->id)
            ->whereDate('pay_period_start', $startOfMonth)
            ->whereDate('pay_period_end', $endOfMonth)
            ->first();

        $this->assertNotNull($salary);
        // Lương cơ bản của cashierEmp được cấu hình là 5,000,000 VND
        $this->assertEquals(5000000.0, (float) $salary->base_salary);

        // 5. Kiểm tra tự động tạo SalaryAdjustment loại 'violation' với số tiền phạt 1,000,000 VND
        $adjustment = SalaryAdjustment::where('salary_id', $salary->id)
            ->where('type', 'violation')
            ->where('reference_id', $report->id)
            ->where('reference_type', ViolationReport::class)
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertEquals(1000000.0, (float) $adjustment->amount);
        $this->assertStringContainsString('Tố cáo ID #'.$report->id, $adjustment->reason);

        // 6. Kiểm tra lương thực nhận (net_salary) đã bị cấn trừ chính xác: 5,000,000 - 1,000,000 = 4,000,000 VND
        $salary->refresh();
        $this->assertEquals(1000000.0, (float) $salary->deduction_amount);
        $this->assertEquals(4000000.0, (float) $salary->net_salary);

        // 7. Xác minh hệ thống ghi Audit Log bảo mật
        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $this->restaurant->id,
            'action' => 'violation_resolved',
            'subject_type' => ViolationReport::class,
            'subject_id' => $report->id,
        ]);
    }
}
