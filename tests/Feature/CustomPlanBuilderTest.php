<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomPlanBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['superadmin.2fa_verified_until' => now()->addMinutes(15)->timestamp]);
        // Disable search indexing during tests to avoid Meilisearch connection errors
        config(['scout.driver' => null]);
    }

    public function test_superadmin_can_create_custom_ad_hoc_plan_and_apply_to_restaurant(): void
    {
        // Ensure super_admin role exists
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $superAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $superAdmin->assignRole('super_admin');
        $this->withSession(['superadmin.2fa_verified_user_id' => $superAdmin->id]);

        $restaurant = Restaurant::create([
            'name' => 'Kichi Test Restaurant',
            'code' => 'KICHI1',
            'slug' => 'kichi-test-restaurant',
            'status' => 'active',
        ]);

        $response = $this->actingAs($superAdmin)->post(route('superadmin.restaurants.custom-plan.store', $restaurant), [
            'name' => 'Gói VIP Kichi 50',
            'price' => 12000000,
            'billing_cycle' => 'biennial',
            'max_branches' => 50,
            'max_users' => 100,
            'max_tables' => 500,
            'max_dishes' => 300,
            'max_areas' => 20,
            'max_storage_mb' => 10240,
            'api_rate_limit' => 120,
            'password' => 'password',
            'rfm_ai_analysis' => true,
            'priority_support' => true,
            'fraud_detection' => false,
            'kitchen_display' => true,
        ]);

        $response->assertRedirect();

        $restaurant->refresh();
        $this->assertNotNull($restaurant->plan_id);

        $plan = $restaurant->plan;
        $this->assertEquals('Gói VIP Kichi 50', $plan->name);
        $this->assertTrue($plan->is_custom);
        $this->assertEquals($restaurant->id, $plan->restaurant_id);
        $this->assertEquals(300, $plan->max_dishes);
        $this->assertTrue((bool) $plan->features['rfm_ai_analysis']);
        $this->assertTrue((bool) $plan->features['priority_support']);
        $this->assertFalse((bool) $plan->features['fraud_detection']);

        // Check active subscription dates
        $sub = $restaurant->subscriptions()->where('status', 'active')->first();
        $this->assertNotNull($sub);
        $this->assertEquals('biennial', $sub->billing_cycle);
        $this->assertEquals(12000000, $sub->price);
        $this->assertEquals(now()->addDays(730)->toDateString(), $sub->ended_at->toDateString());
    }

    public function test_dishes_quota_is_enforced_when_creating_products(): void
    {
        // Ensure owner role exists
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $restaurant = Restaurant::create([
            'name' => 'Kichi Quota Restaurant',
            'code' => 'KICHI2',
            'slug' => 'kichi-quota-restaurant',
            'status' => 'active',
            'owner_user_id' => $owner->id,
        ]);

        $owner->update(['restaurant_id' => $restaurant->id]);

        $customPlan = SubscriptionPlan::create([
            'restaurant_id' => $restaurant->id,
            'is_custom' => true,
            'code' => 'custom_quota',
            'name' => 'Custom Quota Plan',
            'price' => 1000,
            'billing_cycle' => 'monthly',
            'max_branches' => 5,
            'max_users' => 5,
            'max_tables' => 5,
            'max_dishes' => 2, // Limit to 2 dishes
            'status' => 'active',
            'features' => [],
        ]);

        $restaurant->update(['plan_id' => $customPlan->id]);

        // Create ProductCategory first
        $category = ProductCategory::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Thực đơn test',
            'slug' => 'thuc-don-test',
            'display_order' => 1,
            'status' => 'active',
        ]);

        // Create 2 products successfully via factory to avoid SQL slug / key errors
        Product::factory()->count(2)->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
        ]);

        // Attempting to create the 3rd product through route should fail with 403
        $response = $this->actingAs($owner)->post(route('products.store'), [
            'category_id' => $category->id,
            'name' => 'Dish 3',
            'price' => 120,
            'description' => 'Should fail due to quota limit exceed',
        ]);

        $response->assertStatus(403);
    }
}
