<?php

namespace Tests\Feature;

use App\Models\CashCount;
use App\Models\CashHandover;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ShiftClosing;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\CashControlSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kiểm soát tiền cuối ca: đếm mù, giải trình chênh lệch, bàn giao hai chữ ký.
 */
class BlindCashCountTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private WorkShift $shift;

    private User $cashier;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->cashier = $this->makeUser('cashier');
        $this->manager = $this->makeUser('manager');
        $this->branch->update(['manager_user_id' => $this->manager->id]);

        $this->shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca sáng',
            'code' => 'CA-SANG',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'status' => 'active',
        ]);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function setSetting(string $key, mixed $value): void
    {
        DB::table('restaurant_settings')->updateOrInsert(
            ['restaurant_id' => $this->restaurant->id, 'branch_id' => null, 'key_name' => $key],
            ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    // ── Đếm mù ───────────────────────────────────────────────────────────────

    public function test_preview_hides_expected_cash_until_the_till_is_counted(): void
    {
        $response = $this->actingAs($this->cashier)->getJson(route('shift-closings.preview', [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertTrue($response->json('blind_count_required'));
        $this->assertNull($response->json('expected_cash'), 'Số kỳ vọng bị lộ trước khi đếm.');
        $this->assertNull($response->json('cash_sales_amount'));
    }

    public function test_counting_reveals_expected_cash_and_locks_the_count(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('shift-closings.count'), [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'denominations' => ['500000' => 2, '100000' => 3, '20000' => 1],
        ]);

        $response->assertOk();
        // Máy chủ tự tính tổng từ mệnh giá: 2×500k + 3×100k + 1×20k = 1.320.000
        $this->assertEquals(1_320_000, $response->json('total_counted'));
        $this->assertNotNull($response->json('expected_cash'));

        $count = CashCount::findOrFail($response->json('cash_count_id'));
        $this->assertNotNull($count->expected_revealed_at);

        // Đã lộ số kỳ vọng thì không sửa được số đếm nữa.
        $this->expectException(\RuntimeException::class);
        $count->update(['total_counted' => 999]);
    }

    public function test_server_recomputes_the_total_and_ignores_a_forged_one(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('shift-closings.count'), [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'denominations' => ['100000' => 1],
            'total_counted' => 5_000_000, // client cố tình gửi số khác
        ]);

        $response->assertOk();
        $this->assertEquals(100_000, $response->json('total_counted'));
    }

    public function test_invalid_denomination_is_rejected(): void
    {
        $this->actingAs($this->cashier)->postJson(route('shift-closings.count'), [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'denominations' => ['77777' => 1],
        ])->assertStatus(422);
    }

    public function test_preview_reveals_figures_after_a_count_exists(): void
    {
        $this->actingAs($this->cashier)->postJson(route('shift-closings.count'), [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'denominations' => ['100000' => 1],
        ])->assertOk();

        $response = $this->actingAs($this->cashier)->getJson(route('shift-closings.preview', [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
        ]));

        $this->assertFalse($response->json('blind_count_required'));
        $this->assertNotNull($response->json('expected_cash'));
        $this->assertEquals(100_000, $response->json('counted_cash'));
    }

    // ── Chốt ca ──────────────────────────────────────────────────────────────

    public function test_closing_without_counting_is_blocked(): void
    {
        $this->actingAs($this->cashier)
            ->post(route('shift-closings.store'), [
                'shift_id' => $this->shift->id,
                'closing_date' => today()->toDateString(),
                'actual_cash' => 500_000,
                'submit' => 1,
            ])
            ->assertSessionHasErrors('cash_count_id');

        $this->assertDatabaseCount('shift_closings', 0);
    }

    public function test_closing_amount_must_match_the_counted_amount(): void
    {
        $count = $this->actingAs($this->cashier)->postJson(route('shift-closings.count'), [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'denominations' => ['100000' => 1],
        ])->json();

        // Gõ lại một số khác sau khi đã biết kỳ vọng — đây chính là cách lách.
        $this->actingAs($this->cashier)
            ->post(route('shift-closings.store'), [
                'shift_id' => $this->shift->id,
                'closing_date' => today()->toDateString(),
                'actual_cash' => 0,
                'cash_count_id' => $count['cash_count_id'],
                'submit' => 1,
            ])
            ->assertSessionHasErrors('actual_cash');
    }

    public function test_variance_over_threshold_requires_an_explanation(): void
    {
        $this->setSetting(CashControlSettings::VARIANCE_THRESHOLD, 20000);

        // Không có doanh thu nên kỳ vọng = 0; đếm được 100k → chênh 100k.
        $count = $this->actingAs($this->cashier)->postJson(route('shift-closings.count'), [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'denominations' => ['100000' => 1],
        ])->json();

        $this->actingAs($this->cashier)
            ->post(route('shift-closings.store'), [
                'shift_id' => $this->shift->id,
                'closing_date' => today()->toDateString(),
                'actual_cash' => 100_000,
                'cash_count_id' => $count['cash_count_id'],
                'submit' => 1,
            ])
            ->assertSessionHasErrors('variance_explanation');

        // Có giải trình thì chốt được.
        $this->actingAs($this->cashier)
            ->post(route('shift-closings.store'), [
                'shift_id' => $this->shift->id,
                'closing_date' => today()->toDateString(),
                'actual_cash' => 100_000,
                'cash_count_id' => $count['cash_count_id'],
                'variance_explanation' => 'Khách trả thừa, chưa kịp hoàn lại.',
                'submit' => 1,
            ])
            ->assertRedirect();

        $closing = ShiftClosing::firstOrFail();
        $this->assertSame((int) $count['cash_count_id'], (int) $closing->cash_count_id);
        $this->assertNotNull($closing->variance_explained_at);
    }

    public function test_blind_count_can_be_switched_off_by_the_owner(): void
    {
        $this->setSetting(CashControlSettings::BLIND_COUNT, false);

        $response = $this->actingAs($this->cashier)->getJson(route('shift-closings.preview', [
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
        ]));

        $this->assertFalse($response->json('blind_count_required'));
        $this->assertNotNull($response->json('expected_cash'));
    }

    // ── Xác nhận của quản lý ─────────────────────────────────────────────────

    public function test_manager_cannot_confirm_their_own_closing(): void
    {
        $closing = ShiftClosing::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'cashier_user_id' => $this->manager->id,
            'expected_cash' => 0,
            'actual_cash' => 0,
            'cash_difference' => 0,
            'status' => 'submitted',
            'closed_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->patch(route('shift-closings.confirm', $closing))
            ->assertForbidden();
    }

    public function test_confirmation_is_blocked_while_a_variance_is_unexplained(): void
    {
        $closing = ShiftClosing::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $this->shift->id,
            'closing_date' => today()->toDateString(),
            'cashier_user_id' => $this->cashier->id,
            'expected_cash' => 500_000,
            'actual_cash' => 300_000,
            'cash_difference' => -200_000,
            'status' => 'submitted',
            'closed_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->patch(route('shift-closings.confirm', $closing))
            ->assertSessionHasErrors('error');

        $this->assertSame('submitted', $closing->fresh()->status);

        // Có giải trình rồi thì xác nhận được, và người xác nhận được ghi lại.
        $closing->update([
            'variance_explanation' => 'Thiếu tiền do trả nhầm cho khách.',
            'variance_explained_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->patch(route('shift-closings.confirm', $closing))
            ->assertRedirect();

        $closing->refresh();
        $this->assertSame('confirmed', $closing->status);
        $this->assertSame($this->manager->id, $closing->variance_confirmed_by);
    }

    // ── Bàn giao tiền ────────────────────────────────────────────────────────

    public function test_handover_needs_both_signatures_and_two_different_people(): void
    {
        $signature = 'data:image/png;base64,'.base64_encode('fake-signature');

        // Không tự bàn giao cho chính mình.
        $this->actingAs($this->cashier)
            ->post(route('cash-handovers.store'), [
                'to_user_id' => $this->cashier->id,
                'amount' => 1_000_000,
                'signature' => $signature,
            ])
            ->assertStatus(422);

        $this->actingAs($this->cashier)
            ->post(route('cash-handovers.store'), [
                'to_user_id' => $this->manager->id,
                'amount' => 1_000_000,
                'signature' => $signature,
            ])
            ->assertRedirect();

        $handover = CashHandover::firstOrFail();
        $this->assertSame(CashHandover::STATUS_PENDING, $handover->status);
        $this->assertFalse($handover->isFullySigned());

        // Người khác không ký thay người nhận được.
        $this->actingAs($this->cashier)
            ->patch(route('cash-handovers.acknowledge', $handover), ['signature' => $signature])
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->patch(route('cash-handovers.acknowledge', $handover), ['signature' => $signature])
            ->assertRedirect();

        $handover->refresh();
        $this->assertSame(CashHandover::STATUS_COMPLETED, $handover->status);
        $this->assertTrue($handover->isFullySigned());
    }

    public function test_recipient_can_dispute_a_mismatched_amount(): void
    {
        $signature = 'data:image/png;base64,'.base64_encode('fake-signature');

        $this->actingAs($this->cashier)->post(route('cash-handovers.store'), [
            'to_user_id' => $this->manager->id,
            'amount' => 1_000_000,
            'signature' => $signature,
        ])->assertRedirect();

        $handover = CashHandover::firstOrFail();

        $this->actingAs($this->manager)
            ->patch(route('cash-handovers.dispute', $handover), [
                'dispute_reason' => 'Đếm lại chỉ có 800.000đ.',
            ])
            ->assertRedirect();

        $this->assertSame(CashHandover::STATUS_DISPUTED, $handover->fresh()->status);
    }
}
