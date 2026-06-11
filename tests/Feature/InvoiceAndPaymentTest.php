<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private User $cashier;

    private Employee $cashierEmp;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private Area $area;

    private RestaurantTable $table;

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

        // Set Tenant Context
        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        // 3. Manager Setup
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->manager->assignRole($this->managerRole);

        // 4. Cashier Setup (Employee + WorkShift + Schedule to pass SetTenantContext middleware)
        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
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

        // 5. Area & Table Setup
        $this->area = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Khu vuc A',
            'code' => 'khu-vuc-a',
            'display_order' => 1,
            'status' => 'active',
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $this->area->id,
            'name' => 'Ban 101',
            'capacity' => 4,
            'status' => 'available',
            'qr_token' => Str::random(32),
        ]);
    }

    public function test_only_cashier_can_perform_payment_and_complete_order(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-1001',
            'table_id' => $this->table->id,
            'status' => 'preparing',
            'payment_status' => 'unpaid',
            'subtotal' => 200000,
            'discount_amount' => 0,
            'total_amount' => 200000,
        ]);

        $this->table->update(['status' => 'occupied']);

        // 1. Manager tries to complete order -> 403 Forbidden
        $response = $this->actingAs($this->manager)
            ->patch("/orders/{$order->id}/status", [
                'status' => 'completed',
            ]);
        $response->assertStatus(403);
        $order->refresh();
        $this->assertSame('preparing', $order->status);

        // 2. Cashier completes order successfully
        $response = $this->actingAs($this->cashier)
            ->patch("/orders/{$order->id}/status", [
                'status' => 'completed',
            ]);
        $response->assertRedirect();

        $order->refresh();
        $this->table->refresh();

        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('available', $this->table->status);

        // 3. Verify Payment record exists and matches amount
        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('paid', $payment->status);
        $this->assertSame('cash', $payment->payment_method);
        $this->assertEquals(200000.0, (float) $payment->amount);
    }

    public function test_table_status_occupancy_on_store_order(): void
    {
        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'price' => 50000,
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->assertSame('available', $this->table->status);

        $response = $this->actingAs($this->cashier)
            ->post('/orders', [
                'table_id' => $this->table->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $this->table->refresh();
        $this->assertSame('occupied', $this->table->status);
    }

    public function test_table_status_occupancy_on_order_confirmation(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-1002',
            'table_id' => $this->table->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total_amount' => 100000,
        ]);

        $this->assertSame('available', $this->table->status);

        // Confirming the order
        $response = $this->actingAs($this->cashier)
            ->patch("/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertRedirect();
        $this->table->refresh();
        $this->assertSame('occupied', $this->table->status);
    }

    public function test_table_status_release_on_order_cancellation(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-1003',
            'table_id' => $this->table->id,
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total_amount' => 100000,
        ]);

        $this->table->update(['status' => 'occupied']);

        // Cancel order
        $response = $this->actingAs($this->cashier)
            ->patch("/orders/{$order->id}/status", [
                'status' => 'cancelled',
            ]);

        $response->assertRedirect();
        $this->table->refresh();
        $this->assertSame('available', $this->table->status);
    }

    public function test_only_cashier_can_apply_voucher(): void
    {
        $promotion = Promotion::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Giam 50k',
            'code' => 'GIAM50',
            'type' => 'fixed_amount',
            'value' => 50000,
            'min_order_amount' => 100000,
            'is_active' => true,
            'is_approved' => true,
        ]);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-1004',
            'table_id' => $this->table->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 150000,
            'discount_amount' => 0,
            'total_amount' => 150000,
        ]);

        // 1. Manager attempts to apply voucher -> 403 Forbidden
        $response = $this->actingAs($this->manager)
            ->postJson('/api/promotions/apply', [
                'order_id' => $order->id,
                'code' => 'GIAM50',
            ]);
        $response->assertStatus(403);
        $order->refresh();
        $this->assertEquals(0, (float) $order->discount_amount);

        // 2. Cashier applies voucher successfully
        $response = $this->actingAs($this->cashier)
            ->postJson('/api/promotions/apply', [
                'order_id' => $order->id,
                'code' => 'GIAM50',
            ]);
        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals(50000.0, (float) $order->discount_amount);
        $this->assertEquals(100000.0, (float) $order->total_amount);
    }
}
