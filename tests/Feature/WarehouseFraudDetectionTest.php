<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\WarehouseFraudDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseFraudDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_fraud_detection_service_calculates_risk_scores_and_alerts()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);
        $user->assignRole('warehouse_staff');

        $unit = Unit::create(['restaurant_id' => $restaurant->id, 'name' => 'kg', 'symbol' => 'kg', 'type' => 'mass']);
        $ingredient = Ingredient::create(['restaurant_id' => $restaurant->id, 'unit_id' => $unit->id, 'name' => 'Tôm Hùm', 'sku' => 'LOBSTER']);

        // Create 3 disputes assigned to $user
        for ($i = 0; $i < 3; $i++) {
            InventoryDiscrepancyDispute::create([
                'restaurant_id' => $restaurant->id,
                'dispute_code' => "DSP-20260812-000{$i}",
                'ingredient_id' => $ingredient->id,
                'dispatched_quantity' => 10,
                'received_quantity' => 8,
                'discrepancy_quantity' => 2,
                'financial_loss_amount' => 1000000,
                'responsible_type' => 'user',
                'responsible_user_id' => $user->id,
                'status' => 'open',
            ]);
        }

        $service = app(WarehouseFraudDetectionService::class);
        $result = $service->analyzeRiskAndFraudPatterns($restaurant->id);

        $this->assertArrayHasKey('risk_alerts', $result);
        $this->assertArrayHasKey('staff_risk_scores', $result);

        // Staff risk score for $user should be HIGH_RISK (score >= 60)
        $userScore = collect($result['staff_risk_scores'])->firstWhere('user_id', $user->id);
        $this->assertNotNull($userScore);
        $this->assertEquals(60, $userScore['risk_score']);
        $this->assertEquals('HIGH_RISK', $userScore['risk_level']);
    }
}
