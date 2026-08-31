<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\Coupon;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\EmailMicroserviceClient;
use App\Services\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminBusinessInvariantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'support_specialist', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->admin->assignRole('super_admin');
        $this->withSession([
            'superadmin.2fa_verified_until' => now()->addMinutes(15)->timestamp,
            'superadmin.2fa_verified_user_id' => $this->admin->id,
        ]);
    }

    public function test_tenant_creation_assigns_owner_role(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'code' => 'business-invariant-plan',
            'status' => 'active',
            'is_custom' => false,
        ]);

        $this->mock(EmailMicroserviceClient::class, function ($mock): void {
            $mock->shouldReceive('sendWelcome')->once()->andReturnTrue();
        });

        $response = $this->actingAs($this->admin)->post(route('superadmin.restaurants.store'), [
            'name' => 'Invariant Restaurant',
            'plan_id' => $plan->id,
            'owner_name' => 'Invariant Owner',
            'owner_email' => 'invariant-owner@example.test',
        ]);

        $response->assertRedirect();
        $owner = User::where('email', 'invariant-owner@example.test')->firstOrFail();
        $this->assertSame($owner->restaurant_id, Restaurant::where('owner_user_id', $owner->id)->value('id'));
        $this->assertTrue($owner->fresh()->hasRole('owner'));
    }

    public function test_reply_from_another_ticket_cannot_be_modified(): void
    {
        $tenant = Restaurant::factory()->create();
        $otherTenant = Restaurant::factory()->create();

        $ticket = SupportTicket::create([
            'restaurant_id' => $tenant->id,
            'created_by' => $this->admin->id,
            'code' => 'TKT-INVARIANT-A',
            'channel' => 'admin_portal',
            'category' => 'technical',
            'severity' => 'medium',
            'priority' => 'p2',
            'status' => 'open',
            'title' => 'Ticket A',
            'description' => 'Description A',
        ]);
        $otherTicket = SupportTicket::create([
            'restaurant_id' => $otherTenant->id,
            'created_by' => $this->admin->id,
            'code' => 'TKT-INVARIANT-B',
            'channel' => 'admin_portal',
            'category' => 'technical',
            'severity' => 'medium',
            'priority' => 'p2',
            'status' => 'open',
            'title' => 'Ticket B',
            'description' => 'Description B',
        ]);
        $reply = SupportTicketReply::create([
            'support_ticket_id' => $otherTicket->id,
            'user_id' => $this->admin->id,
            'message' => 'Original reply',
        ]);

        $response = $this->actingAs($this->admin)->patch(
            route('superadmin.support.tickets.replies.update', [$ticket->id, $reply->id]),
            ['message' => 'Tampered reply'],
        );

        $response->assertNotFound();
        $this->assertSame('Original reply', $reply->fresh()->message);
    }

    public function test_referral_approval_and_payout_are_separate_states(): void
    {
        $user = User::factory()->create(['commission_balance' => 100000]);
        $withdrawal = WithdrawalRequest::create([
            'user_id' => $user->id,
            'amount' => 50000,
            'bank_name' => 'Test Bank',
            'bank_account_number' => '123456789',
            'bank_account_name' => 'Test User',
            'status' => 'pending',
        ]);

        $approve = $this->actingAs($this->admin)->post(
            route('superadmin.referrals.withdrawals.approve', $withdrawal),
            ['notes' => 'Approved for payout'],
        );
        $approve->assertRedirect();
        $this->assertDatabaseHas('withdrawal_requests', [
            'id' => $withdrawal->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);

        $paid = $this->post(route('superadmin.referrals.withdrawals.paid', $withdrawal), [
            'payout_reference' => 'BANK-REF-001',
        ]);
        $paid->assertRedirect();
        $this->assertDatabaseHas('withdrawal_requests', [
            'id' => $withdrawal->id,
            'status' => 'paid',
            'paid_by' => $this->admin->id,
            'payout_reference' => 'BANK-REF-001',
        ]);
    }

    public function test_unverified_platform_admin_can_receive_verification_email_again(): void
    {
        $account = User::factory()->create([
            'email_verified_at' => null,
            'status' => 'active',
        ]);
        $account->assignRole('support_specialist');

        $this->mock(EmailVerificationService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andReturnTrue();
        });

        $response = $this->actingAs($this->admin)->post(
            route('superadmin.accounts.resend-verification', $account),
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_closed_ticket_cannot_be_reopened_without_explicit_transition(): void
    {
        $ticket = SupportTicket::create([
            'restaurant_id' => Restaurant::factory()->create()->id,
            'created_by' => $this->admin->id,
            'code' => 'TKT-INVARIANT-C',
            'channel' => 'admin_portal',
            'category' => 'technical',
            'severity' => 'medium',
            'priority' => 'p2',
            'status' => 'closed',
            'title' => 'Closed ticket',
            'description' => 'Description',
        ]);

        $response = $this->actingAs($this->admin)->patch(
            route('superadmin.support.tickets.update', $ticket),
            ['status' => 'open'],
        );

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => 'closed',
        ]);
    }

    public function test_coupon_discount_is_applied_and_consumed_by_manual_override(): void
    {
        $tenant = Restaurant::factory()->create();
        $subscription = RestaurantSubscription::create([
            'restaurant_id' => $tenant->id,
            'plan_id' => $tenant->plan_id,
            'status' => 'active',
            'started_at' => now(),
            'ended_at' => now()->addMonth(),
            'price' => 100000,
            'original_price' => 100000,
        ]);
        $coupon = Coupon::create([
            'code' => 'INVARIANT10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'max_uses' => 1,
            'uses_count' => 0,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('superadmin.restaurants.billing-overrides.store', $tenant),
            [
                'password' => 'password123',
                'type' => 'discount',
                'discount_amount' => 0,
                'coupon_code' => $coupon->code,
                'reason' => 'Apply approved partner coupon',
            ],
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('billing_adjustments', [
            'restaurant_id' => $tenant->id,
            'restaurant_subscription_id' => $subscription->id,
            'coupon_code' => $coupon->code,
            'discount_amount' => 10000,
        ]);
        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'uses_count' => 1,
        ]);
    }
}
