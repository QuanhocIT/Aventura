<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseAiRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'warehouse_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);
        $this->restaurant = Restaurant::factory()->create();
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
            'status' => 'active',
        ]);
        $unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);
        Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $central->id,
            'unit_id' => $unit->id,
            'name' => 'AI stock signal',
            'sku' => 'AI-STOCK-01',
            'min_stock_level' => 10,
            'reorder_level' => 10,
            'average_cost' => 100,
            'status' => 'active',
        ]);
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_branch_id' => $central->id,
            'status' => 'active',
        ]);
        $this->manager->assignRole('warehouse_manager');
    }

    public function test_warehouse_manager_receives_data_backed_recommendations_with_next_action(): void
    {
        $response = $this->actingAs($this->manager)
            ->getJson(route('warehouse.ai-recommendations'));

        $response->assertOk()
            ->assertJsonStructure([
                'ai' => [
                    'engine',
                    'score',
                    'level',
                    'signals',
                    'confidence',
                    'disclaimer',
                ],
            ]);

        $signals = collect($response->json('ai.signals'));
        $this->assertTrue($signals->contains(fn (array $signal): bool => ($signal['source'] ?? null) === 'inventory_forecast'));
        $this->assertTrue($signals->contains(fn (array $signal): bool => isset($signal['action_url'], $signal['action_label'])));
    }

    public function test_non_warehouse_user_cannot_read_warehouse_ai_recommendations(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $user->assignRole('waiter');

        $this->actingAs($user)
            ->getJson(route('warehouse.ai-recommendations'))
            ->assertForbidden();
    }
}
