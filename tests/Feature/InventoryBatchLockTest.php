<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryBatch;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\BatchRecallRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Khóa lô & thu hồi: quản lý khóa lô (loại khỏi tiêu thụ) + gửi yêu cầu kho thu hồi
 * (báo Chủ + Trưởng kho); chỉ Chủ mở khóa; chặn khác nhà hàng.
 */
class InventoryBatchLockTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private User $warehouseManager;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private InventoryBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'warehouse_manager', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id, 'status' => 'active']);
        $this->manager->assignRole('manager');
        $this->warehouseManager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->warehouseManager->assignRole('warehouse_manager');

        $unit = Unit::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Kg', 'symbol' => 'kg', 'type' => 'weight']);
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id,
            'unit_id' => $unit->id, 'name' => 'Cá hồi', 'sku' => 'CH-01',
            'average_cost' => 300000, 'status' => 'active',
        ]);
        $this->batch = InventoryBatch::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id,
            'ingredient_id' => $ingredient->id, 'batch_number' => 'LOT-001',
            'quantity_remaining' => 10, 'unit_cost' => 300000,
            'purchased_at' => now()->subDay()->toDateString(),
            'expiry_date' => now()->addDays(2)->toDateString(), 'status' => 'active',
        ]);
    }

    public function test_manager_can_lock_batch(): void
    {
        $this->actingAs($this->manager)->post("/inventory/batches/{$this->batch->id}/lock", [
            'reason' => 'Nghi nhiễm khuẩn, ngừng sử dụng',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->batch->refresh();
        $this->assertEquals('locked', $this->batch->status);
        $this->assertEquals($this->manager->id, $this->batch->locked_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory_batch_locked', 'subject_id' => $this->batch->id]);
    }

    public function test_lock_requires_reason(): void
    {
        $this->actingAs($this->manager)->from('/inventory')
            ->post("/inventory/batches/{$this->batch->id}/lock", [])
            ->assertSessionHasErrors(['reason']);
        $this->assertEquals('active', $this->batch->refresh()->status);
    }

    public function test_recall_request_notifies_owner_and_warehouse(): void
    {
        Notification::fake();

        $this->actingAs($this->manager)->post("/inventory/batches/{$this->batch->id}/recall", [
            'note' => 'Thu hồi để tiêu hủy',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->batch->refresh();
        $this->assertEquals('recalled', $this->batch->status);
        $this->assertEquals($this->manager->id, $this->batch->recall_requested_by);

        Notification::assertSentTo($this->owner, BatchRecallRequestedNotification::class);
        Notification::assertSentTo($this->warehouseManager, BatchRecallRequestedNotification::class);
    }

    public function test_only_owner_can_unlock(): void
    {
        $this->batch->update(['status' => 'locked', 'locked_by' => $this->manager->id, 'locked_at' => now(), 'lock_reason' => 'x']);

        // Quản lý không được mở khóa.
        $this->actingAs($this->manager)->post("/inventory/batches/{$this->batch->id}/unlock")
            ->assertForbidden();
        $this->assertEquals('locked', $this->batch->refresh()->status);

        // Chủ mở khóa được.
        $this->actingAs($this->owner)->post("/inventory/batches/{$this->batch->id}/unlock")
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('active', $this->batch->refresh()->status);
    }

    public function test_other_restaurant_cannot_lock(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $otherManager = User::factory()->create(['restaurant_id' => $otherRestaurant->id, 'status' => 'active']);
        $otherManager->assignRole('manager');

        $response = $this->actingAs($otherManager)->post("/inventory/batches/{$this->batch->id}/lock", [
            'reason' => 'Cố khóa lô nhà hàng khác',
        ]);
        $this->assertContains($response->status(), [403, 404]);
        $this->assertEquals('active', $this->batch->refresh()->status);
    }
}
