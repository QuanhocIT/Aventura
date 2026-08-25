<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenuBehaviorAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branchA;

    private RestaurantBranch $branchB;

    private Product $dishA;

    private Product $dishB;

    private Product $dishC;

    private Customer $returningCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $plan = SubscriptionPlan::factory()->create(['features' => ['advanced_analytics' => true]]);
        $this->owner = User::factory()->create(['status' => 'active']);
        $this->owner->assignRole($role);
        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
            'plan_id' => $plan->id,
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->branchA = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Chi nhánh A',
        ]);
        $this->branchB = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Chi nhánh B',
        ]);

        $category = ProductCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'name' => 'Món chính',
        ]);
        $this->dishA = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'category_id' => $category->id,
            'name' => 'Món tăng trưởng',
            'price' => 50000,
            'cost_price' => 20000,
        ]);
        $this->dishB = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'category_id' => $category->id,
            'name' => 'Món giảm nhiệt',
            'price' => 45000,
            'cost_price' => 20000,
        ]);
        $this->dishC = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'category_id' => $category->id,
            'name' => 'Trà gọi kèm',
            'price' => 15000,
            'cost_price' => 5000,
        ]);
        $this->returningCustomer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
        ]);

        app(TenantContext::class)->setRestaurantId($this->restaurant->id);
    }

    public function test_returns_trends_pairs_customer_habits_and_branch_breakdown(): void
    {
        $this->createOrder(now()->subDays(2), $this->branchA, $this->returningCustomer, [
            [$this->dishA, 4], [$this->dishC, 1],
        ]);
        $this->createOrder(now()->subDays(3), $this->branchA, $this->returningCustomer, [
            [$this->dishA, 3], [$this->dishC, 1],
        ]);
        $this->createOrder(now()->subDays(4), $this->branchB, null, [
            [$this->dishB, 1],
        ]);
        $this->createOrder(now()->subDays(40), $this->branchA, $this->returningCustomer, [
            [$this->dishB, 5],
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/menu-engineering/api/behavior?days=30')
            ->assertOk();

        $response->assertJsonPath('summary.orders', 3)
            ->assertJsonPath('summary.rising_products', 2)
            ->assertJsonPath('summary.falling_products', 1)
            ->assertJsonPath('customer_habits.repeat_customers', 1)
            ->assertJsonPath('customer_habits.repeat_rate', 100);

        $this->assertSame(
            'up',
            collect($response->json('rising'))->firstWhere('product_id', $this->dishA->id)['trend'],
        );
        $this->assertSame(
            'down',
            collect($response->json('falling'))->firstWhere('product_id', $this->dishB->id)['trend'],
        );
        $this->assertCount(2, $response->json('branch_breakdown'));
        $this->assertSame('Món tăng trưởng', $response->json('pairs.0.item_a'));
        $this->assertSame('Trà gọi kèm', $response->json('pairs.0.item_b'));
    }

    public function test_respects_active_branch_scope(): void
    {
        $this->createOrder(now()->subDays(2), $this->branchA, null, [[$this->dishA, 2]]);
        $this->createOrder(now()->subDays(2), $this->branchB, null, [[$this->dishB, 6]]);
        app(TenantContext::class)->setActiveBranchId($this->branchA->id);

        $response = $this->withSession(['active_branch_id' => $this->branchA->id])
            ->actingAs($this->owner)
            ->getJson('/menu-engineering/api/behavior?days=30')
            ->assertOk();

        $this->assertSame(1, $response->json('summary.orders'));
        $this->assertSame($this->branchA->id, $response->json('branch_breakdown.0.branch_id'));
        $this->assertSame($this->dishA->id, $response->json('top_dishes.0.product_id'));
    }

    private function createOrder($date, RestaurantBranch $branch, ?Customer $customer, array $items): Order
    {
        $amount = collect($items)->sum(fn (array $item): float => $item[0]->price * $item[1]);
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $branch->id,
            'table_id' => null,
            'customer_id' => $customer?->id,
            'created_by' => $this->owner->id,
            'cashier_user_id' => $this->owner->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => $amount,
            'total_amount' => $amount,
            'completed_at' => $date,
            'created_at' => $date,
        ]);

        foreach ($items as [$product, $quantity]) {
            OrderItem::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'line_total' => $product->price * $quantity,
                'status' => 'served',
                'created_at' => $date,
            ]);
        }

        return $order;
    }
}
