<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * TRƯỚC ĐÂY KHÔNG TỒN TẠI: owner không có cách nào tạo/sửa/xoá chi nhánh —
 * kẹt cứng với chi nhánh duy nhất tạo lúc đăng ký, dù QuotaService/TenantQuotaMiddleware
 * đã hỗ trợ đầy đủ resource 'branches' từ trước nhưng chưa từng gắn route.
 */
class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create(['status' => 'active']);
        $this->restaurant->plan->update(['max_branches' => 2]);

        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->owner->assignRole($ownerRole);
    }

    public function test_owner_can_create_a_branch(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post(route('branches.store'), [
            'code' => 'CN02',
            'name' => 'Chi nhánh Quận 3',
            'phone' => '0900000000',
            'address' => '123 Đường ABC, Quận 3',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('restaurant_branches', [
            'restaurant_id' => $this->restaurant->id,
            'code' => 'CN02',
            'name' => 'Chi nhánh Quận 3',
            'status' => 'active',
        ]);
    }

    public function test_branch_creation_is_blocked_once_quota_reached(): void
    {
        RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id, 'code' => 'CN01']);
        RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id, 'code' => 'CN02']);

        $this->actingAs($this->owner);

        $response = $this->post(route('branches.store'), [
            'code' => 'CN03',
            'name' => 'Chi nhánh thứ 3 (vượt hạn mức)',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('restaurant_branches', ['code' => 'CN03']);
    }

    public function test_non_owner_cannot_manage_branches(): void
    {
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $manager->assignRole($managerRole);

        $this->actingAs($manager);

        $response = $this->post(route('branches.store'), [
            'code' => 'CN02',
            'name' => 'Chi nhánh trái phép',
        ]);

        $response->assertForbidden();
    }

    public function test_owner_cannot_delete_the_last_branch(): void
    {
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->actingAs($this->owner);

        $response = $this->delete(route('branches.destroy', $branch));

        $response->assertSessionHasErrors('branch');
        $this->assertDatabaseHas('restaurant_branches', ['id' => $branch->id]);
    }

    public function test_owner_cannot_delete_a_branch_with_employees(): void
    {
        $branchA = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $branchA->id,
        ]);

        $this->actingAs($this->owner);

        $response = $this->delete(route('branches.destroy', $branchA));

        $response->assertSessionHasErrors('branch');
        $this->assertDatabaseHas('restaurant_branches', ['id' => $branchA->id]);
    }

    public function test_owner_can_delete_empty_branch_when_another_exists(): void
    {
        RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $branchB = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->actingAs($this->owner);

        $response = $this->delete(route('branches.destroy', $branchB));

        $response->assertSessionHasNoErrors();
        $this->assertSoftDeleted('restaurant_branches', ['id' => $branchB->id]);
    }

    public function test_owner_cannot_manage_another_restaurants_branch(): void
    {
        $otherRestaurant = Restaurant::factory()->create(['status' => 'active']);
        $otherBranch = RestaurantBranch::factory()->create(['restaurant_id' => $otherRestaurant->id]);

        $this->actingAs($this->owner);

        // resolveRouteBinding() của BelongsToRestaurant đã tự scope theo
        // restaurant_id của user hiện tại — chi nhánh nhà hàng khác coi như
        // "không tồn tại" (404) ngay ở bước route-model-binding, trước khi vào
        // tới controller. An toàn hơn 403 vì không tiết lộ tài nguyên có tồn tại.
        $response = $this->delete(route('branches.destroy', $otherBranch));

        $response->assertNotFound();
    }
}
