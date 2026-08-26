<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\OperationalInfringementReport;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationalAuditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_workflow_runs_from_approval_to_remediation_and_reinspection(): void
    {
        Storage::fake('local');

        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $inspector = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
        ]);
        $inspector->assignRole('operations_inspector');

        $auditor = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
        ]);
        $auditor->assignRole('compliance_auditor');

        $assignee = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $assignee->assignRole('manager');
        $branch->update(['manager_user_id' => $assignee->id]);

        $this->actingAs($inspector)
            ->postJson(route('operational-audit.reports.store'), [
                'branch_id' => $branch->id,
                'infringement_date' => now()->toDateString(),
                'severity_level' => 'severe',
                'description' => 'Khu vực sơ chế không thực hiện đủ bước vệ sinh cuối ca.',
                'penalty_amount' => 750000,
                'remediation_deadline' => now()->addDays(3)->toDateString(),
                'remediation_plan' => 'Vệ sinh lại khu vực, lập checklist và đào tạo lại ca trưởng.',
            ])
            ->assertOk();

        $report = OperationalInfringementReport::firstOrFail();

        $this->actingAs($owner)
            ->postJson(route('operational-audit.reports.approve', $report->id))
            ->assertOk();

        $this->assertDatabaseHas('operational_infringement_reports', [
            'id' => $report->id,
            'status' => 'remediation_in_progress',
            'assigned_to' => $assignee->id,
            'assignment_status' => 'assigned',
        ]);

        $this->actingAs($owner)
            ->postJson(route('operational-audit.reports.remediation', $report->id), [
                'remediation_notes' => 'Chá»§ doanh nghiá»‡p khÃ´ng trá»±c tiáº¿p ná»™p kháº¯c phá»¥c.',
            ])
            ->assertForbidden();

        $this->actingAs($inspector)
            ->postJson(route('operational-audit.reports.assign', $report->id), [
                'assigned_to' => $assignee->id,
                'remediation_deadline' => now()->addDays(2)->toDateString(),
                'remediation_plan' => 'Làm lại vệ sinh, chụp ảnh trước/sau và ký checklist ca.',
            ])
            ->assertForbidden();

        $this->actingAs($assignee)
            ->postJson(route('operational-audit.reports.assignment.accept', $report->id))
            ->assertOk();

        $this->actingAs($assignee)
            ->post(route('operational-audit.reports.remediation', $report->id), [
                'remediation_notes' => 'Đã hoàn thành vệ sinh và đào tạo lại nhân sự ca tối.',
                'remediation_proof' => UploadedFile::fake()->image('remediation.jpg'),
            ])
            ->assertOk();

        $this->assertDatabaseHas('operational_infringement_reports', [
            'id' => $report->id,
            'status' => 'reinspection_pending',
            'assigned_to' => $assignee->id,
        ]);

        $this->actingAs($assignee)
            ->postJson(route('operational-audit.reports.reinspect', $report->id), [
                'result' => 'pass',
                'reinspection_notes' => 'Không được tự xác minh CAPA do mình nộp.',
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->postJson(route('operational-audit.reports.reinspect', $report->id), [
                'result' => 'pass',
                'reinspection_notes' => 'Đối chiếu checklist và ảnh hiện trường: đạt yêu cầu.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('operational_infringement_reports', [
            'id' => $report->id,
            'status' => 'closed',
            'reinspection_result' => 'pass',
            'closed_by' => $auditor->id,
        ]);

        $this->assertGreaterThanOrEqual(4, AuditLog::where('subject_type', OperationalInfringementReport::class)
            ->where('subject_id', $report->id)
            ->count());
    }
}
