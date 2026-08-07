<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\LeaveRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected Unit $unit;

    protected Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);

        $this->owner->forceFill([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ])->save();

        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'weight',
        ]);

        $this->ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'unit_id' => $this->unit->id,
            'name' => 'Bột mì',
            'sku' => 'BOT-MI',
            'average_cost' => 20,
            'status' => 'active',
        ]);

        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_on_hand' => 1000.0,
            'theoretical_quantity' => 1000.0,
            'last_cost' => 20,
        ]);
    }

    /**
     * Test 1: Kiểm kho nhanh (Fast Reconciliation)
     */
    public function test_inventory_reconcile_updates_quantities_and_creates_transaction()
    {
        $this->actingAs($this->owner);

        // Giả sử đếm thực tế là 950g (hụt 50g)
        $response = $this->post(route('inventory.reconcile'), [
            'reconcile_items' => [
                [
                    'ingredient_id' => $this->ingredient->id,
                    'physical_qty' => 950.0,
                ],
            ],
            'notes' => 'Kiểm kho cuối ca chiều',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Kiểm tra số lượng tồn kho được cập nhật
        $inventory = Inventory::where('restaurant_id', $this->restaurant->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->firstOrFail();

        $this->assertEquals(950.0, $inventory->quantity_on_hand);
        $this->assertEquals(950.0, $inventory->theoretical_quantity);

        // Kiểm tra transaction được sinh ra loại 'stocktake'
        $transaction = InventoryTransaction::where('restaurant_id', $this->restaurant->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->where('type', 'stocktake')
            ->firstOrFail();

        $this->assertEquals('out', $transaction->direction);
        $this->assertEquals(50.0, $transaction->quantity);
        $this->assertEquals(20.0, $transaction->unit_cost);
        $this->assertEquals(1000.0, $transaction->total_cost); // 50 * 20
        $this->assertStringStartsWith('Kiểm kho cuối ca chiều', $transaction->notes);
    }

    /**
     * Test 2: Báo cáo đối soát chéo KDS vs POS
     */
    public function test_cross_reconciliation_report_returns_accurate_discrepancies()
    {
        $this->actingAs($this->owner);

        // Tạo món ăn (Product)
        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Phở Bò',
            'price' => 50000,
            'cost_price' => 20000,
        ]);

        // Tạo 1 đơn hàng đã hoàn thành và thanh toán (POS bán)
        $orderPaid = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => 'ORD-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 50000,
            'channel' => 'dine_in',
        ]);

        // Món phở trong đơn đã bán này đã được làm (KDS chế biến)
        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $orderPaid->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
            'status' => 'served',
            'prepared_at' => now(),
            'served_at' => now(),
        ]);

        // Tạo 1 đơn bị hủy hoặc chưa thanh toán nhưng bếp VẪN làm (KDS chế biến nhưng không có POS bán tương ứng)
        $orderUnpaid = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => 'ORD-002',
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'total_amount' => 50000,
            'channel' => 'dine_in',
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $orderUnpaid->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
            'status' => 'cancelled',
            'prepared_at' => now(), // Đã chế biến xong trước khi bị hủy
        ]);

        // Truy cập báo cáo đối soát chéo
        $response = $this->get(route('reports.reconciliation'));

        $response->assertOk();

        // Lấy dữ liệu Inertia truyền xuống
        $page = $response->original->getData()['page'];
        $reconciliation = $page['props']['reconciliation'];
        $totals = $page['props']['totals'];

        // Tổng bếp làm phải là 2 (1 cái từ đơn thanh toán, 1 cái từ đơn đã nấu nhưng hủy)
        $this->assertEquals(2.0, $totals['total_cooked']);
        // Tổng POS thanh toán thực tế là 1
        $this->assertEquals(1.0, $totals['total_billed']);
        // Chênh lệch thất thoát là 1
        $this->assertEquals(1.0, $totals['total_discrepancy']);
        // Thất thoát giá vốn ước tính là 20.000đ
        $this->assertEquals(20000.0, $totals['total_loss_cost']);
    }

    /**
     * Test 3: Xếp ca không bắt buộc đủ 11 giờ nghỉ
     */
    public function test_schedule_allows_assignment_without_rest_bypass()
    {
        $this->actingAs($this->owner);

        // Tạo nhân viên & ca làm việc
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $employee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_code' => 'EMP-VIOLATE',
            'full_name' => 'Lê Gia Khánh',
            'email' => 'giakhanh@example.com',
            'phone' => '0987654321',
            'citizen_id_number' => '123456789014',
            'address' => 'Hải Phòng',
            'hire_date' => today(),
            'base_salary' => 5000000,
            'job_title' => 'Phục vụ',
            'employment_type' => 'full_time',
            'status' => 'active',
            'role_id' => $managerRole->id,
        ]);

        $shiftMorning = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Ca Sáng',
            'code' => 'CA_SANG',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'status' => 'active',
        ]);

        $shiftEvening = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Ca Tối Hôm Trước',
            'code' => 'CA_TOI',
            'start_time' => '22:00:00',
            'end_time' => '05:00:00', // Overnight kết thúc lúc 05:00 sáng ngày hôm sau
            'is_overnight' => true,
            'status' => 'active',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00')); // Wednesday

        // Gán ca tối cho ngày hôm trước (Tuesday - 2026-06-09)
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee->id,
            'shift_id' => $shiftEvening->id,
            'scheduled_date' => '2026-06-09',
            'status' => 'scheduled',
        ]);

        // Gán ca sáng cho ngày hôm nay dù thời gian nghỉ chỉ còn 1 giờ.
        $response = $this->post(route('employees.schedules.store'), [
            'day' => 'Wednesday',
            'employee_name' => $employee->full_name,
            'shift_name' => $shiftMorning->name,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Đã phân ca thành công
        $assignment = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('shift_id', $shiftMorning->id)
            ->first();
        $this->assertNotNull($assignment);
        $this->assertEquals('2026-06-10', Carbon::parse($assignment->scheduled_date)->toDateString());

        $this->assertFalse(AuditLog::where('restaurant_id', $this->restaurant->id)
            ->where('action', 'schedule_rest_rule_bypass')
            ->exists());
    }

    /**
     * Test 4: Bypass hạn ngạch xin nghỉ phép 30% bộ phận
     */
    public function test_leave_quota_violation_requires_bypass()
    {
        $this->actingAs($this->owner);

        // Tạo 3 nhân viên có cùng role_id
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $employeeA = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_code' => 'EMP-A',
            'full_name' => 'Nguyễn Văn A',
            'email' => 'a@example.com',
            'phone' => '0901234561',
            'citizen_id_number' => '123456789015',
            'address' => 'Hà Nội',
            'hire_date' => today(),
            'base_salary' => 6000000,
            'job_title' => 'Pha chế',
            'employment_type' => 'full_time',
            'status' => 'active',
            'role_id' => $managerRole->id,
        ]);

        $employeeB = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_code' => 'EMP-B',
            'full_name' => 'Nguyễn Văn B',
            'email' => 'b@example.com',
            'phone' => '0901234562',
            'citizen_id_number' => '123456789016',
            'address' => 'Hà Nội',
            'hire_date' => today(),
            'base_salary' => 6000000,
            'job_title' => 'Pha chế',
            'employment_type' => 'full_time',
            'status' => 'active',
            'role_id' => $managerRole->id,
        ]);

        $employeeC = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_code' => 'EMP-C',
            'full_name' => 'Nguyễn Văn C',
            'email' => 'c@example.com',
            'phone' => '0901234563',
            'citizen_id_number' => '123456789017',
            'address' => 'Hà Nội',
            'hire_date' => today(),
            'base_salary' => 6000000,
            'job_title' => 'Pha chế',
            'employment_type' => 'full_time',
            'status' => 'active',
            'role_id' => $managerRole->id,
        ]);

        // Gán 1 đơn nghỉ phép cho ngày mai của nhân viên A (33.3% của tổng 3 nhân sự, đã bắt đầu chạm ngưỡng)
        LeaveRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employeeA->id,
            'leave_type' => 'annual',
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Việc gia đình',
            'status' => 'approved',
        ]);

        // Cố gắng xin nghỉ thêm cho nhân viên B vào ngày mai (sẽ làm 2/3 = 66% bộ phận nghỉ -> vượt 30%)
        $response = $this->post(route('employees.leaves.store'), [
            'employee_id' => $employeeB->id,
            'leave_type' => 'annual',
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Đi khám bệnh',
        ]);

        // Phải báo lỗi vượt hạn ngạch 30%
        $response->assertSessionHasErrors(['start_date']);

        // Gửi kèm mã bypass MANAGER123 và lý do
        $responseWithBypass = $this->post(route('employees.leaves.store'), [
            'employee_id' => $employeeB->id,
            'leave_type' => 'annual',
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Đi khám bệnh',
            'bypass_code' => 'MANAGER123',
            'bypass_reason' => 'Lý do sức khỏe khẩn cấp, đã có nhân viên ca khác tăng ca bù',
        ]);

        $responseWithBypass->assertRedirect();
        $responseWithBypass->assertSessionHasNoErrors();

        // Đơn xin nghỉ được tạo thành công
        $leave = LeaveRequest::where('employee_id', $employeeB->id)->first();
        $this->assertNotNull($leave);
        $this->assertEquals(today()->addDay()->toDateString(), Carbon::parse($leave->start_date)->toDateString());

        // Log bypass phải được ghi nhận
        $this->assertTrue(AuditLog::where('restaurant_id', $this->restaurant->id)
            ->where('action', 'leave_quota_bypass')
            ->exists()
        );
    }

    /**
     * Test 5: Gộp đơn trùng bàn khi đồng bộ offline gặp xung đột
     */
    public function test_offline_order_table_sync_conflict_resolution()
    {
        $this->actingAs($this->owner);
        $this->post(route('branch.switch'), ['branch_id' => $this->branch->id])->assertRedirect();

        // Tạo bàn
        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Bàn VIP 99',
            'capacity' => 10,
            'status' => 'available',
        ]);

        // Tạo sản phẩm 1 & 2
        $p1 = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Cơm Chiên Dương Châu',
            'price' => 50000,
            'is_active' => true,
            'is_available' => true,
        ]);

        $p2 = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Nước ép cam',
            'price' => 30000,
            'is_active' => true,
            'is_available' => true,
        ]);

        // Gửi đơn 1 cho Bàn VIP 99 (POS A đồng bộ)
        $response1 = $this->post(route('orders.store'), [
            'channel' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2, 'notes' => 'Ít dầu'],
            ],
            'guests_count' => 2,
        ]);

        $response1->assertRedirect();

        $order1 = Order::where('table_id', $table->id)->where('status', 'pending')->first();
        $this->assertNotNull($order1);
        $this->assertEquals(100000, $order1->total_amount);

        // Bàn phải chuyển sang occupied
        $this->assertEquals('occupied', $table->refresh()->status);

        // Gửi đơn 2 cho Bàn VIP 99 (POS B đồng bộ offline xung đột)
        $response2 = $this->post(route('orders.store'), [
            'channel' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 1],
                ['product_id' => $p2->id, 'quantity' => 3],
            ],
            'guests_count' => 2,
        ]);

        $response2->assertRedirect();

        // Kiểm tra đơn 1 đã được gộp các món từ đơn 2
        $order1->refresh();

        // Tổng tiền mới: Cơm chiên (2 + 1) * 50k = 150k + Nước cam (3) * 30k = 90k => 240k
        $this->assertEquals(240000, $order1->total_amount);

        // Kiểm tra xem số lượng món trong order_items
        $itemP1 = OrderItem::where('order_id', $order1->id)->where('product_id', $p1->id)->first();
        $this->assertEquals(3, $itemP1->quantity);

        $itemP2 = OrderItem::where('order_id', $order1->id)->where('product_id', $p2->id)->first();
        $this->assertEquals(3, $itemP2->quantity);

        // Log gộp đơn phải được ghi nhận
        $this->assertTrue(AuditLog::where('restaurant_id', $this->restaurant->id)
            ->where('action', 'order_merged')
            ->exists()
        );
    }

    public function test_paid_order_still_occupies_table_until_all_items_are_served(): void
    {
        $this->actingAs($this->owner);
        $this->post(route('branch.switch'), ['branch_id' => $this->branch->id])->assertRedirect();

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'available', // mô phỏng dữ liệu cũ bị trả bàn sớm sau thanh toán
        ]);
        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'price' => 50000,
            'is_active' => true,
            'is_available' => true,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $table->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'completed_at' => now(),
        ]);
        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
            'status' => 'pending',
        ]);

        $response = $this->post(route('orders.store'), [
            'channel' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'guests_count' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('items');
        $this->assertSame(1, Order::where('table_id', $table->id)->count());
    }

    public function test_preparing_queue_hides_a_duplicate_table_order(): void
    {
        $oldOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => ($table = RestaurantTable::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
            ]))->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'completed_at' => now(),
        ]);
        $newOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $table->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        $product = Product::factory()->create(['restaurant_id' => $this->restaurant->id]);

        foreach ([$oldOrder, $newOrder] as $order) {
            OrderItem::create([
                'restaurant_id' => $this->restaurant->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 50000,
                'line_total' => 50000,
                'status' => 'pending',
            ]);
        }

        $orders = app(\App\Repositories\OrderRepositoryInterface::class)
            ->getOrdersQuery($this->restaurant->id, [
                'status' => 'preparing',
                'date' => today()->toDateString(),
                'branch_id' => $this->branch->id,
            ])->get();

        $this->assertTrue($orders->contains('id', $oldOrder->id));
        $this->assertFalse($orders->contains('id', $newOrder->id));
        $this->assertSame($oldOrder->id, $table->fresh()->activeOrder()->value('id'));
    }

    /**
     * Test 6: Trùng lắp client_item_id không tạo OrderItem trùng
     */
    public function test_client_item_id_prevents_duplicate_cooking_on_sync()
    {
        $this->actingAs($this->owner);
        $this->post(route('branch.switch'), ['branch_id' => $this->branch->id])->assertRedirect();

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'available',
        ]);

        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'price' => 50000,
        ]);

        // Gửi đơn 1 với client_item_id 'uuid-1234'
        $response1 = $this->post(route('orders.store'), [
            'channel' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'client_item_id' => 'uuid-1234'],
            ],
            'guests_count' => 1,
        ]);
        $response1->assertRedirect();

        $order = Order::where('table_id', $table->id)->where('status', 'pending')->first();
        $this->assertNotNull($order);

        // Chuyển món sang preparing để giả lập bếp đang làm
        $item = $order->items->first();
        $item->update(['status' => 'preparing']);

        // Gửi đơn 2 với cùng client_item_id 'uuid-1234' (giả lập POS B đồng bộ trùng lắp)
        $response2 = $this->post(route('orders.store'), [
            'channel' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'client_item_id' => 'uuid-1234'],
            ],
            'guests_count' => 1,
        ]);
        $response2->assertRedirect();

        // Kiểm tra xem số lượng món trong order_items không bị tạo mới hoặc tăng lên
        $items = OrderItem::where('order_id', $order->id)->get();
        $this->assertCount(1, $items);
        $this->assertEquals(1.0, $items->first()->quantity);
    }

    /**
     * Test 7: Đối soát PO lệch giá nhưng vẫn tự động cộng kho thực tế
     */
    public function test_verify_order_adds_to_inventory_despite_discrepancy()
    {
        $this->actingAs($this->owner);

        // Tạo PO
        $po = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => Supplier::factory()->create(['restaurant_id' => $this->restaurant->id])->id,
            'po_number' => 'PO-DISCREPANT-TEST',
            'status' => 'approved',
            'total_amount' => 5000,
            'payment_status' => 'escrow_locked',
            'created_by' => $this->owner->id,
        ]);

        $po->items()->create([
            'ingredient_id' => $this->ingredient->id,
            'quantity_ordered' => 100.0,
            'price_per_unit' => 20,
            'total_cost' => 2000,
        ]);

        // Giả lập giao hàng lệch giá (Listed 20, Invoice 25) -> Sẽ bị frozen
        $response = $this->post(route('suppliers.orders.verify', $po->id), [
            'items' => [
                [
                    'ingredient_id' => $this->ingredient->id,
                    'quantity_received' => 90.0,
                    'invoice_price' => 25.0, // lệch giá & lệch số lượng
                ],
            ],
            'rating' => 5,
        ]);

        $po->refresh();
        $this->assertEquals('frozen', $po->status); // Bị frozen

        // Nhưng kho phải được cộng đúng số thực tế nhận (1000g cũ + 90g nhận = 1090g)
        $inventory = Inventory::where('restaurant_id', $this->restaurant->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->first();
        $this->assertEquals(1090.0, $inventory->quantity_on_hand);
    }

    /**
     * Test 8: Sửa đơn hàng cho phép xóa/giảm món 'pending' mà không cần bypass, nhưng block món 'preparing'
     */
    public function test_order_locking_allows_pending_changes_but_blocks_preparing_without_bypass()
    {
        $this->actingAs($this->owner);
        $this->post(route('branch.switch'), ['branch_id' => $this->branch->id])->assertRedirect();

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'available',
        ]);

        $p1 = Product::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id, 'price' => 50000]);
        $p2 = Product::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id, 'price' => 30000]);

        // Tạo đơn có 2 món
        $response = $this->post(route('orders.store'), [
            'channel' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
                ['product_id' => $p2->id, 'quantity' => 1],
            ],
            'guests_count' => 2,
        ]);

        $order = Order::where('table_id', $table->id)->where('status', 'pending')->first();

        $item1 = $order->items()->where('product_id', $p1->id)->first();
        $item2 = $order->items()->where('product_id', $p2->id)->first();

        // TH1: Cả 2 đều pending -> cho phép xóa $p2 và giảm $p1 từ 2 xuống 1 không cần bypass
        $response1 = $this->patch(route('orders.update', $order->id), [
            'items' => [
                ['id' => $item1->id, 'product_id' => $p1->id, 'quantity' => 1],
            ],
        ]);
        $response1->assertRedirect();
        $response1->assertSessionHasNoErrors();

        // Kiểm tra đã cập nhật
        $order->refresh();
        $this->assertCount(1, $order->items()->where('status', '!=', 'cancelled')->get());
        $this->assertEquals(1, $order->items()->where('product_id', $p1->id)->first()->quantity);

        // TH2: Chuyển $p1 sang 'preparing' -> cố gắng giảm số lượng xuống 0.5 phải báo lỗi
        $item1->refresh();
        $item1->update(['status' => 'preparing']);

        $response2 = $this->patch(route('orders.update', $order->id), [
            'items' => [
                ['id' => $item1->id, 'product_id' => $p1->id, 'quantity' => 0.5],
            ],
        ]);
        $response2->assertSessionHasErrors(['items']);
    }

    /**
     * Test 9: Phê duyệt nhanh dùng định dạng user_id:pin
     */
    public function test_pin_bypass_direct_user_id_matching()
    {
        $this->actingAs($this->owner);

        // Tạo 1 manager có pin code băm bcrypt
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'pin_code' => bcrypt('7777'),
        ]);
        $manager->assignRole($managerRole);

        // Thử validate bypass bằng user_id:pin
        $matched = User::validateManagerBypass("{$manager->id}:7777", $this->restaurant->id);
        $this->assertNotNull($matched);
        $this->assertEquals($manager->id, $matched->id);
    }
}
