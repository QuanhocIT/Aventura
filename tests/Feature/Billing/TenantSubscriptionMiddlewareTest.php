<?php

namespace Tests\Feature\Billing;

use App\Http\Middleware\CheckTenantSubscription;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantSubscriptionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', CheckTenantSubscription::class])->get('/_test/subscription-read', fn () => response()->json(['ok' => true]));
        Route::middleware(['web', 'auth', CheckTenantSubscription::class])->post('/_test/subscription-write', fn () => response()->json(['ok' => true]));
    }

    public function test_expired_tenant_can_read_but_cannot_write(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $owner = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner->assignRole($role);

        $restaurant = Restaurant::factory()->create([
            'plan_id' => $plan->id,
            'owner_user_id' => $owner->id,
            'status' => 'expired',
        ]);
        $owner->update(['restaurant_id' => $restaurant->id]);
        $owner = $owner->fresh();
        $token = 'test-csrf-token';

        $this->actingAs($owner);

        $this->getJson('/_test/subscription-read')->assertOk();
        $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson('/_test/subscription-write')
            ->assertStatus(402);
    }

    public function test_suspended_tenant_is_blocked(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $owner = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner->assignRole($role);

        $restaurant = Restaurant::factory()->create([
            'plan_id' => $plan->id,
            'owner_user_id' => $owner->id,
            'status' => 'suspended',
        ]);
        $owner->update(['restaurant_id' => $restaurant->id]);
        $owner = $owner->fresh();
        $token = 'test-csrf-token';

        $this->actingAs($owner);

        $this->getJson('/_test/subscription-read')->assertStatus(403);
        $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson('/_test/subscription-write')
            ->assertStatus(403);
    }
}
