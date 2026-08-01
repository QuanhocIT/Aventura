<?php

namespace Tests\Feature;

use App\Models\Delivery\Shipper;
use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage cho quản lý Shipper (delivery/shippers) — trước đây chưa có test.
 * Bảo vệ luôn bug đã fix (query employees select cột 'name' không tồn tại) qua trang
 * index, và kiểm tra tenant-scoping (destroy có check thủ công + IDOR fix).
 */
class ShipperManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);

        $this->employee = Employee::factory()->create(['restaurant_id' => $this->restaurant->id]);
    }

    public function test_shippers_page_renders(): void
    {
        // Trang này từng lỗi 500 (query employees select cột 'name' không tồn tại) — đã fix.
        $this->actingAs($this->owner)->get('/delivery/shippers')->assertOk();
    }

    public function test_owner_can_create_shipper(): void
    {
        $res = $this->actingAs($this->owner)->postJson('/delivery/shippers', [
            'employee_id' => $this->employee->id,
            'vehicle_type' => 'motorbike',
            'vehicle_plate' => '59X1-12345',
        ]);

        $this->assertLessThan(300, $res->getStatusCode());
        $this->assertDatabaseHas('shippers', [
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee->id,
            'vehicle_type' => 'motorbike',
        ]);
    }

    public function test_create_shipper_validates_vehicle_type(): void
    {
        $this->actingAs($this->owner)->postJson('/delivery/shippers', [
            'employee_id' => $this->employee->id,
            'vehicle_type' => 'ufo',
        ])->assertStatus(422);
    }

    public function test_shipper_is_tenant_scoped(): void
    {
        $other = Restaurant::factory()->create();
        $otherEmployee = Employee::factory()->create(['restaurant_id' => $other->id]);
        $foreign = Shipper::create([
            'restaurant_id' => $other->id,
            'employee_id' => $otherEmployee->id,
            'vehicle_type' => 'bike',
            'is_active' => true,
        ]);

        // Không được vô hiệu hoá shipper của nhà hàng khác.
        $this->actingAs($this->owner)->delete("/delivery/shippers/{$foreign->id}");
        $this->assertDatabaseHas('shippers', ['id' => $foreign->id, 'deleted_at' => null, 'is_active' => true]);
    }
}
