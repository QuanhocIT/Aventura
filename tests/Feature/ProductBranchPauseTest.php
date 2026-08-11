<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBranchPause;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tạm ngưng bán món theo RIÊNG từng chi nhánh + duyệt mở lại: bếp khóa món ở chi nhánh
 * mình (không ảnh hưởng chi nhánh khác); bếp chỉ ĐỀ NGHỊ mở lại, Quản lý/Chủ mới DUYỆT.
 */
class ProductBranchPauseTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $manager;
    private User $kitchen;
    private Restaurant $restaurant;
    private RestaurantBranch $branchA;
    private RestaurantBranch $branchB;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $perm = Permission::firstOrCreate(['name' => 'manage_kitchen', 'guard_name' => 'web']);
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);
        $managerRole->givePermissionTo($perm);
        $kitchenRole->givePermissionTo($perm);

        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);

        $this->branchA = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->branchB = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);

        $this->manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchA->id, 'status' => 'active']);
        $this->manager->assignRole($managerRole);
        $this->kitchen = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchA->id, 'status' => 'active']);
        $this->kitchen->assignRole($kitchenRole);

        $this->product = Product::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => null]);
    }

    public function test_pause_is_isolated_to_branch(): void
    {
        $this->actingAs($this->kitchen)->post("/kitchen/products/{$this->product->id}/pause-branch", [
            'reason' => 'Hết nguyên liệu tại chi nhánh A',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Có tạm ngưng ở A, KHÔNG có ở B.
        $this->assertTrue(ProductBranchPause::where('branch_id', $this->branchA->id)->where('product_id', $this->product->id)->activePause()->exists());
        $this->assertFalse(ProductBranchPause::where('branch_id', $this->branchB->id)->where('product_id', $this->product->id)->activePause()->exists());
    }

    public function test_pause_requires_reason(): void
    {
        $this->actingAs($this->kitchen)->from('/kitchen')
            ->post("/kitchen/products/{$this->product->id}/pause-branch", [])
            ->assertSessionHasErrors(['reason']);
    }

    public function test_kitchen_requests_reopen_but_cannot_approve(): void
    {
        ProductBranchPause::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id, 'reason' => 'x', 'paused_by' => $this->kitchen->id, 'status' => 'active',
        ]);

        // Bếp đề nghị mở lại → chuyển 'reopen_requested'.
        $this->actingAs($this->kitchen)->post("/kitchen/products/{$this->product->id}/request-reopen")
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('reopen_requested', ProductBranchPause::where('product_id', $this->product->id)->first()->status);

        // Bếp KHÔNG được tự duyệt mở lại.
        $this->actingAs($this->kitchen)->post("/kitchen/products/{$this->product->id}/approve-reopen")
            ->assertForbidden();
        $this->assertEquals('reopen_requested', ProductBranchPause::where('product_id', $this->product->id)->first()->status);
    }

    public function test_manager_approves_reopen(): void
    {
        ProductBranchPause::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id, 'reason' => 'x', 'paused_by' => $this->kitchen->id, 'status' => 'reopen_requested',
        ]);

        $this->actingAs($this->manager)->post("/kitchen/products/{$this->product->id}/approve-reopen")
            ->assertRedirect()->assertSessionHasNoErrors();

        $pause = ProductBranchPause::where('product_id', $this->product->id)->first();
        $this->assertEquals('reopened', $pause->status);
        $this->assertEquals($this->manager->id, $pause->reopened_by);
        // Không còn tạm ngưng đang hiệu lực.
        $this->assertFalse(ProductBranchPause::where('product_id', $this->product->id)->activePause()->exists());
    }

    public function test_owner_without_branch_cannot_pause_chainwide(): void
    {
        // Chủ ở phạm vi tất cả chi nhánh (không set active branch) → chặn 422.
        $this->actingAs($this->owner)->post("/kitchen/products/{$this->product->id}/pause-branch", [
            'reason' => 'Thử tạm ngưng toàn chuỗi',
        ])->assertStatus(422);
    }
}
