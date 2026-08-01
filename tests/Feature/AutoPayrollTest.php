<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ScheduleAssignment;
use App\Models\ShiftClosing;
use App\Models\User;
use App\Models\ViolationReport;
use App\Models\WorkShift;
use App\Services\SalaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutoPayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private Role $ownerRole;

    private Role $cashierRole;

    private Role $kitchenRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['branch_id' => $this->branch->id]);

        $this->ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $this->kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);

        $this->owner->assignRole($this->ownerRole);
    }

    public function test_automated_payroll_calculates_salary_and_applies_progressive_deductions(): void
    {
        // 1. Setup Cashier and Chef
        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $cashierUser->assignRole($this->cashierRole);

        $cashier = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
            'base_salary' => 8000000,
        ]);

        $chefUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $chefUser->assignRole($this->kitchenRole);

        $chef = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $chefUser->id,
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'base_salary' => 12000000,
        ]);

        // 2. Mock Cash Register Shortage for Cashier (Negative cash difference of -150,000)
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Chieu',
            'code' => 'CA_CHIEU',
            'start_time' => '16:00:00',
            'end_time' => '23:00:00',
            'status' => 'active',
        ]);

        ShiftClosing::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $shift->id,
            'closing_date' => '2026-05-15',
            'cashier_user_id' => $cashierUser->id,
            'expected_cash' => 5000000,
            'actual_cash' => 4850000,
            'cash_difference' => -150000,
            'status' => 'confirmed',
        ]);

        // 3. Mock Disciplinary Violation for Chef (50,000 penalty)
        ViolationReport::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $chef->id,
            'reported_by' => $this->owner->id,
            'violation_type' => 'Di Tre',
            'severity' => 'low',
            'description' => 'Di tre 15 phut',
            'penalty_amount' => 50000,
            'occurred_at' => '2026-05-10 08:15:00',
            'status' => 'open',
        ]);

        // 4. Mock Inventory Waste for Chef resolved temporally via Scheduled Shift
        // Chef shift: 2026-05-20 08:00:00 - 16:00:00
        $morningShift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sang',
            'code' => 'CA_SANG',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $chef->id,
            'shift_id' => $morningShift->id,
            'scheduled_date' => '2026-05-20',
            'status' => 'scheduled',
        ]);

        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Thit Bo',
            'sku' => 'BEEF',
            'average_cost' => 200000,
        ]);

        // Inventory waste happens at 2026-05-20 10:30:00 (during Chef shift)
        InventoryTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'waste',
            'direction' => 'out',
            'quantity' => 1.5,
            'unit_cost' => 200000,
            'total_cost' => 300000, // Loss is 300,000
            'occurred_at' => '2026-05-20 10:30:00',
            'notes' => 'Lam hong thit bo',
        ]);

        // 5. Generate monthly payroll drafts for 2026-05
        $service = app(SalaryService::class);
        $result = $service->generateMonthlyDrafts($this->restaurant->id, '2026-05');

        $this->assertEquals(2, $result['created'] + $result['skipped']);

        // 6. Verify Cashier salary details
        $cashierSalary = Salary::where('employee_id', $cashier->id)->first();

        $this->assertNotNull($cashierSalary);
        $this->assertEquals('2026-05-01', $cashierSalary->pay_period_start->toDateString());
        $this->assertEquals(8000000, (float) $cashierSalary->base_salary);
        $this->assertEquals(150000, (float) $cashierSalary->deduction_amount);
        $this->assertEquals(7850000, (float) $cashierSalary->net_salary);

        $shortageAdj = SalaryAdjustment::where('salary_id', $cashierSalary->id)
            ->where('type', 'cash_shortage')
            ->first();
        $this->assertNotNull($shortageAdj);
        $this->assertEquals(150000, (float) $shortageAdj->amount);

        // 7. Verify Chef salary details
        $chefSalary = Salary::where('employee_id', $chef->id)->first();

        $this->assertNotNull($chefSalary);
        $this->assertEquals('2026-05-01', $chefSalary->pay_period_start->toDateString());
        $this->assertEquals(12000000, (float) $chefSalary->base_salary);
        // Total deductions: 50,000 (violation) + 300,000 (inventory loss) = 350,000
        $this->assertEquals(350000, (float) $chefSalary->deduction_amount);
        $this->assertEquals(11650000, (float) $chefSalary->net_salary);

        $violationAdj = SalaryAdjustment::where('salary_id', $chefSalary->id)
            ->where('type', 'violation')
            ->first();
        $this->assertNotNull($violationAdj);
        $this->assertEquals(50000, (float) $violationAdj->amount);

        $lossAdj = SalaryAdjustment::where('salary_id', $chefSalary->id)
            ->where('type', 'inventory_loss')
            ->first();
        $this->assertNotNull($lossAdj);
        $this->assertEquals(300000, (float) $lossAdj->amount);
    }

    public function test_automated_payroll_calculates_hourly_wage_based_on_shift_closings(): void
    {
        $partTimeUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $partTimeUser->assignRole($this->cashierRole);

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $partTimeUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
            'compensation_type' => 'hourly',
            'pay_rate' => 50000, // 50,000 VND / hour
        ]);

        $morningShift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sang',
            'code' => 'CA_SANG_TEST',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        // Giả lập làm việc 8 tiếng trong ngày 2026-05-10
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $morningShift->id,
            'scheduled_date' => '2026-05-10',
            'check_in_at' => '2026-05-10 08:00:00',
            'check_out_at' => '2026-05-10 16:00:00',
            'status' => 'completed',
        ]);

        // Giả lập làm việc 4 tiếng trong ngày 2026-05-11 (làm nửa ca hoặc về sớm)
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $morningShift->id,
            'scheduled_date' => '2026-05-11',
            'check_in_at' => '2026-05-11 08:00:00',
            'check_out_at' => '2026-05-11 12:00:00',
            'status' => 'completed',
        ]);

        $service = app(SalaryService::class);
        $result = $service->generateMonthlyDrafts($this->restaurant->id, '2026-05');

        $salary = Salary::where('employee_id', $employee->id)->first();

        $this->assertNotNull($salary);
        // 8h + 4h = 12h * 50,000 = 600,000 VND
        $this->assertEquals(600000, (float) $salary->base_salary);
        $this->assertEquals(600000, (float) $salary->net_salary);
    }

    public function test_inventory_loss_deduction_respects_allowed_waste_threshold(): void
    {
        $chefUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $chefUser->assignRole($this->kitchenRole);

        $chef = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $chefUser->id,
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'base_salary' => 10000000,
        ]);

        $morningShift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sang',
            'code' => 'CA_SANG_TEST_INVENTORY',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $chef->id,
            'shift_id' => $morningShift->id,
            'scheduled_date' => '2026-05-20',
            'status' => 'scheduled',
        ]);

        // Cấu hình allowed_waste_ratio là 10.00% cho nguyên liệu thịt bò
        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Thit Bo Co Dinh Luong',
            'sku' => 'BEEF_COMP',
            'average_cost' => 200000,
            'allowed_waste_ratio' => 10.00,
        ]);

        // Thất thoát 300,000 VND thịt bò
        InventoryTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'waste',
            'direction' => 'out',
            'quantity' => 1.5,
            'unit_cost' => 200000,
            'total_cost' => 300000,
            'occurred_at' => '2026-05-20 10:30:00',
            'notes' => 'Lam hong thit bo co dinh luong',
        ]);

        $service = app(SalaryService::class);
        $service->generateMonthlyDrafts($this->restaurant->id, '2026-05');

        $salary = Salary::where('employee_id', $chef->id)->first();

        $this->assertNotNull($salary);
        // Lượng phạt thực tế sau khi giảm trừ 10%: 300,000 * (1 - 0.10) = 270,000 VND
        $this->assertEquals(270000, (float) $salary->deduction_amount);
        $this->assertEquals(9730000, (float) $salary->net_salary);
    }

    public function test_disputed_deduction_is_frozen_and_not_deducted_from_net_salary(): void
    {
        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $cashierUser->assignRole($this->cashierRole);

        $cashier = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
            'base_salary' => 8000000,
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Chieu',
            'code' => 'CA_CHIEU_DISPUTE',
            'start_time' => '16:00:00',
            'end_time' => '23:00:00',
            'status' => 'active',
        ]);

        // Hụt két 150,000 VND
        $closing = ShiftClosing::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $shift->id,
            'closing_date' => '2026-05-15',
            'cashier_user_id' => $cashierUser->id,
            'expected_cash' => 5000000,
            'actual_cash' => 4850000,
            'cash_difference' => -150000,
            'status' => 'confirmed',
        ]);

        $service = app(SalaryService::class);
        $service->generateMonthlyDrafts($this->restaurant->id, '2026-05');

        $salary = Salary::where('employee_id', $cashier->id)->first();
        $this->assertNotNull($salary);
        $this->assertEquals(150000, (float) $salary->deduction_amount);
        $this->assertEquals(7850000, (float) $salary->net_salary);

        // Giả lập nhân viên gửi khiếu nại cấn trừ lương
        $shortageAdj = SalaryAdjustment::where('salary_id', $salary->id)
            ->where('type', 'cash_shortage')
            ->first();
        $this->assertNotNull($shortageAdj);

        $shortageAdj->update([
            'status' => 'disputed',
            'dispute_reason' => 'Do loi cong thanh toan the tin dung',
        ]);

        // Tính toán lại
        $service->recalculate($salary);

        // Khoản phạt hụt két bị 'disputed' phải bị đóng băng và KHÔNG được trừ vào net_salary
        $this->assertEquals(0, (float) $salary->deduction_amount);
        $this->assertEquals(8000000, (float) $salary->net_salary);
    }

    public function test_prevent_modifying_past_shift_closings_after_payroll_approved(): void
    {
        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $cashierUser->assignRole($this->cashierRole);

        $cashier = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
            'base_salary' => 8000000,
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Chieu',
            'code' => 'CA_CHIEU_LOCK',
            'start_time' => '16:00:00',
            'end_time' => '23:00:00',
            'status' => 'active',
        ]);

        $closing = ShiftClosing::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $shift->id,
            'closing_date' => '2026-05-15',
            'cashier_user_id' => $cashierUser->id,
            'expected_cash' => 5000000,
            'actual_cash' => 4850000,
            'cash_difference' => -150000,
            'status' => 'confirmed',
        ]);

        $service = app(SalaryService::class);
        $service->generateMonthlyDrafts($this->restaurant->id, '2026-05');

        $salary = Salary::where('employee_id', $cashier->id)->first();
        $this->assertNotNull($salary);

        // Phê duyệt bảng lương (approved)
        $salary->update(['status' => 'approved']);

        // Kỳ lương 2026-05 hiện tại đã bị khóa, thử cập nhật chốt ca sẽ ném ngoại lệ
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dữ liệu chấm công chốt ca đã bị khóa do bảng lương của kỳ này đã được phê duyệt.');

        $closing->update(['notes' => 'Co gang sua chua ghi chu sau khi luong da duyet']);
    }

    public function test_bulk_salary_adjustments_applied_successfully_by_owner(): void
    {
        $user1 = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $employee1 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $user1->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
            'base_salary' => 5000000,
        ]);

        $user2 = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $employee2 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $user2->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
            'base_salary' => 6000000,
        ]);

        $service = app(SalaryService::class);
        $service->generateMonthlyDrafts($this->restaurant->id, '2026-05');

        $salary1 = Salary::where('employee_id', $employee1->id)->first();
        $salary2 = Salary::where('employee_id', $employee2->id)->first();

        // Đăng nhập Owner
        $this->actingAs($this->owner);

        // Áp dụng thưởng hàng loạt 100,000đ cho cả hai nhân viên
        $response = $this->post(route('salaries.adjustments.bulk'), [
            'salary_ids' => [$salary1->id, $salary2->id],
            'type' => 'bonus',
            'amount' => 100000,
            'reason' => 'Thuong nong du an tap the',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Kiểm tra xem lương net của cả 2 đã được cộng 100,000đ thành công
        $salary1->refresh();
        $salary2->refresh();

        $this->assertEquals(100000, (float) $salary1->bonus_amount);
        $this->assertEquals(5100000, (float) $salary1->net_salary);

        $this->assertEquals(100000, (float) $salary2->bonus_amount);
        $this->assertEquals(6100000, (float) $salary2->net_salary);
    }

    public function test_employee_can_dispute_adjustment_and_notifies_owner(): void
    {
        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $cashierUser->assignRole($this->cashierRole);

        $cashier = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
            'base_salary' => 8000000,
        ]);

        // Tạo lịch trực bao trùm thời gian hiện tại để vượt qua Middleware "Khóa tài khoản ngoài ca trực"
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Test Dispute',
            'code' => 'CA_DISPUTE_TEST',
            'start_time' => now()->subHours(1)->format('H:i:s'),
            'end_time' => now()->addHours(2)->format('H:i:s'),
            'status' => 'active',
        ]);

        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $cashier->id,
            'shift_id' => $shift->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        $service = app(SalaryService::class);
        $service->generateMonthlyDrafts($this->restaurant->id, '2026-05');
        $salary = Salary::where('employee_id', $cashier->id)->first();

        // Tạo một khoản phạt kỷ luật 200,000đ
        $adjustment = SalaryAdjustment::create([
            'salary_id' => $salary->id,
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $cashier->id,
            'type' => 'violation',
            'amount' => 200000,
            'reason' => 'Vi pham noi quy',
            'status' => 'applied',
        ]);

        $service->recalculate($salary);
        $this->assertEquals(7800000, (float) $salary->net_salary);

        // Nhân viên đăng nhập để khiếu nại cấn trừ lương
        $this->actingAs($cashierUser);

        $response = $this->patch(route('salaries.adjustments.dispute', $adjustment->id), [
            'dispute_reason' => 'Toi khong di tre vao ngay hom do, co camera lam chung',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Kiểm tra xem trạng thái cấn trừ đã đổi thành disputed và lương net đã được đóng băng (loại trừ cấn trừ)
        $adjustment->refresh();
        $salary->refresh();

        $this->assertEquals('disputed', $adjustment->status);
        $this->assertEquals('Toi khong di tre vao ngay hom do, co camera lam chung', $adjustment->dispute_reason);
        $this->assertEquals(0, (float) $salary->deduction_amount);
        $this->assertEquals(8000000, (float) $salary->net_salary); // Trở lại lương gốc

        // Kiểm tra Owner đã nhận được notification trong database thành công
        $ownerNotificationExists = DB::table('notifications')
            ->where('notifiable_id', $this->owner->id)
            ->exists();

        $this->assertTrue($ownerNotificationExists);
    }
}
