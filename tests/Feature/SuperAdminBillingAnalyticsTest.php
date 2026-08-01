<?php

namespace Tests\Feature;

use App\Models\BillingAdjustment;
use App\Models\BillingInvoice;
use App\Models\CommissionLog;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * analytics/ledger/revenueRecognition/dunning/lifecycle không có test nào
 * trước khi BillingController được tách sang BillingAnalyticsService (chia
 * để trị) — thêm ở đây để bắt lỗi tách sai (gọi nhầm instance, sai field khi
 * di chuyển) và đóng khoảng trống test coverage vốn đã tồn tại từ trước.
 */
class SuperAdminBillingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $billingAdmin;

    private Restaurant $restaurant;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['superadmin.2fa_verified_until' => now()->addMinutes(15)->timestamp]);

        Permission::firstOrCreate(['name' => 'superadmin.billing.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'billing_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'superadmin.billing.view', 'guard_name' => 'web']);
        $role->syncPermissions(['superadmin.billing.view', 'superadmin.billing.manage']);

        $this->billingAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->billingAdmin->assignRole($role);
        $this->withSession(['superadmin.2fa_verified_user_id' => $this->billingAdmin->id]);

        $this->plan = SubscriptionPlan::factory()->create(['price' => 500000, 'is_custom' => false]);
        $this->restaurant = Restaurant::factory()->create(['status' => 'active', 'plan_id' => $this->plan->id]);

        $subscription = RestaurantSubscription::create([
            'restaurant_id' => $this->restaurant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'started_at' => now()->subMonths(2),
            'ended_at' => now()->addMonth(),
            'renewal_at' => now()->addMonth(),
            'price' => 500000,
            'last_notified_at' => now()->subDays(5),
            'last_paid_at' => now()->subDays(4),
        ]);

        BillingInvoice::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_subscription_id' => $subscription->id,
            'invoice_number' => 'INV-TEST-0001',
            'type' => 'payment_success',
            'status' => 'processed',
            'currency' => 'VND',
            'total' => 500000,
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDays(7)->toDateString(),
        ]);

        BillingInvoice::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_subscription_id' => $subscription->id,
            'invoice_number' => 'INV-TEST-0002',
            'type' => 'payment_success',
            'status' => 'pending',
            'currency' => 'VND',
            'total' => 200000,
            'issued_on' => now()->subDays(40)->toDateString(),
            'due_on' => now()->subDays(35)->toDateString(),
        ]);

        BillingAdjustment::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_subscription_id' => $subscription->id,
            'type' => 'discount',
            'days' => 0,
            'discount_amount' => 50000,
            'reason' => 'Khuyến mãi test',
        ]);

        CommissionLog::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->billingAdmin->id,
            'buyer_id' => $this->billingAdmin->id,
            'amount' => 1000000,
            'commission_percentage' => 5,
            'commission_amount' => 50000,
        ]);
    }

    public function test_analytics_page_returns_kpis_and_aging_buckets(): void
    {
        $response = $this->actingAs($this->billingAdmin)->get(route('superadmin.billing.analytics'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('super-admin/billing/Analytics')
            ->has('kpis.mrr')
            ->has('aging_buckets.8_30')
            ->where('bad_debt.total_adjustments', number_format(50000))
        );
    }

    public function test_ledger_page_lists_invoice_adjustment_and_commission_entries(): void
    {
        $response = $this->actingAs($this->billingAdmin)->get(route('superadmin.billing.ledger'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('super-admin/billing/Ledger')
            ->has('entries.data', 4)
        );
    }

    public function test_revenue_recognition_page_computes_earned_and_deferred(): void
    {
        $response = $this->actingAs($this->billingAdmin)->get(route('superadmin.billing.revenue-recognition'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('super-admin/billing/RevenueRecognition')
            ->has('summary.total_cash')
            ->where('summary.subscription_count', 1)
        );
    }

    public function test_dunning_page_groups_subscriptions_by_urgency(): void
    {
        $response = $this->actingAs($this->billingAdmin)->get(route('superadmin.billing.dunning'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('super-admin/billing/Dunning')
            ->has('groups.converted', 1)
        );
    }

    public function test_lifecycle_page_returns_funnel_and_plan_stats(): void
    {
        $response = $this->actingAs($this->billingAdmin)->get(route('superadmin.billing.lifecycle'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('super-admin/billing/Lifecycle')
            ->where('funnel.total_registered', 1)
        );
    }
}
