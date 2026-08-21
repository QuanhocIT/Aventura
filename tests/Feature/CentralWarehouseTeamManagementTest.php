<?php

namespace Tests\Feature;

use App\Models\BranchPayrollBudget;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use App\Models\WageTier;
use App\Services\CentralWarehouseStaffKpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CentralWarehouseTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $manager;
    protected User $staff;
    protected Restaurant $restaurant;
    protected RestaurantBranch $centralBranch;
    protected RestaurantBranch $businessBranch;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'warehouse_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'warehouse_staff', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->centralBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
        ]);
        $this->businessBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_central_warehouse' => false,
            'warehouse_type' => 'business',
        ]);

        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->owner->assignRole('owner');

        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_branch_id' => $this->centralBranch->id,
        ]);
        $this->manager->assignRole('warehouse_manager');

        $this->staff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_branch_id' => $this->centralBranch->id,
            'supervisor_user_id' => $this->manager->id,
            'warehouse_staff_status' => 'active',
        ]);
        $this->staff->assignRole('warehouse_staff');
    }

    public function test_warehouse_manager_can_access_team_page(): void
    {
        $employee = \App\Models\Employee::factory()->create([
            'user_id' => $this->staff->id,
            'restaurant_id' => $this->restaurant->id,
        ]);

        \App\Models\LeaveRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee->id,
            'requested_by' => $this->staff->id,
            'leave_type' => 'annual',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)->get(route('warehouse.team.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/CentralWarehouseTeam')
            ->has('staffMembers')
            ->has('supervisors')
            ->has('leaveRequests')
        );
    }

    public function test_warehouse_manager_sees_central_payroll_budget_on_employee_page(): void
    {
        BranchPayrollBudget::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->centralBranch->id,
            'effective_month' => now()->startOfMonth(),
            'budget_amount' => 15000000,
        ]);

        $response = $this->actingAs($this->manager)->get('/employees');

        $response->assertOk();
        $props = $response->original->getData()['page']['props'];
        $this->assertSame($this->centralBranch->id, (int) $props['payrollBudget']['branch_id']);
        $this->assertTrue($props['payrollBudget']['configured']);
        $this->assertEqualsWithDelta(15000000, (float) $props['payrollBudget']['budget_amount'], 0.01);
        $this->assertSame($this->centralBranch->id, (int) $props['branches'][0]['id']);
    }

    public function test_warehouse_manager_can_assign_supervisor_to_warehouse_staff(): void
    {
        $newManager = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $newManager->assignRole('warehouse_manager');

        $response = $this->actingAs($this->manager)->post(route('warehouse.team.assign-supervisor'), [
            'staff_user_id' => $this->staff->id,
            'supervisor_user_id' => $newManager->id,
            'notes' => 'Bổ nhiệm Trưởng kho mới',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $this->staff->id,
            'supervisor_user_id' => $newManager->id,
        ]);
        $this->assertDatabaseHas('warehouse_staff_supervisor_histories', [
            'warehouse_staff_id' => $this->staff->id,
            'supervisor_user_id' => $newManager->id,
            'status' => 'active',
        ]);
    }

    public function test_warehouse_manager_can_assign_task_to_active_warehouse_staff(): void
    {
        $response = $this->actingAs($this->manager)->post(route('warehouse.team.tasks.assign'), [
            'assigned_to' => $this->staff->id,
            'task_type' => 'picking',
            'priority' => 'high',
            'notes' => 'Soạn nguyên liệu gấp',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('warehouse_task_assignments', [
            'restaurant_id' => $this->restaurant->id,
            'assigned_to' => $this->staff->id,
            'assigned_by' => $this->manager->id,
            'task_type' => 'picking',
            'priority' => 'high',
            'status' => 'assigned',
        ]);
    }

    public function test_cannot_assign_task_to_manager_or_owner(): void
    {
        $response = $this->actingAs($this->manager)->post(route('warehouse.team.tasks.assign'), [
            'assigned_to' => $this->manager->id, // Trying to assign to manager!
            'task_type' => 'picking',
            'priority' => 'normal',
        ]);

        $response->assertSessionHasErrors('assigned_to');
    }

    public function test_cannot_assign_task_to_paused_staff(): void
    {
        $this->staff->update(['warehouse_staff_status' => 'paused']);

        $response = $this->actingAs($this->manager)->post(route('warehouse.team.tasks.assign'), [
            'assigned_to' => $this->staff->id,
            'task_type' => 'receiving',
            'priority' => 'normal',
        ]);

        $response->assertSessionHasErrors('assigned_to');
    }

    public function test_kpi_service_calculates_correct_composite_score(): void
    {
        // Create 2 completed tasks
        WarehouseTaskAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'assigned_to' => $this->staff->id,
            'assigned_by' => $this->manager->id,
            'task_type' => 'picking',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $kpiService = app(CentralWarehouseStaffKpiService::class);
        $kpi = $kpiService->calculateStaffKpi($this->restaurant->id, $this->staff->id);

        $this->assertEquals(1, $kpi['total_tasks']);
        $this->assertEquals(100.0, $kpi['completion_rate']);
        $this->assertGreaterThan(0, $kpi['composite_score']);
    }

    public function test_warehouse_manager_can_create_warehouse_staff_employee(): void
    {
        BranchPayrollBudget::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->centralBranch->id,
            'effective_month' => now()->startOfMonth(),
            'budget_amount' => 50000000,
        ]);
        WageTier::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->centralBranch->id,
            'name' => 'Nhân viên Kho Tổng',
            'compensation_type' => 'fixed',
            'rate' => 8000000,
            'is_active' => true,
        ]);

        \Illuminate\Support\Facades\Storage::fake('local');

        $payload = [
            'name' => 'Nguyễn Văn Kho Mới',
            'email' => 'khomoi@example.com',
            'phone' => '0988776655',
            'citizen_id_number' => '079988776655',
            'address' => '456 Lê Duẩn, Quận 1, TP.HCM',
            'date_of_birth' => '1995-08-15',
            'citizen_id_front' => \Illuminate\Http\UploadedFile::fake()->image('front.jpg', 600, 400),
            'citizen_id_back' => \Illuminate\Http\UploadedFile::fake()->image('back.jpg', 600, 400),
            'hire_date' => now()->toDateString(),
            'base_salary' => 8000000,
            'wage_tier_id' => WageTier::where('restaurant_id', $this->restaurant->id)
                ->where('branch_id', $this->centralBranch->id)
                ->value('id'),
            'role' => 'warehouse_staff',
            'job_title' => 'Nhân viên Kho Tổng',
        ];

        $response = $this->actingAs($this->manager)
            ->from('/employees')
            ->post('/employees', $payload);

        $response->assertRedirect('/employees');
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'restaurant_id' => $this->restaurant->id,
            'email' => 'khomoi@example.com',
            'warehouse_branch_id' => $this->centralBranch->id,
        ]);

        $user = User::where('email', 'khomoi@example.com')->first();
        $this->assertTrue($user->hasRole('warehouse_staff'));
    }

    public function test_warehouse_manager_cannot_create_employee_with_other_roles(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $payload = [
            'name' => 'Nguyễn Thu Ngân',
            'email' => 'thungan@example.com',
            'phone' => '0988776644',
            'citizen_id_number' => '079988776644',
            'address' => '456 Lê Duẩn, Quận 1, TP.HCM',
            'date_of_birth' => '1995-08-15',
            'citizen_id_front' => \Illuminate\Http\UploadedFile::fake()->image('front.jpg', 600, 400),
            'citizen_id_back' => \Illuminate\Http\UploadedFile::fake()->image('back.jpg', 600, 400),
            'hire_date' => now()->toDateString(),
            'base_salary' => 8000000,
            'role' => 'cashier', // Invalid role for warehouse manager!
            'job_title' => 'Thu Ngân',
        ];

        $response = $this->actingAs($this->manager)
            ->from('/employees')
            ->post('/employees', $payload);

        $response->assertRedirect('/employees');
        $response->assertSessionHasErrors(['role']);

        $this->assertDatabaseMissing('users', [
            'email' => 'thungan@example.com',
        ]);
    }

    public function test_cannot_create_warehouse_roles_at_business_branch(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $payload = [
            'name' => 'Trần Văn Kho Tổng',
            'email' => 'truongkho@example.com',
            'phone' => '0988776633',
            'citizen_id_number' => '079988776633',
            'address' => '123 Hai Bà Trưng, Quận 3, TP.HCM',
            'date_of_birth' => '1990-05-20',
            'citizen_id_front' => \Illuminate\Http\UploadedFile::fake()->image('front.jpg', 600, 400),
            'citizen_id_back' => \Illuminate\Http\UploadedFile::fake()->image('back.jpg', 600, 400),
            'hire_date' => now()->toDateString(),
            'base_salary' => 15000000,
            'role' => 'warehouse_manager',
            'job_title' => 'Trưởng Kho Tổng',
            'branch_id' => $this->businessBranch->id, // Business branch is not allowed!
        ];

        $response = $this->actingAs($this->owner)
            ->from('/employees')
            ->post('/employees', $payload);

        $response->assertRedirect('/employees');
        $response->assertSessionHasErrors(['branch_id']);

        $this->assertDatabaseMissing('users', [
            'email' => 'truongkho@example.com',
        ]);
    }

    public function test_owner_can_create_warehouse_manager_at_central_branch(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $payload = [
            'name' => 'Trần Văn Kho Tổng',
            'email' => 'truongkho_ok@example.com',
            'phone' => '0988776622',
            'citizen_id_number' => '079988776622',
            'address' => '123 Hai Bà Trưng, Quận 3, TP.HCM',
            'date_of_birth' => '1990-05-20',
            'citizen_id_front' => \Illuminate\Http\UploadedFile::fake()->image('front.jpg', 600, 400),
            'citizen_id_back' => \Illuminate\Http\UploadedFile::fake()->image('back.jpg', 600, 400),
            'hire_date' => now()->toDateString(),
            'base_salary' => 15000000,
            'role' => 'warehouse_manager',
            'job_title' => 'Trưởng Kho Tổng',
            'branch_id' => $this->centralBranch->id,
        ];

        $response = $this->actingAs($this->owner)
            ->from('/employees')
            ->post('/employees', $payload);

        $response->assertRedirect('/employees');
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'restaurant_id' => $this->restaurant->id,
            'email' => 'truongkho_ok@example.com',
            'warehouse_branch_id' => $this->centralBranch->id,
        ]);
    }
}
