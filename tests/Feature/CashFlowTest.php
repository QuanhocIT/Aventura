<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ShiftClosing;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
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

        // 3. Submit Shift Closing
        $response = $this->post(route('shift-closings.store'), [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'actual_cash' => 440000, // actual cash counted
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

    public function test_dashboard_alerts_appear_on_variance_and_budget_overrun(): void
    {
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
        $discrepancyAlert = collect($alerts)->first(fn($a) => str_contains($a['message'], 'chênh lệch vượt quá 2%'));
        $this->assertNotNull($discrepancyAlert);
        $this->assertEquals('danger', $discrepancyAlert['type']);

        // Check budget overrun alert
        $overrunAlert = collect($alerts)->first(fn($a) => str_contains($a['message'], 'vượt quá ngân sách'));
        $this->assertNotNull($overrunAlert);
        $this->assertEquals('warning', $overrunAlert['type']);
    }
}
