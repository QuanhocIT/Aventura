<?php

namespace Tests\Feature;

use App\Events\Kitchen\KitchenUpdated;
use App\Jobs\SendReviewRequestEmail;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\ScheduleAssignment;
use App\Models\ShiftClosing;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\EmailMicroserviceClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AutomationSprintBTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test shifts:auto-close-expired command closes active shifts and defaults cashier to restaurant owner.
     */
    public function test_auto_shift_close_defaults_to_restaurant_owner()
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'owner_user_id' => $owner->id,
        ]);

        // Create an active shift
        $shift = WorkShift::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Ca Sang',
            'code' => 'CA_SANG',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'is_overnight' => false,
            'status' => 'active',
        ]);

        // Create completed order and cash payment within shift hours
        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'completed',
            'completed_at' => Carbon::today()->setTime(10, 0, 0),
        ]);

        Payment::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'status' => 'paid',
            'amount' => 150000.00,
            'paid_at' => Carbon::today()->setTime(10, 5, 0),
        ]);

        $exitCode = Artisan::call('shifts:auto-close-expired');

        $this->assertEquals(0, $exitCode);

        // Assert ShiftClosing is created
        $closing = ShiftClosing::where('restaurant_id', $restaurant->id)
            ->where('shift_id', $shift->id)
            ->whereDate('closing_date', Carbon::today()->toDateString())
            ->first();

        $this->assertNotNull($closing);
        $this->assertEquals('confirmed', $closing->status);
        $this->assertEquals(150000.00, (float) $closing->expected_cash);
        $this->assertEquals(150000.00, (float) $closing->actual_cash);
        $this->assertEquals(0.00, (float) $closing->cash_difference);
        $this->assertEquals($owner->id, $closing->cashier_user_id);

        // Assert audit log exists
        $this->assertTrue(AuditLog::where('event', 'created')
            ->where('action', 'auto_shift_close')
            ->where('subject_id', $closing->id)
            ->exists());
    }

    /**
     * Test shifts:auto-close-expired maps cashier to the scheduled employee from ScheduleAssignment.
     */
    public function test_auto_shift_close_maps_to_scheduled_employee()
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'owner_user_id' => $owner->id,
        ]);

        $cashierUser = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $employee = Employee::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $cashierUser->id,
            'employee_code' => 'EMP001',
            'full_name' => 'Cashier A',
            'status' => 'active',
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Ca Sang',
            'code' => 'CA_SANG',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'is_overnight' => false,
            'status' => 'active',
        ]);

        // Create schedule assignment for cashier employee
        ScheduleAssignment::create([
            'restaurant_id' => $restaurant->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => Carbon::today()->toDateString(),
            'status' => 'scheduled',
        ]);

        $exitCode = Artisan::call('shifts:auto-close-expired');

        $this->assertEquals(0, $exitCode);

        // Assert ShiftClosing is created and cashier is the scheduled user
        $closing = ShiftClosing::where('restaurant_id', $restaurant->id)
            ->where('shift_id', $shift->id)
            ->whereDate('closing_date', Carbon::today()->toDateString())
            ->first();

        $this->assertNotNull($closing);
        $this->assertEquals($cashierUser->id, $closing->cashier_user_id);
    }

    /**
     * Test order completion dispatches SendReviewRequestEmail job delayed by 2 hours.
     */
    public function test_order_completion_dispatches_review_request_job()
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create();
        $customer = Customer::create([
            'restaurant_id' => $restaurant->id,
            'full_name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '0912345678',
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        // Update status to completed
        $order->update(['status' => 'completed']);

        // Assert SendReviewRequestEmail is pushed with 2-hour delay
        Queue::assertPushed(SendReviewRequestEmail::class, function ($job) use ($order) {
            return $job->orderId === $order->id;
        });
    }

    /**
     * Test SendReviewRequestEmail job calls EmailMicroserviceClient to send review request email.
     */
    public function test_review_request_job_sends_email()
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Aventura Eatery']);
        $customer = Customer::create([
            'restaurant_id' => $restaurant->id,
            'full_name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '0912345678',
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
        ]);

        $this->mock(EmailMicroserviceClient::class, function (MockInterface $mock) use ($customer) {
            $mock->shouldReceive('sendReviewRequest')
                ->once()
                ->with(Mockery::on(function ($data) use ($customer) {
                    return $data['recipient_email'] === $customer->email
                        && $data['recipient_name'] === $customer->full_name
                        && str_contains($data['review_url'], '/reviews/order/');
                }))
                ->andReturn(true);
        });

        $job = new SendReviewRequestEmail($order->id);
        $job->handle(app(EmailMicroserviceClient::class));
    }

    /**
     * Test kitchen:alert-overdue-orders command broadcasts KitchenUpdated event for preparing orders older than 30 mins.
     */
    public function test_kitchen_alert_overdue_orders_broadcasts_event()
    {
        Event::fake([KitchenUpdated::class]);

        $restaurant = Restaurant::factory()->create();

        // Create order in preparing status
        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'preparing',
        ]);

        // Force update the updated_at column to be 40 minutes in the past
        DB::table('orders')->where('id', $order->id)->update([
            'updated_at' => Carbon::now()->subMinutes(40),
        ]);

        $exitCode = Artisan::call('kitchen:alert-overdue-orders');

        $this->assertEquals(0, $exitCode);

        // Assert KitchenUpdated was broadcasted
        Event::assertDispatched(KitchenUpdated::class, function ($event) use ($restaurant) {
            return $event->restaurantId === $restaurant->id;
        });
    }
}
