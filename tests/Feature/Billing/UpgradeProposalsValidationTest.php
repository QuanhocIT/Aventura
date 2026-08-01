<?php

namespace Tests\Feature\Billing;

use App\Models\Area;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UpgradeProposalsValidationTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $freePlan;

    protected SubscriptionPlan $basicPlan;

    protected SubscriptionPlan $proPlan;

    protected SubscriptionPlan $enterprisePlan;

    protected User $owner;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Update or create plans
        $this->freePlan = SubscriptionPlan::updateOrCreate(
            ['code' => 'free'],
            [
                'name' => 'Miễn Phí',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'max_branches' => 1,
                'max_tables' => 15,
                'max_users' => 5,
                'status' => 'active',
                'features' => [
                    'max_storage_mb' => 500,
                    'kitchen_display' => false,
                    'hr_timekeeping' => false,
                    'hr_full' => false,
                    'supplier_portal' => false,
                    'fraud_detection' => false,
                ],
            ]
        );

        $this->basicPlan = SubscriptionPlan::updateOrCreate(
            ['code' => 'basic'],
            [
                'name' => 'Cơ Bản',
                'price' => 499000,
                'billing_cycle' => 'monthly',
                'max_branches' => 3,
                'max_tables' => 60,
                'max_users' => 20,
                'status' => 'active',
                'features' => [
                    'max_storage_mb' => 2048,
                    'kitchen_display' => true,
                    'hr_timekeeping' => true,
                    'hr_full' => false,
                    'supplier_portal' => false,
                    'fraud_detection' => false,
                ],
            ]
        );

        $this->proPlan = SubscriptionPlan::updateOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'Chuyên Nghiệp',
                'price' => 999000,
                'billing_cycle' => 'monthly',
                'max_branches' => 10,
                'max_tables' => 200,
                'max_users' => 60,
                'status' => 'active',
                'features' => [
                    'max_storage_mb' => 5120,
                    'kitchen_display' => true,
                    'hr_timekeeping' => true,
                    'hr_full' => true,
                    'supplier_portal' => false,
                    'fraud_detection' => true,
                ],
            ]
        );

        $this->enterprisePlan = SubscriptionPlan::updateOrCreate(
            ['code' => 'enterprise'],
            [
                'name' => 'Doanh Nghiệp',
                'price' => 1999000,
                'billing_cycle' => 'monthly',
                'max_branches' => null,
                'max_tables' => null,
                'max_users' => null,
                'status' => 'active',
                'features' => [
                    'max_storage_mb' => 20480,
                    'kitchen_display' => true,
                    'hr_timekeeping' => true,
                    'hr_full' => true,
                    'supplier_portal' => true,
                    'fraud_detection' => true,
                ],
            ]
        );

        // 2. Create Restaurant & Owner
        $this->owner = User::factory()->create();
        $this->restaurant = Restaurant::factory()->create([
            'plan_id' => $this->freePlan->id,
            'owner_user_id' => $this->owner->id,
            'status' => 'active',
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Set roles & permissions using Spatie
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $permissions = [
            'manage_kitchen',
            'manage_salary',
            'manage_schedule',
            'view_report',
            'view_violations',
            'view_audit_log',
            'manage_violations',
            'report_violations',
            'view_fraud_detection',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $ownerRole->syncPermissions($permissions);
        $this->owner->assignRole($ownerRole);

        // Ensure cache is cleared for test isolation
        Cache::flush();
    }

    /**
     * Test 1: Sidebar widget is rendered for the Owner and shares available plans and current plan details.
     */
    public function test_sidebar_widget_shares_available_plans_and_plan_details(): void
    {
        $response = $this->actingAs($this->owner)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('tenant')
            ->where('tenant.name', $this->restaurant->name)
            ->where('tenant.plan.code', 'free')
            ->has('available_plans')
        );
    }

    /**
     * Test 2: Quota Alerting Banner triggers warning when usage is >= 85% of limit.
     */
    public function test_quota_alerting_banner_shares_warnings_above_85_percent(): void
    {
        $area = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Khu A',
            'code' => 'KHU_A',
            'status' => 'active',
        ]);

        // For freePlan, max_tables limit is 15.
        // We will create 13 tables (13 / 15 = 86.7% >= 85%).
        for ($i = 0; $i < 13; $i++) {
            RestaurantTable::create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'area_id' => $area->id,
                'name' => "T-{$i}",
                'capacity' => 4,
                'status' => 'available',
            ]);
        }

        // Clear cache so QuotaService gets fresh data
        Cache::forget("quota_summary:{$this->restaurant->id}");

        $response = $this->actingAs($this->owner)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('tenant.quota_summary.resources.tables')
            ->where('tenant.quota_summary.resources.tables.percentage', 87)
            ->where('tenant.quota_summary.resources.tables.used', 13)
        );
    }

    /**
     * Test 3: Feature gate blocking works and renders FeatureGate Vue page.
     */
    public function test_feature_gate_page_redirection_for_restricted_features(): void
    {
        // 1. Kitchen Display (requires basicPlan)
        $response = $this->actingAs($this->owner)->get('/kitchen');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FeatureGate')
            ->where('feature', 'kitchen_display')
            ->where('plan_name', 'Miễn Phí')
            ->where('required_plan', 'Cơ Bản')
        );

        // 2. Schedules / Timekeeping (requires basicPlan)
        $response = $this->actingAs($this->owner)->get('/schedules');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FeatureGate')
            ->where('feature', 'hr_timekeeping')
            ->where('plan_name', 'Miễn Phí')
            ->where('required_plan', 'Cơ Bản')
        );

        // 3. Salaries (requires proPlan)
        $response = $this->actingAs($this->owner)->get('/salaries');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FeatureGate')
            ->where('feature', 'hr_full')
            ->where('plan_name', 'Miễn Phí')
            ->where('required_plan', 'Chuyên Nghiệp')
        );

        // 4. RFPs / Bidding (requires enterprisePlan)
        $response = $this->actingAs($this->owner)->get('/rfps');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FeatureGate')
            ->where('feature', 'supplier_portal')
            ->where('plan_name', 'Miễn Phí')
            ->where('required_plan', 'Doanh Nghiệp')
        );

        // 5. Fraud Audit (requires proPlan)
        $response = $this->actingAs($this->owner)->get('/fraud');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FeatureGate')
            ->where('feature', 'fraud_detection')
            ->where('plan_name', 'Miễn Phí')
            ->where('required_plan', 'Chuyên Nghiệp')
        );

        // 6. Violations (requires proPlan / hr_full)
        $response = $this->actingAs($this->owner)->get('/violations');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FeatureGate')
            ->where('feature', 'hr_full')
            ->where('plan_name', 'Miễn Phí')
            ->where('required_plan', 'Chuyên Nghiệp')
        );
    }

    /**
     * Test 4: Billing history page renders and has the correct restaurant info.
     */
    public function test_billing_history_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->owner)->get('/billing/history');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('billing/History')
            ->has('restaurant')
            ->where('restaurant.plan_name', 'Miễn Phí')
            ->has('subscriptions')
        );
    }
}
