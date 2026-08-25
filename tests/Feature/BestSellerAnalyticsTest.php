<?php

namespace Tests\Feature;

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
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BestSellerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branchA;

    private RestaurantBranch $branchB;

    private ProductCategory $mainCategory;

    private ProductCategory $drinkCategory;

    /** Món chủ lực — chiếm phần lớn sản lượng. */
    private Product $star;

    /** Món hạng hai — bán ổn định. */
    private Product $support;

    /** Món đuôi dài — bán rất ít, kỳ này giảm. */
    private Product $tail;

    /** Món chỉ bán ở kỳ trước — dùng để kiểm tra "dropouts". */
    private Product $retiredDish;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $plan = SubscriptionPlan::factory()->create([
            'features' => ['advanced_analytics' => true],
        ]);

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

        $this->mainCategory = ProductCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'name' => 'Món chính',
        ]);
        $this->drinkCategory = ProductCategory::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'name' => 'Đồ uống',
        ]);

        $this->star = $this->makeProduct('Phở bò tái', $this->mainCategory, 60000, 20000);
        $this->support = $this->makeProduct('Cơm sườn', $this->mainCategory, 50000, 25000);
        $this->tail = $this->makeProduct('Trà tắc', $this->drinkCategory, 15000, 5000);
        $this->retiredDish = $this->makeProduct('Món theo mùa', $this->drinkCategory, 20000, 8000);

        app(TenantContext::class)->setRestaurantId($this->restaurant->id);
    }

    public function test_ranking_is_ordered_by_quantity_and_classified_with_abc(): void
    {
        $this->seedCurrentPeriod();

        $response = $this->actingAs($this->owner)
            ->getJson('/best-sellers/api/analytics?preset=30&metric=quantity')
            ->assertOk();

        $ranking = collect($response->json('ranking'));

        $this->assertSame($this->star->id, $ranking[0]['product_id']);
        $this->assertSame(1, $ranking[0]['rank']);
        $this->assertSame('A', $ranking[0]['abc_class']);
        $this->assertSame($this->support->id, $ranking[1]['product_id']);

        // Luỹ kế phải tăng đơn điệu và kết thúc ở 100%.
        $cumulative = $ranking
            ->filter(fn (array $row): bool => $row['metric_value'] > 0)
            ->pluck('cumulative_percent')
            ->all();
        $sorted = $cumulative;
        sort($sorted);
        $this->assertSame($sorted, $cumulative);
        $this->assertEqualsWithDelta(100.0, (float) end($cumulative), 0.05);

        // Món đuôi dài đóng góp không đáng kể nên phải rơi xuống nhóm C.
        $tailRow = $ranking->firstWhere('product_id', $this->tail->id);
        $this->assertSame('C', $tailRow['abc_class']);

        $response->assertJsonPath('summary.top_dish.product_id', $this->star->id)
            ->assertJsonPath('pareto.dishes_for_80', 1);
    }

    public function test_metric_switch_reorders_ranking_by_gross_profit(): void
    {
        // Trà tắc bán ít phần nhưng biên lợi nhuận rất dày; món chính thì ngược lại.
        $this->createOrder(now()->subDays(2), $this->branchA, [
            [$this->support, 40],   // LN gộp: 40 × 25.000 = 1.000.000
            [$this->tail, 120],     // LN gộp: 120 × 10.000 = 1.200.000
        ]);

        $byQuantity = $this->actingAs($this->owner)
            ->getJson('/best-sellers/api/analytics?preset=30&metric=quantity')
            ->assertOk();
        $this->assertSame($this->tail->id, $byQuantity->json('ranking.0.product_id'));

        $byProfit = $this->actingAs($this->owner)
            ->getJson('/best-sellers/api/analytics?preset=30&metric=profit')
            ->assertOk();

        $this->assertSame($this->tail->id, $byProfit->json('ranking.0.product_id'));
        $this->assertSame(1200000.0, (float) $byProfit->json('ranking.0.gross_profit'));
        $this->assertSame(1000000.0, (float) $byProfit->json('ranking.1.gross_profit'));
    }

    public function test_compares_against_previous_period_and_flags_movers(): void
    {
        // Kỳ trước (ngày 31–60): món mùa vụ có bán, món chủ lực bán ít.
        $this->createOrder(now()->subDays(40), $this->branchA, [
            [$this->star, 10],
            [$this->retiredDish, 25],
        ]);

        // Kỳ này (30 ngày gần nhất): món chủ lực tăng mạnh, món mùa vụ biến mất.
        $this->createOrder(now()->subDays(3), $this->branchA, [
            [$this->star, 30],
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/best-sellers/api/analytics?preset=30&metric=quantity')
            ->assertOk();

        $rising = collect($response->json('movers.rising'))->firstWhere('product_id', $this->star->id);
        $this->assertNotNull($rising);
        $this->assertSame('up', $rising['trend']);
        $this->assertSame(200.0, (float) $rising['change_percent']);

        $dropouts = collect($response->json('movers.dropouts'))->pluck('product_id')->all();
        $this->assertContains($this->retiredDish->id, $dropouts);

        $response->assertJsonPath('summary.previous.total_qty', 35);
    }

    public function test_respects_active_branch_scope(): void
    {
        $this->createOrder(now()->subDays(2), $this->branchA, [[$this->star, 5]]);
        $this->createOrder(now()->subDays(2), $this->branchB, [[$this->support, 9]]);

        app(TenantContext::class)->setActiveBranchId($this->branchA->id);

        $response = $this->withSession(['active_branch_id' => $this->branchA->id])
            ->actingAs($this->owner)
            ->getJson('/best-sellers/api/analytics?preset=30&metric=quantity')
            ->assertOk();

        $this->assertSame($this->star->id, $response->json('ranking.0.product_id'));
        $this->assertSame(5, $response->json('summary.total_qty'));
        $this->assertCount(1, $response->json('branches'));
        $this->assertSame($this->branchA->id, $response->json('branches.0.branch_id'));
    }

    public function test_category_filter_limits_the_ranking(): void
    {
        $this->createOrder(now()->subDays(2), $this->branchA, [
            [$this->star, 12],
            [$this->tail, 7],
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/best-sellers/api/analytics?preset=30&category_id='.$this->drinkCategory->id)
            ->assertOk();

        $productIds = collect($response->json('ranking'))->pluck('product_id')->all();
        $this->assertContains($this->tail->id, $productIds);
        $this->assertNotContains($this->star->id, $productIds);
    }

    public function test_dish_detail_returns_breakdowns_and_paired_dishes(): void
    {
        $this->createOrder(now()->subDays(2)->setTime(12, 30), $this->branchA, [
            [$this->star, 3],
            [$this->tail, 2],
        ]);
        $this->createOrder(now()->subDays(3)->setTime(19, 15), $this->branchA, [
            [$this->star, 4],
            [$this->tail, 1],
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/best-sellers/api/dishes/'.$this->star->id.'?preset=30')
            ->assertOk();

        $response->assertJsonPath('product.id', $this->star->id)
            ->assertJsonPath('summary.qty', 7)
            ->assertJsonPath('summary.order_count', 2)
            ->assertJsonPath('summary.attach_rate', 100)
            ->assertJsonPath('paired_with.0.product_id', $this->tail->id)
            ->assertJsonPath('paired_with.0.confidence', 100);

        $dayparts = collect($response->json('dayparts'))->keyBy('key');
        $this->assertSame(3, $dayparts['lunch']['qty']);
        $this->assertSame(4, $dayparts['dinner']['qty']);
    }

    public function test_dish_detail_rejects_product_from_another_restaurant(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $foreignProduct = Product::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'branch_id' => null,
            'category_id' => null,
            'name' => 'Món nhà khác',
            'price' => 30000,
            'cost_price' => 10000,
        ]);

        $this->actingAs($this->owner)
            ->getJson('/best-sellers/api/dishes/'.$foreignProduct->id.'?preset=30')
            ->assertNotFound();
    }

    public function test_index_page_renders_analytics(): void
    {
        $this->seedCurrentPeriod();

        $this->actingAs($this->owner)
            ->get('/best-sellers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('best-sellers/Index')
                ->where('analytics.summary.top_dish.product_id', $this->star->id)
                ->has('analytics.ranking', 3)
                ->has('categories', 2)
            );
    }

    public function test_export_streams_csv_with_ranking_rows(): void
    {
        $this->seedCurrentPeriod();

        $response = $this->actingAs($this->owner)->get('/best-sellers/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Phở bò tái', $csv);
        $this->assertStringContainsString('Nhóm ABC', $csv);
    }

    public function test_plan_without_advanced_analytics_sees_feature_gate(): void
    {
        $basicPlan = SubscriptionPlan::factory()->create([
            'features' => ['advanced_analytics' => false],
        ]);
        $this->restaurant->update(['plan_id' => $basicPlan->id]);

        $this->actingAs($this->owner)
            ->get('/best-sellers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('FeatureGate'));

        $this->actingAs($this->owner)
            ->getJson('/best-sellers/api/analytics')
            ->assertForbidden()
            ->assertJsonPath('feature', 'advanced_analytics');
    }

    public function test_staff_without_analytics_permission_is_blocked(): void
    {
        $waiterRole = Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);
        $waiter = User::factory()->create([
            'status' => 'active',
            'restaurant_id' => $this->restaurant->id,
        ]);
        $waiter->assignRole($waiterRole);

        $this->actingAs($waiter)
            ->getJson('/best-sellers/api/analytics')
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Kỳ hiện tại: 1 món chủ lực áp đảo, 1 món hỗ trợ, 1 món đuôi dài.
     */
    private function seedCurrentPeriod(): void
    {
        $this->createOrder(now()->subDays(2), $this->branchA, [
            [$this->star, 60],
            [$this->support, 12],
            [$this->tail, 1],
        ]);
        $this->createOrder(now()->subDays(5), $this->branchB, [
            [$this->star, 40],
            [$this->support, 8],
        ]);
    }

    private function makeProduct(
        string $name,
        ProductCategory $category,
        float $price,
        float $cost,
    ): Product {
        return Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'category_id' => $category->id,
            'name' => $name,
            'price' => $price,
            'cost_price' => $cost,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $items
     */
    private function createOrder($date, RestaurantBranch $branch, array $items): Order
    {
        $amount = collect($items)->sum(fn (array $item): float => $item[0]->price * $item[1]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $branch->id,
            'table_id' => null,
            'customer_id' => null,
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
