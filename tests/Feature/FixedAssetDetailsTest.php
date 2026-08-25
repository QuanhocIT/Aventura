<?php

namespace Tests\Feature;

use App\Models\FixedAsset;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_creation_persists_structured_detail_fields(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $owner->assignRole('owner');

        $this->actingAs($owner)
            ->post(route('fixed-assets.store'), [
                'asset_code' => 'FA-DETAIL-001',
                'name' => 'Máy điều hòa',
                'category' => 'equipment',
                'brand' => 'Daikin',
                'model' => 'FTKF35',
                'quantity' => 6,
                'unit' => 'cái',
                'serial_number' => 'DAIKIN-SET-001',
                'branch_id' => $branch->id,
                'purchase_date' => '2026-08-23',
                'cost' => 42000000,
                'unit_cost' => 7000000,
                'supplier' => 'Nhà cung cấp thiết bị A',
                'invoice_number' => 'INV-001',
                'warranty_until' => '2028-08-23',
                'specifications' => 'Công suất 12000 BTU, inverter.',
                'notes' => 'Lắp tại khu vực phục vụ khách.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixed_assets', [
            'asset_code' => 'FA-DETAIL-001',
            'brand' => 'Daikin',
            'model' => 'FTKF35',
            'quantity' => 6,
            'unit' => 'cái',
            'unit_cost' => 7000000,
            'invoice_number' => 'INV-001',
        ]);

        $asset = FixedAsset::where('asset_code', 'FA-DETAIL-001')->firstOrFail();
        $this->assertSame(6, (int) $asset->quantity);
        $this->assertSame('2028-08-23', $asset->warranty_until?->toDateString());

        $this->actingAs($owner)
            ->patch(route('fixed-assets.update', $asset), [
                'name' => 'Máy điều hòa Daikin tại sảnh',
                'category' => 'equipment',
                'brand' => 'Daikin',
                'model' => 'FTKF35',
                'quantity' => 6,
                'unit' => 'cái',
                'serial_number' => 'DAIKIN-SET-001-UPDATED',
                'unit_cost' => 7000000,
                'supplier' => 'Nhà cung cấp thiết bị B',
                'invoice_number' => 'INV-002',
                'warranty_until' => '2028-08-23',
                'specifications' => 'Đã cập nhật vị trí lắp đặt.',
                'notes' => 'Đã bổ sung thông tin nhận diện.',
            ])
            ->assertRedirect();

        $asset->refresh();
        $this->assertSame('DAIKIN-SET-001-UPDATED', $asset->serial_number);
        $this->assertSame('Nhà cung cấp thiết bị B', $asset->supplier);
        $this->assertSame('INV-002', $asset->invoice_number);
        $this->assertSame('Đã bổ sung thông tin nhận diện.', $asset->notes);
    }
}
