<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinancialAuthorityTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $managerRole = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ]);
        $managerRole->givePermissionTo(Permission::firstOrCreate([
            'name' => 'manage_salary',
            'guard_name' => 'web',
        ]));

        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->manager->assignRole($managerRole);
    }

    public function test_manager_cannot_change_product_price_or_cogs_mode(): void
    {
        $category = ProductCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Món chính',
            'slug' => 'mon-chinh',
            'status' => 'active',
        ]);
        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'code' => 'MON-01',
            'name' => 'Món kiểm soát',
            'slug' => 'mon-kiem-soat',
            'price' => 50000,
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->actingAs($this->manager)
            ->patch(route('products.update', $product), [
                'price' => 99000,
            ])
            ->assertForbidden();

        $this->assertSame(50000.0, (float) $product->refresh()->price);
    }

    public function test_warehouse_manager_cannot_write_central_warehouse_prices(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'warehouse_manager',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo(Permission::firstOrCreate([
            'name' => 'warehouse.manage',
            'guard_name' => 'web',
        ]));

        $warehouseManager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $warehouseManager->assignRole($role);

        $this->actingAs($warehouseManager)
            ->postJson(route('warehouse.ingredient-prices.update'), [
                'prices' => [[
                    'ingredient_id' => 1,
                    'average_cost' => 12345,
                ]],
            ])
            ->assertForbidden();
    }

    public function test_manager_cannot_write_operating_expense(): void
    {
        $this->actingAs($this->manager)
            ->post(route('expenses.store'), [
                'amount' => 100000,
                'expense_date' => now()->toDateString(),
                'description' => 'Khoản chi thử nghiệm',
            ])
            ->assertForbidden();
    }

    public function test_manager_cannot_approve_payroll(): void
    {
        $this->actingAs($this->manager)
            ->post(route('salaries.approve-bulk'), [
                'salary_ids' => [1],
            ])
            ->assertForbidden();
    }
}
