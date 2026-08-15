<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage cho module Thiết bị (equipment) — trước đây chưa có test riêng.
 * Các endpoint ghi dữ liệu phải qua permission; route binding vẫn bảo vệ tenant
 * để không thể thao tác lên thiết bị của nhà hàng khác.
 */
class EquipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
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

    public function test_cashier_cannot_create_or_delete_equipment(): void
    {
        $cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        $eq = Equipment::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Bếp phụ',
            'category' => 'kitchen',
        ]);

        $this->actingAs($cashier)->post('/equipment', [
            'name' => 'Không được tạo',
            'category' => 'kitchen',
        ])->assertForbidden();

        $this->actingAs($cashier)->delete("/equipment/{$eq->id}")->assertForbidden();
        $this->assertDatabaseHas('equipment', ['id' => $eq->id]);
    }

    public function test_cashier_can_report_equipment_issue(): void
    {
        $cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        $eq = Equipment::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Máy POS',
            'category' => 'pos',
        ]);

        $this->actingAs($cashier)->post('/equipment/report-issue', [
            'equipment_id' => $eq->id,
            'title' => 'Màn hình không lên',
            'type' => 'repair',
        ])->assertRedirect();

        $this->assertDatabaseHas('equipment_maintenance_logs', [
            'equipment_id' => $eq->id,
            'reported_by' => $cashier->id,
            'status' => 'pending',
        ]);
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
