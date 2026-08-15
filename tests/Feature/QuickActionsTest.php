<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Area;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Models\TemporaryOrder;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuickActionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $staff;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected Area $area;
    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::create([
            'name' => 'Nhà Hàng Quick Action',
            'code' => 'QUICKACT',
            'slug' => 'nha-hang-quick-action',
            'status' => 'active',
        ]);

        $this->branch = RestaurantBranch::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Chi Nhánh Chính',
            'code' => 'CN1',
            'status' => 'active',
        ]);

        $this->area = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Khu Tầng 1',
            'code' => 'AREA-01',
        ]);

        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
        ]);

        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Chủ Nhà Hàng',
            'email' => 'owner.quick@test.com',
        ]);

        $this->staff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Nhân Viên Thu Ngân',
            'email' => 'staff.quick@test.com',
        ]);

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $manageOrders = Permission::firstOrCreate(['name' => 'manage_orders', 'guard_name' => 'web']);
        $manageKitchen = Permission::firstOrCreate(['name' => 'manage_kitchen', 'guard_name' => 'web']);
        $approveRequests = Permission::firstOrCreate(['name' => 'approve_requests', 'guard_name' => 'web']);

        $ownerRole->givePermissionTo([$manageOrders, $manageKitchen, $approveRequests]);
        $this->owner->assignRole($ownerRole);
    }

    public function test_quick_table_assignment_finds_best_matching_table(): void
    {
        $table1 = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $this->area->id,
            'name' => 'Bàn 2 người',
            'capacity' => 2,
            'status' => 'available',
        ]);

        $table2 = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $this->area->id,
            'name' => 'Bàn 4 người',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $reservation = TableReservation::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'guest_name' => 'Nguyễn Văn A',
            'guest_phone' => '0901234567',
            'reservation_date' => now()->toDateString(),
            'reservation_time' => '19:00',
            'party_size' => 4,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('reservations.auto-assign', $reservation));

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'table_name' => 'Bàn 4 người']);

        $reservation->refresh();
        $this->assertEquals($table2->id, $reservation->table_id);
        $this->assertEquals('confirmed', $reservation->status);
    }

    public function test_quick_qr_order_batch_approval_converts_orders_and_flags_overdue(): void
    {
        $table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $this->area->id,
            'name' => 'Bàn 01',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $prod1 = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Phở Bò',
            'code' => 'PROD-PHO',
            'slug' => 'pho-bo',
            'price' => 50000,
            'is_active' => true,
        ]);

        $prod2 = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Bún Chả',
            'code' => 'PROD-BUN',
            'slug' => 'bun-cha',
            'price' => 45000,
            'is_active' => true,
        ]);

        $tempRecent = TemporaryOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $table->id,
            'cart_data' => [
                ['product_id' => $prod1->id, 'name' => 'Phở Bò', 'quantity' => 2, 'unit_price' => 50000, 'line_total' => 100000],
            ],
            'total_amount' => 100000,
            'status' => 'pending',
            'created_at' => now()->subMinutes(2),
        ]);

        $tempOld = TemporaryOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $table->id,
            'cart_data' => [
                ['product_id' => $prod2->id, 'name' => 'Bún Chả', 'quantity' => 1, 'unit_price' => 45000, 'line_total' => 45000],
            ],
            'total_amount' => 45000,
            'status' => 'pending',
            'created_at' => now()->subMinutes(45),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('orders.batch-approve-qr'), [
                'temporary_order_ids' => [$tempRecent->id, $tempOld->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'approved_count' => 1,
            ]);

        $tempRecent->refresh();
        $this->assertEquals('approved', $tempRecent->status);

        $tempOld->refresh();
        $this->assertEquals('pending', $tempOld->status);

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $this->restaurant->id,
            'channel' => 'qr',
            'total_amount' => 100000,
        ]);
    }

    public function test_quick_selective_inventory_count_preset(): void
    {
        $ingLow = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Thịt Bò',
            'min_stock_level' => 5.0,
            'average_cost' => 200000,
        ]);

        $ingNormal = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Rau Thơm',
            'min_stock_level' => 5.0,
            'average_cost' => 10000,
        ]);

        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $ingLow->id,
            'quantity_on_hand' => 2.0,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('inventory.counts.quick-preset'), [
                'branch_id' => $this->branch->id,
                'preset' => 'low_stock',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('inventory_count_sessions', [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'type' => 'spot_check',
        ]);
    }

    public function test_quick_recommended_supply_request(): void
    {
        $centralBranch = app(\App\Services\CentralWarehouseService::class)->ensureCentralWarehouse($this->restaurant->id);

        $ingLow = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Cà Phê Bột',
            'min_stock_level' => 10.0,
            'average_cost' => 150000,
        ]);

        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $centralBranch->id,
            'ingredient_id' => $ingLow->id,
            'quantity_on_hand' => 100.0,
        ]);

        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $ingLow->id,
            'quantity_on_hand' => 1.0,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('supply-requests.quick-recommended'), [
                'branch_id' => $this->branch->id,
                'notes' => 'Cấp gấp cà phê',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('central_supply_requests', [
            'restaurant_id' => $this->restaurant->id,
            'to_branch_id' => $this->branch->id,
        ]);
    }

    public function test_kitchen_overdue_sla_waiter_call(): void
    {
        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Lẩu Hải Sản',
            'code' => 'PROD-LAU',
            'slug' => 'lau-hai-san',
            'price' => 350000,
            'is_active' => true,
        ]);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-TEST-01',
            'status' => 'preparing',
            'payment_status' => 'unpaid',
            'total_amount' => 350000,
        ]);

        $item = OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 350000,
            'line_total' => 350000,
            'status' => 'preparing',
            'sent_to_kitchen_at' => now()->subMinutes(20),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('kitchen.items.notify-waiter', $item));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_approval_batch_low_risk_processing(): void
    {
        $lowRiskApproval = ApprovalRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'operation_type' => 'discount_small',
            'operation_data' => ['discount_amount' => 50000],
            'amount_involved' => 50000,
            'status' => 'pending',
            'requester_id' => $this->staff->id,
            'created_at' => now()->subHours(25),
        ]);

        $highRiskApproval = ApprovalRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'operation_type' => 'operating_expense_large',
            'operation_data' => ['expense_amount' => 50000000],
            'amount_involved' => 50000000,
            'status' => 'pending',
            'requester_id' => $this->staff->id,
            'created_at' => now()->subHours(30),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('approvals.batch-approve-low-risk'));

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'approved_count' => 1]);

        $lowRiskApproval->refresh();
        $this->assertEquals('approved', $lowRiskApproval->status);

        $highRiskApproval->refresh();
        $this->assertEquals('pending', $highRiskApproval->status);
    }
}
