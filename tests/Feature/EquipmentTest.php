<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage cho module Thiết bị (equipment) — trước đây chưa có test riêng.
 * destroy() KHÔNG check quyền thủ công → dựa vào tenant-scoping ở route binding
 * (BelongsToRestaurant::resolveRouteBinding). Test tenant-scoping bảo vệ chống IDOR.
 */
class EquipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
    }

    public function test_equipment_page_renders(): void
    {
        $this->actingAs($this->owner)->get('/equipment')->assertOk();
    }

    public function test_owner_can_create_equipment(): void
    {
        $res = $this->actingAs($this->owner)->post('/equipment', [
            'name' => 'Tủ đông Sanaky',
            'category' => 'refrigeration',
            'brand' => 'Sanaky',
        ]);

        $res->assertRedirect();
        $res->assertSessionHasNoErrors();
        $this->assertDatabaseHas('equipment', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Tủ đông Sanaky',
            'category' => 'refrigeration',
        ]);
    }

    public function test_create_equipment_validates_category(): void
    {
        $this->actingAs($this->owner)->post('/equipment', [
            'name' => 'Sai loại',
            'category' => 'khong-hop-le',
        ])->assertSessionHasErrors('category');
    }

    public function test_owner_can_delete_own_equipment(): void
    {
        $eq = Equipment::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Bếp gas',
            'category' => 'kitchen',
        ]);

        $this->actingAs($this->owner)->delete("/equipment/{$eq->id}")->assertRedirect();
        $this->assertDatabaseMissing('equipment', ['id' => $eq->id]);
    }

    public function test_equipment_is_tenant_scoped(): void
    {
        $other = Restaurant::factory()->create();
        $foreign = Equipment::create([
            'restaurant_id' => $other->id,
            'name' => 'Thiết bị nhà hàng khác',
            'category' => 'kitchen',
        ]);

        // Yêu cầu bảo mật: không xoá được thiết bị của nhà hàng khác (IDOR fix).
        $this->actingAs($this->owner)->delete("/equipment/{$foreign->id}");
        $this->assertDatabaseHas('equipment', ['id' => $foreign->id]);
    }
}
