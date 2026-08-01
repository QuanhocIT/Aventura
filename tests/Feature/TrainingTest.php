<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\TrainingCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage cho module Đào tạo (training) — trước đây chưa có test riêng.
 * Bao gồm hiển thị trang, tạo/xoá khoá học, và kiểm tra cách ly tenant (IDOR).
 */
class TrainingTest extends TestCase
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

    public function test_training_page_renders(): void
    {
        $this->actingAs($this->owner)->get('/training')->assertOk();
    }

    public function test_owner_can_create_a_course(): void
    {
        $res = $this->actingAs($this->owner)->post('/training/courses', [
            'title' => 'Đào tạo ATTP',
            'description' => 'An toàn thực phẩm',
            'type' => 'attp',
            'is_required' => true,
        ]);

        $res->assertRedirect();
        $res->assertSessionHasNoErrors();
        $this->assertDatabaseHas('training_courses', [
            'restaurant_id' => $this->restaurant->id,
            'title' => 'Đào tạo ATTP',
            'type' => 'attp',
        ]);
    }

    public function test_create_course_validates_type(): void
    {
        $this->actingAs($this->owner)->post('/training/courses', [
            'title' => 'Sai loại',
            'type' => 'khong-hop-le',
        ])->assertSessionHasErrors('type');
    }

    public function test_owner_can_delete_own_course(): void
    {
        $course = TrainingCourse::create([
            'restaurant_id' => $this->restaurant->id,
            'title' => 'Khoá tạm',
            'type' => 'custom',
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->delete("/training/courses/{$course->id}")->assertRedirect();
        $this->assertDatabaseMissing('training_courses', ['id' => $course->id]);
    }

    public function test_course_is_tenant_scoped(): void
    {
        $other = Restaurant::factory()->create();
        $foreign = TrainingCourse::create([
            'restaurant_id' => $other->id,
            'title' => 'Khoá nhà hàng khác',
            'type' => 'custom',
            'created_by' => $this->owner->id,
        ]);

        // Yêu cầu bảo mật: không xoá được khoá của nhà hàng khác (IDOR fix ở BelongsToRestaurant).
        $this->actingAs($this->owner)->delete("/training/courses/{$foreign->id}");
        $this->assertDatabaseHas('training_courses', ['id' => $foreign->id]);
    }
}
