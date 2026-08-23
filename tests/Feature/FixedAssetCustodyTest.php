<?php

namespace Tests\Feature;

use App\Models\FixedAsset;
use App\Models\FixedAssetHandover;
use App\Models\FixedAssetInspection;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetCustodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_manager_accepts_handover_and_inspector_records_evaluation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $owner->assignRole('owner');

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $manager->assignRole('manager');

        $inspector = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
        ]);
        $inspector->assignRole('operations_inspector');

        $asset = FixedAsset::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'asset_code' => 'FA-CUSTODY-001',
            'name' => 'Máy POS chi nhánh',
            'purchase_date' => today()->toDateString(),
            'in_service_date' => today()->toDateString(),
            'cost' => 15000000,
            'residual_value' => 0,
            'useful_life_months' => 0,
            'status' => 'active',
            'custody_status' => 'unassigned',
            'condition_status' => 'unassessed',
        ]);

        $this->actingAs($owner)
            ->post(route('fixed-assets.handovers.store', $asset), [
                'branch_id' => $branch->id,
                'to_user_id' => $manager->id,
                'handover_date' => today()->toDateString(),
                'condition_at_handover' => 'good',
                'custody_location' => 'Quầy thu ngân',
                'notes' => 'Bàn giao đủ phụ kiện.',
            ])
            ->assertRedirect();

        $handover = FixedAssetHandover::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(FixedAssetHandover::STATUS_PENDING, $handover->status);
        $this->assertDatabaseHas('fixed_assets', [
            'id' => $asset->id,
            'custody_status' => 'pending_handover',
        ]);

        $this->actingAs($manager)
            ->post(route('fixed-asset-handovers.accept', $handover), [
                'notes' => 'Đã kiểm đếm và nhận đủ.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixed_asset_handovers', [
            'id' => $handover->id,
            'status' => FixedAssetHandover::STATUS_ACCEPTED,
            'accepted_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('fixed_assets', [
            'id' => $asset->id,
            'branch_id' => $branch->id,
            'custodian_user_id' => $manager->id,
            'custody_status' => 'assigned',
        ]);

        $this->actingAs($inspector)
            ->post(route('fixed-assets.inspections.store', $asset), [
                'fixed_asset_handover_id' => $handover->id,
                'inspection_type' => 'handover',
                'inspected_at' => today()->toDateString(),
                'condition_status' => 'good',
                'result' => 'pass',
                'score' => 95,
                'findings' => 'Đối chiếu đúng mã tài sản, vị trí và tình trạng sử dụng.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixed_asset_inspections', [
            'fixed_asset_id' => $asset->id,
            'inspector_id' => $inspector->id,
            'result' => FixedAssetInspection::RESULT_PASS,
            'score' => 95,
        ]);
        $this->assertDatabaseHas('fixed_assets', [
            'id' => $asset->id,
            'condition_status' => 'good',
            'custody_status' => 'assigned',
        ]);

        $this->actingAs($inspector)
            ->get(route('fixed-assets.index'))
            ->assertOk();
    }

    public function test_manager_rejection_restores_previous_custody_state(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $owner->assignRole('owner');

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $manager->assignRole('manager');

        $asset = FixedAsset::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'asset_code' => 'FA-CUSTODY-002',
            'name' => 'Thiết bị bếp',
            'purchase_date' => today()->toDateString(),
            'in_service_date' => today()->toDateString(),
            'cost' => 8000000,
            'residual_value' => 0,
            'useful_life_months' => 0,
            'status' => 'active',
            'custody_status' => 'unassigned',
            'condition_status' => 'unassessed',
        ]);

        $this->actingAs($owner)->post(route('fixed-assets.handovers.store', $asset), [
            'branch_id' => $branch->id,
            'to_user_id' => $manager->id,
            'handover_date' => today()->toDateString(),
            'condition_at_handover' => 'major_issue',
            'notes' => 'Thiết bị có dấu hiệu hư hỏng.',
        ])->assertRedirect();

        $handover = FixedAssetHandover::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($manager)
            ->post(route('fixed-asset-handovers.reject', $handover), [
                'reason' => 'Không nhận vì tình trạng thực tế không đúng biên bản.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixed_asset_handovers', [
            'id' => $handover->id,
            'status' => FixedAssetHandover::STATUS_REJECTED,
        ]);
        $this->assertDatabaseHas('fixed_assets', [
            'id' => $asset->id,
            'branch_id' => null,
            'custodian_user_id' => null,
            'custody_status' => 'unassigned',
        ]);
    }
}
