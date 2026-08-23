<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InternalTransfer;
use App\Models\InventoryTransaction;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IngredientSpendReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_reconcile_central_purchase_central_supply_and_branch_purchase_without_double_counting(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Kho Tổng',
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
        ]);
        $business = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Chi nhánh Kinh doanh',
            'is_central_warehouse' => false,
            'warehouse_type' => 'business',
        ]);
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
        ]);
        $owner->assignRole('owner');

        $unit = Unit::factory()->create(['restaurant_id' => $restaurant->id, 'symbol' => 'kg']);
        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'unit_id' => $unit->id,
            'average_cost' => 100,
        ]);

        InventoryTransaction::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'ingredient_id' => $ingredient->id,
            'performed_by' => $owner->id,
            'type' => 'purchase',
            'direction' => 'in',
            'quantity' => 10,
            'unit_cost' => 100,
            'total_cost' => 1000,
            'source_type' => 'warehouse_receiving_voucher',
            'source_id' => 101,
            'occurred_at' => '2026-08-10 10:00:00',
        ]);

        InventoryTransaction::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $business->id,
            'ingredient_id' => $ingredient->id,
            'performed_by' => $owner->id,
            'type' => 'purchase',
            'direction' => 'in',
            'quantity' => 4,
            'unit_cost' => 50,
            'total_cost' => 200,
            'occurred_at' => '2026-08-11 10:00:00',
        ]);

        $supplyRequest = SupplyRequest::create([
            'restaurant_id' => $restaurant->id,
            'request_code' => 'SR-REPORT-001',
            'from_branch_id' => $central->id,
            'to_branch_id' => $business->id,
            'created_by' => $owner->id,
            'status' => SupplyRequest::STATUS_COMPLETED,
            'total_amount' => 300,
        ]);
        InventoryTransaction::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $business->id,
            'ingredient_id' => $ingredient->id,
            'performed_by' => $owner->id,
            'type' => 'transfer',
            'direction' => 'in',
            'quantity' => 3,
            'unit_cost' => 100,
            'total_cost' => 300,
            'source_type' => 'supply_request',
            'source_id' => $supplyRequest->id,
            'occurred_at' => '2026-08-12 10:00:00',
        ]);
        // The matching Kho Tổng outbound ledger must not be counted a second time.
        InventoryTransaction::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'ingredient_id' => $ingredient->id,
            'performed_by' => $owner->id,
            'type' => 'transfer',
            'direction' => 'out',
            'quantity' => 3,
            'unit_cost' => 100,
            'total_cost' => 300,
            'source_type' => 'supply_request',
            'source_id' => $supplyRequest->id,
            'occurred_at' => '2026-08-12 09:00:00',
        ]);

        $internalTransfer = InternalTransfer::create([
            'restaurant_id' => $restaurant->id,
            'from_branch_id' => $business->id,
            'to_branch_id' => $central->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 2,
            'status' => 'completed',
            'created_by' => $owner->id,
            'completed_by' => $owner->id,
            'completed_at' => '2026-08-13 10:00:00',
        ]);
        InventoryTransaction::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'ingredient_id' => $ingredient->id,
            'performed_by' => $owner->id,
            'type' => 'adjustment',
            'direction' => 'in',
            'quantity' => 2,
            'unit_cost' => 60,
            'total_cost' => 120,
            'source_type' => 'internal_transfer',
            'source_id' => $internalTransfer->id,
            'occurred_at' => '2026-08-13 10:00:00',
        ]);

        // Production output is a stock addition, not a new purchase.
        InventoryTransaction::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'ingredient_id' => $ingredient->id,
            'performed_by' => $owner->id,
            'type' => 'purchase',
            'direction' => 'in',
            'quantity' => 99,
            'unit_cost' => 1,
            'total_cost' => 99,
            'source_type' => 'work_order',
            'source_id' => 999,
            'occurred_at' => '2026-08-14 10:00:00',
        ]);

        $response = $this->actingAs($owner)->get(route('finance.ingredient-spend.index', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ingredient-spend/Index')
            ->where('report.summary.central_purchase_amount', 1000)
            ->where('report.summary.central_supply_amount', 300)
            ->where('report.summary.external_purchase_amount', 200)
            ->where('report.summary.interbranch_transfer_amount', 120)
            ->where('report.summary.actual_cash_commitment_amount', 1200)
            ->where('report.transaction_count', 4)
            ->has('report.branch_rows', 2)
        );
    }

    public function test_owner_can_filter_the_report_to_a_business_branch(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
        ]);
        $business = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => false,
            'warehouse_type' => 'business',
        ]);
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $central->id]);
        $owner->assignRole('owner');
        $unit = Unit::factory()->create(['restaurant_id' => $restaurant->id]);
        $ingredient = Ingredient::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $central->id, 'unit_id' => $unit->id]);

        InventoryTransaction::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $business->id,
            'ingredient_id' => $ingredient->id,
            'performed_by' => $owner->id,
            'type' => 'purchase',
            'direction' => 'in',
            'quantity' => 2,
            'unit_cost' => 75,
            'total_cost' => 150,
            'occurred_at' => '2026-08-15 10:00:00',
        ]);

        $response = $this->actingAs($owner)->get(route('finance.ingredient-spend.index', [
            'branch_id' => $business->id,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('report.selected_branch_id', $business->id)
            ->where('report.summary.external_purchase_amount', 150)
            ->where('report.summary.central_purchase_amount', 0)
            ->where('report.transaction_count', 1)
        );
    }
}
