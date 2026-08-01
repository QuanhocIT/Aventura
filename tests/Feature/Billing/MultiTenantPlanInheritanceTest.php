<?php

namespace Tests\Feature\Billing;

use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiTenantPlanInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_inherit_the_exact_same_plan_and_quota_as_the_owner(): void
    {
        // 1. Create PRO plan
        $proPlan = SubscriptionPlan::updateOrCreate(['code' => 'pro'], [
            'name' => 'Pro',
            'price' => 499000,
            'billing_cycle' => 'monthly',
            'max_branches' => 999, // high quota
            'max_tables' => 999,
            'max_users' => 999,
            'features' => [
                'ai_features' => true,
            ],
            'status' => 'active',
        ]);

        // 2. Create FREE plan
        $freePlan = SubscriptionPlan::updateOrCreate(['code' => 'free'], [
            'name' => 'Free',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'max_branches' => 1,
            'max_tables' => 10,
            'max_users' => 5,
            'features' => [
                'ai_features' => false,
            ],
            'status' => 'active',
        ]);

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $owner->assignRole($ownerRole);

        // Initially setup restaurant on FREE plan
        $restaurant = Restaurant::factory()->create([
            'plan_id' => $freePlan->id,
            'owner_user_id' => $owner->id,
        ]);
        $owner->update(['restaurant_id' => $restaurant->id]);

        // Create an employee (manager) under the same restaurant
        $employee = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);
        $employee->assignRole($managerRole);

        $quotaService = app(QuotaService::class);

        // 3. Verify that under FREE plan, both owner and employee have FREE limits and features
        $this->assertEquals('Free', $restaurant->fresh()->plan->name);
        $this->assertEquals(5, $quotaService->getLimit($owner->restaurant, 'employees'));
        $this->assertEquals(5, $quotaService->getLimit($employee->restaurant, 'employees'));
        $this->assertFalse($quotaService->hasFeature($owner->restaurant, 'ai_features'));
        $this->assertFalse($quotaService->hasFeature($employee->restaurant, 'ai_features'));

        // 4. Upgrade restaurant to PRO plan
        $restaurant->update(['plan_id' => $proPlan->id]);

        // 5. Verify that both owner and employee instantly inherit the PRO plan and its quotas
        $this->assertEquals('Pro', $restaurant->fresh()->plan->name);
        $this->assertEquals(999, $quotaService->getLimit($owner->fresh()->restaurant, 'employees'));
        $this->assertEquals(999, $quotaService->getLimit($employee->fresh()->restaurant, 'employees'));
        $this->assertTrue($quotaService->hasFeature($owner->fresh()->restaurant, 'ai_features'));
        $this->assertTrue($quotaService->hasFeature($employee->fresh()->restaurant, 'ai_features'));
    }
}
