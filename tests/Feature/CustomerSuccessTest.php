<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Coupon;
use App\Mail\ChurnEarlyWarningMail;
use App\Services\CustomerSuccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class CustomerSuccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $owner;
    protected Restaurant $restaurant;
    protected SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['superadmin.2fa_verified_until' => now()->addMinutes(15)->timestamp]);

        // Disable Scout search indexing to prevent Meilisearch connections during testing
        config(['scout.driver' => null]);

        // Ensure roles exist (Spatie permission)
        Role::findOrCreate('super_admin');
        Role::findOrCreate('owner');

        // Setup SuperAdmin
        $this->superAdmin = User::factory()->create([
            'two_factor_confirmed_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');
        $this->withSession(['superadmin.2fa_verified_user_id' => $this->superAdmin->id]);

        // Setup Restaurant Owner
        $this->owner = User::factory()->create([
            'name' => 'Owner Name',
            'email' => 'owner@example.com',
            'phone' => '0987654321',
        ]);
        $this->owner->assignRole('owner');

        // Setup Plan
        $this->plan = SubscriptionPlan::create([
            'name' => 'Pro Plan',
            'code' => 'PRO',
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'max_branches' => 5,
            'max_tables' => 50,
            'max_users' => 20,
            'status' => 'active',
        ]);

        // Setup Restaurant
        $this->restaurant = Restaurant::create([
            'name' => 'Burger Queen',
            'code' => 'BURGERQ',
            'slug' => 'burger-queen',
            'status' => 'active',
            'plan_id' => $this->plan->id,
            'owner_user_id' => $this->owner->id,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
        ]);

        $this->owner->update(['restaurant_id' => $this->restaurant->id]);
    }

    public function test_health_score_calculation_reasons_and_risk_levels(): void
    {
        $service = app(CustomerSuccessService::class);

        // Case 1: Optimal health
        $this->owner->update(['last_login_at' => now()->subHours(5)]);
        
        // Setup some orders for previous 3 weeks and current week
        for ($i = 0; $i < 3; $i++) {
            Order::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'subtotal' => 10000,
                'total_amount' => 10000,
                'created_at' => now()->subDays(10),
            ]);
        }
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'subtotal' => 10000,
            'total_amount' => 10000,
            'created_at' => now()->subDays(2),
        ]);

        $metrics = $service->calculateHealthScore($this->restaurant);

        $this->assertEquals(100, $metrics['health_score']);
        $this->assertEquals('low', $metrics['churn_risk_level']);
        $this->assertStringContainsString('Mọi chỉ số vận hành', $metrics['churn_risk_reason']);

        // Case 2: Inactivity & Unresolved Tickets (Medium Risk)
        $this->owner->update(['last_login_at' => now()->subDays(5)]); // -10 points
        
        SupportTicket::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'TKT-001',
            'category' => 'technical',
            'severity' => 'medium',
            'priority' => 'p3',
            'status' => 'open',
            'title' => 'Lỗi in hóa đơn',
            'description' => 'Không in được hóa đơn',
        ]); // -10 points

        $metrics = $service->calculateHealthScore($this->restaurant);

        $this->assertEquals(80, $metrics['health_score']); // 100 - 10 (login) - 10 (ticket) = 80 -> low limit is 80 (>= 80 is low)
        
        // Add another ticket to drop it to medium risk
        SupportTicket::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'TKT-002',
            'category' => 'technical',
            'severity' => 'high',
            'priority' => 'p2',
            'status' => 'in_progress',
            'title' => 'Lỗi bếp',
            'description' => 'Màn hình bếp đơ',
        ]); // now 2 unresolved tickets -> -20 points

        $metrics = $service->calculateHealthScore($this->restaurant);
        $this->assertEquals(70, $metrics['health_score']); // 100 - 10 - 20 = 70
        $this->assertEquals('medium', $metrics['churn_risk_level']);
    }

    public function test_artisan_command_recalculates_health_and_triggers_email(): void
    {
        Mail::fake();

        // Setup high risk triggers:
        // 1. Long inactivity (>14 days)
        $this->owner->update(['last_login_at' => now()->subDays(20)]); // -30

        // Create orders to ensure order drop deduction is 0
        for ($i = 0; $i < 3; $i++) {
            Order::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'created_at' => now()->subDays(10),
            ]);
        }
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'created_at' => now()->subDays(2),
        ]);
        
        // 2. High unresolved tickets count (>=3 tickets)
        for ($i = 0; $i < 3; $i++) {
            SupportTicket::create([
                'restaurant_id' => $this->restaurant->id,
                'code' => 'TKT-00' . $i,
                'category' => 'technical',
                'severity' => 'high',
                'priority' => 'p2',
                'status' => 'open',
                'title' => 'Lỗi ' . $i,
                'description' => 'Mô tả ' . $i,
            ]); // -30
        }

        // Run the command
        $this->artisan('restaurants:calculate-health')
            ->assertSuccessful();

        $this->restaurant->refresh();

        $this->assertEquals(40, $this->restaurant->health_score); // 100 - 30 (login) - 30 (tickets) = 40
        $this->assertEquals('high', $this->restaurant->churn_risk_level);
        $this->assertNotNull($this->restaurant->churn_risk_flagged_at);

        // Assert that care email was triggered
        Mail::assertSent(ChurnEarlyWarningMail::class, function ($mail) {
            return $mail->hasTo('owner@example.com') &&
                   $mail->restaurantName === 'Burger Queen' &&
                   $mail->ownerName === 'Owner Name' &&
                   $mail->promoCode === 'AVENTURACARE30';
        });
    }

    public function test_superadmin_can_access_churn_dashboard_and_manually_trigger_action(): void
    {
        Mail::fake();

        // Create orders to ensure order drop deduction is 0
        for ($i = 0; $i < 3; $i++) {
            Order::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'created_at' => now()->subDays(10),
            ]);
        }
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'created_at' => now()->subDays(2),
        ]);

        // Recalculate metrics
        $this->artisan('restaurants:calculate-health');

        // 1. Verify access to Churn Dashboard
        $response = $this->actingAs($this->superAdmin)
            ->get(route('superadmin.churn.index'));

        $response->assertSuccessful();

        // 2. Verify manual recalculation action
        $response = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.churn.recalculate'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 3. Verify manual care email trigger action
        $response = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.churn.trigger-email', $this->restaurant));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(ChurnEarlyWarningMail::class);
    }
}
