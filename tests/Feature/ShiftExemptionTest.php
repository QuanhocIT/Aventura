<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
