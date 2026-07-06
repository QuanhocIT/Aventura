<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\CustomerFeedback;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\TemporaryOrder;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkShift;
use App\Jobs\VerifyTemporaryOrderDelayJob;
use App\Events\Customer\TemporaryOrderCreated;
use App\Events\Customer\TemporaryOrderUpdated;
use App\Events\Customer\TemporaryOrderEscalated;
use App\Events\Customer\StaffCalled;
use App\Events\Customer\PaymentRequested;
use App\Events\Customer\FeedbackSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class QROrderingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $manager;
    protected User $staff;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected Area $area;
    protected RestaurantTable $table;
    protected ProductCategory $category;
    protected Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Spatie roles
        $ownerRole   = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $this->staffRole   = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        // Create users
        $this->owner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->branch     = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id
        ]);

        $this->owner->forceFill([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id
        ])->save();

        // Create manager
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'active',
            'email_verified_at' => now(),
        ]);
        $this->manager->assignRole($managerRole);

        // Create staff
        $this->staff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'active',
            'email_verified_at' => now(),
        ]);
        $this->staff->assignRole($this->staffRole);

        // Area & Table setup
        $this->area = Area::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'name'          => 'Khu VIP 1',
            'code'          => 'VIP1'
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'area_id'       => $this->area->id,
            'name'          => 'Bàn 10',
            'qr_code'       => 'QR-VIP1-B10',
            'qr_token'      => 'token-b10-secret',
            'capacity'      => 4,
            'status'        => 'available',
        ]);

        // Category setup
        $this->category = ProductCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'name'          => 'Đặc Sản',
            'slug'          => 'dac-san',
            'status'        => 'active',
            'display_order' => 1
        ]);
    }

    /**
     * Test Pha 1: Menu loading & stock availability calculation.
     */
    public function test_menu_loading_and_inventory_calculation(): void
    {
        // Setup Unit
        $unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Gram',
            'symbol'        => 'g',
            'type'          => 'mass'
        ]);

        // Setup Ingredients
        $ingShrimp = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id'       => $unit->id,
            'name'          => 'Tôm Sú',
            'sku'           => 'ING-SHRIMP',
            'status'        => 'active'
        ]);

        $ingBeef = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id'       => $unit->id,
            'name'          => 'Bò Mỹ',
            'sku'           => 'ING-BEEF',
            'status'        => 'active'
        ]);

        // Setup Products
        $prodSoup = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'category_id'   => $this->category->id,
            'name'          => 'Lẩu Tôm Sú',
            'code'          => 'PROD-SOUP',
            'price'         => 350000,
            'is_active'     => true,
            'is_available'  => true,
            'track_inventory' => true,
        ]);

        $prodBeef = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'category_id'   => $this->category->id,
            'name'          => 'Bò Cuộn',
            'code'          => 'PROD-BEEF',
            'price'         => 180000,
            'is_active'     => true,
            'is_available'  => true,
            'track_inventory' => true,
        ]);

        // Recipes (BOM)
        ProductRecipe::create([
            'restaurant_id' => $this->restaurant->id,
            'product_id'    => $prodSoup->id,
            'ingredient_id' => $ingShrimp->id,
            'unit_id'       => $unit->id,
            'quantity'      => 200.0,
            'waste_rate'    => 10.0, // 200 * 1.1 = 220g required
        ]);

        ProductRecipe::create([
            'restaurant_id' => $this->restaurant->id,
            'product_id'    => $prodBeef->id,
            'ingredient_id' => $ingBeef->id,
            'unit_id'       => $unit->id,
            'quantity'      => 100.0,
            'waste_rate'    => 0.0, // 100g required
        ]);

        // Inventory
        // Shrimp: 100g (insufficient for 1 unit of prodSoup which requires 220g)
        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'ingredient_id' => $ingShrimp->id,
            'quantity_on_hand' => 100.0,
            'theoretical_quantity' => 100.0,
        ]);

        // Beef: 500g (sufficient for 1 unit of prodBeef which requires 100g)
        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'ingredient_id' => $ingBeef->id,
            'quantity_on_hand' => 500.0,
            'theoretical_quantity' => 500.0,
        ]);

        // Setup shift & schedules for resolveCurrentShiftStaff
        $shift = WorkShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'name'          => 'Ca Sáng',
            'code'          => 'CS1',
            'start_time'    => '00:00:00',
            'end_time'      => '23:59:59',
            'status'        => 'active'
        ]);

        $employee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_code' => 'EMP-WAIT-1',
            'full_name'     => 'Nhân Viên Hùng',
            'email'         => 'waiter1@aventura.com',
            'phone'         => '0988112233',
            'citizen_id_number' => '123456789012',
            'address'       => 'Hà Nội',
            'hire_date'     => today(),
            'base_salary'   => 5000000,
            'job_title'     => 'Phục vụ',
            'employment_type' => 'full_time',
            'status'        => 'active',
            'role_id'       => $this->staffRole->id,
            'user_id'       => $this->staff->id
        ]);

        ScheduleAssignment::create([
            'restaurant_id'  => $this->restaurant->id,
            'branch_id'      => $this->branch->id,
            'employee_id'    => $employee->id,
            'shift_id'       => $shift->id,
            'scheduled_date' => today()->toDateString(),
        ]);

        $response = $this->get(route('customer.qr-order.show', [
            'restaurant' => $this->restaurant->id,
            'token'      => $this->table->qr_token
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('customers/QROrder')
            ->has('products', 2)
            ->where('products.0.name', 'Lẩu Tôm Sú')
            ->where('products.0.in_stock', false)
            ->where('products.1.name', 'Bò Cuộn')
            ->where('products.1.in_stock', true)
            ->has('staffList', 1)
            ->where('staffList.0.name', $this->staff->name)
        );
    }

    /**
     * Test Pha 2: Temporary order submission.
     */
    public function test_temporary_order_submission_success(): void
    {
        Queue::fake();
        Event::fake();

        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'category_id'   => $this->category->id,
            'name'          => 'Bò Cuộn',
            'code'          => 'PROD-BEEF',
            'price'         => 180000,
            'is_active'     => true,
            'is_available'  => true,
            'track_inventory' => false, // bypass inventory check
        ]);

        $payload = [
            'customer_name'  => 'Anh Quân',
            'customer_phone' => '0912345678',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity'   => 2,
                    'notes'      => 'Ít cay'
                ]
            ]
        ];

        $response = $this->postJson(route('customer.qr-order.submit', [
            'restaurant' => $this->restaurant->id,
            'token'      => $this->table->qr_token
        ]), $payload);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('temporary_order.status', 'waiting_verification');

        $this->assertDatabaseHas('temporary_orders', [
            'restaurant_id' => $this->restaurant->id,
            'table_id'      => $this->table->id,
            'customer_name' => 'Anh Quân',
            'status'        => 'waiting_verification',
            'total_amount'  => 360000.0,
        ]);

        // Verify delay job dispatched
        Queue::assertPushed(VerifyTemporaryOrderDelayJob::class, function ($job) {
            return $job->delay instanceof \DateTimeInterface;
        });

        // Verify WebSocket event broadcasted
        Event::assertDispatched(TemporaryOrderCreated::class);
    }

    /**
     * Test Pha 2: Out of stock validation during temporary order submit.
     */
    public function test_temporary_order_submission_fails_out_of_stock(): void
    {
        // Setup Unit
        $unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Gram',
            'symbol'        => 'g',
            'type'          => 'mass'
        ]);

        $ingBeef = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id'       => $unit->id,
            'name'          => 'Bò Mỹ',
            'sku'           => 'ING-BEEF',
            'status'        => 'active'
        ]);

        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'category_id'   => $this->category->id,
            'name'          => 'Bò Cuộn',
            'code'          => 'PROD-BEEF',
            'price'         => 180000,
            'is_active'     => true,
            'is_available'  => true,
            'track_inventory' => true,
        ]);

        ProductRecipe::create([
            'restaurant_id' => $this->restaurant->id,
            'product_id'    => $product->id,
            'ingredient_id' => $ingBeef->id,
            'unit_id'       => $unit->id,
            'quantity'      => 100.0,
            'waste_rate'    => 0.0,
        ]);

        // Beef stock: 50g (insufficient since ordering 1 unit requires 100g)
        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'ingredient_id' => $ingBeef->id,
            'quantity_on_hand' => 50.0,
            'theoretical_quantity' => 50.0,
        ]);

        $payload = [
            'customer_name'  => 'Anh Quân',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity'   => 1,
                ]
            ]
        ];

        $response = $this->postJson(route('customer.qr-order.submit', [
            'restaurant' => $this->restaurant->id,
            'token'      => $this->table->qr_token
        ]), $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => "Món 'Bò Cuộn' đã hết nguyên liệu chế biến."
        ]);
    }

    /**
     * Test Pha 3: Queue delay job escalation.
     */
    public function test_delayed_job_escalation(): void
    {
        Event::fake();

        $tempOrder = TemporaryOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'table_id'      => $this->table->id,
            'customer_name' => 'Anh Quân',
            'status'        => 'waiting_verification',
            'cart_data'     => [],
            'total_amount'  => 0.0
        ]);

        $job = new VerifyTemporaryOrderDelayJob($tempOrder->id);
        $job->handle();

        $tempOrder->refresh();
        $this->assertEquals('escalated', $tempOrder->status);

        // Assert audit logs table records the escalation
        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $this->restaurant->id,
            'action'        => 'temporary_order_escalated',
            'subject_id'    => $tempOrder->id,
            'event'         => 'updated'
        ]);

        Event::assertDispatched(TemporaryOrderEscalated::class);
    }

    /**
     * Test Pha 4: Waiter/Cashier confirms the temporary order.
     */
    public function test_waiter_confirms_temporary_order(): void
    {
        $this->withoutExceptionHandling();
        Event::fake();

        // Mock AI suggestion API to port 8003
        Http::fake([
            '*/api/analytics/upsell-suggestion' => Http::response([
                'suggestion' => 'Khách gọi Bò Cuộn, khuyên dùng kèm Coca Lạnh để tăng hương vị!',
                'recommended_item' => 'Coca Lạnh',
                'confidence' => 0.92
            ], 200)
        ]);

        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'category_id'   => $this->category->id,
            'name'          => 'Bò Cuộn',
            'code'          => 'PROD-BEEF',
            'price'         => 180000,
            'is_active'     => true,
            'is_available'  => true,
            'track_inventory' => false,
        ]);

        $tempOrder = TemporaryOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'table_id'      => $this->table->id,
            'customer_name' => 'Anh Quân',
            'status'        => 'waiting_verification',
            'cart_data'     => [
                [
                    'product_id' => $product->id,
                    'name'       => $product->name,
                    'sku'        => $product->code,
                    'quantity'   => 2,
                    'unit_price' => 180000,
                    'line_total' => 360000
                ]
            ],
            'total_amount'  => 360000
        ]);

        $response = $this->actingAs($this->staff)
            ->postJson(route('temporary-orders.confirm', $tempOrder->id));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['success', 'message', 'order_id', 'upsell']);
        
        $tempOrder->refresh();
        $this->assertEquals('confirmed', $tempOrder->status);
        $this->assertNotNull($tempOrder->order_id);

        // Verify official order was created with status pending, channel qr, and links table
        $order = Order::find($tempOrder->order_id);
        $this->assertNotNull($order);
        $this->assertEquals('qr', $order->channel);
        $this->assertEquals($this->table->id, $order->table_id);
        $this->assertEquals(360000.0, $order->total_amount);

        // Verify table status is occupied
        $this->table->refresh();
        $this->assertEquals('occupied', $this->table->status);

        // Verify WebSocket event broadcasted
        Event::assertDispatched(TemporaryOrderUpdated::class);
    }

    /**
     * Test Pha 4: Waiter cancels the temporary order.
     */
    public function test_waiter_cancels_temporary_order(): void
    {
        Event::fake();

        $tempOrder = TemporaryOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'table_id'      => $this->table->id,
            'customer_name' => 'Anh Quân',
            'status'        => 'waiting_verification',
            'cart_data'     => [],
            'total_amount'  => 0.0
        ]);

        $response = $this->actingAs($this->staff)
            ->postJson(route('temporary-orders.cancel', $tempOrder->id), [
                'reason' => 'Đơn spam phá hoại từ xa'
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $tempOrder->refresh();
        $this->assertEquals('cancelled', $tempOrder->status);
        $this->assertEquals($this->staff->id, $tempOrder->cancelled_by);
        $this->assertEquals('Đơn spam phá hoại từ xa', $tempOrder->cancellation_reason);

        // Assert audit logs contains cancel details
        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $this->restaurant->id,
            'action'        => 'temporary_order_cancelled',
            'subject_id'    => $tempOrder->id,
        ]);

        Event::assertDispatched(TemporaryOrderUpdated::class);
    }

    /**
     * Test Pha 5: Manager reviews rejected QR order logs.
     */
    public function test_manager_reviews_rejected_logs(): void
    {
        // Create a cancelled temporary order
        $tempOrder = TemporaryOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'table_id'      => $this->table->id,
            'customer_name' => 'Khách Hủy',
            'status'        => 'cancelled',
            'cancelled_by'  => $this->staff->id,
            'cancellation_reason' => 'Không đúng món',
            'cart_data'     => [
                [
                    'name' => 'Món Ăn Hủy',
                    'quantity' => 1
                ]
            ],
            'total_amount'  => 100000,
            'updated_at'    => now()
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson(route('temporary-orders.rejected-logs'));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(1, 'rejected_logs');
        $response->assertJsonFragment([
            'id' => $tempOrder->id,
            'cancellation_reason' => 'Không đúng món',
            'cancelled_by_name' => $this->staff->name,
        ]);
    }

    /**
     * Test Pha 6: Guest customer interactions (Call Staff, Payment Request, Feedback Submission).
     */
    public function test_guest_interactions_flow(): void
    {
        $this->withoutExceptionHandling();
        Event::fake();

        // 1. Call staff
        $response = $this->postJson(route('customer.qr-order.call-staff', $this->restaurant->id), [
            'table_id' => $this->table->id,
            'message'  => 'Cần thêm bát đũa'
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        Event::assertDispatched(StaffCalled::class);

        // 2. Request payment (fails if no unpaid order)
        $response = $this->postJson(route('customer.qr-order.payment-request', $this->restaurant->id), [
            'table_id' => $this->table->id,
        ]);
        $response->assertStatus(422);

        // Create unpaid active order
        $order = Order::create([
            'restaurant_id'  => $this->restaurant->id,
            'branch_id'      => $this->branch->id,
            'table_id'       => $this->table->id,
            'created_by'     => $this->owner->id,
            'order_number'   => 'ORD-MOCK',
            'channel'        => 'qr',
            'status'         => 'confirmed',
            'payment_status' => 'unpaid',
            'total_amount'   => 100000
        ]);

        $response = $this->postJson(route('customer.qr-order.payment-request', $this->restaurant->id), [
            'table_id' => $this->table->id,
        ]);
        $response->assertOk();
        $response->assertJsonPath('success', true);
        Event::assertDispatched(PaymentRequested::class);

        // 3. Feedback submit with ratings
        $feedbackPayload = [
            'table_id'     => $this->table->id,
            'order_id'     => $order->id,
            'rating'       => 5,
            'content'      => 'Món ăn ngon và phục vụ tốt!',
            'is_anonymous' => false,
            'submitted_by_name' => 'Anh Quân',
            'submitted_by_phone' => '0912345678',
            'items_rating' => [
                [
                    'product_id' => 99,
                    'rating'     => 5,
                    'comment'    => 'Quá ngon!'
                ]
            ],
            'staff_rating' => [
                [
                    'employee_id' => 88,
                    'rating'      => 4,
                    'comment'     => 'Nhanh nhẹn'
                ]
            ]
        ];

        $response = $this->postJson(route('customer.qr-order.feedback', $this->restaurant->id), $feedbackPayload);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('customer_feedback', [
            'restaurant_id' => $this->restaurant->id,
            'order_id'      => $order->id,
            'rating'        => 5,
            'submitted_by_name' => 'Anh Quân',
            'submitted_by_phone' => '0912345678',
        ]);

        // Verify JSON fields casts
        $feedback = CustomerFeedback::where('order_id', $order->id)->first();
        $this->assertNotNull($feedback->items_rating);
        $this->assertEquals(99, $feedback->items_rating[0]['product_id']);
        $this->assertEquals(88, $feedback->staff_rating[0]['employee_id']);

        Event::assertDispatched(FeedbackSubmitted::class);
    }
}
