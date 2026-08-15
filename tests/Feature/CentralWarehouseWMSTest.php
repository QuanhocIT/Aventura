<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryCountSession;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseFraudCase;
use App\Models\WarehouseLocation;
use App\Services\CentralWarehouseService;
use App\Services\InventoryCountService;
use App\Services\WarehouseFraudDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralWarehouseWMSTest extends TestCase
{
    use RefreshDatabase;

    public function test_fefo_picking_requires_reason_when_selecting_newer_batch()
    {
        $restaurant = Restaurant::factory()->create();

        $centralBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
        ]);

        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => false,
        ]);

        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Ký',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);

        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'name' => 'Bò Tươi',
            'sku' => 'BEEF-NEW',
            'average_cost' => 200000,
        ]);

        // Create older batch expiring in 3 days
        $oldBatch = InventoryBatch::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
            'ingredient_id' => $ingredient->id,
            'batch_number' => 'BATCH-OLD',
            'quantity_remaining' => 50,
            'purchased_at' => now(),
            'expiry_date' => now()->addDays(3),
            'status' => 'active',
        ]);

        // Create newer batch expiring in 30 days
        $newBatch = InventoryBatch::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
            'ingredient_id' => $ingredient->id,
            'batch_number' => 'BATCH-NEW',
            'quantity_remaining' => 50,
            'purchased_at' => now(),
            'expiry_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 100,
        ]);

        $picker = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $picker->assignRole('warehouse_staff');

        $manager = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $manager->assignRole('warehouse_manager');

        $requester = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);

        $service = app(CentralWarehouseService::class);

        // 1. Create & Approve Supply Request
        $request = $service->createSupplyRequest($restaurant->id, $branch->id, $requester, [
            ['ingredient_id' => $ingredient->id, 'quantity' => 10],
        ]);
        $approved = $service->approveSupplyRequest($request, $manager);

        // 2. Picking newer batch WITHOUT reason throws exception
        $this->expectException(\InvalidArgumentException::class);
        $service->prepareDispatch($approved, $picker, [
            [
                'id' => $approved->items->first()->id,
                'actual_dispatched_quantity' => 10,
                'batch_id' => $newBatch->id,
                'non_fefo_reason' => null,
            ],
        ]);
    }

    public function test_inventory_count_service_enforces_counter_assignment_from_backend()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        $counter1 = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $counter2 = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $unassignedUser = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $session = InventoryCountSession::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'type' => 'spot_check',
            'status' => 'in_progress',
            'counted_by' => $counter1->id,
            'second_counted_by' => $counter2->id,
            'started_at' => now(),
        ]);

        $service = app(InventoryCountService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->submitCounts($session, $unassignedUser, []);
    }

    public function test_fraud_case_creation_and_status_update()
    {
        $restaurant = Restaurant::factory()->create();
        $manager = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $manager->assignRole('warehouse_manager');

        $detectionService = app(WarehouseFraudDetectionService::class);

        $result = $detectionService->analyzeRiskAndFraudPatterns($restaurant->id);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('fraud_cases', $result);

        $case = WarehouseFraudCase::create([
            'restaurant_id' => $restaurant->id,
            'case_code' => 'FRD-TEST-99',
            'category' => 'split_order_pattern',
            'severity' => 'high',
            'title' => 'Nghi vấn chia nhỏ đơn cấp phát',
            'description' => 'Phát hiện chia nhỏ đơn cấp phát để né hạn mức',
            'status' => WarehouseFraudCase::STATUS_OPEN,
        ]);

        $assigned = $detectionService->assignCase($case, $manager);
        $this->assertEquals(WarehouseFraudCase::STATUS_INVESTIGATING, $assigned->status);
        $this->assertEquals($manager->id, $assigned->assigned_to);

        $resolved = $detectionService->updateCaseStatus($case, WarehouseFraudCase::STATUS_RESOLVED, $manager, 'Đã xác minh và hoàn tất điều tra');
        $this->assertEquals(WarehouseFraudCase::STATUS_RESOLVED, $resolved->status);
        $this->assertEquals($manager->id, $resolved->resolved_by);
    }
}
