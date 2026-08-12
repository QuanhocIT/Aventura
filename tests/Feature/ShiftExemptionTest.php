<?php

namespace Tests\Feature;

use App\Http\Middleware\SetTenantContext;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ShiftExemptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_manager_and_operations_inspector_are_exempt_from_shift_lock()
    {
        $restaurant = Restaurant::factory()->create();

        // 1. Warehouse Manager User
        $warehouseManager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);
        $warehouseManager->assignRole('warehouse_manager');

        // 2. Operations Inspector User
        $inspector = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);
        $inspector->assignRole('operations_inspector');

        // Verify exemption method returns true
        $this->assertTrue($warehouseManager->isExemptFromShiftLock());
        $this->assertTrue($inspector->isExemptFromShiftLock());
    }

    public function test_manager_and_warehouse_manager_keep_access_after_shift_expiry(): void
    {
        $restaurant = Restaurant::factory()->create();

        foreach (['manager', 'warehouse_manager'] as $role) {
            $user = User::factory()->create([
                'restaurant_id' => $restaurant->id,
            ]);
            $user->assignRole($role);

            SetTenantContext::$enforceShiftLockInTests = true;

            session([
                'employee_id' => 999,
                'shift_allowed_until' => now()->subMinute()->timestamp,
            ]);

            $request = Request::create('/dashboard', 'GET');
            $request->setUserResolver(fn () => $user);
            $response = app(SetTenantContext::class)->handle(
                $request,
                fn () => response()->json(['ok' => true]),
            );

            $this->assertSame(200, $response->getStatusCode());
            $this->assertNull(session('shift_allowed_until'));
        }

        SetTenantContext::$enforceShiftLockInTests = false;
    }
}
