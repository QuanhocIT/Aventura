<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use App\Models\ShiftClosing;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\MaterializedViewRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cashier;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected WorkShift $shift;

    protected Role $ownerRole;

    protected Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup roles
        $this->ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create([
            'status' => 'active',
        ]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->owner->assignRole($this->ownerRole);

        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->cashier->assignRole($this->cashierRole);

        $this->shift = WorkShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
    }

    public function test_can_open_cash_register(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->post(route('cash-flow.registers.open'), [
            'shift_id' => $this->shift->id,
            'opening_balance' => 500000,
            'expense_budget' => 200000,
            'notes' => 'Mở két ca sáng',
        ]);

        $response->assertRedirect(route('cash-flow.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('cash_registers', [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'opening_balance' => 500000,
            'expense_budget' => 200000,
            'status' => 'open',
            'opened_by' => $this->cashier->id,
        ]);
    }

    public function test_cash_payment_automatically_creates_cash_transaction(): void
    {
        $this->actingAs($this->cashier);

        // 1. Open the cash register
        $register = CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'opened_by' => $this->cashier->id,
            'opening_balance' => 500000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        // 2. Create an order
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->cashier->id,
            'total_amount' => 150000,
            'payment_status' => 'unpaid',
        ]);

        // 3. Create a cash payment (should trigger observer)
        $payment = Payment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'processed_by' => $this->cashier->id,
            'payment_method' => 'cash',
            'status' => 'paid',
            'amount' => 150000,
            'cash_received' => 150000,
            'change_amount' => 0,
            'paid_at' => now(),
        ]);

        // 4. Check if transaction was logged under active register
        $this->assertDatabaseHas('cash_transactions', [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'cash_register_id' => $register->id,
            'type' => 'in',
            'amount' => 150000,
            'source' => 'order',
            'reference_id' => $order->id,
            'reference_type' => Order::class,
        ]);
    }

    public function test_can_record_manual_cash_expense(): void
    {
        $this->actingAs($this->cashier);

        // Open register
        $register = CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'opened_by' => $this->cashier->id,
            'opening_balance' => 500000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        // Create transaction: Outflow (expense)
        $response = $this->post(route('cash-flow.transactions.store'), [
            'type' => 'out',
            'amount' => 75000,
            'voucher_code' => 'HD-TEST-001',
            'notes' => 'Chi tiền mua xà phòng',
            'source' => 'expense',
        ]);

        $response->assertRedirect(route('cash-flow.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('cash_transactions', [
            'cash_register_id' => $register->id,
            'type' => 'out',
            'amount' => 75000,
            'source' => 'expense',
            'notes' => 'Chi tiền mua xà phòng',
        ]);
    }

    public function test_closing_shift_automatically_closes_linked_register(): void
    {
        $this->actingAs($this->cashier);

        // 1. Open Register
        $register = CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'opened_by' => $this->cashier->id,
            'opening_balance' => 500000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        // 2. Add an expense manual transaction
        CashTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'cash_register_id' => $register->id,
            'type' => 'out',
            'amount' => 50000,
            'source' => 'expense',
            'notes' => 'Đi chợ mua gia vị',
            'created_by' => $this->cashier->id,
            'occurred_at' => now(),
        ]);

        // Expected cash = 500k - 50k = 450k

        // 3. Đếm tiền mù trước — chế độ này bật mặc định.
        $count = $this->postJson(route('shift-closings.count'), [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'denominations' => ['100000' => 4, '20000' => 2], // = 440.000
        ])->json();

        // 4. Submit Shift Closing
        $response = $this->post(route('shift-closings.store'), [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'actual_cash' => 440000, // actual cash counted
            'cash_count_id' => $count['cash_count_id'],
            'variance_explanation' => 'Hụt két 10k do trả nhầm tiền thừa.',
            'other_expense_amount' => 50000,
            'notes' => 'Hụt két 10k',
            'submit' => 1,
        ]);

        $response->assertRedirect(route('shift-closings.index'));
        $response->assertSessionHas('success');

        // Verify register status is updated to 'closed'
        $register->refresh();
        $this->assertEquals('closed', $register->status);
        $this->assertEquals(440000, (float) $register->closing_balance);
        $this->assertEquals(450000, (float) $register->expected_closing_balance);
        $this->assertEquals(-10000, (float) $register->difference);

        // Verify ShiftClosing is linked
        $closing = ShiftClosing::where('shift_id', $this->shift->id)
            ->whereDate('closing_date', today())
            ->first();
        $this->assertNotNull($closing);
        $this->assertEquals($register->id, $closing->cash_register_id);
    }

    public function test_shift_close_creates_one_aggregate_slip_with_actual_transfer_and_signed_responsibility(): void
    {
        $this->actingAs($this->cashier);

        $count = $this->postJson(route('shift-closings.count'), [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'denominations' => ['500000' => 1],
        ])->json();

        $response = $this->post(route('shift-closings.store'), [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'actual_cash' => 500000,
            'cash_count_id' => $count['cash_count_id'],
            'variance_explanation' => 'Thiếu tiền mặt khi kiểm đếm cuối ca.',
            'actual_transfer_amount' => 120000,
            'responsibility_amount' => -10000,
            'responsibility_note' => 'Thiếu tiền mặt khi kiểm đếm cuối ca',
            'submit' => 0,
        ]);

        $response->assertRedirect(route('shift-closings.index'));

        $closings = ShiftClosing::where('restaurant_id', $this->restaurant->id)
            ->where('branch_id', $this->branch->id)
            ->where('shift_id', $this->shift->id)
            ->whereDate('closing_date', today())
            ->get();

        $this->assertCount(1, $closings);
        $closing = $closings->firstOrFail();
        $this->assertSame('Toàn bộ khu vực', $closing->area_name);
        $this->assertNotNull($closing->period_start_at);
        $this->assertNotNull($closing->closed_at);
        $this->assertEquals(120000, (float) $closing->actual_transfer_amount);
        $this->assertEquals(0, (float) $closing->responsibility_amount);
        $this->assertSame('Thiếu tiền mặt khi kiểm đếm cuối ca', $closing->responsibility_note);
    }

    public function test_positive_responsibility_is_added_to_monthly_salary_after_confirmation(): void
    {
        $this->actingAs($this->cashier);

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'base_salary' => 8000000,
            'status' => 'active',
        ]);

        $closing = ShiftClosing::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'cashier_user_id' => $this->cashier->id,
            'expected_cash' => 500000,
            'actual_cash' => 500000,
            'cash_difference' => 0,
            'transfer_amount' => 100000,
            'actual_transfer_amount' => 100000,
            'transfer_difference' => 0,
            'responsibility_amount' => 250000,
            'status' => 'submitted',
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->patch(
            route('shift-closings.confirm', $closing),
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('salary_adjustments', [
            'reference_id' => $closing->id,
            'reference_type' => ShiftClosing::class,
            'employee_id' => $employee->id,
            'type' => 'bonus',
            'amount' => 250000,
        ]);

        $salary = Salary::where('employee_id', $employee->id)->firstOrFail();
        $this->assertEquals(250000, (float) $salary->bonus_amount);
    }

    public function test_dashboard_alerts_appear_on_variance_and_budget_overrun(): void
    {
        $this->restaurant->plan->update([
            'features' => array_merge($this->restaurant->plan->features ?? [], [
                'inventory_basic' => true,
                'advanced_analytics' => true,
            ]),
        ]);

        $this->actingAs($this->owner);

        // 1. Create a closed register with discrepancy > 2% of expected closing balance
        // Difference = -50,000, Expected = 1,000,000 -> Variance = 5% > 2%
        CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'opened_by' => $this->cashier->id,
            'closed_by' => $this->cashier->id,
            'opening_balance' => 1000000,
            'closing_balance' => 950000,
            'expected_closing_balance' => 1000000,
            'difference' => -50000,
            'status' => 'closed',
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
        ]);

        // 2. Create an open register with budget overrun
        // Budget = 100,000. Expenses = 120,000
        $registerOpen = CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'opened_by' => $this->cashier->id,
            'opening_balance' => 200000,
            'expense_budget' => 100000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        CashTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'cash_register_id' => $registerOpen->id,
            'type' => 'out',
            'amount' => 120000,
            'source' => 'expense',
            'notes' => 'Sửa tủ lạnh',
            'created_by' => $this->cashier->id,
            'occurred_at' => now(),
        ]);

        // Visit Dashboard
        $response = $this->get(route('dashboard'));
        $response->assertOk();

        // Retrieve alerts passed to view
        $alerts = $response->original->getData()['page']['props']['alerts'];

        $this->assertNotEmpty($alerts);

        // Check discrepancy alert
        $discrepancyAlert = collect($alerts)->first(fn ($a) => str_contains($a['message'], 'chênh lệch vượt quá 2%'));
        $this->assertNotNull($discrepancyAlert);
        $this->assertEquals('danger', $discrepancyAlert['type']);

        // Check budget overrun alert
        $overrunAlert = collect($alerts)->first(fn ($a) => str_contains($a['message'], 'vượt quá ngân sách'));
        $this->assertNotNull($overrunAlert);
        $this->assertEquals('warning', $overrunAlert['type']);
    }

    public function test_all_branch_cash_flow_aggregates_and_identifies_branch(): void
    {
        $secondBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $secondShift = WorkShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $secondBranch->id,
            'status' => 'active',
        ]);

        $activeRegister = CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'opened_by' => $this->cashier->id,
            'cashier_user_id' => $this->cashier->id,
            'opening_balance' => 100000,
            'expense_budget' => 50000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        CashTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'cash_register_id' => $activeRegister->id,
            'type' => 'in',
            'amount' => 50000,
            'source' => 'other',
            'notes' => 'Thu tiền chi nhánh A',
            'created_by' => $this->cashier->id,
            'occurred_at' => now(),
        ]);

        CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $secondBranch->id,
            'shift_id' => $secondShift->id,
            'closing_date' => today()->subDay(),
            'opened_by' => $this->cashier->id,
            'closed_by' => $this->cashier->id,
            'opening_balance' => 200000,
            'closing_balance' => 280000,
            'expected_closing_balance' => 300000,
            'difference' => -20000,
            'status' => 'closed',
            'opened_at' => now()->subDay()->subHours(8),
            'closed_at' => now()->subDay(),
        ]);

        $this->actingAs($this->owner);
        $response = $this->get(route('cash-flow.index'));
        $response->assertOk();

        $props = $response->original->getData()['page']['props'];

        $this->assertTrue($props['isAllBranches']);
        $this->assertSame('Toàn chuỗi', $props['activeRegister']['branch_name']);
        $this->assertEquals(150000, $props['activeRegister']['expected_cash']);
        $this->assertEquals(430000, $props['forecast']['current_cash']);
        $this->assertSame($this->branch->name, $props['activeTransactions'][0]['branch_name']);
        $this->assertSame(
            $secondBranch->name,
            collect($props['registers'])->firstWhere('branch_id', $secondBranch->id)['branch_name'],
        );
    }

    /**
     * Kiểm tra materialized view 'cash_flow_30d' (CashFlowChartBuilder) — trang
     * Cash Flow trước đây KHÔNG cache gì cả, giờ đọc qua MaterializedViewReader.
     */
    public function test_cash_flow_chart_reflects_materialized_view(): void
    {
        $register = CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'opened_by' => $this->cashier->id,
            'opening_balance' => 0,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        CashTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'cash_register_id' => $register->id,
            'type' => 'in',
            'amount' => 500000,
            'source' => 'other',
            'notes' => 'Test income',
            'created_by' => $this->cashier->id,
            'occurred_at' => now(),
        ]);

        app(MaterializedViewRefresher::class)->refresh('cash_flow_30d', $this->restaurant->id);

        $this->actingAs($this->owner);
        $response = $this->get(route('cash-flow.index'));
        $response->assertOk();

        $chartData = $response->original->getData()['page']['props']['chartData'];
        $this->assertCount(30, $chartData);

        $todayEntry = collect($chartData)->last();
        $this->assertEquals(500000, $todayEntry['in']);

        // Thêm giao dịch mới nhưng KHÔNG refresh lại MV — nếu trang vẫn đọc
        // rollup cũ (500000, không cộng thêm 999999) thì chứng minh nó đọc từ
        // bảng analytics_rollups, không phải quét lại cash_transactions mỗi request.
        CashTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'cash_register_id' => $register->id,
            'type' => 'in',
            'amount' => 999999,
            'source' => 'other',
            'notes' => 'Should not appear while rollup is fresh',
            'created_by' => $this->cashier->id,
            'occurred_at' => now(),
        ]);

        $response2 = $this->get(route('cash-flow.index'));
        $chartData2 = $response2->original->getData()['page']['props']['chartData'];
        $todayEntry2 = collect($chartData2)->last();
        $this->assertEquals(500000, $todayEntry2['in'], 'Trang phải đọc rollup đã materialize, không quét lại cash_transactions mỗi request.');

        // Integrity constraints are covered by dedicated endpoint tests below.
    }

    public function test_only_one_register_can_be_open_in_a_branch(): void
    {
        CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'opened_by' => $this->cashier->id,
            'cashier_user_id' => $this->cashier->id,
            'opening_balance' => 500000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $anotherCashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $anotherCashier->assignRole($this->cashierRole);

        $response = $this->actingAs($anotherCashier)->post(route('cash-flow.registers.open'), [
            'shift_id' => $this->shift->id,
            'opening_balance' => 100000,
        ]);

        $response->assertSessionHasErrors('shift_id');
        $this->assertDatabaseCount('cash_registers', 1);
    }

    public function test_branch_can_open_independent_registers_per_cashier_area(): void
    {
        $frontArea = Area::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Sảnh trước',
            'code' => 'FRONT',
        ]);
        $terraceArea = Area::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Sân vườn',
            'code' => 'GARDEN',
        ]);

        $secondCashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $secondCashier->assignRole($this->cashierRole);

        $this->actingAs($this->cashier)->post(route('cash-flow.registers.open'), [
            'shift_id' => $this->shift->id,
            'area_id' => $frontArea->id,
            'opening_balance' => 300000,
        ])->assertRedirect();

        $this->actingAs($secondCashier)->post(route('cash-flow.registers.open'), [
            'shift_id' => $this->shift->id,
            'area_id' => $terraceArea->id,
            'opening_balance' => 200000,
        ])->assertRedirect();

        $this->assertDatabaseCount('cash_registers', 2);
        $this->assertDatabaseHas('cash_registers', [
            'branch_id' => $this->branch->id,
            'area_id' => $frontArea->id,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('cash_registers', [
            'branch_id' => $this->branch->id,
            'area_id' => $terraceArea->id,
            'status' => 'open',
        ]);
    }

    public function test_cash_payment_without_opening_creates_a_reconciliation_exception(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => null,
            'created_by' => $this->cashier->id,
            'total_amount' => 150000,
            'payment_status' => 'unpaid',
        ]);

        Payment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'processed_by' => $this->cashier->id,
            'payment_method' => 'cash',
            'status' => 'paid',
            'amount' => 150000,
            'cash_received' => 150000,
            'change_amount' => 0,
            'paid_at' => now(),
        ]);

        $register = CashRegister::where('restaurant_id', $this->restaurant->id)->firstOrFail();
        $this->assertTrue((bool) $register->auto_opened);
        $this->assertTrue((bool) $register->requires_opening_reconciliation);
        $this->assertEquals(0, (float) $register->opening_balance);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_register_id' => $register->id,
            'amount' => 150000,
            'source' => 'order',
        ]);

        $blockedExpense = $this->actingAs($this->cashier)->post(route('cash-flow.transactions.store'), [
            'type' => 'out',
            'amount' => 10000,
            'notes' => 'Mua vật tư vệ sinh',
            'source' => 'expense',
            'voucher_code' => 'HD-AUTO-001',
        ]);
        $blockedExpense->assertSessionHasErrors('amount');

        $this->actingAs($this->owner)->post(
            route('cash-flow.registers.reconcile-opening', $register),
            [
                'opening_balance' => 500000,
                'notes' => 'Quản lý đã kiểm đếm cùng thu ngân.',
            ],
        )->assertRedirect();

        $register->refresh();
        $this->assertFalse((bool) $register->requires_opening_reconciliation);
        $this->assertEquals(650000, (float) $register->expected_closing_balance);
    }

    public function test_manual_expense_requires_a_unique_voucher_code(): void
    {
        CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'opened_by' => $this->cashier->id,
            'opening_balance' => 500000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($this->cashier)->post(route('cash-flow.transactions.store'), [
            'type' => 'out',
            'amount' => 75000,
            'notes' => 'Chi mua vật tư vệ sinh',
            'source' => 'expense',
        ]);

        $response->assertSessionHasErrors('voucher_code');
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_manual_expense_cannot_make_the_register_balance_negative(): void
    {
        CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'opened_by' => $this->cashier->id,
            'opening_balance' => 50000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($this->cashier)->post(route('cash-flow.transactions.store'), [
            'type' => 'out',
            'amount' => 75000,
            'notes' => 'Chi mua vật tư vệ sinh',
            'source' => 'expense',
            'voucher_code' => 'HD-NEGATIVE-001',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_repeating_a_cash_request_is_idempotent(): void
    {
        CashRegister::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today(),
            'opened_by' => $this->cashier->id,
            'opening_balance' => 500000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $payload = [
            'type' => 'out',
            'amount' => 75000,
            'notes' => 'Chi mua vật tư vệ sinh',
            'source' => 'expense',
            'voucher_code' => 'HD-IDEMPOTENT-001',
            'idempotency_key' => 'cash-request-test-001',
        ];

        $this->actingAs($this->cashier)->post(route('cash-flow.transactions.store'), $payload)
            ->assertRedirect();
        $this->actingAs($this->cashier)->post(route('cash-flow.transactions.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('cash_transactions', 1);
    }
}
