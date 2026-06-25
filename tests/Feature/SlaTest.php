<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\User;
use App\Mail\SupportTicketEscalationMail;
use App\Services\SlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class SlaTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $owner;
    protected Restaurant $restaurant;
    protected SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => null]);

        Role::findOrCreate('super_admin');
        Role::findOrCreate('owner');

        $this->superAdmin = User::factory()->create([
            'two_factor_confirmed_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->plan = SubscriptionPlan::where('code', 'enterprise')->first();
        if (!$this->plan) {
            $this->plan = SubscriptionPlan::create([
                'name' => 'Enterprise Plan',
                'code' => 'enterprise',
                'price' => 1499000,
                'billing_cycle' => 'monthly',
                'max_branches' => 99,
                'max_tables' => 999,
                'max_users' => 999,
                'status' => 'active',
            ]);
        }

        $this->restaurant = Restaurant::create([
            'name' => 'Grand Palace',
            'code' => 'GRANDP',
            'slug' => 'grand-palace',
            'status' => 'active',
            'plan_id' => $this->plan->id,
            'owner_user_id' => $this->owner->id,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
        ]);

        $this->owner->update(['restaurant_id' => $this->restaurant->id]);
    }

    public function test_sla_due_time_calculation_on_ticket_creation(): void
    {
        $service = app(SlaService::class);

        // Case 1: Enterprise (2 hours SLA)
        $ticket = SupportTicket::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->owner->id,
            'code' => 'TKT-ENT',
            'channel' => 'tenant_portal',
            'category' => 'technical',
            'severity' => 'high',
            'priority' => 'p2',
            'status' => 'open',
            'title' => 'Lỗi kết nối',
            'description' => 'Không thể kết nối máy in',
        ]);
        $ticket->sla_due_at = $service->calculateSlaDueAt($ticket);
        $ticket->save();

        $expectedDue = $ticket->created_at->copy()->addHours(2);
        $this->assertEquals($expectedDue->timestamp, $ticket->sla_due_at->timestamp);
        $this->assertEquals('pending', $ticket->sla_status);

        // Case 2: Pro Plan (12 hours SLA)
        $proPlan = SubscriptionPlan::where('code', 'pro')->first();
        if (!$proPlan) {
            $proPlan = SubscriptionPlan::create([
                'name' => 'Pro Plan',
                'code' => 'pro',
                'price' => 699000,
                'billing_cycle' => 'monthly',
                'max_branches' => 10,
                'max_tables' => 100,
                'max_users' => 50,
                'status' => 'active',
            ]);
        }
        $proRestaurant = Restaurant::create([
            'name' => 'Pro Diner',
            'code' => 'PRODIN',
            'slug' => 'pro-diner',
            'status' => 'active',
            'plan_id' => $proPlan->id,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
        ]);

        $proTicket = SupportTicket::create([
            'restaurant_id' => $proRestaurant->id,
            'code' => 'TKT-PRO',
            'channel' => 'tenant_portal',
            'category' => 'technical',
            'severity' => 'high',
            'priority' => 'p2',
            'status' => 'open',
            'title' => 'Lỗi kết nối',
            'description' => 'Không thể kết nối máy in',
        ]);
        $proTicket->sla_due_at = $service->calculateSlaDueAt($proTicket);
        $proTicket->save();

        $expectedProDue = $proTicket->created_at->copy()->addHours(12);
        $this->assertEquals($expectedProDue->timestamp, $proTicket->sla_due_at->timestamp);
    }

    public function test_sla_status_accessor(): void
    {
        $ticket = SupportTicket::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'TKT-SLA-TEST',
            'channel' => 'tenant_portal',
            'category' => 'technical',
            'severity' => 'high',
            'priority' => 'p2',
            'status' => 'open',
            'title' => 'Lỗi kết nối',
            'description' => 'Không thể kết nối máy in',
            'created_at' => now(),
            'sla_due_at' => now()->addHours(2),
        ]);

        // 1. Pending (2 hours remaining)
        $this->assertEquals('pending', $ticket->sla_status);

        // 2. Warning (30 minutes remaining)
        $ticket->sla_due_at = now()->addMinutes(30);
        $ticket->save();
        $this->assertEquals('warning', $ticket->sla_status);

        // 3. Breached (10 minutes past due)
        $ticket->sla_due_at = now()->subMinutes(10);
        $ticket->save();
        $this->assertEquals('breached', $ticket->sla_status);

        // 4. Fulfilled (responded before due time)
        $ticket->sla_due_at = now()->addHours(2);
        $ticket->first_response_at = now()->addMinutes(30);
        $ticket->save();
        $this->assertEquals('fulfilled', $ticket->sla_status);
    }

    public function test_artisan_command_escalates_enterprise_tickets(): void
    {
        Mail::fake();

        // Create an unresponded Enterprise ticket created 90 minutes ago
        $ticket = SupportTicket::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'TKT-ESC',
            'channel' => 'tenant_portal',
            'category' => 'technical',
            'severity' => 'high',
            'priority' => 'p2',
            'status' => 'open',
            'title' => 'Sự cố khẩn cấp',
            'description' => 'Mất kết nối toàn bộ hệ thống POS',
            'created_at' => now()->subMinutes(90),
            'sla_due_at' => now()->addMinutes(30), // SLA 2 hours -> due in 30 mins
        ]);

        $this->artisan('tickets:check-sla')
            ->assertSuccessful();

        $ticket->refresh();
        $this->assertNotNull($ticket->escalated_at);

        Mail::assertQueued(SupportTicketEscalationMail::class);
    }

    public function test_superadmin_can_access_sla_dashboard_and_manually_trigger_action(): void
    {
        Mail::fake();

        $ticket = SupportTicket::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'TKT-MAN',
            'channel' => 'tenant_portal',
            'category' => 'technical',
            'severity' => 'high',
            'priority' => 'p2',
            'status' => 'open',
            'title' => 'Sự cố khẩn cấp',
            'description' => 'Mất kết nối toàn bộ hệ thống POS',
            'created_at' => now(),
            'sla_due_at' => now()->addHours(2),
        ]);

        // Access Support Dashboard (which displays SLA stats)
        $response = $this->actingAs($this->superAdmin)
            ->get(route('superadmin.support.index'));
        $response->assertSuccessful();

        // Recalculate SLA times manually
        $response = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.support.sla.recalculate'));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Escalate manually
        $response = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.support.tickets.escalate', $ticket));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertQueued(SupportTicketEscalationMail::class);
    }
}
