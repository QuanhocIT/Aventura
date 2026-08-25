<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\OperatingExpense;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\CentralWarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionReadinessSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branch1;

    private RestaurantBranch $branch2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->branch1 = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->branch2 = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
    }

    public function test_kitchen_and_waiter_cannot_open_cash_register_or_store_transactions(): void
    {
        $kitchenUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch1->id,
        ]);
        $kitchenUser->assignRole('kitchen');

        $shift = WorkShift::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $response = $this->actingAs($kitchenUser)->post(route('cash-flow.registers.open'), [
            'shift_id' => $shift->id,
            'opening_balance' => 500000,
        ]);

        $response->assertStatus(403);
    }

    public function test_branch_manager_cannot_view_debt_of_other_branches(): void
    {
        $managerBranch1 = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch1->id,
        ]);
        $managerBranch1->assignRole('manager');

        $customer = Customer::factory()->create(['restaurant_id' => $this->restaurant->id]);

        AccountReceivable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch1->id,
            'customer_id' => $customer->id,
            'amount' => 1000000,
            'received_amount' => 0,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
        ]);

        AccountReceivable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch2->id,
            'customer_id' => $customer->id,
            'amount' => 999000000,
            'received_amount' => 0,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
        ]);

        $response = $this->actingAs($managerBranch1)->get(route('debts.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('stats.total_receivable', 1000000)
        );
    }

    public function test_branch_manager_updating_salary_creates_pending_approval_request(): void
    {
        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch1->id,
        ]);
        $manager->assignRole('manager');

        $empUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch1->id,
        ]);
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch1->id,
            'user_id' => $empUser->id,
            'base_salary' => 5000000,
            'pay_rate' => 25000,
        ]);

        $this->actingAs($manager)->patch(route('employees.update', $employee), [
            'base_salary' => 10000000,
        ]);

        $this->assertDatabaseHas('salary_change_requests', [
            'employee_id' => $employee->id,
            'new_base_salary' => 10000000,
            'status' => 'pending',
        ]);

        $employee->refresh();
        $this->assertEquals(5000000, (float) $employee->base_salary);
    }

    public function test_duplicate_receive_supply_request_does_not_double_increment_inventory(): void
    {
        $sender = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch1->id]);
        $receiver = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch2->id]);
        $receiver->assignRole('manager');

        $ingredient = Ingredient::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $supplyRequest = SupplyRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'from_branch_id' => $this->branch1->id,
            'to_branch_id' => $this->branch2->id,
            'request_code' => 'SR-TEST-001',
            'created_by' => $receiver->id,
            'dispatched_by' => $sender->id,
            'status' => SupplyRequest::STATUS_DISPATCHED,
        ]);

        $item = SupplyRequestItem::create([
            'supply_request_id' => $supplyRequest->id,
            'ingredient_id' => $ingredient->id,
            'requested_quantity' => 10,
            'actual_dispatched_quantity' => 10,
            'unit_cost' => 100,
        ]);

        $service = app(CentralWarehouseService::class);

        // Receive call 1
        $service->receiveSupplyRequest($supplyRequest, $receiver, [
            ['id' => $item->id, 'received_quantity' => 10],
        ]);

        $inv = Inventory::where('branch_id', $this->branch2->id)
            ->where('ingredient_id', $ingredient->id)
            ->first();
        $this->assertEquals(10, (float) $inv->quantity_on_hand);

        // Retry call 2 with same quantity (10)
        $service->receiveSupplyRequest($supplyRequest, $receiver, [
            ['id' => $item->id, 'received_quantity' => 10],
        ]);

        $inv->refresh();
        $this->assertEquals(10, (float) $inv->quantity_on_hand);
    }

    public function test_approved_expense_cannot_be_edited_or_deleted(): void
    {
        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch1->id,
        ]);
        $manager->assignRole('manager');

        $expense = OperatingExpense::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch1->id,
            'amount' => 500000,
            'expense_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $responseEdit = $this->actingAs($manager)->patch(route('expenses.update', $expense), [
            'amount' => 999999,
            'expense_date' => now()->toDateString(),
        ]);
        $responseEdit->assertStatus(403);

        $responseDelete = $this->actingAs($manager)->delete(route('expenses.destroy', $expense));
        $responseDelete->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_access_secure_file(): void
    {
        $response = $this->getJson(route('secure-files.download', ['path' => 'invoices/secret.pdf']));
        $response->assertStatus(401);
    }
}
