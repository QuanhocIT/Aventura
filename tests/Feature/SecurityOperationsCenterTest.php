<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\MaintenanceMode;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SecurityOperationsCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->admin->assignRole('super_admin');

        $this->withSession([
            'superadmin.2fa_verified_until' => now()->addMinutes(10)->timestamp,
            'superadmin.2fa_verified_user_id' => $this->admin->id,
        ]);
    }

    public function test_force_logout_user_invalidates_security_session_version_and_audits(): void
    {
        $target = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($this->admin);

        $this->postJson("/super-admin/security-center/users/{$target->id}/force-logout")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, $target->refresh()->security_session_version);
        $this->assertNotNull($target->force_logout_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'force_logout_user', 'subject_id' => $target->id]);

        $this->actingAs($target)->withSession(['security_session_version' => 0])
            ->get('/settings/profile')
            ->assertRedirect('/login');
    }

    public function test_force_logout_all_invalidates_every_user(): void
    {
        $target = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($this->admin);

        $this->postJson('/super-admin/security-center/force-logout-all')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, $target->refresh()->security_session_version);
        $this->assertSame(1, $this->admin->refresh()->security_session_version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'force_logout_all']);
    }

    public function test_api_key_can_be_rotated_once_and_old_hash_is_replaced(): void
    {
        $restaurant = Restaurant::factory()->create();
        [$oldPlainKey, $apiKey] = ApiKey::generate($restaurant->id, 'Operations test');
        $oldHash = $apiKey->key_hash;
        $this->actingAs($this->admin);

        $response = $this->postJson("/super-admin/security-center/api-keys/{$apiKey->id}/rotate");

        $response->assertOk()->assertJsonStructure(['plain_key']);
        $newPlainKey = $response->json('plain_key');
        $apiKey->refresh();

        $this->assertNotSame($oldPlainKey, $newPlainKey);
        $this->assertNotSame($oldHash, $apiKey->key_hash);
        $this->assertNull(ApiKey::findByPlainKey($oldPlainKey));
        $this->assertSame($apiKey->id, ApiKey::findByPlainKey($newPlainKey)?->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'api_key_rotate', 'subject_id' => $apiKey->id]);
    }

    public function test_operations_center_is_available_to_superadmin_and_has_operational_snapshot(): void
    {
        $this->actingAs($this->admin)
            ->get('/super-admin/operations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('super-admin/operations/Index')
                ->has('services')
                ->has('queue')
                ->has('webhooks')
                ->has('scheduler')
                ->has('probes'));
    }

    public function test_global_maintenance_blocks_tenant_requests_but_not_superadmin(): void
    {
        MaintenanceMode::create([
            'restaurant_id' => null,
            'enabled' => true,
            'message' => 'Đang nâng cấp hệ thống',
        ]);

        $tenantUser = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($tenantUser)
            ->get('/dashboard')
            ->assertStatus(503)
            ->assertSee('Đang nâng cấp hệ thống');

        $this->actingAs($this->admin)
            ->get('/super-admin/dashboard')
            ->assertOk();
    }
}
