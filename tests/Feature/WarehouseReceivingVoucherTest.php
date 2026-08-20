<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WarehouseReceivingVoucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseReceivingVoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_confirmation_posts_grn_into_inventory_batches_and_ledger(): void
    {
        $restaurant = Restaurant::factory()->create();
        $centralBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
        ]);
        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'name' => 'Thit bo',
            'sku' => 'BEEF-GRN',
            'average_cost' => 100000,
            'status' => 'active',
        ]);
        $location = WarehouseLocation::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
            'zone' => 'Khu kho',
            'rack' => 'A1',
            'shelf' => '01',
            'bin' => '01',
            'location_code' => 'A1-01-01',
            'status' => 'active',
        ]);
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole(Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']));
        $staff = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $staff->assignRole('warehouse_staff');

        $voucher = WarehouseReceivingVoucher::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
            'received_by' => $staff->id,
            'received_at' => now(),
            'status' => 'discrepancy',
            'total_expected_qty' => 10,
            'total_actual_qty' => 8,
            'total_discrepancy_qty' => -2,
        ]);
        $voucher->items()->create([
            'ingredient_id' => $ingredient->id,
            'expected_qty' => 10,
            'actual_qty' => 8,
            'unit_cost' => 120000,
            'lot_number' => 'LOT-GRN-01',
            'expiry_date' => now()->addDays(14)->toDateString(),
            'location_id' => $location->id,
            'item_status' => 'short',
        ]);

        $response = $this->actingAs($owner)->postJson(
            route('warehouse.receiving-vouchers.confirm', $voucher->id),
            [
                'notes' => 'Da doi chieu bien ban giao nhan.',
                'quality_status' => 'passed',
            ],
        );

        $response->assertOk();
        $this->assertEquals('confirmed', $voucher->refresh()->status);
        $this->assertEquals(8.0, (float) Inventory::where('branch_id', $centralBranch->id)->where('ingredient_id', $ingredient->id)->value('quantity_on_hand'));
        $this->assertDatabaseHas('inventory_batches', [
            'branch_id' => $centralBranch->id,
            'ingredient_id' => $ingredient->id,
            'batch_number' => 'LOT-GRN-01',
            'quantity_remaining' => 8,
            'location_id' => $location->id,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'source_type' => 'warehouse_receiving_voucher',
            'source_id' => $voucher->id,
            'direction' => 'in',
            'quantity' => 8,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('inventory_batches', 1);
    }
}
