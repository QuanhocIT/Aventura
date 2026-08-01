<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenuEngineeringBcgTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private Role $ownerRole;

    private Role $cashierRole;

    private SubscriptionPlan $premiumPlan;

    private SubscriptionPlan $basicPlan;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Roles
        $this->ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        // 2. Setup Restaurant Plans
        $this->premiumPlan = SubscriptionPlan::create([
            'name' => 'Premium Plan',
            'code' => 'premium',
            'max_branches' => 5,
            'max_tables' => 100,
            'max_users' => 50,
            'max_dishes' => 200,
            'features' => [
                'ai_advisor' => true,
            ],
            'is_custom' => false,
        ]);

        $this->basicPlan = SubscriptionPlan::create([
            'name' => 'Basic Plan',
            'code' => 'basic',
            'max_branches' => 1,
            'max_tables' => 10,
            'max_users' => 5,
            'max_dishes' => 20,
            'features' => [
                'ai_advisor' => false,
            ],
            'is_custom' => false,
        ]);

        // 3. Tenant & Owner Setup
        $this->owner = User::factory()->create(['status' => 'active']);
        $this->owner->assignRole($this->ownerRole);

        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
            'plan_id' => $this->premiumPlan->id,
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['branch_id' => $this->branch->id]);

        // Set Tenant Context
        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        // 4. Cashier Setup
        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->cashier->assignRole($this->cashierRole);

        // Assign manage_orders permission to owner/manager role (owner has it by default usually)
        $this->ownerRole->givePermissionTo(
            Permission::firstOrCreate(['name' => 'manage_orders', 'guard_name' => 'web'])
        );
    }

    /**
     * Test retrieves menu insights successfully when authorized and on a supported plan.
     */
    public function test_menu_insights_retrieval_success(): void
    {
        $response = $this->actingAs($this->owner)
            ->get('/api/products/menu-insights')
            ->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('insights', $data);
        $this->assertArrayHasKey('bcg', $data);
        $this->assertArrayHasKey('margins', $data);
    }

    /**
     * Test cashier is blocked from retrieving menu insights.
     */
    public function test_menu_insights_forbidden_for_cashier(): void
    {
        $this->actingAs($this->cashier)
            ->get('/api/products/menu-insights')
            ->assertStatus(403);
    }

    /**
     * Test plan restriction for menu insights endpoint.
     */
    public function test_menu_insights_restricted_on_basic_plan(): void
    {
        // Change plan to basic (without ai_advisor)
        $this->restaurant->update(['plan_id' => $this->basicPlan->id]);

        $response = $this->actingAs($this->owner)
            ->get('/api/products/menu-insights')
            ->assertStatus(403);

        $response->assertJsonFragment([
            'feature' => 'ai_advisor',
        ]);
    }

    /**
     * Test creating a combo product successfully from market basket suggestion.
     */
    public function test_create_combo_product_success(): void
    {
        $cat = ProductCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Đồ ăn',
            'slug' => 'do-an',
        ]);

        $productA = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $cat->id,
            'name' => 'Bún Chả Hà Nội',
            'code' => 'BUNCHA',
            'slug' => 'bun-cha',
            'price' => 50000,
            'is_available' => true,
        ]);

        $productB = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $cat->id,
            'name' => 'Trà Đá',
            'code' => 'TRADA',
            'slug' => 'tra-da',
            'price' => 5000,
            'is_available' => true,
        ]);

        $retailSum = $productA->price + $productB->price; // 55,000 VND
        $comboPrice = 48000; // valid (< 55,000)

        $response = $this->actingAs($this->owner)
            ->post('/promotions/combos', [
                'name' => 'Combo Bún Chả & Trà Đá',
                'item_a_id' => $productA->id,
                'item_b_id' => $productB->id,
                'combo_price' => $comboPrice,
                'notes' => 'Tự động tạo từ đề xuất AI.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('products', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Combo Bún Chả & Trà Đá',
            'price' => $comboPrice,
        ]);

        $combo = Product::where('name', 'Combo Bún Chả & Trà Đá')->first();
        $this->assertNotNull($combo);
        $this->assertStringStartsWith('COMBO-', $combo->code);
    }

    /**
     * Test validation error when combo price is greater than or equal to sum of components price.
     */
    public function test_create_combo_price_validation_fails(): void
    {
        $cat = ProductCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Đồ ăn',
            'slug' => 'do-an',
        ]);

        $productA = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $cat->id,
            'name' => 'Bún Chả Hà Nội',
            'code' => 'BUNCHA',
            'slug' => 'bun-cha',
            'price' => 50000,
            'is_available' => true,
        ]);

        $productB = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $cat->id,
            'name' => 'Trà Đá',
            'code' => 'TRADA',
            'slug' => 'tra-da',
            'price' => 5000,
            'is_available' => true,
        ]);

        $retailSum = $productA->price + $productB->price; // 55,000 VND
        $comboPrice = 55000; // invalid (>= 55,000)

        $response = $this->actingAs($this->owner)
            ->from('/products')
            ->post('/promotions/combos', [
                'name' => 'Combo Bún Chả & Trà Đá Hư',
                'item_a_id' => $productA->id,
                'item_b_id' => $productB->id,
                'combo_price' => $comboPrice,
                'notes' => 'Giá combo sai quy tắc.',
            ]);

        $response->assertRedirect('/products');
        $response->assertSessionHasErrors(['combo_price']);
    }
}
