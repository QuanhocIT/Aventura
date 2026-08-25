<?php

namespace Tests\Feature;

use App\Models\BranchPayrollBudget;
use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\WageTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Nối Quỹ lương chi nhánh vào luồng TẠO NHÂN VIÊN:
 *  - Chọn bậc lương → KHOÁ mức lương theo bậc (manager không tự nhập).
 *  - Quản lý (không phải Chủ) BẮT BUỘC chọn bậc lương.
 *  - Tổng lương vượt quỹ chi nhánh → CHẶN.
 *  - Chủ không đặt quỹ → tạo tự do (không ràng buộc).
 */
class EmployeeWageTierIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $perm = Permission::firstOrCreate(['name' => 'manage_employees', 'guard_name' => 'web']);
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        foreach (['cashier', 'waiter', 'kitchen'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $ownerRole->givePermissionTo($perm);
        $managerRole->givePermissionTo($perm);

        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
    }

    /** Payload hợp lệ tối thiểu để POST /employees (ảnh CCCD giả). */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nguyễn Văn A',
            'email' => 'nv.a+'.uniqid().'@example.com',
            'phone' => '0900000000',
            'citizen_id_number' => '012345678901',
            'address' => '123 Đường ABC',
            'date_of_birth' => '1998-01-01',
            'citizen_id_front' => UploadedFile::fake()->image('front.jpg'),
            'citizen_id_back' => UploadedFile::fake()->image('back.jpg'),
            'hire_date' => Carbon::now()->toDateString(),
            'base_salary' => 7000000,
            'role' => 'cashier',
            'job_title' => 'Thu ngân',
            'branch_id' => $this->branch->id,
        ], $overrides);
    }

    public function test_wage_tier_locks_the_wage(): void
    {
        BranchPayrollBudget::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'effective_month' => Carbon::now()->startOfMonth(),
            'budget_amount' => 50000000,
        ]);
        $tier = WageTier::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Thu ngân ca',
            'compensation_type' => 'shift',
            'rate' => 250000,
            'is_active' => true,
        ]);

        // base_salary người dùng nhập (999) phải bị BỎ QUA, khoá theo bậc.
        $this->actingAs($this->owner)->from('/employees')->post('/employees', $this->validPayload([
            'wage_tier_id' => $tier->id,
            'base_salary' => 999,
        ]))->assertRedirect('/employees')->assertSessionHasNoErrors();

        $emp = Employee::withoutGlobalScopes()->where('restaurant_id', $this->restaurant->id)->latest('id')->first();
        $this->assertNotNull($emp);
        $this->assertEquals($tier->id, $emp->wage_tier_id);
        $this->assertEquals('shift', $emp->compensation_type);
        $this->assertEqualsWithDelta(250000, (float) $emp->pay_rate, 0.01);
        $this->assertEqualsWithDelta(250000, (float) $emp->base_salary, 0.01);
    }

    public function test_over_budget_is_blocked(): void
    {
        BranchPayrollBudget::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'effective_month' => Carbon::now()->startOfMonth(),
            'budget_amount' => 5000000, // quỹ chỉ 5tr
        ]);
        $tier = WageTier::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Bếp trưởng',
            'compensation_type' => 'fixed',
            'rate' => 8000000, // 8tr/tháng > quỹ 5tr
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)->from('/employees')->post('/employees', $this->validPayload([
            'wage_tier_id' => $tier->id,
        ]))->assertRedirect('/employees')->assertSessionHasErrors(['base_salary']);

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_manager_must_pick_a_wage_tier(): void
    {
        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id, // để canAccessBranch() = true
            'status' => 'active',
        ]);
        $manager->assignRole('manager');

        // Chủ đã tạo sẵn bậc lương cho chi nhánh → Quản lý BẮT BUỘC chọn.
        WageTier::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Thu ngân',
            'compensation_type' => 'shift',
            'rate' => 250000,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->from('/employees')->post('/employees', $this->validPayload([
            'email' => 'staff.by.manager@example.com',
            // KHÔNG gửi wage_tier_id
        ]))->assertRedirect('/employees')->assertSessionHasErrors(['wage_tier_id']);

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_owner_can_create_without_tier_when_no_budget(): void
    {
        $this->actingAs($this->owner)->from('/employees')->post('/employees', $this->validPayload([
            'compensation_type' => 'fixed',
            'base_salary' => 7000000,
        ]))->assertRedirect('/employees')->assertSessionHasNoErrors();

        $emp = Employee::withoutGlobalScopes()->where('restaurant_id', $this->restaurant->id)->latest('id')->first();
        $this->assertNotNull($emp);
        $this->assertNull($emp->wage_tier_id);
        $this->assertEqualsWithDelta(7000000, (float) $emp->base_salary, 0.01);
    }
}
