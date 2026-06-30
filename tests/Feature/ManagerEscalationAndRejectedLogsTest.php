<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Area;
use App\Models\AuditLog;
use App\Jobs\CheckQrOrderTimeout;
use App\Events\QrOrderTimeoutEscalated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagerEscalationAndRejectedLogsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $cashier;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private RestaurantTable $table;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
        ]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Main Branch',
        ]);

        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->manager->assignRole($managerRole);

        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->cashier->assignRole($cashierRole);

        $area = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Khu A',
            'code' => 'khu-a',
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $area->id,
            'name' => 'Bàn 1',
            'capacity' => 4,
            'status' => 'available',
            'qr_token' => 'token-123',
        ]);

        $this->product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'code' => 'P-001',
            'name' => 'Beef Steak',
            'slug' => 'beef-steak',
            'price' => 100000,
            'is_active' => true,
            'is_available' => true,
            'track_inventory' => false,
        ]);
    }

    public function test_submitting_qr_order_schedules_timeout_checker_job()
    {
        Queue::fake();

        $response = $this->postJson(route('qr.order.submit', [
            'restaurant' => $this->restaurant->id,
            'table_token' => $this->table->qr_token,
        ]), [
            'note' => 'Please hurry',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'notes' => 'Medium rare',
                ]
            ]
        ]);

        $response->assertStatus(200);

        // Verify CheckQrOrderTimeout is pushed with 2 minutes delay
        Queue::assertPushed(CheckQrOrderTimeout::class, function ($job) {
            return $job->order->channel === 'qr' && $job->delay instanceof \DateTimeInterface;
        });
    }

    public function test_job_escalates_timeout_event_if_order_remains_pending()
    {
        Event::fake();

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-123',
            'channel' => 'qr',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total_amount' => 100000,
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
        ]);

        $job = new CheckQrOrderTimeout($order);
        $job->handle();

        // Verify Escalation Event was fired
        Event::assertDispatched(QrOrderTimeoutEscalated::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

    public function test_job_does_not_escalate_if_order_is_no_longer_pending()
    {
        Event::fake();

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-456',
            'channel' => 'qr',
            'status' => 'confirmed', // Already confirmed!
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total_amount' => 100000,
        ]);

        $job = new CheckQrOrderTimeout($order);
        $job->handle();

        // Verify Escalation Event was NOT fired
        Event::assertNotDispatched(QrOrderTimeoutEscalated::class);
    }

    public function test_spam_cancellation_saves_complete_metadata_in_audit_log_and_manager_retrieves_them()
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-SPAM',
            'channel' => 'qr',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 200000,
            'discount_amount' => 0,
            'total_amount' => 200000,
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 100000,
            'line_total' => 200000,
        ]);

        // Cancel order using staff account
        $response = $this->actingAs($this->cashier)
            ->postJson(route('orders.cancel-spam', $order->id));
        $response->assertStatus(200);

        // Verify audit log has items and area details
        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $this->restaurant->id,
            'action' => 'spam_order_cancelled',
            'event' => 'deleted',
        ]);

        $log = AuditLog::where('action', 'spam_order_cancelled')->first();
        $this->assertNotNull($log->new_values);
        $this->assertEquals('Khu A', $log->new_values['area_name']);
        $this->assertCount(1, $log->new_values['items']);
        $this->assertEquals('Beef Steak', $log->new_values['items'][0]['product_name']);

        // Check if Manager receives logs in the orders list
        $response = $this->actingAs($this->manager)
            ->get(route('orders.index', ['status' => 'cancelled_spam']));
        $response->assertStatus(200);

        $response->assertInertia(function ($page) {
            $page->has('cancelledLogs');
            $logs = $page->toArray()['props']['cancelledLogs'];
            $this->assertCount(1, $logs);
            $this->assertEquals('ORD-SPAM', $logs[0]['order_number']);
            $this->assertEquals('Bàn 1', $logs[0]['table_name']);
            $this->assertEquals('Khu A', $logs[0]['area_name']);
            $this->assertCount(1, $logs[0]['items']);
            $this->assertEquals('Beef Steak', $logs[0]['items'][0]['product_name']);
        });
    }
}
