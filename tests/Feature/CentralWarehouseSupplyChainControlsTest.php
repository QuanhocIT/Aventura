<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WarehouseReceivingVoucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralWarehouseSupplyChainControlsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private RestaurantBranch $central;
    private User $owner;
    private Ingredient $freshIngredient;
    private WarehouseLocation $coldLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->central = RestaurantBranch::factory()->create([
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
        $this->freshIngredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $unit->id,
            'name' => 'Fresh control ingredient',
            'sku' => 'FRESH-CONTROL',
            'storage_type' => 'fresh',
            'storage_temperature_min_c' => 2,
            'storage_temperature_max_c' => 6,
            'batch_tracking_required' => true,
            'average_cost' => 100,
            'status' => 'active',
        ]);
        $this->coldLocation = WarehouseLocation::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->central->id,
            'zone' => 'Cold',
            'location_code' => 'COLD-01',
            'status' => 'active',
            'is_cold_storage' => true,
        ]);
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->owner->assignRole('owner');
    }

    public function test_failed_receiving_requires_disposition_without_posting_inventory(): void
    {
        $voucher = $this->makeVoucher();

        $response = $this->actingAs($this->owner)->postJson(
            route('warehouse.receiving-vouchers.confirm', $voucher->id),
            [
                'quality_status' => 'failed',
                'quality_notes' => 'Bao bi rach va khong dat ngoai quan.',
            ],
        );

        $response->assertStatus(422)->assertJsonPath('requires_disposition', true);
        $this->assertEquals('pending_disposition', $voucher->refresh()->status);
        $this->assertDatabaseMissing('inventory_transactions', ['source_id' => $voucher->id]);

        $disposeResponse = $this->actingAs($this->owner)->postJson(
            route('warehouse.receiving-vouchers.dispose', $voucher->id),
            [
                'disposition' => 'return_supplier',
                'reason' => 'Da lap bien ban tra nha cung cap.',
            ],
        );

        $disposeResponse->assertOk();
        $this->assertEquals('returned', $voucher->refresh()->status);
    }

    public function test_cold_receiving_requires_temperature_and_records_it_on_confirm(): void
    {
        $voucher = $this->makeVoucher();

        $missingTemperature = $this->actingAs($this->owner)->postJson(
            route('warehouse.receiving-vouchers.confirm', $voucher->id),
            ['quality_status' => 'passed'],
        );
        $missingTemperature->assertStatus(422);
        $this->assertEquals('pending_review', $voucher->refresh()->status);

        $confirmed = $this->actingAs($this->owner)->postJson(
            route('warehouse.receiving-vouchers.confirm', $voucher->id),
            [
                'quality_status' => 'passed',
                'temperature_min_c' => 3,
                'temperature_max_c' => 5,
            ],
        );

        $confirmed->assertOk();
        $voucher->refresh();
        $this->assertEquals('confirmed', $voucher->status);
        $this->assertEquals('passed', $voucher->temperature_status);
        $this->assertSame('3.00', $voucher->temperature_min_c);
        $this->assertDatabaseHas('inventory_batches', [
            'ingredient_id' => $this->freshIngredient->id,
            'quantity_remaining' => 8,
        ]);
    }

    public function test_supply_chain_alerts_expose_missing_supplier_and_low_stock(): void
    {
        $this->freshIngredient->update([
            'storage_type' => 'dry',
            'batch_tracking_required' => false,
            'min_stock_level' => 10,
            'reorder_level' => 10,
            'safety_stock_quantity' => 5,
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('warehouse.supply-chain.alerts'));

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'no_supplier']);
        $response->assertJsonFragment(['type' => 'low_stock']);
    }

    private function makeVoucher(): WarehouseReceivingVoucher
    {
        $voucher = WarehouseReceivingVoucher::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->central->id,
            'received_by' => $this->owner->id,
            'received_at' => now(),
            'status' => 'pending_review',
            'quality_status' => 'pending',
            'total_expected_qty' => 10,
            'total_actual_qty' => 8,
            'total_discrepancy_qty' => -2,
        ]);
        $voucher->items()->create([
            'ingredient_id' => $this->freshIngredient->id,
            'expected_qty' => 10,
            'actual_qty' => 8,
            'unit_cost' => 100,
            'lot_number' => 'LOT-CONTROL-01',
            'expiry_date' => now()->addDays(10)->toDateString(),
            'location_id' => $this->coldLocation->id,
            'item_status' => 'short',
        ]);

        return $voucher;
    }
}
