<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\Employee;
use App\Models\Order;
use App\Models\AuditLog;
use App\Models\ViolationReport;
use App\Events\FraudAlertTriggered;
use App\Jobs\SendFraudAlertEmailJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAdvisorTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $manager;
    protected User $cashier;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected Employee $cashierEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Spatie roles
        $ownerRole   = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        // Owner setup
        $this->owner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id, 'status' => 'active']);
        $this->branch     = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id
        ]);

        $this->owner->forceFill([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id
        ])->save();

        // Manager setup
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'active',
            'email_verified_at' => now(),
        ]);
        $this->manager->assignRole($managerRole);

        // Cashier setup
        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->branch->id,
            'name'          => 'Nguyễn Văn A',
            'status'        => 'active',
            'email_verified_at' => now(),
        ]);
        $this->cashier->assignRole($cashierRole);

        // Employee setup for Cashier to satisfy observers
        $this->cashierEmployee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->cashier->id,
            'employee_code' => 'EMP-CASH-TEST',
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
            'role_id' => $cashierRole->id,
        ]);
    }

    /**
     * Test `/ai-advisor` access authorization.
     */
    public function test_advisor_index_accessible_by_owner_and_manager(): void
    {
        // 1. Unauthenticated/Guest
        $response = $this->get(route('ai-advisor.index'));
        $response->assertRedirect(route('login'));

        // 2. Authorized (owner)
        $response = $this->actingAs($this->owner)->get(route('ai-advisor.index'));
        $response->assertOk();

        // 3. Authorized (manager)
        $response = $this->actingAs($this->manager)->get(route('ai-advisor.index'));
        $response->assertOk();

        // 4. Unauthorized (cashier)
        $response = $this->actingAs($this->cashier)->get(route('ai-advisor.index'));
        $response->assertForbidden();
    }

    /**
     * Test `api/chatbot/advisor-message` access authorization and response.
     */
    public function test_advisor_message_accessible_by_owner_and_manager(): void
    {
        // Fake Python Microservice request
        Http::fake([
            '*/advisor-chat' => Http::response([
                'found' => true,
                'answer' => 'Chào bạn, tôi là trợ lý AI.',
                'suggestions' => ['Doanh thu hôm nay đạt bao nhiêu?'],
                'category' => 'finance',
                'confidence' => 1.0,
            ], 200)
        ]);

        $payload = [
            'message' => 'Doanh thu hôm nay đạt bao nhiêu?',
            'session_id' => 'test-session-123',
        ];

        // 1. Unauthenticated
        $response = $this->postJson(route('chatbot.advisor-message'), $payload);
        $response->assertUnauthorized();

        // 2. Authorized (owner)
        $response = $this->actingAs($this->owner)->postJson(route('chatbot.advisor-message'), $payload);
        $response->assertOk();
        $response->assertJson([
            'found' => true,
            'answer' => 'Chào bạn, tôi là trợ lý AI.',
            'suggestions' => ['Doanh thu hôm nay đạt bao nhiêu?'],
        ]);

        // 3. Authorized (manager)
        $response = $this->actingAs($this->manager)->postJson(route('chatbot.advisor-message'), $payload);
        $response->assertOk();

        // 4. Unauthorized (cashier)
        $response = $this->actingAs($this->cashier)->postJson(route('chatbot.advisor-message'), $payload);
        $response->assertForbidden();
    }

    /**
     * Test `api/chatbot/advisor-message` validation.
     */
    public function test_advisor_message_validation(): void
    {
        // 1. Message is required
        $response = $this->actingAs($this->owner)->postJson(route('chatbot.advisor-message'), [
            'message' => '',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);

        // 2. Message length exceeds maximum
        $response = $this->actingAs($this->owner)->postJson(route('chatbot.advisor-message'), [
            'message' => str_repeat('a', 501),
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    /**
     * Test AuditLogObserver triggers anomaly on repeated price modification.
     */
    public function test_audit_log_observer_detects_repeated_price_modification(): void
    {
        Event::fake([FraudAlertTriggered::class]);
        Queue::fake();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        // Create 2 previous modification logs
        AuditLog::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'action' => 'price_modified',
            'event' => 'updated',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'created_at' => now()->subMinutes(5),
        ]);

        AuditLog::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'action' => 'price_modified',
            'event' => 'updated',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'created_at' => now()->subMinutes(2),
        ]);

        // Create the 3rd log, which should trigger the rule
        AuditLog::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'action' => 'price_modified',
            'event' => 'updated',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'created_at' => now(),
        ]);

        // Verify violation report is registered
        $this->assertDatabaseHas('violation_reports', [
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->cashierEmployee->id,
            'violation_type' => 'AI: Sửa giá món nhiều lần',
            'severity' => 'high',
            'status' => 'open',
        ]);

        // Verify event and email job were dispatched
        Event::assertDispatched(FraudAlertTriggered::class);
        Queue::assertPushed(SendFraudAlertEmailJob::class);
    }

    /**
     * Test AuditLogObserver triggers anomaly on repeated order cancellation.
     */
    public function test_audit_log_observer_detects_repeated_order_cancellation(): void
    {
        Event::fake([FraudAlertTriggered::class]);
        Queue::fake();

        // Create 1 previous cancel log
        AuditLog::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'action' => 'order_cancelled',
            'event' => 'updated',
            'subject_type' => Order::class,
            'subject_id' => 998,
            'created_at' => now()->subHour(),
        ]);

        // Create 2nd cancel log, which triggers the rule
        AuditLog::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'action' => 'order_cancelled',
            'event' => 'updated',
            'subject_type' => Order::class,
            'subject_id' => 999,
            'created_at' => now(),
        ]);

        // Verify violation report is registered
        $this->assertDatabaseHas('violation_reports', [
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->cashierEmployee->id,
            'violation_type' => 'AI: Hủy đơn hàng liên tục nhạy cảm',
            'severity' => 'critical',
            'status' => 'open',
        ]);

        // Verify event and email job were dispatched
        Event::assertDispatched(FraudAlertTriggered::class);
        Queue::assertPushed(SendFraudAlertEmailJob::class);
    }

    /**
     * Test AuditLogObserver triggers anomaly on repeated discount application.
     */
    public function test_audit_log_observer_detects_repeated_discount_application(): void
    {
        Event::fake([FraudAlertTriggered::class]);
        Queue::fake();

        // Create 2 previous discount logs
        AuditLog::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'action' => 'discount_applied',
            'event' => 'updated',
            'subject_type' => Order::class,
            'subject_id' => 881,
            'created_at' => now()->subMinutes(10),
        ]);

        AuditLog::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'action' => 'discount_applied',
            'event' => 'updated',
            'subject_type' => Order::class,
            'subject_id' => 882,
            'created_at' => now()->subMinutes(5),
        ]);

        // Create 3rd log, which triggers the rule
        AuditLog::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'action' => 'discount_applied',
            'event' => 'updated',
            'subject_type' => Order::class,
            'subject_id' => 883,
            'created_at' => now(),
        ]);

        // Verify violation report is registered
        $this->assertDatabaseHas('violation_reports', [
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->cashierEmployee->id,
            'violation_type' => 'AI: Áp voucher liên tục bất thường',
            'severity' => 'high',
            'status' => 'open',
        ]);

        // Verify event and email job were dispatched
        Event::assertDispatched(FraudAlertTriggered::class);
        Queue::assertPushed(SendFraudAlertEmailJob::class);
    }
}
