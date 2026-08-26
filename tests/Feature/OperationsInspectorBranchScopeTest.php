<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompanyPolicy;
use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\OperationalInfringementReport;
use App\Models\OperationalInspection;
use App\Models\OperationalInspectionPlan;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\ViolationReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsInspectorBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_branch_scopes_audit_workspaces_and_policies(): void
    {
        [$restaurant, $branchA, $branchB, $inspector] = $this->inspectorFixture();

        OperationalInfringementReport::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchA->id,
            'report_code' => 'INF-SCOPE-A',
            'inspector_id' => $inspector->id,
            'infringement_date' => today(),
            'description' => 'Phát hiện tại chi nhánh A cần theo dõi.',
        ]);
        OperationalInfringementReport::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchB->id,
            'report_code' => 'INF-SCOPE-B',
            'inspector_id' => $inspector->id,
            'infringement_date' => today(),
            'description' => 'Phát hiện tại chi nhánh B cần theo dõi.',
        ]);

        OperationalInspectionPlan::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchA->id,
            'plan_code' => 'PLAN-SCOPE-A',
            'title' => 'Kế hoạch chi nhánh A',
            'scheduled_date' => today(),
            'created_by' => $inspector->id,
        ]);
        OperationalInspectionPlan::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchB->id,
            'plan_code' => 'PLAN-SCOPE-B',
            'title' => 'Kế hoạch chi nhánh B',
            'scheduled_date' => today(),
            'created_by' => $inspector->id,
        ]);

        OperationalInspection::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchA->id,
            'inspection_code' => 'INS-SCOPE-A',
            'title' => 'Phiên kiểm tra chi nhánh A',
            'created_by' => $inspector->id,
            'scheduled_at' => now(),
        ]);
        OperationalInspection::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchB->id,
            'inspection_code' => 'INS-SCOPE-B',
            'title' => 'Phiên kiểm tra chi nhánh B',
            'created_by' => $inspector->id,
            'scheduled_at' => now(),
        ]);

        CompanyPolicy::create([
            'restaurant_id' => $restaurant->id,
            'policy_code' => 'POL-SCOPE-ALL',
            'title' => 'Quy định toàn chuỗi',
            'content' => 'Áp dụng cho mọi chi nhánh.',
            'applies_to_all_branches' => true,
            'status' => 'published',
            'created_by' => $inspector->id,
        ]);
        CompanyPolicy::create([
            'restaurant_id' => $restaurant->id,
            'policy_code' => 'POL-SCOPE-B',
            'title' => 'Quy định riêng chi nhánh B',
            'content' => 'Chỉ áp dụng cho chi nhánh B.',
            'applies_to_all_branches' => false,
            'applicable_branch_ids' => [$branchB->id],
            'status' => 'published',
            'created_by' => $inspector->id,
        ]);

        $session = [
            'active_branch_id' => $branchA->id,
            'active_branch_scope' => 'branch',
        ];

        $this->withSession($session)->actingAs($inspector)
            ->get(route('operations.audit.overview'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/AuditOverview')
                ->where('reportStats.total', 1)
                ->where('planStats.planned', 1)
                ->where('inspectionStats.total', 1)
            );

        $this->withSession($session)->actingAs($inspector)
            ->get(route('operations.audit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/OperationalAudit')
                ->where('reportStats.total', 1)
                ->where('policies.0.title', 'Quy định toàn chuỗi')
                ->missing('policies.1')
            );

        $this->withSession($session)->actingAs($inspector)
            ->get(route('operations.inspection-workspace'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/InspectionWorkspace')
                ->has('inspections', 1)
                ->where('inspections.0.inspection_code', 'INS-SCOPE-A')
                ->where('pagination.total', 1)
            );

        $inspectionA = OperationalInspection::where('inspection_code', 'INS-SCOPE-A')->firstOrFail();
        $inspectionB = OperationalInspection::where('inspection_code', 'INS-SCOPE-B')->firstOrFail();
        $this->withSession($session)->actingAs($inspector)
            ->getJson(route('operational-audit.inspections.show', $inspectionA->id))
            ->assertOk()
            ->assertJsonPath('data.inspection_code', 'INS-SCOPE-A');
        $this->withSession($session)->actingAs($inspector)
            ->getJson(route('operational-audit.inspections.show', $inspectionB->id))
            ->assertNotFound();

        $this->withSession($session)->actingAs($inspector)
            ->get(route('operations.company-policies'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/CompanyPolicies')
                ->where('policies.0.title', 'Quy định toàn chuỗi')
                ->missing('policies.1')
            );
    }

    public function test_selected_branch_scopes_assets_violations_and_audit_logs(): void
    {
        [$restaurant, $branchA, $branchB, $inspector] = $this->inspectorFixture();

        foreach ([[$branchA, 'FA-SCOPE-A'], [$branchB, 'FA-SCOPE-B']] as [$branch, $code]) {
            FixedAsset::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'asset_code' => $code,
                'name' => $code,
                'purchase_date' => today(),
                'in_service_date' => today(),
                'cost' => 1000000,
                'residual_value' => 0,
                'useful_life_months' => 36,
                'status' => 'active',
                'custody_status' => 'unassigned',
                'condition_status' => 'unassessed',
            ]);
        }

        $employeeA = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchA->id,
        ]);
        $employeeB = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchB->id,
        ]);
        ViolationReport::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchA->id,
            'employee_id' => $employeeA->id,
            'reported_by' => $inspector->id,
            'violation_type' => 'scope_test',
            'description' => 'Vi phạm tại chi nhánh A.',
            'occurred_at' => now(),
        ]);
        ViolationReport::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchB->id,
            'employee_id' => $employeeB->id,
            'reported_by' => $inspector->id,
            'violation_type' => 'scope_test',
            'description' => 'Vi phạm tại chi nhánh B.',
            'occurred_at' => now(),
        ]);

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchA->id,
            'user_id' => $inspector->id,
            'user_role' => 'operations_inspector',
            'event' => 'created',
            'action' => 'scope_test_a',
            'subject_id' => 1,
            'created_at' => now(),
        ]);
        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchB->id,
            'user_id' => $inspector->id,
            'user_role' => 'operations_inspector',
            'event' => 'created',
            'action' => 'scope_test_b',
            'subject_id' => 2,
            'created_at' => now()->subSecond(),
        ]);

        $session = [
            'active_branch_id' => $branchA->id,
            'active_branch_scope' => 'branch',
        ];

        $this->withSession($session)->actingAs($inspector)
            ->get(route('fixed-assets.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('fixed-assets/Index')
                ->has('assets.data', 1)
                ->where('assets.data.0.asset_code', 'FA-SCOPE-A')
                ->where('stats.total', 1)
            );

        $this->withSession($session)->actingAs($inspector)
            ->get(route('violations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('violations/Index')
                ->where('pagination.total', 1)
                ->where('reports.0.employee_id', $employeeA->id)
                ->missing('reports.1')
            );

        $this->withSession($session)->actingAs($inspector)
            ->get(route('audit-logs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('audit-logs/Index')
                ->where('total', 1)
                ->where('logs.data.0.action', 'scope_test_a')
            );
    }

    /** @return array{0: Restaurant, 1: RestaurantBranch, 2: RestaurantBranch, 3: User} */
    private function inspectorFixture(): array
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $branchA = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Chi nhánh A']);
        $branchB = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Chi nhánh B']);
        $inspector = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'status' => 'active',
        ]);

        $role = Role::firstOrCreate(['name' => 'operations_inspector', 'guard_name' => 'web']);
        $permissionNames = [
            'view_violations',
            'company_policies.view',
            'operational_audit.view',
            'operational_audit.report',
            'operational_inspection.view',
            'operational_inspection.execute',
            'fixed_assets.view',
            'fixed_assets.view_all',
            'fixed_assets.inspect',
            'audit.read',
            'view_audit_log',
        ];
        $permissions = collect($permissionNames)->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
        $role->givePermissionTo($permissions);
        $inspector->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$restaurant, $branchA, $branchB, $inspector];
    }
}
