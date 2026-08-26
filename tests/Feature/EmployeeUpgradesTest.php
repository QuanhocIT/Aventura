<?php

namespace Tests\Feature;

use App\Http\Middleware\SetTenantContext;
use App\Jobs\ProcessPostPaymentActions;
use App\Models\CashRegister;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OvertimeRequest;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use App\Models\ScheduleAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\SalaryService;
use App\Support\Tenant\TenantContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeUpgradesTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Roles
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create(['name' => 'Aventura Bistro Test']);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $this->owner->assignRole('owner');
    }

    /**
     * Test 1A: isWithinScheduledShift date range filtering.
     */
    public function test_is_within_scheduled_shift_optimised()
    {
        $waiterUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $waiterUser->assignRole('waiter');

        $employee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $waiterUser->id,
            'role_id' => Role::where('name', 'waiter')->first()->id,
            'employee_code' => 'EMP201',
            'full_name' => 'Test Waiter',
            'job_title' => 'Waiter',
            'status' => 'active',
            'base_salary' => 5000000,
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Test',
            'code' => 'CA-TEST',
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
            'status' => 'active',
        ]);

        // Scheduled assignment for today
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->assertTrue($employee->isWithinScheduledShift());
    }

    /**
     * Test 2A: Overtime calculation with approved/unapproved requests.
     */
    public function test_overtime_calculation_with_approvals()
    {
        $hourlyUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $hourlyUser->id,
            'employee_code' => 'EMP202',
            'full_name' => 'Hourly Worker',
            'job_title' => 'Bartender',
            'status' => 'active',
            'compensation_type' => 'hourly',
            'pay_rate' => 10000, // 10,000 VND / hour
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Chieu',
            'code' => 'CA-CHIEU',
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
            'status' => 'active',
        ]);

        $dateStr = '2026-06-30';

        // 8 hours total: check-in 2 hours early, check-out 2 hours late. (4 hours scheduled, 4 hours OT)
        $assignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $dateStr,
            'check_in_at' => $dateStr.' 11:00:00',
            'check_out_at' => $dateStr.' 19:00:00',
            'status' => 'completed',
        ]);

        $service = new SalaryService;

        // Chính sách: giờ làm ngoài ca mà KHÔNG có đơn OT được duyệt thì không
        // được tính lương — phiếu lương in rõ "chưa được duyệt OT (không tính
        // lương)" và số giờ đó vẫn hiện ở cột riêng để quản lý duyệt bù sau.
        // Xem SalaryService::hourlyCalculation và buildBreakdown.

        // SCENARIO 1: Chưa có đơn OT nào được duyệt.
        // Trong ca: 4h. Ngoài ca: 4h nhưng chưa duyệt → không tính lương.
        // Tổng: 4 * 10.000 = 40.000
        $wages1 = $service->calculateDynamicBaseSalary($employee, '2026-06-01', '2026-07-01');
        $this->assertEquals(40000.0, $wages1);

        // SCENARIO 2: Đơn OT được duyệt 2 giờ.
        // Trong ca: 4h. OT được duyệt: 2h × 1.5. Còn 2h ngoài ca vẫn chưa duyệt.
        // Tổng: (4 * 10.000) + (2 * 10.000 * 1.5) = 70.000
        OvertimeRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee->id,
            'scheduled_date' => $dateStr,
            'hours_requested' => 4.0,
            'hours_approved' => 2.0,
            'status' => 'approved',
            'approved_by' => $this->owner->id,
        ]);

        $wages2 = $service->calculateDynamicBaseSalary($employee, '2026-06-01', '2026-07-01');
        $this->assertEquals(70000.0, $wages2);
    }

    public function test_approved_off_day_overtime_uses_actual_hours_and_snapshotted_rate(): void
    {
        $employee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-OT-OFFDAY',
            'full_name' => 'Off Day Worker',
            'status' => 'active',
            'compensation_type' => 'hourly',
            'pay_rate' => 10000,
        ]);

        OvertimeRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'scheduled_date' => '2026-06-30',
            'hours_requested' => 3,
            'hours_approved' => 3,
            'status' => 'approved',
            'overtime_type' => 'rest_day',
            'hourly_rate' => 10000,
            'overtime_multiplier' => 2,
            'check_in_at' => '2026-06-30 18:00:00',
            'check_out_at' => '2026-06-30 20:00:00',
            'worked_hours' => 2,
            'actual_amount' => 40000,
            'payroll_status' => 'ready',
        ]);

        $wages = app(SalaryService::class)->calculateDynamicBaseSalary($employee, '2026-06-01', '2026-06-30');

        $this->assertEquals(40000.0, $wages);
    }

    public function test_employee_can_register_overtime_and_system_snapshots_pay_quote(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $user->assignRole('waiter');

        $employee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $user->id,
            'employee_code' => 'EMP-OT-REGISTER',
            'full_name' => 'Register OT Worker',
            'status' => 'active',
            'compensation_type' => 'fixed',
            'base_salary' => 6240000,
        ]);
        $date = now()->addDay()->toDateString();

        $response = $this->actingAs($user)->from('/employee-portal')->post('/overtime-requests', [
            'scheduled_date' => $date,
            'hours_requested' => 2,
            'start_time' => '18:00',
            'end_time' => '20:00',
            'overtime_type' => 'normal',
            'reason' => 'Đơn hàng tăng đột biến',
        ]);

        $response->assertRedirect('/employee-portal');
        $this->assertDatabaseHas('overtime_requests', [
            'employee_id' => $employee->id,
            'status' => 'pending',
            'overtime_type' => 'normal',
            'overtime_multiplier' => 1.5,
            'estimated_amount' => 90000,
        ]);
    }

    /**
     * Test 2B: Department-specific inventory loss allocation.
     */
    public function test_department_specific_inventory_loss()
    {
        $chefUser = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $chefUser->assignRole('kitchen');
        $chef = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $chefUser->id,
            'role_id' => Role::where('name', 'kitchen')->first()->id,
            'employee_code' => 'EMP_CHEF',
            'full_name' => 'Chef Master',
            'job_title' => 'Chef',
            'status' => 'active',
            'base_salary' => 8000000,
        ]);

        $waiterUser = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $waiterUser->assignRole('waiter');
        $waiter = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $waiterUser->id,
            'role_id' => Role::where('name', 'waiter')->first()->id,
            'employee_code' => 'EMP_WAITER',
            'full_name' => 'Waiter Runner',
            'job_title' => 'Server',
            'status' => 'active',
            'base_salary' => 5000000,
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Toan',
            'code' => 'CA-TOAN',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        $dateStr = '2026-06-30';

        // Schedule both on the same shift
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $chef->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $dateStr,
            'check_in_at' => $dateStr.' 08:00:00',
            'check_out_at' => $dateStr.' 16:00:00',
            'status' => 'completed',
        ]);

        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $waiter->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $dateStr,
            'check_in_at' => $dateStr.' 08:00:00',
            'check_out_at' => $dateStr.' 16:00:00',
            'status' => 'completed',
        ]);

        $unit = Unit::create(['restaurant_id' => $this->restaurant->id, 'name' => 'kg', 'symbol' => 'kg']);

        // Food ingredient
        $meat = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $unit->id,
            'name' => 'Thit bo',
            'category_name' => 'Thịt',
            'allowed_waste_ratio' => 5.0,
            'average_cost' => 100000,
            'status' => 'active',
        ]);

        $invMeat = Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $meat->id,
            'quantity_on_hand' => 10,
        ]);

        // Create waste transaction during the shift.
        // This will automatically fire the SalaryRecalculationObserver, which creates draft salaries and sweeps adjustments.
        $wasteTx = InventoryTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $meat->id,
            'inventory_id' => $invMeat->id,
            'type' => 'waste',
            'direction' => 'out',
            'quantity' => 1.0,
            'unit_cost' => 100000,
            'total_cost' => 100000,
            'occurred_at' => $dateStr.' 12:00:00',
        ]);

        // Retrieve the auto-created draft salaries
        $chefSalary = Salary::where('employee_id', $chef->id)->first();
        $this->assertNotNull($chefSalary);
        // Chef should be penalized
        $this->assertTrue($chefSalary->adjustments()->where('type', 'inventory_loss')->exists());

        $waiterSalary = Salary::where('employee_id', $waiter->id)->first();
        $this->assertNotNull($waiterSalary);
        // Waiter should NOT be penalized for kitchen waste
        $this->assertFalse($waiterSalary->adjustments()->where('type', 'inventory_loss')->exists());
    }

    /**
     * Test 2C: Single Cash Register per Cashier limit.
     */
    public function test_single_open_cash_register_limit()
    {
        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $cashierUser->assignRole('cashier');

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Thu Ngan',
            'code' => 'CA-TN',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        // Attempt 1: Open register
        $response1 = $this->actingAs($cashierUser)->post(route('cash-flow.registers.open'), [
            'shift_id' => $shift->id,
            'opening_balance' => 500000,
            'expense_budget' => 100000,
            'notes' => 'Open test register',
        ]);

        $response1->assertRedirect(route('cash-flow.index'));
        $this->assertDatabaseHas('cash_registers', [
            'restaurant_id' => $this->restaurant->id,
            'cashier_user_id' => $cashierUser->id,
            'status' => 'open',
        ]);

        // Attempt 2: Try to open another register without closing the first one
        $response2 = $this->actingAs($cashierUser)->post(route('cash-flow.registers.open'), [
            'shift_id' => $shift->id,
            'opening_balance' => 300000,
            'notes' => 'Attempt duplicate register',
        ]);

        $response2->assertSessionHasErrors(['shift_id']);
        $this->assertEquals(1, CashRegister::where('cashier_user_id', $cashierUser->id)->where('status', 'open')->count());
    }

    /**
     * Test 1B/1C: Session-based shift checks, warning times, and JSON 403 on expiry.
     */
    public function test_employee_shift_allowed_until_session_flow()
    {
        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $cashierUser->assignRole('cashier');

        $employee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $cashierUser->id,
            'role_id' => Role::where('name', 'cashier')->first()->id,
            'employee_code' => 'EMP203',
            'full_name' => 'Session Cashier',
            'job_title' => 'Cashier',
            'status' => 'active',
            'base_salary' => 5000000,
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Test 2',
            'code' => 'CA-TEST2',
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
            'status' => 'active',
        ]);

        // Scheduled assignment for today
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        // Simulate login
        event(new Login('web', $cashierUser, false));

        // Check session has employee_id and shift_allowed_until
        $this->assertEquals($employee->id, session('employee_id'));
        $this->assertNotNull(session('shift_allowed_until'));

        // Middleware bypasses when shift is active
        SetTenantContext::$enforceShiftLockInTests = true;
        $response = $this->actingAs($cashierUser)->get('/dashboard');
        $response->assertStatus(200);

        // Simulate shift expiration by setting allowed until in the past
        session(['shift_allowed_until' => now()->subMinutes(1)->timestamp]);

        // Expecting a 403 Forbidden with SHIFT_EXPIRED for JSON requests
        $responseJson = $this->actingAs($cashierUser)
            ->getJson('/dashboard');
        $responseJson->assertStatus(403);
        $responseJson->assertJsonFragment(['error' => 'SHIFT_EXPIRED']);

        // Khi phát hiện hết ca, middleware đăng xuất và huỷ luôn session, nên
        // dấu hiệu "hết ca" không còn sau request trên. Phải dựng lại trạng thái
        // đó — kèm employee_id, nếu không middleware sẽ tự nạp lại cả hai khoá
        // từ lịch làm việc và coi như ca vẫn hợp lệ.
        session([
            'employee_id' => $employee->id,
            'shift_allowed_until' => now()->subMinutes(1)->timestamp,
        ]);

        // Expecting a redirect to login for page requests
        $responsePage = $this->actingAs($cashierUser)->get('/dashboard');
        $responsePage->assertRedirect('/login');

        SetTenantContext::$enforceShiftLockInTests = false;
    }

    /**
     * Test 2B: Branch context switching via session and user accessor.
     */
    public function test_branch_context_switching_session_and_accessor()
    {
        $branch2 = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Branch 2',
        ]);

        $this->actingAs($this->owner);

        // Verify fallback to db branch_id initially
        $this->assertEquals($this->owner->branch_id, $this->owner->getBranchIdAttribute($this->owner->getRawOriginal('branch_id')));

        // Call switch branch route
        $response = $this->post(route('branch.switch'), [
            'branch_id' => $branch2->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals($branch2->id, session('active_branch_id'));

        // TenantContext and the dynamic accessor are populated on the next request.
        $this->get('/dashboard');
        $this->assertEquals($branch2->id, app(TenantContext::class)->activeBranchId());
        $this->assertEquals($branch2->id, $this->owner->branch_id);
    }

    /**
     * Test 3A: Webhook processing queues post-payment actions job.
     */
    public function test_vietqr_webhook_queues_post_payment_processing()
    {
        Queue::fake();

        $waiterUser = User::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'TESTORDER123',
            'total_amount' => 150000,
            'payment_status' => 'unpaid',
            'created_by' => $waiterUser->id,
        ]);

        config()->set('billing.webhook_secret', 'test-webhook-secret');
        // Webhook thật luôn kèm số tiền thực nhận; controller từ chối callback
        // không có amount để không đánh dấu đã thanh toán khi chưa biết nhận
        // được bao nhiêu tiền.
        $webhookPayload = [
            'description' => "AVTORD{$order->id} chuyen khoan",
            'transferAmount' => 150000,
        ];
        $signature = hash_hmac('sha256', json_encode($webhookPayload), 'test-webhook-secret');

        $response = $this->postJson(route('api.webhooks.payments.vietqr'), $webhookPayload, [
            'X-SePay-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);

        // Assert job was pushed
        Queue::assertPushed(ProcessPostPaymentActions::class, function ($job) use ($order) {
            return $job->orderId === $order->id;
        });

        // Assert order is paid
        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals('completed', $order->fresh()->status);
    }
}
