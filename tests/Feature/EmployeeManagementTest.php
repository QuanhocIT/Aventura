<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\BranchPayrollBudget;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage cho EmployeeManagementController (trước đây chưa có test riêng):
 * CRUD nhân viên + bảo mật ảnh CCCD (disk private, route có auth thay vì
 * đường dẫn /storage/ công khai đoán được).
 */
class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
    }

    private function validEmployeePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Trần Thị Nhân Viên',
            'email' => 'nhanvien@example.com',
            'phone' => '0912345678',
            'citizen_id_number' => '079123456789',
            'address' => '123 Lê Lợi, Quận 1, TP.HCM',
            'date_of_birth' => '1998-05-20',
            'citizen_id_front' => UploadedFile::fake()->image('front.jpg', 600, 400),
            'citizen_id_back' => UploadedFile::fake()->image('back.jpg', 600, 400),
            'hire_date' => now()->toDateString(),
            'base_salary' => 8000000,
            'role' => 'waiter',
            'job_title' => 'Phục vụ',
        ], $overrides);
    }

    public function test_employees_page_renders(): void
    {
        $this->actingAs($this->owner)->get('/employees')->assertOk();
    }

    public function test_owner_account_is_not_displayed_in_employee_list(): void
    {
        $ownerEmployee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->owner->id,
            'full_name' => $this->owner->name,
        ]);
        $staffEmployee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'full_name' => 'Nhân viên hiển thị',
        ]);

        $response = $this->actingAs($this->owner)->get('/employees');
        $response->assertOk();

        $props = $response->original->getData()['page']['props'];
        $employees = collect($props['employees']);

        $this->assertFalse($employees->contains('id', $ownerEmployee->id));
        $this->assertTrue($employees->contains('id', $staffEmployee->id));
    }

    public function test_employee_without_ratings_does_not_receive_a_five_star_display_value(): void
    {
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'rating_star' => 5.0,
            'rating_count' => 0,
        ]);

        $response = $this->actingAs($this->owner)->get('/employees');
        $response->assertOk();

        $props = $response->original->getData()['page']['props'];
        $employeeData = collect($props['employees'])->firstWhere('id', $employee->id);

        $this->assertNotNull($employeeData);
        $this->assertNull($employeeData['rating_star']);
        $this->assertSame(0, $employeeData['rating_count']);
    }

    public function test_owner_can_create_employee_and_cccd_is_stored_privately(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $response = $this->actingAs($this->owner)
            ->post('/employees', $this->validEmployeePayload());

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $employee = Employee::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurant->id)
            ->where('email', 'nhanvien@example.com')
            ->first();

        $this->assertNotNull($employee);

        // Ảnh CCCD phải nằm trên disk PRIVATE, giá trị DB là path thô
        // (không còn tiền tố /storage/ công khai).
        $this->assertNotEmpty($employee->citizen_id_front_url);
        $this->assertStringStartsWith('citizen_ids/', $employee->citizen_id_front_url);
        /** @var \Illuminate\Filesystem\FilesystemAdapter $localDisk */
        $localDisk = Storage::disk('local');
        $localDisk->assertExists($employee->citizen_id_front_url);
        $localDisk->assertExists($employee->citizen_id_back_url);

        // Tuyệt đối không có file nào rơi vào disk public
        $this->assertSame([], Storage::disk('public')->allFiles('citizen_ids'));

        // User đi kèm được tạo ở trạng thái hoạt động ngay
        $this->assertDatabaseHas('users', [
            'email' => 'nhanvien@example.com',
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
    }

    public function test_branch_manager_can_create_frontline_staff_but_cannot_grant_elevated_roles(): void
    {
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $manager->assignRole('manager');

        BranchPayrollBudget::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $branch->id,
            'effective_month' => now()->startOfMonth(),
            'budget_amount' => 50000000,
        ]);

        $allowedResponse = $this->actingAs($manager)
            ->withSession(['active_branch_id' => $branch->id])
            ->post('/employees', $this->validEmployeePayload([
                'email' => 'order-manager-created@example.com',
                'role' => 'waiter',
                'branch_id' => $branch->id,
            ]));

        $allowedResponse->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email' => 'order-manager-created@example.com',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $forbiddenResponse = $this->actingAs($manager)
            ->withSession(['active_branch_id' => $branch->id])
            ->post('/employees', $this->validEmployeePayload([
                'email' => 'warehouse-manager-created@example.com',
                'role' => 'warehouse_manager',
                'branch_id' => $branch->id,
            ]));

        $forbiddenResponse->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', [
            'email' => 'warehouse-manager-created@example.com',
        ]);
    }

    public function test_branch_manager_cannot_create_staff_before_owner_grants_payroll_budget(): void
    {
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $manager->assignRole('manager');

        $response = $this->actingAs($manager)
            ->withSession(['active_branch_id' => $branch->id])
            ->from('/employees')
            ->post('/employees', $this->validEmployeePayload([
                'email' => 'blocked-before-budget@example.com',
                'branch_id' => $branch->id,
            ]));

        $response->assertRedirect('/employees')->assertSessionHasErrors('base_salary');
        $this->assertDatabaseMissing('users', ['email' => 'blocked-before-budget@example.com']);
    }

    public function test_create_employee_requires_cccd_images(): void
    {
        $response = $this->actingAs($this->owner)->post(
            '/employees',
            $this->validEmployeePayload(['citizen_id_front' => null, 'citizen_id_back' => null])
        );

        $response->assertSessionHasErrors(['citizen_id_front', 'citizen_id_back']);
    }

    public function test_citizen_id_image_requires_authentication(): void
    {
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'citizen_id_front_url' => 'citizen_ids/front.jpg',
        ]);

        $this->get("/employees/{$employee->id}/citizen-id/front")
            ->assertRedirect(route('login'));
    }

    public function test_citizen_id_image_denied_for_normal_staff(): void
    {
        Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $staff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $staff->assignRole('cashier');

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'citizen_id_front_url' => 'citizen_ids/front.jpg',
        ]);

        $this->actingAs($staff)
            ->get("/employees/{$employee->id}/citizen-id/front")
            ->assertStatus(403);
    }

    public function test_owner_of_another_restaurant_cannot_view_citizen_id(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $otherOwner = User::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'status' => 'active',
        ]);
        $otherOwner->assignRole('owner');
        $otherRestaurant->update(['owner_user_id' => $otherOwner->id]);

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'citizen_id_front_url' => 'citizen_ids/front.jpg',
        ]);

        // IDOR fix: route-model-binding scope theo tenant → 404 (hoặc 403 nếu lọt binding)
        $status = $this->actingAs($otherOwner)
            ->get("/employees/{$employee->id}/citizen-id/front")
            ->getStatusCode();

        $this->assertContains($status, [403, 404]);
    }

    public function test_owner_can_view_citizen_id_image(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('citizen_ids/front-ok.jpg', 'anh-gia-lap');

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'citizen_id_front_url' => 'citizen_ids/front-ok.jpg',
        ]);

        $this->actingAs($this->owner)
            ->get("/employees/{$employee->id}/citizen-id/front")
            ->assertOk();
    }

    public function test_owner_can_update_employee(): void
    {
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'full_name' => 'Tên Cũ',
        ]);

        $response = $this->actingAs($this->owner)->patch("/employees/{$employee->id}", [
            'full_name' => 'Tên Mới Cập Nhật',
            'job_title' => 'Trưởng ca',
        ]);

        $response->assertRedirect();

        $employee->refresh();
        $this->assertSame('Tên Mới Cập Nhật', $employee->full_name);
    }

    public function test_owner_can_toggle_employee_status(): void
    {
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->owner)
            ->patch("/employees/{$employee->id}/toggle-status")
            ->assertRedirect();

        $employee->refresh();
        $this->assertSame('inactive', $employee->status);
    }

    public function test_normal_staff_cannot_manage_employee_accounts_or_view_the_employee_page(): void
    {
        $staff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $staff->assignRole('cashier');

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);

        $this->actingAs($staff)->get('/employees')->assertForbidden();
        $this->actingAs($staff)->post('/employees', [])->assertForbidden();
        $this->actingAs($staff)->patch("/employees/{$employee->id}", [
            'full_name' => 'Không được phép',
        ])->assertForbidden();
        $this->actingAs($staff)
            ->patch("/employees/{$employee->id}/toggle-status")
            ->assertForbidden();
    }

    public function test_normal_staff_cannot_change_restaurant_settings(): void
    {
        $staff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $staff->assignRole('cashier');

        $this->actingAs($staff)->get('/settings/restaurant')->assertForbidden();
        $this->actingAs($staff)->get('/online-store')->assertForbidden();
        $this->actingAs($staff)->get('/settings/integrations')->assertForbidden();
        $this->actingAs($staff)->get('/operation-policies')->assertForbidden();
    }
}
