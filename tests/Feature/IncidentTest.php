<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Notifications\IncidentEscalatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sổ sự cố khẩn cấp: mọi nhân viên được báo; sự cố nghiêm trọng tự động escalate lên
 * Chủ (kèm thông báo); không được xoá; đóng phải kèm báo cáo; chỉ Quản lý/Chủ xử lý.
 */
class IncidentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $staff;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->staff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->staff->assignRole('cashier');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'other',
            'severity' => 'low',
            'title' => 'Sự cố nhỏ',
            'description' => 'Mô tả sự cố đủ dài để qua validation.',
            'occurred_at' => Carbon::now()->subHour()->toDateTimeString(),
            'injured_count' => 0,
        ], $overrides);
    }

    public function test_minor_incident_is_not_escalated(): void
    {
        Notification::fake();

        $this->actingAs($this->staff)->post('/incidents', $this->payload())
            ->assertRedirect()->assertSessionHasNoErrors();

        $incident = Incident::withoutGlobalScopes()->latest('id')->first();
        $this->assertNotNull($incident);
        $this->assertFalse($incident->escalated);
        $this->assertEquals('open', $incident->status);
        Notification::assertNothingSent();
    }

    public function test_fire_incident_auto_escalates_and_notifies_owner(): void
    {
        Notification::fake();

        $this->actingAs($this->staff)->post('/incidents', $this->payload([
            'type' => 'fire',
            'severity' => 'low', // dù mức độ thấp, cháy nổ vẫn phải escalate
            'title' => 'Chập điện khu bếp',
        ]))->assertRedirect();

        $incident = Incident::withoutGlobalScopes()->latest('id')->first();
        $this->assertTrue($incident->escalated);
        $this->assertEquals('escalated', $incident->status);
        $this->assertEquals($this->owner->id, $incident->escalated_to);

        Notification::assertSentTo($this->owner, IncidentEscalatedNotification::class);
    }

    public function test_injury_forces_escalation(): void
    {
        Notification::fake();

        $this->actingAs($this->staff)->post('/incidents', $this->payload([
            'type' => 'other',
            'severity' => 'low',
            'injured_count' => 1,
        ]))->assertRedirect();

        $this->assertTrue(Incident::withoutGlobalScopes()->latest('id')->first()->escalated);
    }

    public function test_incident_cannot_be_deleted(): void
    {
        $incident = Incident::create($this->payload([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'reported_by' => $this->staff->id,
            'status' => 'open',
        ]));

        $this->expectException(\RuntimeException::class);
        $incident->delete();
    }

    public function test_resolve_requires_report(): void
    {
        $incident = Incident::create($this->payload([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'reported_by' => $this->staff->id,
            'status' => 'open',
        ]));

        // Thiếu báo cáo → lỗi validation.
        $this->actingAs($this->owner)->post("/incidents/{$incident->id}/resolve", [])
            ->assertSessionHasErrors(['resolution_report']);
        $this->assertEquals('open', $incident->refresh()->status);

        // Có báo cáo đầy đủ → đóng được.
        $this->actingAs($this->owner)->post("/incidents/{$incident->id}/resolve", [
            'resolution_report' => 'Đã sơ cứu, gọi bảo trì, khắc phục xong và ghi nhận đầy đủ.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $incident->refresh();
        $this->assertEquals('resolved', $incident->status);
        $this->assertEquals($this->owner->id, $incident->resolved_by);
    }

    public function test_staff_cannot_acknowledge_or_resolve(): void
    {
        $incident = Incident::create($this->payload([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'reported_by' => $this->staff->id,
            'status' => 'open',
        ]));

        $this->actingAs($this->staff)->post("/incidents/{$incident->id}/acknowledge")
            ->assertForbidden();
        $this->actingAs($this->staff)->post("/incidents/{$incident->id}/resolve", [
            'resolution_report' => 'Nhân viên thường không được đóng sự cố dù có báo cáo.',
        ])->assertForbidden();
    }

    public function test_other_restaurant_cannot_resolve(): void
    {
        $incident = Incident::create($this->payload([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'reported_by' => $this->staff->id,
            'status' => 'open',
        ]));

        $otherRestaurant = Restaurant::factory()->create();
        $otherOwner = User::factory()->create(['restaurant_id' => $otherRestaurant->id, 'status' => 'active']);
        $otherOwner->assignRole('owner');

        $response = $this->actingAs($otherOwner)->post("/incidents/{$incident->id}/resolve", [
            'resolution_report' => 'Người ngoài nhà hàng cố đóng sự cố không thuộc quyền.',
        ]);
        $this->assertContains($response->status(), [403, 404]);
        $this->assertEquals('open', $incident->refresh()->status);
    }
}
