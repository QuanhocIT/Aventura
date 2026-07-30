<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage cho TableReservationController — trước đây có 0 test dù đây là
 * luồng khách đặt bàn công khai + vòng đời xác nhận/dẫn khách/hủy/no-show.
 */
class TableReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

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

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => null,
        ]);
    }

    private function makeTable(string $name = 'Bàn 1', string $status = 'available'): RestaurantTable
    {
        $area = Area::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        return RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $area->id,
            'name' => $name,
            'capacity' => 4,
            'status' => $status,
            'qr_code' => 'QR-'.uniqid(),
            'qr_token' => 'token-'.uniqid(),
        ]);
    }

    private function makeReservation(array $overrides = []): TableReservation
    {
        return TableReservation::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'guest_name' => 'Nguyễn Văn Khách',
            'guest_phone' => '0901234567',
            'reservation_date' => today()->addDay()->toDateString(),
            'reservation_time' => '19:00:00',
            'party_size' => 4,
            'status' => 'pending',
            'source' => 'qr',
        ], $overrides));
    }

    // ─────────── Khách đặt bàn công khai ───────────

    public function test_guest_can_create_public_reservation(): void
    {
        $this->makeTable();

        $response = $this->postJson("/r/{$this->restaurant->id}/reservations", [
            'guest_name' => 'Trần Thị Khách',
            'guest_phone' => '0912345678',
            'reservation_date' => today()->addDay()->toDateString(),
            'reservation_time' => '18:30',
            'party_size' => 2,
            'source' => 'qr',
        ]);

        $this->assertLessThan(400, $response->getStatusCode());

        $this->assertDatabaseHas('table_reservations', [
            'restaurant_id' => $this->restaurant->id,
            'guest_phone' => '0912345678',
            'party_size' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_public_reservation_rejects_past_date(): void
    {
        $this->makeTable();

        $this->postJson("/r/{$this->restaurant->id}/reservations", [
            'guest_name' => 'Khách Quá Khứ',
            'guest_phone' => '0912345678',
            'reservation_date' => today()->subDay()->toDateString(),
            'reservation_time' => '18:30',
            'party_size' => 2,
        ])->assertStatus(422);

        $this->assertSame(0, TableReservation::withoutGlobalScopes()->count());
    }

    public function test_public_reservation_blocked_when_all_tables_are_taken(): void
    {
        // Nhà hàng chỉ có 1 bàn, đã có 1 đặt bàn confirmed cùng khung giờ
        $this->makeTable();
        $this->makeReservation([
            'reservation_time' => '20:00:00',
            'status' => 'confirmed',
        ]);

        $response = $this->postJson("/r/{$this->restaurant->id}/reservations", [
            'guest_name' => 'Khách Đến Muộn',
            'guest_phone' => '0999888777',
            'reservation_date' => today()->addDay()->toDateString(),
            'reservation_time' => '20:00',
            'party_size' => 2,
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, TableReservation::withoutGlobalScopes()->count());
    }

    // ─────────── Vòng đời đặt bàn phía nhà hàng ───────────

    public function test_reservations_page_renders(): void
    {
        $this->actingAs($this->owner)->get('/reservations')->assertOk();
    }

    public function test_owner_can_confirm_reservation_and_assign_table(): void
    {
        $table = $this->makeTable();
        $reservation = $this->makeReservation();

        $this->actingAs($this->owner)
            ->post("/reservations/{$reservation->id}/confirm", ['table_id' => $table->id])
            ->assertRedirect();

        $reservation->refresh();
        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame($table->id, (int) $reservation->table_id);
        $this->assertNotNull($reservation->confirmed_at);
        $this->assertSame($this->owner->id, (int) $reservation->confirmed_by);
    }

    public function test_cannot_assign_same_table_twice_in_overlapping_window(): void
    {
        $table = $this->makeTable();

        // Bàn đã được gán cho 1 đặt bàn lúc 19:00
        $this->makeReservation([
            'reservation_time' => '19:00',
            'status' => 'confirmed',
            'table_id' => $table->id,
        ]);

        // Đặt bàn khác lúc 19:30 — nằm trong cửa sổ ±90 phút, phải bị chặn
        $conflicting = $this->makeReservation(['reservation_time' => '19:30']);

        $response = $this->actingAs($this->owner)
            ->post("/reservations/{$conflicting->id}/confirm", ['table_id' => $table->id]);

        $response->assertSessionHasErrors(['table_id']);

        $conflicting->refresh();
        $this->assertSame('pending', $conflicting->status);
    }

    public function test_cannot_confirm_reservation_twice(): void
    {
        $reservation = $this->makeReservation(['status' => 'confirmed']);

        $this->actingAs($this->owner)
            ->post("/reservations/{$reservation->id}/confirm", [])
            ->assertSessionHasErrors();
    }

    public function test_seating_guest_marks_table_occupied(): void
    {
        $table = $this->makeTable();
        $reservation = $this->makeReservation([
            'status' => 'confirmed',
            'table_id' => $table->id,
        ]);

        $this->actingAs($this->owner)
            ->post("/reservations/{$reservation->id}/seat")
            ->assertRedirect();

        $reservation->refresh();
        $table->refresh();

        $this->assertSame('seated', $reservation->status);
        $this->assertNotNull($reservation->seated_at);
        $this->assertSame('occupied', $table->status);
    }

    public function test_cannot_seat_unconfirmed_reservation(): void
    {
        $reservation = $this->makeReservation(); // pending

        $this->actingAs($this->owner)
            ->post("/reservations/{$reservation->id}/seat")
            ->assertSessionHasErrors();

        $this->assertSame('pending', $reservation->refresh()->status);
    }

    public function test_owner_can_cancel_reservation_with_reason(): void
    {
        $reservation = $this->makeReservation();

        $this->actingAs($this->owner)
            ->post("/reservations/{$reservation->id}/cancel", ['reason' => 'Khách gọi điện xin hủy'])
            ->assertRedirect();

        $reservation->refresh();
        $this->assertSame('cancelled', $reservation->status);
        $this->assertSame('Khách gọi điện xin hủy', $reservation->cancellation_reason);
        $this->assertNotNull($reservation->cancelled_at);
    }

    public function test_cancel_requires_a_reason(): void
    {
        $reservation = $this->makeReservation();

        $this->actingAs($this->owner)
            ->post("/reservations/{$reservation->id}/cancel", ['reason' => 'ok'])
            ->assertSessionHasErrors(['reason']);

        $this->assertSame('pending', $reservation->refresh()->status);
    }

    public function test_no_show_releases_reserved_table_and_writes_audit_log(): void
    {
        $table = $this->makeTable('Bàn giữ chỗ', 'reserved');
        $reservation = $this->makeReservation([
            'status' => 'confirmed',
            'table_id' => $table->id,
        ]);

        $this->actingAs($this->owner)
            ->post("/reservations/{$reservation->id}/no-show")
            ->assertRedirect();

        $reservation->refresh();
        $table->refresh();

        $this->assertSame('no_show', $reservation->status);
        // Bàn giữ chỗ được trả về available để bán cho khách khác
        $this->assertSame('available', $table->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reservation_no_show',
            'event' => 'updated',
            'subject_id' => $reservation->id,
            'user_id' => $this->owner->id,
        ]);
    }

    // ─────────── Cách ly tenant ───────────

    public function test_owner_cannot_touch_reservation_of_another_restaurant(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $foreignReservation = TableReservation::create([
            'restaurant_id' => $otherRestaurant->id,
            'guest_name' => 'Khách nhà hàng khác',
            'guest_phone' => '0900000001',
            'reservation_date' => today()->addDay()->toDateString(),
            'reservation_time' => '19:00',
            'party_size' => 2,
            'status' => 'pending',
        ]);

        $status = $this->actingAs($this->owner)
            ->post("/reservations/{$foreignReservation->id}/confirm", [])
            ->getStatusCode();

        $this->assertContains($status, [403, 404]);
        $this->assertSame('pending', $foreignReservation->refresh()->status);
    }
}
