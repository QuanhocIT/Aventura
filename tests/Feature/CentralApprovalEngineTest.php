<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Ingredient;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralApprovalEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_inventory_operations_require_owner_approval()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $unit = Unit::factory()->create(['restaurant_id' => $restaurant->id, 'type' => 'mass']);

        $owner = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);
        $owner->assignRole('owner');

        $manager = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);
        $manager->assignRole('manager');

        // 1. Manager attempts to store new ingredient -> Approval Request created
        $response = $this->actingAs($manager)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('inventory.ingredients.store'), [
                'name' => 'Thịt Bò Mỹ Cắt Lát',
                'unit_id' => $unit->id,
                'category' => 'Thịt tươi',
                'storage_type' => 'fresh',
                'default_shelf_life_days' => 3,
                'storage_location' => 'Tủ mát Bếp 1',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('approval_requests', [
            'restaurant_id' => $restaurant->id,
            'requester_id' => $manager->id,
            'operation_type' => 'inventory_create',
            'status' => 'pending',
        ]);

        $approval = ApprovalRequest::where('operation_type', 'inventory_create')->first();

        // 2. Owner approves the pending request using PATCH method
        $approveResponse = $this->actingAs($owner)
            ->withSession(['active_branch_id' => $branch->id])
            ->patch(route('approvals.approve', $approval));
        $approveResponse->assertRedirect();

        $this->assertDatabaseHas('approval_requests', [
            'id' => $approval->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('ingredients', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Thịt Bò Mỹ Cắt Lát',
        ]);
    }
}
