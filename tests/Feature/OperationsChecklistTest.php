<?php

namespace Tests\Feature;

use App\Models\ChecklistTemplate;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage cho module Checklist vận hành (operations-checklist) — trước đây
 * chưa có test riêng. Tạo/xoá mẫu checklist + kiểm tra cách ly tenant (IDOR).
 */
class OperationsChecklistTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

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
    }

    public function test_checklist_page_renders(): void
    {
        $this->actingAs($this->owner)->get('/operations-checklist')->assertOk();
    }

    public function test_owner_can_create_a_template_with_items(): void
    {
        $res = $this->actingAs($this->owner)->post('/operations-checklist/templates', [
            'name' => 'Mở cửa buổi sáng',
            'type' => 'opening',
            'items' => [
                ['title' => 'Bật đèn & điều hoà', 'requires_photo' => false],
                ['title' => 'Kiểm tra tủ lạnh', 'requires_photo' => true],
            ],
        ]);

        $res->assertRedirect();
        $res->assertSessionHasNoErrors();

        $template = ChecklistTemplate::where('restaurant_id', $this->restaurant->id)
            ->where('name', 'Mở cửa buổi sáng')->first();
        $this->assertNotNull($template);
        $this->assertDatabaseHas('checklist_items', [
            'template_id' => $template->id,
            'title' => 'Kiểm tra tủ lạnh',
        ]);
    }

    public function test_owner_creates_handover_checklist_assigned_to_branch(): void
    {
        $branch = \App\Models\RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $otherBranch = \App\Models\RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->owner)->post('/operations-checklist/templates', [
            'name' => 'Bàn giao ca tối',
            'type' => 'handover',
            'items' => [
                ['title' => 'Đối chiếu tiền quỹ', 'requires_photo' => true],
                ['title' => 'Bàn giao thiết bị & sự cố tồn', 'requires_photo' => false],
            ],
            'branch_ids' => [$branch->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $template = ChecklistTemplate::where('restaurant_id', $this->restaurant->id)
            ->where('type', 'handover')->where('name', 'Bàn giao ca tối')->first();
        $this->assertNotNull($template);

        // Chỉ gán đúng chi nhánh đã chọn.
        $this->assertTrue($template->branches()->where('restaurant_branches.id', $branch->id)->exists());
        $this->assertFalse($template->branches()->where('restaurant_branches.id', $otherBranch->id)->exists());

        // scopeForBranch: chi nhánh được gán thấy mẫu; chi nhánh khác không thấy.
        $this->assertTrue(ChecklistTemplate::forBranch($branch->id)->whereKey($template->id)->exists());
        $this->assertFalse(ChecklistTemplate::forBranch($otherBranch->id)->whereKey($template->id)->exists());
    }

    public function test_create_template_validates_items_required(): void
    {
        $this->actingAs($this->owner)->post('/operations-checklist/templates', [
            'name' => 'Thiếu mục',
            'type' => 'opening',
            'items' => [],
        ])->assertSessionHasErrors('items');
    }

    public function test_owner_can_delete_own_template(): void
    {
        $template = ChecklistTemplate::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Mẫu tạm',
            'type' => 'custom',
            'sort_order' => 0,
        ]);

        $this->actingAs($this->owner)->delete("/operations-checklist/templates/{$template->id}")->assertRedirect();
        $this->assertDatabaseMissing('checklist_templates', ['id' => $template->id]);
    }

    public function test_template_is_tenant_scoped(): void
    {
        $other = Restaurant::factory()->create();
        $foreign = ChecklistTemplate::create([
            'restaurant_id' => $other->id,
            'name' => 'Mẫu nhà hàng khác',
            'type' => 'custom',
            'sort_order' => 0,
        ]);

        $this->actingAs($this->owner)->delete("/operations-checklist/templates/{$foreign->id}");
        $this->assertDatabaseHas('checklist_templates', ['id' => $foreign->id]);
    }
}
