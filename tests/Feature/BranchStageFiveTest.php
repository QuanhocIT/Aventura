<?php

namespace Tests\Feature;

use App\Console\Commands\AuditBranchData;
use App\Models\Area;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\OrderStatsCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchStageFiveTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branchA;

    private RestaurantBranch $branchB;

    private User $owner;

    private Area $areaA;

    private Product $productA;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $ownerRole->syncPermissions(Permission::all());
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create(['status' => 'active']);
        $this->branchA = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'STAGE5-A',
            'name' => 'Chi nhánh A',
        ]);
        $this->branchB = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'STAGE5-B',
            'name' => 'Chi nhánh B',
        ]);

        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->owner->assignRole($ownerRole);

        $this->areaA = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Khu A',
            'code' => 'STAGE5-A-AREA',
            'display_order' => 1,
            'status' => 'active',
        ]);

        $category = ProductCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
        ]);
        $this->productA = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'category_id' => $category->id,
            'track_inventory' => false,
        ]);
        $this->unit = Unit::factory()->create(['restaurant_id' => $this->restaurant->id]);
    }

    private function switchToBranch(RestaurantBranch $branch): void
    {
        $this->post(route('branch.switch'), ['branch_id' => $branch->id])->assertRedirect();
    }

    public function test_order_inventory_and_table_writes_keep_active_branch_id(): void
    {
        $this->actingAs($this->owner);
        $this->switchToBranch($this->branchA);

        $this->post(route('orders.store'), [
            'channel' => 'takeaway',
            'items' => [[
                'product_id' => $this->productA->id,
                'quantity' => 1,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
        ]);

        $this->post(route('inventory.ingredients.store'), [
            'name' => 'Nguyên liệu A',
            'unit_id' => $this->unit->id,
            'category' => 'Stage 5',
            'storage_type' => 'fresh',
            'default_shelf_life_days' => 3,
            'storage_location' => 'Tủ mát Bếp 1',
        ])->assertRedirect();

        $this->assertDatabaseHas('ingredients', [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Nguyên liệu A',
        ]);

        $this->post(route('tables.store'), [
            'name' => 'Bàn A1',
            'area_id' => $this->areaA->id,
            'capacity' => 4,
            'branch_id' => $this->branchA->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('restaurant_tables', [
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Bàn A1',
        ]);
    }

    public function test_ingredient_cannot_be_created_without_storage_location_and_shelf_life(): void
    {
        $this->actingAs($this->owner);
        $this->switchToBranch($this->branchA);

        $response = $this->from('/inventory')
            ->post(route('inventory.ingredients.store'), [
                'name' => 'Nguyên liệu thiếu thông tin bảo quản',
                'unit_id' => $this->unit->id,
                'storage_type' => 'fresh',
            ]);

        $response->assertRedirect('/inventory');
        $response->assertSessionHasErrors([
            'default_shelf_life_days',
            'storage_location',
        ]);
        $this->assertDatabaseMissing('ingredients', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Nguyên liệu thiếu thông tin bảo quản',
        ]);
    }

    public function test_branch_a_cannot_read_branch_b_and_manager_cannot_switch_to_b(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'table_id' => null,
            'order_number' => 'STAGE5-A-ORDER',
        ]);
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchB->id,
            'table_id' => null,
            'order_number' => 'STAGE5-B-ORDER',
        ]);

        $this->actingAs($this->owner);
        $this->switchToBranch($this->branchA);

        $response = $this->get(route('orders.index'));
        $orderNumbers = collect($response->original->getData()['page']['props']['orders'])
            ->pluck('order_number')
            ->all();

        $this->assertContains('STAGE5-A-ORDER', $orderNumbers);
        $this->assertNotContains('STAGE5-B-ORDER', $orderNumbers);

        $managerRole = Role::findByName('manager', 'web');
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $manager->assignRole($managerRole);
        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'user_id' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->post(route('branch.switch'), ['branch_id' => $this->branchB->id])
            ->assertForbidden();
    }

    public function test_branch_cache_keys_and_values_are_isolated(): void
    {
        foreach ([[$this->branchA, 'CACHE-A'], [$this->branchB, 'CACHE-B']] as [$branch, $number]) {
            Order::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $branch->id,
                'table_id' => null,
                'order_number' => $number,
                'status' => 'completed',
                'created_at' => now(),
            ]);
        }

        $cache = app(OrderStatsCacheService::class);
        $statsA = $cache->getTodayStats($this->restaurant->id, $this->branchA->id);
        $statsB = $cache->getTodayStats($this->restaurant->id, $this->branchB->id);

        $this->assertSame(1, $statsA['total']);
        $this->assertSame(1, $statsB['total']);
        $this->assertNotSame(
            $cache->buildKey($this->restaurant->id, $this->branchA->id),
            $cache->buildKey($this->restaurant->id, $this->branchB->id),
        );
    }

    public function test_branch_audit_lists_null_branch_rows_without_mutating_them(): void
    {
        $legacyArea = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'name' => 'Khu cũ chưa xác định',
            'code' => 'STAGE5-UNRESOLVED',
            'display_order' => 99,
            'status' => 'active',
        ]);

        $report = app(AuditBranchData::class)
            ->buildReport($this->restaurant->id);
        $areaFinding = collect($report['tables'])->firstWhere('table', 'areas');

        $this->assertNotNull($areaFinding);
        $this->assertGreaterThanOrEqual(1, $areaFinding['missing_branch_id']);
        $this->assertDatabaseHas('areas', [
            'id' => $legacyArea->id,
            'branch_id' => null,
        ]);
    }
}
