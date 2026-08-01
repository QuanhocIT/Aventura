<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeSecurityAndLockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_stricter_employee_creation_requires_deep_profile_and_id_photos(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'owner_user_id' => $owner->id,
        ]);
        $owner->update(['restaurant_id' => $restaurant->id]);
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => $owner->id,
        ]);
        $owner->update(['branch_id' => $branch->id]);

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner->assignRole($ownerRole);

        // Attempting to create employee with missing fields
        $response = $this->actingAs($owner)->post('/employees', []);
        $response->assertSessionHasErrors([
            'name', 'email', 'phone', 'citizen_id_number', 'address', 'date_of_birth', 'citizen_id_front', 'citizen_id_back',
        ]);

        // Creating with valid data and file uploads
        $frontFile = UploadedFile::fake()->image('front.jpg');
        $backFile = UploadedFile::fake()->image('back.jpg');

        $data = [
            'name' => 'Test Employee',
            'email' => 'employee_test@aventura.vn',
            'phone' => '0912345678',
            'citizen_id_number' => '123456789012',
            'address' => '456 Tran Hung Dao, District 1, HCM',
            'date_of_birth' => '1995-10-15',
            'citizen_id_front' => $frontFile,
            'citizen_id_back' => $backFile,
            'hire_date' => '2026-05-01',
            'base_salary' => 8000000,
            'role' => 'cashier',
            'job_title' => 'Thu Ngan',
        ];

        $response = $this->actingAs($owner)->post('/employees', $data);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify the employee record exists
        $employee = Employee::where('email', 'employee_test@aventura.vn')->first();
        $this->assertNotNull($employee);
        $this->assertSame('123456789012', $employee->citizen_id_number);
        $this->assertSame('1995-10-15', $employee->date_of_birth->toDateString());
        $this->assertNotNull($employee->citizen_id_front_url);
        $this->assertNotNull($employee->citizen_id_back_url);

        // Check user creation and role mapping
        $createdUser = User::where('email', 'employee_test@aventura.vn')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->hasRole('cashier'));

        // Ảnh CCCD giờ nằm trên disk PRIVATE (local) — không còn truy cập
        // công khai qua /storage/ nữa (vá lộ PII 2026-07-30).
        Storage::disk('local')->assertExists($employee->citizen_id_front_url);
        Storage::disk('local')->assertExists($employee->citizen_id_back_url);
    }

    public function test_can_export_employee_profile(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $owner->update(['restaurant_id' => $restaurant->id]);

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner->assignRole($ownerRole);

        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => User::factory()->create(['restaurant_id' => $restaurant->id])->id,
            'role_id' => Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web'])->id,
        ]);

        // Accessing GET route for export
        $response = $this->actingAs($owner)->get("/employees/{$employee->id}/export-profile");
        $response->assertStatus(200);
        $response->assertSee('HỒ SƠ PHÁP LÝ & LÝ LỊCH TRÍCH NGANG NHÂN SỰ', false);
        $response->assertSee($employee->full_name);
        $response->assertSee($employee->employee_code);

        // Access unauthorized restaurant owner
        $otherOwner = User::factory()->create();
        $otherRestaurant = Restaurant::factory()->create(['owner_user_id' => $otherOwner->id]);
        $otherOwner->update(['restaurant_id' => $otherRestaurant->id]);
        $otherOwner->assignRole($ownerRole);

        // Owner nhà hàng khác không được xem hồ sơ nhân viên này. Nhờ route-model-binding
        // đã scope theo tenant (BelongsToRestaurant::resolveRouteBinding), truy cập chéo
        // trả 404 (không lộ sự tồn tại của resource) — an toàn hơn 403.
        $response = $this->actingAs($otherOwner)->get("/employees/{$employee->id}/export-profile");
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_locked_employee_is_instantly_logged_out_and_blocked_on_login(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $owner->update(['restaurant_id' => $restaurant->id]);
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'code' => 'LOCKING-A',
        ]);

        $cashierUser = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashierUser->assignRole($cashierRole);

        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'user_id' => $cashierUser->id,
            'role_id' => $cashierRole->id,
            'status' => 'active',
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Ca Chieu',
            'code' => 'CA_CHIEU',
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'status' => 'active',
        ]);

        ScheduleAssignment::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        // Login as cashier and access dashboard successfully
        $response = $this->actingAs($cashierUser)->get('/dashboard');
        $response->assertStatus(200);

        // Lock the user by setting status to inactive
        $cashierUser->update(['status' => 'inactive']);

        // Next request by cashier should be intercepted by SetTenantContext and logged out
        $response = $this->actingAs($cashierUser)->get('/dashboard');
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(auth()->check());

        // Try logging in using inactive credentials
        $response = $this->post('/login', [
            'email' => $cashierUser->email,
            'password' => 'password',
        ]);
        $response->assertSessionHasErrors(['email']);
    }
}
