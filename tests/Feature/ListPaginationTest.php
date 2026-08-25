<?php

namespace Tests\Feature;

use App\Models\CustomerFeedback;
use App\Models\Employee;
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
 * Các trang danh sách chỉ tăng theo thời gian đã chuyển sang phân trang.
 *
 * Điểm dễ hỏng nhất khi phân trang một trang có KPI: chỉ số tổng bị tính trên
 * đúng trang đang xem thay vì trên toàn bộ dữ liệu. Hai bài test dưới đây khoá
 * lại chính điều đó.
 */
class ListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private User $manager;

    private Employee $offender;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['manage_feedback', 'view_violations', 'manage_violations', 'report_violations'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->givePermissionTo(['manage_feedback', 'view_violations', 'manage_violations', 'report_violations']);

        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $owner->id,
        ]);
        $owner->update(['branch_id' => $this->branch->id]);

        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->manager->assignRole($managerRole);

        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->manager->id,
            'role_id' => $managerRole->id,
            'status' => 'active',
        ]);

        $offenderUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->offender = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $offenderUser->id,
            'role_id' => $managerRole->id,
            'status' => 'active',
        ]);
    }

    public function test_violation_list_is_paginated(): void
    {
        for ($i = 0; $i < 30; $i++) {
            ViolationReport::create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'employee_id' => $this->offender->id,
                'reported_by' => $this->manager->id,
                'is_anonymous' => false,
                'violation_type' => 'Đi trễ',
                'description' => 'Biên bản số '.($i + 1),
                'occurred_at' => now()->subMinutes($i),
                'status' => 'pending',
            ]);
        }

        $props = $this->actingAs($this->manager)->get('/violations')->viewData('page')['props'];

        $this->assertCount(25, $props['reports']);
        $this->assertSame(30, $props['pagination']['total']);
        $this->assertSame(2, $props['pagination']['last_page']);

        $page2 = $this->actingAs($this->manager)->get('/violations?page=2')->viewData('page')['props'];
        $this->assertCount(5, $page2['reports']);
    }

    public function test_feedback_kpis_are_counted_across_all_pages_not_just_the_visible_one(): void
    {
        // 30 phản hồi: 26 điểm 5 và 4 điểm 1. Trang đầu chỉ chứa 25 dòng, nên
        // nếu KPI tính trên trang thì tổng sẽ ra 25 và phân bố sẽ sai.
        foreach (range(1, 26) as $i) {
            CustomerFeedback::create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'rating' => 5,
                'content' => 'Rất hài lòng '.$i,
                'status' => 'new',
            ]);
        }
        foreach (range(1, 4) as $i) {
            CustomerFeedback::create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'rating' => 1,
                'content' => 'Không hài lòng '.$i,
                'status' => 'resolved',
            ]);
        }

        $props = $this->actingAs($this->manager)->get('/feedback')->viewData('page')['props'];

        $this->assertCount(25, $props['feedbacks']);
        $this->assertSame(30, $props['pagination']['total']);

        $stats = $props['stats'];
        $this->assertSame(30, $stats['total']);
        $this->assertSame(26, $stats['new']);
        $this->assertSame(26, $stats['distribution'][5]);
        $this->assertSame(4, $stats['distribution'][1]);
        // (26×5 + 4×1) / 30 = 4.47
        $this->assertEqualsWithDelta(4.5, $stats['average'], 0.05);
    }
}
