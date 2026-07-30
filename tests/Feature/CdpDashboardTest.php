<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage cho CDP & RFM Dashboard — trang này trước đây trả TOÀN BỘ khách hàng
 * về trình duyệt rồi lọc/phân trang bằng JavaScript. Đã chuyển sang phân trang
 * phía server (2026-07-30); các test dưới khoá lại hành vi đó.
 */
class CdpDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->restaurant->plan->update([
            'features' => array_merge($this->restaurant->plan->features ?? [], [
                'advanced_analytics' => true,
            ]),
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);
        $this->owner->assignRole($ownerRole);
    }

    private function makeCustomers(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Customer::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'full_name' => "Khách số {$i}",
                'phone' => '09'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            ]);
        }
    }

    public function test_cdp_dashboard_renders_for_owner(): void
    {
        $this->makeCustomers(3);

        $this->actingAs($this->owner)
            ->get('/customers/cdp')
            ->assertOk();
    }

    public function test_customers_are_paginated_not_returned_in_full(): void
    {
        // 25 khách > cỡ trang 20 → trang đầu chỉ được chứa đúng 20 bản ghi
        $this->makeCustomers(25);

        $response = $this->actingAs($this->owner)->get('/customers/cdp');
        $response->assertOk();

        $customers = $response->viewData('page')['props']['customers'];

        $this->assertCount(20, $customers['data']);
        $this->assertSame(25, $customers['total']);
        $this->assertNotEmpty($customers['links']);
    }

    public function test_second_page_returns_remaining_customers(): void
    {
        $this->makeCustomers(25);

        $response = $this->actingAs($this->owner)->get('/customers/cdp?page=2');
        $response->assertOk();

        $customers = $response->viewData('page')['props']['customers'];

        $this->assertCount(5, $customers['data']);
    }

    public function test_search_filters_on_the_server(): void
    {
        $this->makeCustomers(25);
        Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'full_name' => 'Nguyễn Đặc Biệt',
            'phone' => '0777000111',
        ]);

        $response = $this->actingAs($this->owner)->get('/customers/cdp?search=Đặc+Biệt');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertSame(1, $props['customers']['total']);
        $this->assertSame('Nguyễn Đặc Biệt', $props['customers']['data'][0]['full_name']);
        // Bộ lọc phải được trả về để ô tìm kiếm giữ nguyên giá trị sau khi tải lại
        $this->assertSame('Đặc Biệt', $props['filters']['search']);
    }

    public function test_dashboard_does_not_leak_customers_of_another_restaurant(): void
    {
        $this->makeCustomers(2);

        $otherRestaurant = Restaurant::factory()->create();
        Customer::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'full_name' => 'Khách Nhà Hàng Khác',
            'phone' => '0888999000',
        ]);

        $response = $this->actingAs($this->owner)->get('/customers/cdp');
        $customers = $response->viewData('page')['props']['customers'];

        $this->assertSame(2, $customers['total']);
        $this->assertNotContains(
            'Khách Nhà Hàng Khác',
            array_column($customers['data'], 'full_name')
        );
    }

    public function test_staff_without_permission_cannot_open_dashboard(): void
    {
        // 'kitchen' không có quyền manage_customers (owner/manager/cashier/waiter thì có)
        Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);
        $kitchenStaff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $kitchenStaff->assignRole('kitchen');

        $this->actingAs($kitchenStaff)->get('/customers/cdp')->assertStatus(403);
    }

    public function test_dashboard_blocked_when_plan_lacks_advanced_analytics(): void
    {
        $this->restaurant->plan->update([
            'features' => array_merge($this->restaurant->plan->features ?? [], [
                'advanced_analytics' => false,
            ]),
        ]);

        $this->actingAs($this->owner)->get('/customers/cdp')->assertStatus(403);
    }
}
