<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Ingredient;
use App\Models\IngredientPriceHistory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralWarehousePriceGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_manager_can_submit_price_proposal_without_applying_it(): void
    {
        [$restaurant, $ingredient] = $this->makeCatalog();
        $manager = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $manager->assignRole('warehouse_manager');

        $this->actingAs($manager)
            ->postJson(route('warehouse.ingredient-prices.propose'), [
                'prices' => [[
                    'ingredient_id' => $ingredient->id,
                    'average_cost' => 120000,
                ]],
                'reason' => 'Theo báo giá nhà cung cấp mới tháng này.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(100000.0, (float) $ingredient->refresh()->average_cost);
        $this->assertDatabaseHas('approval_requests', [
            'restaurant_id' => $restaurant->id,
            'operation_type' => 'warehouse_price_update',
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);
        $approval = ApprovalRequest::latest('id')->firstOrFail();
        $this->assertSame('Theo báo giá nhà cung cấp mới tháng này.', $approval->operation_data['reason']);
    }

    public function test_owner_update_applies_price_and_records_history(): void
    {
        [$restaurant, $ingredient] = $this->makeCatalog();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');
        $restaurant->update(['owner_user_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson(route('warehouse.ingredient-prices.update'), [
                'prices' => [[
                    'ingredient_id' => $ingredient->id,
                    'average_cost' => 115000,
                ]],
                'reason' => 'Cập nhật theo hóa đơn nhập kho gần nhất.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(115000.0, (float) $ingredient->refresh()->average_cost);
        $this->assertDatabaseHas('ingredient_price_histories', [
            'restaurant_id' => $restaurant->id,
            'ingredient_id' => $ingredient->id,
            'old_price' => 100000,
            'new_price' => 115000,
            'status' => 'approved',
            'change_reason' => 'Cập nhật theo hóa đơn nhập kho gần nhất.',
        ]);
        $this->assertSame(1, IngredientPriceHistory::where('ingredient_id', $ingredient->id)->count());
    }

    public function test_owner_approval_applies_proposal_and_links_both_actors_in_history(): void
    {
        [$restaurant, $ingredient] = $this->makeCatalog();
        $manager = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $manager->assignRole('warehouse_manager');
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');
        $restaurant->update(['owner_user_id' => $owner->id]);

        $this->actingAs($manager)->postJson(route('warehouse.ingredient-prices.propose'), [
            'prices' => [['ingredient_id' => $ingredient->id, 'average_cost' => 125000]],
            'reason' => 'Nhà cung cấp cập nhật bảng giá đầu vào.',
        ])->assertOk();
        $approval = ApprovalRequest::latest('id')->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('approvals.approve', $approval))
            ->assertRedirect();

        $this->assertSame(125000.0, (float) $ingredient->refresh()->average_cost);
        $history = IngredientPriceHistory::where('ingredient_id', $ingredient->id)->latest('id')->firstOrFail();
        $this->assertSame($manager->id, $history->changed_by);
        $this->assertSame($owner->id, $history->approved_by);
        $this->assertSame('approved', $history->status);
    }

    /** @return array{0: Restaurant, 1: Ingredient} */
    private function makeCatalog(): array
    {
        $restaurant = Restaurant::factory()->create();
        RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
            'status' => 'active',
        ]);
        $unit = Unit::factory()->create(['restaurant_id' => $restaurant->id, 'symbol' => 'kg']);
        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'unit_id' => $unit->id,
            'average_cost' => 100000,
            'status' => 'active',
        ]);

        return [$restaurant, $ingredient];
    }
}
