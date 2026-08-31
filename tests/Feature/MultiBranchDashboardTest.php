<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantRevenueSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiBranchDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch1;

    private RestaurantBranch $branch2;

    private Role $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
        ]);
        $this->restaurant->plan->update([
            'max_branches' => 5,
            'features' => array_merge($this->restaurant->plan->features ?? [], [
                'advanced_analytics' => true,
            ]),
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->branch1 = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
            'name' => 'Chi nhánh Q1',
        ]);

        $this->branch2 = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
            'name' => 'Chi nhánh Q3',
        ]);

        $this->owner->update(['branch_id' => $this->branch1->id]);

        $this->ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->owner->assignRole($this->ownerRole);
    }

    public function test_dashboard_displays_consolidated_metrics_and_branch_comparisons(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard'));

        $response->assertOk();

        $page = $response->original->getData()['page'];
        $this->assertEquals('Dashboard', $page['component']);

        $props = $page['props'];
        $this->assertNull($props['branchId']);
        $this->assertCount(2, $props['branches']);
        $this->assertCount(2, $props['branchComparisons']);

        // Assert details of branch comparisons are present
        $this->assertEquals('Chi nhánh Q1', $props['branchComparisons'][0]['name']);
        $this->assertEquals('Chi nhánh Q3', $props['branchComparisons'][1]['name']);
    }

    public function test_dashboard_filters_metrics_when_branch_id_is_provided(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard', ['branch_id' => $this->branch1->id]));

        $response->assertOk();

        $page = $response->original->getData()['page'];
        $props = $page['props'];

        $this->assertEquals($this->branch1->id, $props['branchId']);
        $this->assertCount(2, $props['branches']);
        // Comparisons table should be empty when filtering by a specific branch
        $this->assertEmpty($props['branchComparisons']);
    }

    public function test_dashboard_switches_between_chain_summary_and_branch_summary(): void
    {
        $date = today()->toDateString();

        RestaurantRevenueSummary::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'scope_key' => 'restaurant',
            'summary_type' => 'daily',
            'summary_date' => $date,
            'net_revenue' => 300000,
            'gross_profit' => 120000,
            'completed_order_count' => 3,
        ]);
        RestaurantRevenueSummary::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch1->id,
            'scope_key' => "branch:{$this->branch1->id}",
            'summary_type' => 'daily',
            'summary_date' => $date,
            'net_revenue' => 100000,
            'gross_profit' => 40000,
            'completed_order_count' => 1,
        ]);
        RestaurantRevenueSummary::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch2->id,
            'scope_key' => "branch:{$this->branch2->id}",
            'summary_type' => 'daily',
            'summary_date' => $date,
            'net_revenue' => 200000,
            'gross_profit' => 80000,
            'completed_order_count' => 2,
        ]);

        $allBranches = $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->original
            ->getData()['page']['props'];

        $this->assertNull($allBranches['branchId']);
        $this->assertSame(300000.0, $allBranches['stats']['revenue_today']);

        $this->post(route('branch.switch'), ['branch_id' => $this->branch1->id])
            ->assertRedirect();

        $branch = $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->original
            ->getData()['page']['props'];

        $this->assertSame($this->branch1->id, $branch['branchId']);
        $this->assertSame(100000.0, $branch['stats']['revenue_today']);

        $this->post(route('branch.switch'), ['scope' => 'all'])
            ->assertRedirect();

        $allAgain = $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->original
            ->getData()['page']['props'];

        $this->assertNull($allAgain['branchId']);
        $this->assertSame(300000.0, $allAgain['stats']['revenue_today']);
    }

    public function test_primary_cards_follow_the_selected_scope(): void
    {
        Queue::fake();

        Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'category_id' => null,
            'is_active' => true,
            'is_available' => true,
        ]);
        Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch1->id,
            'category_id' => null,
            'is_active' => true,
            'is_available' => true,
        ]);
        Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch2->id,
            'category_id' => null,
            'is_active' => true,
            'is_available' => true,
        ]);
        Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch1->id,
            'category_id' => null,
            'is_active' => false,
            'is_available' => true,
        ]);
        Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch2->id,
            'category_id' => null,
            'is_active' => true,
            'is_available' => false,
        ]);

        $this->createDashboardOrder($this->branch1, 'completed', 100000);
        $this->createDashboardOrder($this->branch1, 'pending', 50000);
        $this->createDashboardOrder($this->branch2, 'completed', 200000);

        $all = $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->original
            ->getData()['page']['props'];

        $this->assertSame(3, $all['stats']['orders_today']);
        $this->assertSame(2, $all['stats']['orders_completed']);
        $this->assertSame(300000.0, $all['stats']['revenue_today']);
        $this->assertSame(3, $all['stats']['products_count']);

        $this->post(route('branch.switch'), ['branch_id' => $this->branch1->id])
            ->assertRedirect();

        $branch = $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->original
            ->getData()['page']['props'];

        $this->assertSame($this->branch1->id, $branch['branchId']);
        $this->assertSame(2, $branch['stats']['orders_today']);
        $this->assertSame(1, $branch['stats']['orders_completed']);
        $this->assertSame(100000.0, $branch['stats']['revenue_today']);
        $this->assertSame(2, $branch['stats']['products_count']);
    }

    private function createDashboardOrder(RestaurantBranch $branch, string $status, float $total): Order
    {
        $now = now();

        return Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $branch->id,
            'table_id' => null,
            'customer_id' => null,
            'created_by' => $this->owner->id,
            'cashier_user_id' => $this->owner->id,
            'status' => $status,
            'payment_status' => $status === 'completed' ? 'paid' : 'unpaid',
            'subtotal' => $total,
            'discount_amount' => 0,
            'total_amount' => $total,
            'created_at' => $now,
            'updated_at' => $now,
            'completed_at' => $status === 'completed' ? $now : null,
        ]);
    }

    public function test_employee_restricted_to_assigned_branch(): void
    {
        $employeeRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $employeeUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch2->id,
            'status' => 'active',
        ]);
        $employeeUser->assignRole($employeeRole);

        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch2->id,
            'user_id' => $employeeUser->id,
            'role_id' => $employeeRole->id,
            'status' => 'active',
        ]);

        // Employee (manager) attempts to view branch1, but should be restricted to branch2
        $response = $this->actingAs($employeeUser)->get(route('dashboard', ['branch_id' => $this->branch1->id]));

        $response->assertOk();

        $page = $response->original->getData()['page'];
        $props = $page['props'];

        // Assert that branchId defaulted back to employee's assigned branch2
        $this->assertEquals($this->branch2->id, $props['branchId']);
    }
}
