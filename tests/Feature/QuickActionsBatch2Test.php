<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CustomerFeedback;
use App\Models\Delivery\DeliveryBatch;
use App\Models\Delivery\DeliveryBatchItem;
use App\Models\Delivery\Shipper;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\PurchaseOrder;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuickActionsBatch2Test extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected Area $area;
    protected Unit $unit;
    protected WorkShift $shift;
    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::create([
            'name' => 'Nhà Hàng Batch 2',
            'code' => 'BATCH2ACT',
            'slug' => 'nha-hang-batch-2',
            'status' => 'active',
        ]);

        $this->branch = RestaurantBranch::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Chi Nhánh Batch 2',
            'code' => 'CNB2',
            'status' => 'active',
        ]);

        $this->area = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Khu Vực 1',
            'code' => 'AREA-B2',
        ]);

        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
        ]);

        $this->shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Ca Sáng',
            'code' => 'SHIFT-01',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Chủ Nhà Hàng B2',
            'email' => 'owner.batch2@test.com',
        ]);

        $this->employee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->owner->id,
            'employee_code' => 'EMP-OWNER',
            'full_name' => 'Chủ Quán',
            'email' => 'owner.batch2@test.com',
            'status' => 'active',
        ]);

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $manageAttendance = Permission::firstOrCreate(['name' => 'manage_attendance', 'guard_name' => 'web']);
        $manageWarehouse = Permission::firstOrCreate(['name' => 'manage_warehouse', 'guard_name' => 'web']);
        $manageOrders = Permission::firstOrCreate(['name' => 'manage_orders', 'guard_name' => 'web']);
        $manageProducts = Permission::firstOrCreate(['name' => 'manage_products', 'guard_name' => 'web']);
        $manageFeedback = Permission::firstOrCreate(['name' => 'manage_feedback', 'guard_name' => 'web']);
        $manageSuppliers = Permission::firstOrCreate(['name' => 'manage_suppliers', 'guard_name' => 'web']);

        $ownerRole->givePermissionTo([
            $manageAttendance,
            $manageWarehouse,
            $manageOrders,
            $manageProducts,
            $manageFeedback,
            $manageSuppliers,
        ]);
        $this->owner->assignRole($ownerRole);
    }

    public function test_batch_approve_normal_attendance(): void
    {
        $sa1 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => now()->toDateString(),
            'check_in_at' => now()->subHours(4),
            'status' => 'checked_in',
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('attendance.batch-approve-normal'));

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'approved_count' => 1]);

        $sa1->refresh();
        $this->assertEquals($this->owner->id, $sa1->approved_by);
    }

    public function test_warehouse_quick_auto_assign_tasks(): void
    {
        $sr = SupplyRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $this->branch->id,
            'request_code' => 'SR-TEST-B2',
            'created_by' => $this->owner->id,
            'status' => 'pending',
        ]);

        $task1 = WarehouseTaskAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'supply_request_id' => $sr->id,
            'task_type' => 'picking',
            'status' => 'pending',
            'priority' => 'normal',
            'due_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('warehouse.tasks.quick-auto-assign'));

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'assigned_count' => 1]);

        $task1->refresh();
        $this->assertEquals($this->owner->id, $task1->assigned_to);
        $this->assertEquals('assigned', $task1->status);
    }

    public function test_delivery_mark_all_picked_up(): void
    {
        $shipper = Shipper::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee->id,
            'vehicle_type' => 'motorbike',
            'is_active' => true,
        ]);

        $batch = DeliveryBatch::create([
            'restaurant_id' => $this->restaurant->id,
            'shipper_id' => $shipper->id,
            'created_by' => $this->owner->id,
            'status' => 'pending',
        ]);

        $order = \App\Models\Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-DELIV-01',
            'order_type' => 'delivery',
            'status' => 'preparing',
            'total_amount' => 150000,
        ]);

        $item1 = DeliveryBatchItem::create([
            'batch_id' => $batch->id,
            'order_id' => $order->id,
            'sequence_order' => 1,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('delivery.batches.mark-all-picked-up', $batch));

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'picked_up_count' => 1]);

        $item1->refresh();
        $this->assertEquals('in_transit', $item1->status);

        $batch->refresh();
        $this->assertEquals('in_transit', $batch->status);
    }

    public function test_pause_products_with_low_stock_ingredients(): void
    {
        $ingLow = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Sữa Tươi',
            'min_stock_level' => 10.0,
            'average_cost' => 30000,
        ]);

        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $ingLow->id,
            'quantity_on_hand' => 2.0,
        ]);

        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Cà Phê Sữa',
            'code' => 'PROD-CPS',
            'slug' => 'ca-phe-sua',
            'price' => 35000,
            'is_active' => true,
        ]);

        ProductRecipe::create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $ingLow->id,
            'unit_id' => $this->unit->id,
            'quantity' => 0.1,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('products.toggle-out-of-stock-ingredients'));

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'paused_count' => 1]);

        $product->refresh();
        $this->assertFalse((bool) $product->is_active);
    }

    public function test_feedback_quick_template_reply(): void
    {
        $feedback = CustomerFeedback::create([
            'restaurant_id' => $this->restaurant->id,
            'submitted_by_name' => 'Trần Văn B',
            'rating' => 2,
            'content' => 'Món ăn nguội và phục vụ chậm',
            'status' => 'new',
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('feedback.quick-template-reply', $feedback), [
                'template_type' => 'apology_voucher',
                'custom_note' => 'Đã nhắc nhở nhân viên ca trực',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'status' => 'resolved']);

        $feedback->refresh();
        $this->assertEquals('resolved', $feedback->status);
        $this->assertNotNull($feedback->resolution_notes);
    }

    public function test_purchase_orders_send_delivery_reminder(): void
    {
        $supplier = Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Nhà Cung Cấp Nông Sản',
        ]);

        $po = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->owner->id,
            'po_number' => 'PO-TEST-001',
            'status' => 'approved',
            'total_amount' => 5000000,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('purchase-orders.send-delivery-reminder'));

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'reminded_count' => 1]);

        $po->refresh();
        $this->assertNotNull($po->updated_at);
    }
}
