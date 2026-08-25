<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantHealthTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['superadmin.dashboard.view', 'superadmin.tenant.view', 'superadmin.tenant.manage'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $role->syncPermissions([
            'superadmin.dashboard.view',
            'superadmin.tenant.view',
            'superadmin.tenant.manage',
        ]);

        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $this->systemAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->systemAdmin->assignRole($role);
    }

    public function test_health_screen_detects_tenant_and_branch_integrity_issues(): void
    {
        $tenant = Restaurant::factory()->create(['owner_user_id' => null]);
        $otherTenant = Restaurant::factory()->create(['owner_user_id' => null]);
        $activeBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $tenant->id,
            'manager_user_id' => null,
            'status' => 'active',
        ]);
        $inactiveBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $tenant->id,
            'manager_user_id' => null,
            'status' => 'inactive',
        ]);
        $otherBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $otherTenant->id,
            'manager_user_id' => null,
            'status' => 'active',
        ]);

        $otherOwner = User::factory()->create([
            'restaurant_id' => $otherTenant->id,
            'status' => 'active',
        ]);
        $otherOwner->assignRole('owner');
        $otherTenant->update(['owner_user_id' => $otherOwner->id]);
        $otherManager = User::factory()->create([
            'restaurant_id' => $otherTenant->id,
            'branch_id' => $otherBranch->id,
            'status' => 'active',
        ]);
        $otherManager->assignRole('manager');
        $otherBranch->update(['manager_user_id' => $otherManager->id]);

        $manager = User::factory()->create([
            'restaurant_id' => $tenant->id,
            'branch_id' => null,
            'status' => 'active',
        ]);
        $manager->assignRole('manager');

        User::factory()->create([
            'restaurant_id' => $tenant->id,
            'branch_id' => $inactiveBranch->id,
            'status' => 'active',
        ]);
        User::factory()->create([
            'restaurant_id' => $tenant->id,
            'branch_id' => $otherBranch->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->systemAdmin)->get('/super-admin/tenant-health');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('super-admin/tenant-health/Index')
            ->where('summary.tenants', 2)
            ->where('summary.issues', 6)
            ->where('issues.current_page', 1)
            ->where('issues.total', 6)
            ->has('issues.data', 6)
        );

        $this->assertTrue($activeBranch->refresh()->manager_user_id === null);
    }

    public function test_health_issues_are_paginated_by_ten(): void
    {
        Restaurant::factory()->count(11)->create(['owner_user_id' => null]);

        $this->actingAs($this->systemAdmin)
            ->get('/super-admin/tenant-health')
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.issues', 11)
                ->where('issues.current_page', 1)
                ->where('issues.last_page', 2)
                ->where('issues.per_page', 10)
                ->has('issues.data', 10)
                ->where('tenants.current_page', 1)
                ->where('tenants.last_page', 2)
                ->where('tenants.per_page', 10)
                ->has('tenants.data', 10)
            );

        $this->actingAs($this->systemAdmin)
            ->get('/super-admin/tenant-health?issues_page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->where('issues.current_page', 2)
                ->has('issues.data', 1)
            );

        $this->actingAs($this->systemAdmin)
            ->get('/super-admin/tenant-health?tenants_page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenants.current_page', 2)
                ->has('tenants.data', 1)
            );
    }

    public function test_health_repairs_are_authorized_and_audited(): void
    {
        $tenant = Restaurant::factory()->create(['owner_user_id' => null]);
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $tenant->id,
            'manager_user_id' => null,
            'status' => 'active',
        ]);
        $manager = User::factory()->create([
            'restaurant_id' => $tenant->id,
            'branch_id' => null,
            'status' => 'active',
        ]);
        $manager->assignRole('manager');

        $this->actingAs($this->systemAdmin)->withSession([
            'superadmin.2fa_verified_until' => now()->addMinutes(10)->timestamp,
            'superadmin.2fa_verified_user_id' => $this->systemAdmin->id,
        ]);

        $this->post('/super-admin/tenant-health/repair', [
            'action' => 'assign_manager',
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $manager->id,
        ])->assertRedirect();

        $this->assertSame($manager->id, $branch->refresh()->manager_user_id);
        $this->assertSame($branch->id, $manager->refresh()->getRawOriginal('branch_id'));
        $this->assertTrue(AuditLog::where('action', 'tenant_health_assign_manager')->exists());

        $this->post('/super-admin/tenant-health/repair', [
            'action' => 'set_lifecycle',
            'tenant_id' => $tenant->id,
            'lifecycle_status' => 'sandbox',
        ])->assertRedirect();
        $this->assertSame('sandbox', $tenant->refresh()->lifecycleStatus());

        $this->post('/super-admin/tenant-health/repair', [
            'action' => 'set_feature_flag',
            'tenant_id' => $tenant->id,
            'feature' => 'api_access',
            'enabled' => true,
            'mode' => 'override',
        ])->assertRedirect();
        $this->assertTrue(app(QuotaService::class)->hasFeature($tenant->refresh(), 'api_access'));
        $this->assertTrue(AuditLog::where('action', 'tenant_feature_flag_update')->exists());
    }

    public function test_billing_and_support_roles_cannot_access_health_management(): void
    {
        $billingPermission = Permission::firstOrCreate(['name' => 'superadmin.billing.view', 'guard_name' => 'web']);
        $billingRole = Role::firstOrCreate(['name' => 'billing_admin', 'guard_name' => 'web']);
        $billingRole->syncPermissions([$billingPermission]);
        $billing = User::factory()->create(['email_verified_at' => now(), 'two_factor_confirmed_at' => now()]);
        $billing->assignRole($billingRole);

        $this->actingAs($billing)->get('/super-admin/tenant-health')->assertStatus(403);
        $this->actingAs($billing)->post('/super-admin/tenant-health/repair', [])->assertStatus(403);
    }
}
