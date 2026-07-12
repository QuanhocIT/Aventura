<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerIndexPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_index_paginates_server_side_and_counts_segments_across_all_pages(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $owner->update(['restaurant_id' => $restaurant->id]);
        $owner->assignRole(Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']));

        // 12 khách VIP (>=100 điểm) + 3 khách thường (1-99 điểm) — vượt quá 1 trang (10/trang).
        Customer::factory()->count(12)->create([
            'restaurant_id' => $restaurant->id,
            'loyalty_points' => 150,
        ]);
        Customer::factory()->count(3)->create([
            'restaurant_id' => $restaurant->id,
            'loyalty_points' => 20,
        ]);

        $response = $this->actingAs($owner)->get('/customers');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('customers/Index')
            ->where('customers.total', 15)
            ->where('customers.data', fn ($data) => count($data) === 10) // trang 1: 10 dòng
            ->where('segmentCounts.all', 15) // đếm trên TOÀN BỘ dữ liệu, không chỉ trang hiện tại
            ->where('segmentCounts.vip', 12)
            ->where('segmentCounts.regular', 3)
        );
    }

    public function test_segment_filter_applies_server_side_and_paginates_only_matching_rows(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $owner->update(['restaurant_id' => $restaurant->id]);
        $owner->assignRole(Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']));

        Customer::factory()->count(5)->create(['restaurant_id' => $restaurant->id, 'loyalty_points' => 150]);
        Customer::factory()->count(2)->create(['restaurant_id' => $restaurant->id, 'loyalty_points' => 0]);

        $response = $this->actingAs($owner)->get('/customers?segment=vip');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('customers.total', 5)
            ->where('segment', 'vip')
        );
    }
}
