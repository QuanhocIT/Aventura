<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ShiftHandover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bàn giao ca: checklist theo chi nhánh, hai bên xác nhận, không bàn giao thiếu.
 */
class ShiftHandoverTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private User $owner;

    private User $outgoing;

    private User $incoming;

    private ChecklistTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->owner = $this->makeUser('owner');
        $this->outgoing = $this->makeUser('cashier');
        $this->incoming = $this->makeUser('cashier');

        $this->template = ChecklistTemplate::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Bàn giao ca',
            'type' => ChecklistTemplate::TYPE_HANDOVER,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        ChecklistItem::create([
            'template_id' => $this->template->id,
            'title' => 'Kiểm đếm tiền két',
            'requires_photo' => false,
            'sort_order' => 0,
        ]);
        ChecklistItem::create([
            'template_id' => $this->template->id,
            'title' => 'Chụp ảnh khu bếp',
            'requires_photo' => true,
            'sort_order' => 1,
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

    private function openHandover(): ShiftHandover
    {
        $this->actingAs($this->outgoing)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('shift-handovers.store'), [
                'template_id' => $this->template->id,
                'handover_date' => today()->toDateString(),
            ])
            ->assertRedirect();

        return ShiftHandover::firstOrFail();
    }

    // ── Phạm vi chi nhánh ────────────────────────────────────────────────────

    public function test_template_assigned_to_one_branch_is_hidden_from_another(): void
    {
        $otherBranch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->template->branches()->sync([$otherBranch->id]);

        $this->actingAs($this->outgoing)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('shift-handovers.store'), [
                'template_id' => $this->template->id,
                'handover_date' => today()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_template_without_branches_applies_chain_wide(): void
    {
        // Không gán chi nhánh nào = áp cho toàn chuỗi, giữ hành vi cũ.
        $handover = $this->openHandover();

        $this->assertSame($this->template->id, $handover->template_id);
    }

    // ── Không bàn giao thiếu ─────────────────────────────────────────────────

    public function test_cannot_submit_while_checklist_is_incomplete(): void
    {
        $handover = $this->openHandover();

        $this->actingAs($this->outgoing)
            ->patch(route('shift-handovers.submit', $handover), [
                'to_user_id' => $this->incoming->id,
                'cash_amount' => 500_000,
            ])
            ->assertSessionHasErrors('checklist');

        $this->assertSame(ShiftHandover::STATUS_DRAFT, $handover->fresh()->status);
    }

    public function test_item_requiring_a_photo_cannot_be_ticked_without_one(): void
    {
        $handover = $this->openHandover();
        $photoItem = ChecklistItem::where('template_id', $this->template->id)
            ->where('requires_photo', true)->firstOrFail();

        $this->actingAs($this->outgoing)
            ->post(route('shift-handovers.check', $handover), [
                'item_id' => $photoItem->id,
                'is_done' => true,
            ])
            ->assertSessionHasErrors('photo');
    }

    public function test_full_handover_flow(): void
    {
        $handover = $this->openHandover();
        $items = ChecklistItem::where('template_id', $this->template->id)->get();
        $photo = 'data:image/png;base64,'.base64_encode('fake');

        foreach ($items as $item) {
            $this->actingAs($this->outgoing)
                ->post(route('shift-handovers.check', $handover), [
                    'item_id' => $item->id,
                    'is_done' => true,
                    'photo' => $item->requires_photo ? $photo : null,
                ])
                ->assertRedirect();
        }

        $this->assertSame(0, $handover->fresh()->unfinishedItems());

        $this->actingAs($this->outgoing)
            ->patch(route('shift-handovers.submit', $handover), [
                'to_user_id' => $this->incoming->id,
                'cash_amount' => 500_000,
                'equipment_notes' => 'Máy POS 2 bị chậm.',
                'incident_notes' => 'Khách phàn nàn món mặn lúc 19h.',
                'pending_tasks' => 'Chưa nhập hàng rau cho ca tối.',
            ])
            ->assertRedirect();

        $handover->refresh();
        $this->assertSame(ShiftHandover::STATUS_PENDING, $handover->status);
        $this->assertSame($this->incoming->id, $handover->to_user_id);
        $this->assertNotNull($handover->submitted_at);

        // Người khác không xác nhận thay được.
        $this->actingAs($this->outgoing)
            ->patch(route('shift-handovers.accept', $handover))
            ->assertForbidden();

        $this->actingAs($this->incoming)
            ->patch(route('shift-handovers.accept', $handover))
            ->assertRedirect();

        $handover->refresh();
        $this->assertSame(ShiftHandover::STATUS_ACCEPTED, $handover->status);
        $this->assertNotNull($handover->accepted_at);
    }

    public function test_cannot_hand_over_to_yourself(): void
    {
        $handover = $this->openHandover();
        $this->tickEverything($handover);

        $this->actingAs($this->outgoing)
            ->patch(route('shift-handovers.submit', $handover), [
                'to_user_id' => $this->outgoing->id,
                'cash_amount' => 100_000,
            ])
            ->assertStatus(422);
    }

    public function test_incoming_shift_can_dispute(): void
    {
        $handover = $this->openHandover();
        $this->tickEverything($handover);

        $this->actingAs($this->outgoing)
            ->patch(route('shift-handovers.submit', $handover), [
                'to_user_id' => $this->incoming->id,
                'cash_amount' => 500_000,
            ])
            ->assertRedirect();

        $this->actingAs($this->incoming)
            ->patch(route('shift-handovers.dispute', $handover), [
                'dispute_reason' => 'Két thiếu 200.000đ so với biên bản.',
            ])
            ->assertRedirect();

        $this->assertSame(ShiftHandover::STATUS_DISPUTED, $handover->fresh()->status);
    }

    public function test_submitted_handover_can_no_longer_be_edited(): void
    {
        $handover = $this->openHandover();
        $this->tickEverything($handover);

        $this->actingAs($this->outgoing)
            ->patch(route('shift-handovers.submit', $handover), [
                'to_user_id' => $this->incoming->id,
            ])
            ->assertRedirect();

        $item = ChecklistItem::where('template_id', $this->template->id)->firstOrFail();

        $this->actingAs($this->outgoing)
            ->post(route('shift-handovers.check', $handover), [
                'item_id' => $item->id,
                'is_done' => false,
            ])
            ->assertStatus(422);
    }

    public function test_owner_can_scope_a_template_to_chosen_branches(): void
    {
        $otherBranch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->actingAs($this->owner)
            ->post(route('checklist.templates.store'), [
                'name' => 'Bàn giao ca tối',
                'type' => 'handover',
                'items' => [['title' => 'Khóa két', 'requires_photo' => false]],
                'branch_ids' => [$otherBranch->id],
            ])
            ->assertRedirect();

        $template = ChecklistTemplate::where('name', 'Bàn giao ca tối')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$otherBranch->id],
            $template->branches()->pluck('restaurant_branches.id')->all(),
        );
    }

    private function tickEverything(ShiftHandover $handover): void
    {
        $photo = 'data:image/png;base64,'.base64_encode('fake');

        foreach (ChecklistItem::where('template_id', $this->template->id)->get() as $item) {
            $this->actingAs($this->outgoing)->post(route('shift-handovers.check', $handover), [
                'item_id' => $item->id,
                'is_done' => true,
                'photo' => $item->requires_photo ? $photo : null,
            ]);
        }
    }
}
