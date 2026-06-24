<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TablesFloorPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private Area $area;
    private RestaurantTable $table;
    private Role $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);
        
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['branch_id' => $this->branch->id]);

        $this->ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->owner->assignRole($this->ownerRole);

        $this->area = Area::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->table = RestaurantTable::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $this->area->id,
            'status' => 'available',
            'x_pos' => 50,
            'y_pos' => 50,
        ]);
    }

    public function test_owner_can_update_table_coordinates_and_status(): void
    {
        $response = $this->actingAs($this->owner)->patch(route('tables.update', $this->table->id), [
            'x_pos' => 25,
            'y_pos' => 45,
            'status' => 'cleaning',
        ]);

        $response->assertRedirect();
        
        $freshTable = $this->table->fresh();
        $this->assertEquals(25, $freshTable->x_pos);
        $this->assertEquals(45, $freshTable->y_pos);
        $this->assertEquals('cleaning', $freshTable->status);
    }

    public function test_table_coordinates_validation_rules(): void
    {
        // Test out of bounds (negative)
        $response = $this->actingAs($this->owner)->patch(route('tables.update', $this->table->id), [
            'x_pos' => -5,
        ]);
        $response->assertSessionHasErrors(['x_pos']);

        // Test out of bounds (greater than 100)
        $response = $this->actingAs($this->owner)->patch(route('tables.update', $this->table->id), [
            'y_pos' => 105,
        ]);
        $response->assertSessionHasErrors(['y_pos']);

        // Test invalid status value
        $response = $this->actingAs($this->owner)->patch(route('tables.update', $this->table->id), [
            'status' => 'waiting_guests',
        ]);
        $response->assertSessionHasErrors(['status']);
    }

    public function test_non_owner_cannot_update_table_coordinates(): void
    {
        $regularUser = User::factory()->create(['status' => 'active']);
        
        $response = $this->actingAs($regularUser)->patch(route('tables.update', $this->table->id), [
            'x_pos' => 10,
            'y_pos' => 10,
        ]);

        $response->assertStatus(403);
        
        $freshTable = $this->table->fresh();
        $this->assertEquals(50, $freshTable->x_pos);
        $this->assertEquals(50, $freshTable->y_pos);
    }
}
