<?php

namespace Tests\Feature;

use App\Models\OperationalInspectionPlan;
use App\Models\OperationalInfringementReport;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalInspectionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_is_started_by_first_report_and_cannot_close_with_pending_approval(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $inspector = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $inspector->assignRole('operations_inspector');

        $planResponse = $this->actingAs($inspector)->postJson(route('operational-audit.inspection-plans.store'), [
            'branch_id' => $branch->id,
            'title' => 'Kiểm tra an toàn thực phẩm tháng này',
            'inspection_type' => 'thematic',
            'scheduled_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'scope' => 'Đối chiếu khu sơ chế, kho lạnh và checklist vệ sinh cuối ca.',
        ]);

        $planResponse->assertOk();
        $plan = OperationalInspectionPlan::firstOrFail();

        $this->actingAs($inspector)->postJson(route('operational-audit.reports.store'), [
            'branch_id' => $branch->id,
            'inspection_plan_id' => $plan->id,
            'infringement_date' => now()->toDateString(),
            'severity_level' => 'moderate',
            'description' => 'Checklist vệ sinh khu sơ chế không có chữ ký xác nhận cuối ca.',
            'penalty_amount' => 200000,
        ])->assertOk();

        $this->assertDatabaseHas('operational_inspection_plans', [
            'id' => $plan->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($inspector)
            ->postJson(route('operational-audit.inspection-plans.complete', $plan->id), [
                'notes' => 'Đã hoàn tất kiểm tra hiện trường, chờ Chủ doanh nghiệp duyệt biên bản.',
            ])
            ->assertStatus(422);

        $report = OperationalInfringementReport::firstOrFail();
        $this->actingAs($owner)
            ->postJson(route('operational-audit.reports.approve', $report->id))
            ->assertOk();

        $this->actingAs($inspector)
            ->postJson(route('operational-audit.inspection-plans.complete', $plan->id), [
                'notes' => 'Đã hoàn tất kiểm tra hiện trường và chuyển biên bản sang theo dõi khắc phục.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('operational_inspection_plans', [
            'id' => $plan->id,
            'status' => 'completed',
            'completed_by' => $inspector->id,
        ]);
    }
}
