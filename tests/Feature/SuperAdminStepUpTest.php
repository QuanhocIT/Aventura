<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminStepUpTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'superadmin.dashboard.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'superadmin.system.manage', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $role->syncPermissions(['superadmin.dashboard.view', 'superadmin.system.manage']);

        $this->systemAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->systemAdmin->assignRole($role);
    }

    public function test_sensitive_role_change_requires_step_up_session(): void
    {
        $target = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $target->assignRole('billing_admin');

        $response = $this->actingAs($this->systemAdmin)->patch(
            route('superadmin.accounts.update-role', $target),
            ['role' => 'support_specialist'],
        );

        $response->assertRedirect(route('superadmin.security.confirm-2fa'));
        $this->assertTrue($target->refresh()->hasRole('billing_admin'));
    }

    public function test_invalid_step_up_code_is_rejected(): void
    {
        $provider = Mockery::mock(TwoFactorAuthenticationProvider::class);
        $provider->shouldReceive('verify')
            ->once()
            ->with('test-secret', '123456')
            ->andReturn(false);
        $this->app->instance(TwoFactorAuthenticationProvider::class, $provider);

        $response = $this->actingAs($this->systemAdmin)->post(
            route('superadmin.security.confirm-2fa.verify'),
            ['code' => '123456'],
        );

        $response->assertSessionHasErrors('code');
        $response->assertSessionMissing('superadmin.2fa_verified_until');
    }

    public function test_valid_step_up_code_grants_temporary_session(): void
    {
        $provider = Mockery::mock(TwoFactorAuthenticationProvider::class);
        $provider->shouldReceive('verify')
            ->once()
            ->with('test-secret', '123456')
            ->andReturn(true);
        $this->app->instance(TwoFactorAuthenticationProvider::class, $provider);

        $response = $this->actingAs($this->systemAdmin)->post(
            route('superadmin.security.confirm-2fa.verify'),
            ['code' => '123456'],
        );

        $response->assertRedirect(route('superadmin.dashboard'));
        $this->assertGreaterThan(
            now()->timestamp,
            (int) $response->getSession()->get('superadmin.2fa_verified_until'),
        );
    }
}
