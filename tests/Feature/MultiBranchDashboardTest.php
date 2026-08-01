<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
