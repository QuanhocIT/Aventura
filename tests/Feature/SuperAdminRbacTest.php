<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminRbacTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;
    private User $billingAdmin;
    private User $supportSpecialist;
    private User $legacySuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        foreach (['superadmin.dashboard.view', 'superadmin.billing.manage', 'superadmin.support.manage', 'superadmin.system.manage'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Create roles
        $systemAdmin = Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $systemAdmin->syncPermissions(['superadmin.dashboard.view', 'superadmin.billing.manage', 'superadmin.support.manage', 'superadmin.system.manage']);

        $billingAdmin = Role::firstOrCreate(['name' => 'billing_admin', 'guard_name' => 'web']);
        $billingAdmin->syncPermissions(['superadmin.dashboard.view', 'superadmin.billing.manage']);

        $supportSpec = Role::firstOrCreate(['name' => 'support_specialist', 'guard_name' => 'web']);
        $supportSpec->syncPermissions(['superadmin.dashboard.view', 'superadmin.support.manage']);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $base = ['email_verified_at' => now(), 'two_factor_confirmed_at' => now()];

        $this->systemAdmin = User::factory()->create($base);
        $this->systemAdmin->assignRole('system_admin');

        $this->billingAdmin = User::factory()->create($base);
        $this->billingAdmin->assignRole('billing_admin');

        $this->supportSpecialist = User::factory()->create($base);
        $this->supportSpecialist->assignRole('support_specialist');

        $this->legacySuperAdmin = User::factory()->create($base);
        $this->legacySuperAdmin->assignRole('super_admin');
    }

    public function test_billing_admin_can_access_billing_routes(): void
    {
        $this->actingAs($this->billingAdmin);

        $this->get('/super-admin/billing')->assertOk();
        $this->get('/super-admin/coupons')->assertOk();
        $this->get('/super-admin/referrals')->assertOk();
    }

    public function test_billing_admin_cannot_access_system_routes(): void
    {
        $this->actingAs($this->billingAdmin);

        $this->get('/super-admin/settings')->assertStatus(403);
        $this->get('/super-admin/accounts')->assertStatus(403);
        $this->get('/super-admin/audit-logs')->assertStatus(403);
    }

    public function test_billing_admin_cannot_access_support_routes(): void
    {
        $this->actingAs($this->billingAdmin);

        $this->get('/super-admin/support')->assertStatus(403);
        $this->get('/super-admin/chatbot')->assertStatus(403);
        $this->get('/super-admin/campaigns')->assertStatus(403);
    }

    public function test_support_specialist_can_access_support_routes(): void
    {
        $this->actingAs($this->supportSpecialist);

        $this->get('/super-admin/support')->assertOk();
        $this->get('/super-admin/chatbot')->assertOk();
    }

    public function test_support_specialist_cannot_access_billing_routes(): void
    {
        $this->actingAs($this->supportSpecialist);

        $this->get('/super-admin/billing')->assertStatus(403);
        $this->get('/super-admin/coupons')->assertStatus(403);
    }

    public function test_support_specialist_cannot_access_system_routes(): void
    {
        $this->actingAs($this->supportSpecialist);

        $this->get('/super-admin/settings')->assertStatus(403);
        $this->get('/super-admin/accounts')->assertStatus(403);
    }

    public function test_system_admin_has_full_access(): void
    {
        $this->actingAs($this->systemAdmin);

        $this->get('/super-admin/dashboard')->assertOk();
        $this->get('/super-admin/billing')->assertOk();
        $this->get('/super-admin/support')->assertOk();
        $this->get('/super-admin/settings')->assertOk();
        $this->get('/super-admin/accounts')->assertOk();
        $this->get('/super-admin/audit-logs')->assertOk();
    }

    public function test_legacy_super_admin_retains_full_access(): void
    {
        $this->actingAs($this->legacySuperAdmin);

        $this->get('/super-admin/dashboard')->assertOk();
        $this->get('/super-admin/billing')->assertOk();
        $this->get('/super-admin/support')->assertOk();
        $this->get('/super-admin/settings')->assertOk();
        $this->get('/super-admin/accounts')->assertOk();
    }

    public function test_all_subroles_can_access_shared_dashboard(): void
    {
        foreach ([$this->billingAdmin, $this->supportSpecialist, $this->systemAdmin] as $user) {
            $this->actingAs($user);
            $this->get('/super-admin/dashboard')->assertOk();
            $this->get('/super-admin/restaurants')->assertOk();
        }
    }

    public function test_unauthenticated_cannot_access_superadmin(): void
    {
        $this->get('/super-admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_normal_user_cannot_access_superadmin(): void
    {
        $normalUser = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($normalUser);
        $this->get('/super-admin/dashboard')->assertStatus(403);
    }

    public function test_system_admin_can_create_admin_account(): void
    {
        $this->actingAs($this->systemAdmin);

        $response = $this->post('/super-admin/accounts', [
            'name' => 'New Billing User',
            'email' => 'billing@test.com',
            'role' => 'billing_admin',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('temp_password');

        $newUser = User::where('email', 'billing@test.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->hasRole('billing_admin'));
    }

    public function test_system_admin_can_update_role(): void
    {
        $this->actingAs($this->systemAdmin);

        $response = $this->patch("/super-admin/accounts/{$this->billingAdmin->id}/role", [
            'role' => 'support_specialist',
        ]);

        $response->assertRedirect();
        $this->billingAdmin->refresh();
        $this->assertTrue($this->billingAdmin->hasRole('support_specialist'));
        $this->assertFalse($this->billingAdmin->hasRole('billing_admin'));
    }
}
