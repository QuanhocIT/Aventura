<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Notifications\ApprovalDecisionNotification;
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hồi quy cho luồng hoàn tiền đơn hàng.
 *
 * Bug đã vá 2026-07-30: AuditLog::create() trong OrdersController::refund() dùng
 * cột auditable_type/auditable_id (bảng dùng subject_type/subject_id) và thiếu cột
 * 'event' NOT NULL → INSERT ném lỗi SQL bên trong DB::transaction, làm ROLLBACK
 * toàn bộ việc hoàn tiền. Người dùng thấy thông báo "thành công" nhưng đơn không
 * hề đổi trạng thái.
 */
class OrderRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'REFUND-A',
        ]);
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
    }

    private function makePaidOrder(): Order
    {
        return Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->owner->id,
            'order_number' => 'ORD-REFUND-'.uniqid(),
            'channel' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 300000.0,
            'discount_amount' => 0.0,
            'total_amount' => 300000.0,
        ]);
    }

    public function test_full_refund_persists_and_writes_audit_log(): void
    {
        $order = $this->makePaidOrder();

        $this->actingAs($this->owner)
            ->post("/orders/{$order->id}/refund", [
                'reason' => 'Khách phản ánh món ăn không đúng yêu cầu',
                'refund_amount' => 300000,
                'refund_type' => 'full',
            ])
            ->assertRedirect();

        $order->refresh();

        // Điểm mấu chốt: transaction phải COMMIT, không bị rollback ngầm
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
        $this->assertEquals(300000, (float) $order->refund_amount);
        $this->assertNotNull($order->refunded_at);
        $this->assertSame($this->owner->id, (int) $order->refunded_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'refund_processed',
            'event' => 'updated',
            'subject_id' => $order->id,
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_partial_refund_keeps_order_active(): void
    {
        $order = $this->makePaidOrder();

        $this->actingAs($this->owner)
            ->post("/orders/{$order->id}/refund", [
                'reason' => 'Hoàn một phần vì thiếu một món trong đơn',
                'refund_amount' => 100000,
                'refund_type' => 'partial',
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame('partial_refund', $order->payment_status);
        $this->assertSame('completed', $order->status, 'Hoàn một phần không được huỷ đơn');
        $this->assertEquals(100000, (float) $order->refund_amount);
    }

    public function test_orders_page_has_a_refund_filter_and_refund_details(): void
    {
        $order = $this->makePaidOrder();

        $this->actingAs($this->owner)
            ->post("/orders/{$order->id}/refund", [
                'reason' => 'Khách yêu cầu hoàn tiền do món ăn không đúng yêu cầu',
                'refund_amount' => 300000,
                'refund_type' => 'full',
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->owner)->get(route('orders.index', [
            'status' => 'refunded',
            'date' => today()->toDateString(),
        ]));

        $response->assertOk();
        $props = $response->original->getData()['page']['props'];
        $orders = collect($props['orders']);

        $this->assertSame(1, $props['summary']['refunded']);
        $this->assertEquals(300000, (float) $props['summary']['refunded_amount']);
        $this->assertSame($order->id, (int) $orders->first()['id']);
        $this->assertEquals(300000, (float) $orders->first()['refund_amount']);
        $this->assertSame($this->owner->name, $orders->first()['refunded_by_name']);
    }

    public function test_cannot_refund_unpaid_order(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => 'ORD-UNPAID-REFUND',
            'channel' => 'dine_in',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 100000.0,
            'total_amount' => 100000.0,
        ]);

        $this->actingAs($this->owner)
            ->post("/orders/{$order->id}/refund", [
                'reason' => 'Thử hoàn tiền đơn chưa thanh toán',
                'refund_amount' => 50000,
                'refund_type' => 'partial',
            ])
            ->assertStatus(422);

        $this->assertSame('unpaid', $order->refresh()->payment_status);
    }

    public function test_refund_amount_cannot_exceed_order_total(): void
    {
        $order = $this->makePaidOrder();

        $this->actingAs($this->owner)
            ->post("/orders/{$order->id}/refund", [
                'reason' => 'Cố tình hoàn quá số tiền của đơn hàng',
                'refund_amount' => 500000,
                'refund_type' => 'full',
            ])
            ->assertSessionHasErrors(['refund_amount']);

        $this->assertSame('paid', $order->refresh()->payment_status);
    }

    public function test_cashier_refund_creates_approval_request_without_bypass_code(): void
    {
        Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        $order = $this->makePaidOrder();

        $this->actingAs($cashier)
            ->post("/orders/{$order->id}/refund", [
                'reason' => 'Khách yêu cầu hoàn tiền do món ăn bị giao sai',
                'refund_amount' => 50000,
                'refund_type' => 'partial',
            ])
            ->assertRedirect();

        $this->assertSame('paid', $order->refresh()->payment_status);

        $approval = ApprovalRequest::where('restaurant_id', $this->restaurant->id)
            ->where('operation_type', 'order_refund')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('pending', $approval->status);
        $this->assertSame($cashier->id, $approval->requester_id);
        $this->assertSame(50000.0, (float) data_get($approval->operation_data, 'refund_amount'));
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->owner->id,
            'type' => ApprovalRequestedNotification::class,
        ]);
    }

    public function test_owner_approval_processes_refund_and_notifies_requester(): void
    {
        $cashierRole = Role::where('name', 'cashier')->firstOrFail();
        $cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $cashier->assignRole($cashierRole);
        $order = $this->makePaidOrder();

        $this->actingAs($cashier)->post("/orders/{$order->id}/refund", [
            'reason' => 'Khách yêu cầu hoàn tiền do món ăn bị giao sai',
            'refund_amount' => 50000,
            'refund_type' => 'partial',
        ])->assertRedirect();

        $approval = ApprovalRequest::where('operation_type', 'order_refund')->latest('id')->firstOrFail();

        $this->actingAs($this->owner)
            ->patch("/approvals/{$approval->id}/approve")
            ->assertRedirect();

        $this->assertSame('partial_refund', $order->refresh()->payment_status);
        $this->assertEquals(50000, (float) $order->refund_amount);
        $this->assertSame('approved', $approval->refresh()->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $cashier->id,
            'type' => ApprovalDecisionNotification::class,
        ]);
    }

    public function test_owner_rejection_does_not_refund_and_notifies_requester(): void
    {
        $cashierRole = Role::where('name', 'cashier')->firstOrFail();
        $cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $cashier->assignRole($cashierRole);
        $order = $this->makePaidOrder();

        $this->actingAs($cashier)->post("/orders/{$order->id}/refund", [
            'reason' => 'Khách yêu cầu hoàn tiền do món ăn bị giao sai',
            'refund_amount' => 50000,
            'refund_type' => 'partial',
        ])->assertRedirect();

        $approval = ApprovalRequest::where('operation_type', 'order_refund')->latest('id')->firstOrFail();

        $this->actingAs($this->owner)
            ->patch("/approvals/{$approval->id}/reject", [
                'rejection_reason' => 'Đã kiểm tra và không đủ điều kiện hoàn tiền.',
            ])
            ->assertRedirect();

        $this->assertSame('paid', $order->refresh()->payment_status);
        $this->assertNull($order->refund_amount);
        $this->assertSame('rejected', $approval->refresh()->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $cashier->id,
            'type' => ApprovalDecisionNotification::class,
        ]);
    }

    public function test_cannot_refund_order_of_another_restaurant(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $foreignOrder = Order::create([
            'restaurant_id' => $otherRestaurant->id,
            'order_number' => 'ORD-FOREIGN-REFUND',
            'channel' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 200000.0,
            'total_amount' => 200000.0,
        ]);

        $status = $this->actingAs($this->owner)
            ->post("/orders/{$foreignOrder->id}/refund", [
                'reason' => 'Cố hoàn tiền đơn của nhà hàng khác',
                'refund_amount' => 100000,
                'refund_type' => 'partial',
            ])
            ->getStatusCode();

        $this->assertContains($status, [403, 404]);
        $this->assertSame('paid', $foreignOrder->refresh()->payment_status);
    }
}
