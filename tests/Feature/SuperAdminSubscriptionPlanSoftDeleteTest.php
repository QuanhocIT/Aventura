<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminSubscriptionPlanSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['superadmin.2fa_verified_until' => now()->addMinutes(15)->timestamp]);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin_test@aventura.vn',
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');
        $this->withSession(['superadmin.2fa_verified_user_id' => $this->superAdmin->id]);
    }

    public function test_superadmin_can_soft_delete_and_restore_subscription_plan(): void
    {
        $plan = SubscriptionPlan::create([
            'code' => 'test_plan',
            'name' => 'Gói Test Thử Nghệ',
            'price' => 299000,
            'billing_cycle' => 'monthly',
            'max_branches' => 3,
            'max_tables' => 30,
            'max_users' => 10,
            'status' => 'active',
            'features' => ['kitchen_display' => true],
        ]);

        $restaurant = Restaurant::create([
            'plan_id' => $plan->id,
            'name' => 'Nhà Hàng Cũ Đang Dùng Gói',
            'code' => 'REST01',
            'slug' => 'nha-hang-cu',
            'status' => 'active',
        ]);

        // 1. Soft Delete plan
        $response = $this->actingAs($this->superAdmin)
            ->delete(route('superadmin.plans.destroy', $plan->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check DB state
        $this->assertSoftDeleted('subscription_plans', ['id' => $plan->id]);
        $softDeletedPlan = SubscriptionPlan::withTrashed()->find($plan->id);
        $this->assertEquals('inactive', $softDeletedPlan->status);

        // 2. Verify existing customer still retains plan access via relationship
        $restaurant->refresh();
        $this->assertNotNull($restaurant->plan);
        $this->assertEquals($plan->id, $restaurant->plan->id);
        $this->assertEquals('Gói Test Thử Nghệ', $restaurant->plan->name);

        // 3. Verify soft deleted plan is NOT in active plans for new buyers
        $activePlans = SubscriptionPlan::where('status', 'active')->get();
        $this->assertFalse($activePlans->contains('id', $plan->id));

        // 4. Restore plan
        $restoreResponse = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.plans.restore', $plan->id));

        $restoreResponse->assertRedirect();
        $restoreResponse->assertSessionHas('success');

        // Check restored state
        $restoredPlan = SubscriptionPlan::find($plan->id);
        $this->assertNotNull($restoredPlan);
        $this->assertEquals('active', $restoredPlan->status);
        $this->assertNull($restoredPlan->deleted_at);
    }
}
