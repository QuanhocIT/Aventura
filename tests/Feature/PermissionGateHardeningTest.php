<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\ViolationReport;
use App\Support\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Khoá lại các action ghi dữ liệu từng thiếu gate phân quyền.
 *
 * Ba controller dưới đây đều có gate ở index() nhưng bỏ sót ở các action ghi,
 * nên người không xem được danh sách vẫn sửa được dữ liệu.
 */
class PermissionGateHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private User $owner;

    private User $manager;

    private User $kitchen;

    private User $cashier;

    private Employee $kitchenEmployee;

    private Employee $cashierEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'manage_orders', 'view_report', 'manage_kitchen', 'manage_customers',
            'report_violations', 'view_violations', 'manage_violations', 'process_payments',
        ] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        // Đúng theo PermissionsSeeder: bếp CÓ quyền tố giác nhưng KHÔNG có
        // manage_customers và KHÔNG có manage_orders.
        $managerRole->givePermissionTo(['manage_orders', 'view_report', 'manage_customers', 'report_violations', 'view_violations']);
        $kitchenRole->givePermissionTo(['manage_kitchen', 'report_violations']);
        $cashierRole->givePermissionTo(['process_payments', 'manage_customers']);

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['branch_id' => $this->branch->id]);

        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        [$this->manager] = $this->makeStaff($managerRole);
        [$this->kitchen, $this->kitchenEmployee] = $this->makeStaff($kitchenRole);
        [$this->cashier, $this->cashierEmployee] = $this->makeStaff($cashierRole);
    }

    /** @return array{0: User, 1: Employee} */
    private function makeStaff(Role $role): array
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $employee];
    }

    /** @return array<string, mixed> */
    private function violationPayload(int $employeeId): array
    {
        return [
            'employee_id' => $employeeId,
            'violation_type' => 'Đi trễ',
            'description' => 'Nhân viên đi trễ 30 phút không báo trước.',
            'is_anonymous' => false,
            'occurred_at' => now()->subHour()->toDateTimeString(),
        ];
    }

    // ── Biên bản vi phạm ───────────────────────────────────────────────────

    public function test_staff_without_the_permission_cannot_file_a_violation_report(): void
    {
        $outsiderRole = Role::firstOrCreate(['name' => 'warehouse_staff', 'guard_name' => 'web']);
        [$outsider] = $this->makeStaff($outsiderRole);

        $this->actingAs($outsider)
            ->post('/violations', $this->violationPayload($this->cashierEmployee->id))
            ->assertStatus(403);

        $this->assertSame(0, ViolationReport::count());
    }

    public function test_nobody_can_file_a_violation_report_against_themselves(): void
    {
        // Biên bản vi phạm kích hoạt SalaryRecalculationObserver, nên tự lập
        // biên bản cho chính mình là một đường thao túng lương của bản thân.
        $this->actingAs($this->kitchen)
            ->post('/violations', $this->violationPayload($this->kitchenEmployee->id))
            ->assertStatus(403);

        $this->assertSame(0, ViolationReport::count());
    }

    public function test_staff_with_the_permission_can_still_report_someone_else(): void
    {
        $this->actingAs($this->kitchen)
            ->post('/violations', $this->violationPayload($this->cashierEmployee->id))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ViolationReport::count());
    }

    // ── Khách hàng ─────────────────────────────────────────────────────────

    public function test_kitchen_cannot_create_or_edit_customers(): void
    {
        $payload = ['full_name' => 'Nguyễn Văn A', 'phone' => '0900000001'];

        $this->actingAs($this->kitchen)
            ->post('/customers', $payload)
            ->assertStatus(403);

        $this->assertDatabaseMissing('customers', ['phone' => '0900000001']);
    }

    public function test_cashier_can_still_manage_customers(): void
    {
        $this->actingAs($this->cashier)
            ->post('/customers', ['full_name' => 'Nguyễn Văn B', 'phone' => '0900000002'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', ['phone' => '0900000002']);
    }

    public function test_kitchen_cannot_edit_an_existing_customer(): void
    {
        $this->actingAs($this->cashier)
            ->post('/customers', ['full_name' => 'Nguyễn Văn C', 'phone' => '0900000003']);

        $customer = Customer::where('phone', '0900000003')->firstOrFail();

        $this->actingAs($this->kitchen)
            ->patch("/customers/{$customer->id}", [
                'full_name' => 'Bị sửa trộm',
                'phone' => '0900000003',
            ])
            ->assertStatus(403);

        $this->assertSame('Nguyễn Văn C', $customer->fresh()->full_name);
    }

    // ── Trưng bày thực đơn ─────────────────────────────────────────────────

    public function test_kitchen_cannot_reorder_the_menu(): void
    {
        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'display_order' => 1,
        ]);

        $this->actingAs($this->kitchen)
            ->postJson('/menu-engineering/display-order', [
                'items' => [['id' => $product->id, 'display_order' => 99]],
            ])
            ->assertStatus(403);

        $this->assertSame(1, (int) $product->fresh()->display_order);
    }

    public function test_kitchen_cannot_change_a_product_time_slot(): void
    {
        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'time_slot' => null,
        ]);

        $this->actingAs($this->kitchen)
            ->patch("/menu-engineering/products/{$product->id}/time-slot", ['time_slot' => 'dinner'])
            ->assertStatus(403);

        $this->assertNull($product->fresh()->time_slot);
    }

    public function test_manager_can_still_reorder_the_menu(): void
    {
        // Sắp xếp thực đơn là nghiệp vụ trưng bày, không phải đổi giá — siết
        // quá tay thành owner-only sẽ chặn nhầm công việc hằng ngày của Quản lý.
        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'display_order' => 1,
        ]);

        $this->actingAs($this->manager)
            ->postJson('/menu-engineering/display-order', [
                'items' => [['id' => $product->id, 'display_order' => 7]],
            ])
            ->assertOk();

        $this->assertSame(7, (int) $product->fresh()->display_order);
    }
}
