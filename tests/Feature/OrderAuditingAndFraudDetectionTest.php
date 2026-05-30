<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\Area;
use App\Models\ShiftClosing;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\FraudDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderAuditingAndFraudDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $cashier;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected Area $area;
    protected RestaurantTable $table1;
    protected RestaurantTable $table2;
    protected WorkShift $shift;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // Create standard roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        // Set up owner
        $this->owner = User::factory()->create();
        $this->owner->assignRole($ownerRole);

        // Set up restaurant and branch
        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id
        ]);

        $this->owner->forceFill([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id
        ])->save();

        // Set up cashier (who will also have an Employee profile)
        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Nguyễn Thị Thu'
        ]);
        $this->cashier->assignRole($cashierRole);

        Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->cashier->id,
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
            'status' => 'active',
            'role_id' => $cashierRole->id, // Required relation
        ]);

        // Set up Table Area and Tables
        $this->area = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Khu A',
            'code' => 'khu-a',
            'status' => 'active'
        ]);

        $this->table1 = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $this->area->id,
            'name' => 'Bàn 1',
            'capacity' => 4,
            'status' => 'occupied'
        ]);

        $this->table2 = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $this->area->id,
            'name' => 'Bàn 2',
            'capacity' => 4,
            'status' => 'available' // bàn trống để tách sang
        ]);

        // Set up Work Shift
        $this->shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sáng',
            'code' => 'CA_SANG',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'is_overnight' => false,
            'status' => 'active'
        ]);

        // Set up Product
        $this->product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'code' => 'PHO-01',
            'name' => 'Phở Bò',
            'slug' => 'pho-bo',
            'price' => 50000,
            'is_active' => true,
            'is_available' => true,
            'description' => 'Phở ngon gia truyền 12345'
        ]);
    }

    public function test_cashier_can_split_order_which_creates_red_flag_and_audit_log(): void
    {
        // 1. Create original order on Table 1
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table1->id,
            'created_by' => $this->cashier->id,
            'order_number' => 'ORD-1001',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'pending',
            'payment_status' => 'unpaid'
        ]);

        $item1 = OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'line_total' => 100000
        ]);

        // 2. Split order (move 1 Phở Bò to Table 2)
        $response = $this->actingAs($this->cashier)->post(route('orders.split', $order), [
            'table_id' => $this->table2->id,
            'items' => [
                [
                    'order_item_id' => $item1->id,
                    'quantity' => 1,
                ]
            ]
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // 3. Verify original order quantity is reduced (2 - 1 = 1)
        $item1->refresh();
        $this->assertEquals(1.0, (float)$item1->quantity);
        $this->assertEquals(50000.0, (float)$item1->line_total);

        $order->refresh();
        $this->assertEquals(50000.0, (float)$order->total_amount);

        // 4. Verify new split order exists with red flag
        $splitOrder = Order::where('restaurant_id', $this->restaurant->id)
            ->where('is_split', true)
            ->first();

        $this->assertNotNull($splitOrder);
        $this->assertEquals(50000.0, (float)$splitOrder->total_amount);
        $this->assertTrue((bool)$splitOrder->is_red_flagged);
        $this->assertFalse((bool)$splitOrder->is_override_split_penalty);
        $this->assertEquals($order->id, $splitOrder->split_from_order_id);

        // 5. Verify audit log is recorded for the split
        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $this->restaurant->id,
            'action' => 'order_split',
            'event' => 'created',
            'subject_type' => Order::class,
            'subject_id' => $splitOrder->id
        ]);
    }

    public function test_split_order_inflicts_negative_expected_cash_penalty_at_shift_closing(): void
    {
        // 1. Create a completed split order (not overridden yet)
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table2->id,
            'created_by' => $this->cashier->id,
            'order_number' => 'ORD-1002',
            'subtotal' => 60000,
            'total_amount' => 60000,
            'status' => 'completed',
            'payment_status' => 'paid',
            'completed_at' => now(),
            'is_split' => true,
            'is_override_split_penalty' => false,
        ]);

        // Create standard payment for this split order (collected 60k cash)
        Payment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'status' => 'paid',
            'amount' => 60000,
            'paid_at' => now()
        ]);

        // 2. Preview shift closing - cash penalty should be applied
        $response = $this->actingAs($this->cashier)->get(route('shift-closings.preview', [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
        ]));

        $response->assertOk();
        $data = $response->json();

        // 60k cash collected, but since it's an unapproved split order, the penalty is -60k.
        // Therefore, expected_cash must be max(0, 60k - 60k) = 0!
        $this->assertEquals(60000.0, (float)$data['split_penalty_total']);
        $this->assertEquals(0.0, (float)$data['expected_cash']);
        $this->assertCount(1, $data['split_orders']);
    }

    public function test_owner_can_override_split_penalty_which_removes_red_flag(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table2->id,
            'created_by' => $this->cashier->id,
            'order_number' => 'ORD-1003',
            'subtotal' => 60000,
            'total_amount' => 60000,
            'status' => 'completed',
            'payment_status' => 'paid',
            'is_split' => true,
            'is_red_flagged' => true,
            'is_override_split_penalty' => false,
        ]);

        // Owner overrides split penalty
        $response = $this->actingAs($this->owner)->patch(route('orders.override-split-penalty', $order));
        
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $order->refresh();
        $this->assertTrue((bool)$order->is_override_split_penalty);
        $this->assertFalse((bool)$order->is_red_flagged);

        // Verify override audit log
        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $this->restaurant->id,
            'action' => 'order_split_override',
            'event' => 'updated',
            'subject_id' => $order->id
        ]);
    }

    public function test_sensitive_price_and_discount_modifications_are_audit_logged(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table1->id,
            'created_by' => $this->cashier->id,
            'order_number' => 'ORD-1004',
            'subtotal' => 50000,
            'discount_amount' => 0,
            'total_amount' => 50000,
            'status' => 'pending',
            'payment_status' => 'unpaid'
        ]);

        $item = OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000
        ]);

        // Modify order: apply 10k discount and change unit price to 60k
        $response = $this->actingAs($this->owner)->patch(route('orders.update', $order), [
            'discount_amount' => 10000,
            'items' => [
                [
                    'id' => $item->id,
                    'unit_price' => 60000,
                    'quantity' => 1
                ]
            ]
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Verify discount applied audit log
        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $this->restaurant->id,
            'action' => 'discount_applied',
            'event' => 'updated',
            'subject_id' => $order->id
        ]);

        // Verify price modified audit log
        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $this->restaurant->id,
            'action' => 'price_modified',
            'event' => 'updated',
            'subject_id' => $order->id
        ]);
    }

    public function test_ai_fraud_detection_rules_yield_flagged_alerts(): void
    {
        // Perform a price modification to generate an audit log
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table1->id,
            'created_by' => $this->cashier->id,
            'order_number' => 'ORD-1005',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'status' => 'completed',
            'payment_status' => 'paid'
        ]);

        // Create audit log entry directly to make sure restaurant_id and user_id are set correctly
        AuditLog::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->cashier->id,
            'user_role' => 'cashier',
            'event' => 'updated',
            'action' => 'price_modified',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'old_values' => ['unit_price' => 50000],
            'new_values' => ['unit_price' => 30000],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0'
        ]);

        $service = new FraudDetectionService($this->restaurant->id, today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString());
        $alerts = $service->detectAiFraudAlerts();

        // Verify both fallback static alerts AND the parsed database audit log alert exist!
        $this->assertCount(3, $alerts);

        $simulatedAlert1 = collect($alerts)->firstWhere('employee_name', 'Nguyễn Thị Thu');
        $this->assertNotNull($simulatedAlert1);
        $this->assertEquals('AI: Sửa giá món nhiều lần', $simulatedAlert1['violation_type']);
        $this->assertEquals(97.8, $simulatedAlert1['risk_score']);

        $dbAlert = collect($alerts)->firstWhere('violation_type', 'AI: Sửa đổi giá món nhạy cảm');
        $this->assertNotNull($dbAlert);
        $this->assertEquals(92.5, $dbAlert['risk_score']);
        $this->assertStringContainsString('Nguyễn Thị Thu', $dbAlert['description']);
    }
}
