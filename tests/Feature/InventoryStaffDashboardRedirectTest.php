<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryStaffDashboardRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_staff_is_redirected_from_dashboard_to_inventory_page(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $role = Role::firstOrCreate(['name' => 'inventory_staff', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('inventory.index'));
    }

    public function test_owner_is_not_redirected_to_inventory_page(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $owner->update(['restaurant_id' => $restaurant->id]);

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner->assignRole($ownerRole);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk();
    }
}
