<?php

namespace Tests\Feature;

use App\Jobs\RecalculateAverageCostJob;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Giá vốn món (cost_price) phải luôn khớp công thức định lượng.
 *
 * Bug đã vá 2026-07-30: phép tính cost_price từ BOM CHỈ chạy trong
 * RecalculateAverageCostJob (kích hoạt khi nhập hàng làm đổi giá nguyên liệu).
 * Sửa hoặc xoá công thức không kích hoạt gì → cost_price treo ở giá trị cũ,
 * kéo theo toàn bộ Menu Engineering / BCG / cảnh báo biên lợi nhuận chạy sai số.
 */
class ProductCostRecalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'COST-A',
        ]);
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);

        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'weight',
        ]);
    }

    private function makeIngredient(string $name, float $averageCost): Ingredient
    {
        return Ingredient::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => $name,
            'unit_id' => $this->unit->id,
            'average_cost' => $averageCost,
        ]);
    }

    private function makeProduct(float $price, float $costPrice = 0): Product
    {
        return Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'price' => $price,
            'cost_price' => $costPrice,
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    public function test_cost_price_is_computed_from_recipe_with_waste_rate(): void
    {
        $product = $this->makeProduct(price: 100000);
        $beef = $this->makeIngredient('Thịt bò', 200);   // 200đ/g
        $rice = $this->makeIngredient('Gạo', 20);        // 20đ/g

        ProductRecipe::create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $beef->id,
            'unit_id' => $this->unit->id,
            'quantity' => 150,
            'waste_rate' => 10, // hao hụt 10%
        ]);
        ProductRecipe::create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $rice->id,
            'unit_id' => $this->unit->id,
            'quantity' => 200,
            'waste_rate' => 0,
        ]);

        app(ProductCostService::class)->recalculateForProducts([$product->id]);

        // 150 × 200 × 1.10 = 33.000 ; 200 × 20 = 4.000 → 37.000
        $this->assertEquals(37000, (float) $product->fresh()->cost_price);
    }

    public function test_saving_recipe_recalculates_cost_price(): void
    {
        $product = $this->makeProduct(price: 60000, costPrice: 999);
        $ingredient = $this->makeIngredient('Cá hồi', 500);

        $this->actingAs($this->owner)
            ->post('/inventory/recipes', [
                'product_id' => $product->id,
                'items' => [
                    ['ingredient_id' => $ingredient->id, 'quantity' => 50, 'waste_rate' => 0],
                ],
            ])
            ->assertRedirect();

        // 50 × 500 = 25.000 — không còn giữ giá trị cũ 999
        $this->assertEquals(25000, (float) $product->fresh()->cost_price);
    }

    public function test_adding_expensive_ingredient_raises_cost_price(): void
    {
        $product = $this->makeProduct(price: 60000);
        $cheap = $this->makeIngredient('Rau', 10);
        $expensive = $this->makeIngredient('Tôm hùm', 2000);

        $this->actingAs($this->owner)->post('/inventory/recipes', [
            'product_id' => $product->id,
            'items' => [['ingredient_id' => $cheap->id, 'quantity' => 100, 'waste_rate' => 0]],
        ]);

        $this->assertEquals(1000, (float) $product->fresh()->cost_price);

        // Bếp bổ sung tôm hùm vào công thức → giá vốn phải tăng theo ngay
        $this->actingAs($this->owner)->post('/inventory/recipes', [
            'product_id' => $product->id,
            'items' => [
                ['ingredient_id' => $cheap->id, 'quantity' => 100, 'waste_rate' => 0],
                ['ingredient_id' => $expensive->id, 'quantity' => 30, 'waste_rate' => 0],
            ],
        ]);

        // 100×10 + 30×2000 = 61.000 → món này đang bán LỖ so với giá 60.000
        $this->assertEquals(61000, (float) $product->fresh()->cost_price);
    }

    public function test_removing_ingredient_from_recipe_lowers_cost_price(): void
    {
        $product = $this->makeProduct(price: 90000);
        $a = $this->makeIngredient('Nguyên liệu A', 100);
        $b = $this->makeIngredient('Nguyên liệu B', 300);

        $this->actingAs($this->owner)->post('/inventory/recipes', [
            'product_id' => $product->id,
            'items' => [
                ['ingredient_id' => $a->id, 'quantity' => 100, 'waste_rate' => 0],
                ['ingredient_id' => $b->id, 'quantity' => 100, 'waste_rate' => 0],
            ],
        ]);

        $this->assertEquals(40000, (float) $product->fresh()->cost_price);

        // Bỏ nguyên liệu B khỏi công thức (không gửi lên nữa)
        $this->actingAs($this->owner)->post('/inventory/recipes', [
            'product_id' => $product->id,
            'items' => [
                ['ingredient_id' => $a->id, 'quantity' => 100, 'waste_rate' => 0],
            ],
        ]);

        $this->assertEquals(10000, (float) $product->fresh()->cost_price);
    }

    public function test_deleting_single_recipe_row_recalculates_cost_price(): void
    {
        $product = $this->makeProduct(price: 90000);
        $a = $this->makeIngredient('Nguyên liệu A', 100);
        $b = $this->makeIngredient('Nguyên liệu B', 300);

        $this->actingAs($this->owner)->post('/inventory/recipes', [
            'product_id' => $product->id,
            'items' => [
                ['ingredient_id' => $a->id, 'quantity' => 100, 'waste_rate' => 0],
                ['ingredient_id' => $b->id, 'quantity' => 100, 'waste_rate' => 0],
            ],
        ]);

        $recipeB = ProductRecipe::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('ingredient_id', $b->id)
            ->firstOrFail();

        $this->actingAs($this->owner)
            ->delete("/inventory/recipes/{$recipeB->id}")
            ->assertRedirect();

        $this->assertEquals(10000, (float) $product->fresh()->cost_price);
    }

    public function test_ingredient_price_change_job_updates_all_products_using_it(): void
    {
        $ingredient = $this->makeIngredient('Dầu ăn', 50);

        $dishA = $this->makeProduct(price: 40000);
        $dishB = $this->makeProduct(price: 70000);

        foreach ([$dishA, $dishB] as $dish) {
            ProductRecipe::create([
                'restaurant_id' => $this->restaurant->id,
                'product_id' => $dish->id,
                'ingredient_id' => $ingredient->id,
                'unit_id' => $this->unit->id,
                'quantity' => 100,
                'waste_rate' => 0,
            ]);
        }

        // Nhập lô mới đắt hơn: 100 đơn vị cũ @50 + 100 đơn vị mới @150 → TB 100
        (new RecalculateAverageCostJob(
            restaurantId: $this->restaurant->id,
            ingredientId: $ingredient->id,
            oldQty: 100,
            newQty: 100,
            newCost: 150,
        ))->handle();

        $this->assertEquals(100, (float) $ingredient->fresh()->average_cost);
        $this->assertEquals(10000, (float) $dishA->fresh()->cost_price);
        $this->assertEquals(10000, (float) $dishB->fresh()->cost_price);
    }

    public function test_product_without_recipe_keeps_manually_entered_cost_price(): void
    {
        // Món nhập giá vốn thủ công, không có BOM — không được ghi đè về 0
        $product = $this->makeProduct(price: 50000, costPrice: 18000);

        app(ProductCostService::class)->recalculateForProducts([$product->id]);

        $this->assertEquals(18000, (float) $product->fresh()->cost_price);
    }
}
