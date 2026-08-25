<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Support\CashControlSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Chủ cấu hình kiểm soát tiền mặt cuối ca (đếm mù, ngưỡng giải trình/ảnh, bàn giao).
 */
class CashControlSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_cash_control_settings(): void
    {
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id, 'status' => 'active']);
        $owner->assignRole($ownerRole);
        $restaurant->update(['owner_user_id' => $owner->id]);

        $this->actingAs($owner)->post('/shift-closings/cash-control', [
            'blind_cash_count_enabled' => false,
            'cash_variance_threshold' => 50000,
            'cash_evidence_threshold' => 300000,
            'cash_handover_required' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse(CashControlSettings::blindCountEnabled($restaurant->id));
        $this->assertEqualsWithDelta(50000, CashControlSettings::varianceThreshold($restaurant->id), 1);
        $this->assertEqualsWithDelta(300000, CashControlSettings::evidenceThreshold($restaurant->id), 1);
        $this->assertTrue(CashControlSettings::handoverRequired($restaurant->id));
    }

    public function test_manager_cannot_update_cash_control(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $restaurant = Restaurant::factory()->create();
        $manager = User::factory()->create(['restaurant_id' => $restaurant->id, 'status' => 'active']);
        $manager->assignRole($managerRole);

        $this->actingAs($manager)->post('/shift-closings/cash-control', [
            'blind_cash_count_enabled' => false,
            'cash_variance_threshold' => 0,
            'cash_evidence_threshold' => 0,
            'cash_handover_required' => false,
        ])->assertForbidden();
    }
}
