<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Làm sâu kiểm soát chi nhánh:
 *  - Tạm ngưng món: ghi nhật ký (audit) + lưu lý do; mở lại cũng ghi nhật ký.
 *  - Báo hao hụt (waste): BẮT BUỘC lý do (waste_category), 'other' phải kèm ghi chú.
 */
class BranchDeepControlsTest extends TestCase
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
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->forceFill(['branch_id' => $this->branch->id])->save();
    }

    public function test_pause_logs_and_stores_reason(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'manage_kitchen', 'guard_name' => 'web']);
        $this->owner->givePermissionTo($perm);

        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->owner)->post("/kitchen/products/{$product->id}/pause", [
            'minutes' => 30,
            'reason' => 'Hết nguyên liệu tạm thời',
        ])->assertRedirect();

        $product->refresh();
        $this->assertNotNull($product->paused_until);
        $this->assertEquals('Hết nguyên liệu tạm thời', $product->pause_reason);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'kitchen_product_paused',
            'subject_id' => $product->id,
        ]);

        // Mở lại cũng ghi nhật ký và xoá lý do.
        $this->actingAs($this->owner)->post("/kitchen/products/{$product->id}/resume")
            ->assertRedirect();
        $product->refresh();
        $this->assertNull($product->paused_until);
        $this->assertNull($product->pause_reason);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'kitchen_product_resumed',
            'subject_id' => $product->id,
        ]);
    }

    public function test_waste_requires_category(): void
    {
        $unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Kg', 'symbol' => 'kg', 'type' => 'weight',
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'unit_id' => $unit->id,
            'name' => 'Thịt bò', 'sku' => 'BO-01',
            'average_cost' => 250000, 'status' => 'active',
        ]);

        // Thiếu waste_category → lỗi validation.
        $this->actingAs($this->owner)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->from('/inventory')
            ->post('/inventory/waste', [
                'ingredient_id' => $ingredient->id,
                'quantity' => 1,
            ])->assertSessionHasErrors(['waste_category']);

        // waste_category = other nhưng không có ghi chú → lỗi.
        $this->actingAs($this->owner)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->from('/inventory')
            ->post('/inventory/waste', [
                'ingredient_id' => $ingredient->id,
                'quantity' => 1,
                'waste_category' => 'other',
            ])->assertSessionHasErrors(['notes']);

        // Có lý do đầy đủ nhưng THIẾU ẢNH hàng hủy → lỗi (bằng chứng bắt buộc).
        $this->actingAs($this->owner)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->from('/inventory')
            ->post('/inventory/waste', [
                'ingredient_id' => $ingredient->id,
                'quantity' => 1,
                'waste_category' => 'spoilage',
            ])->assertSessionHasErrors(['photo']);
    }
}
