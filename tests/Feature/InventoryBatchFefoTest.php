<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryBatchAllocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryBatchFefoTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_consume_batches_by_expiry_and_refunds_restore_the_same_lots(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => $owner->id,
        ]);
        $owner->forceFill([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ])->save();

        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass',
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Thit bo FEFO',
            'sku' => 'BEEF-FEFO-001',
            'average_cost' => 150,
            'status' => 'active',
        ]);
        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 7,
            'theoretical_quantity' => 7,
            'last_cost' => 200,
        ]);

        $oldBatch = InventoryBatch::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'batch_number' => 'LOT-OLD',
            'quantity_remaining' => 2,
            'unit_cost' => 100,
            'purchased_at' => now()->subDays(2)->toDateString(),
            'expiry_date' => now()->addDays(2)->toDateString(),
            'status' => 'active',
        ]);
        $newBatch = InventoryBatch::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'batch_number' => 'LOT-NEW',
            'quantity_remaining' => 5,
            'unit_cost' => 200,
            'purchased_at' => now()->toDateString(),
            'expiry_date' => now()->addDays(7)->toDateString(),
            'status' => 'active',
        ]);

        $category = ProductCategory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'FEFO Test',
            'slug' => 'fefo-test',
            'status' => 'active',
        ]);
        $product = Product::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'code' => 'FEFO-001',
            'name' => 'Mon FEFO',
            'slug' => 'mon-fefo',
            'description' => 'FEFO test product',
            'price' => 10000,
            'cost_price' => 150,
            'track_inventory' => true,
        ]);
        ProductRecipe::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'quantity' => 1,
            'waste_rate' => 0,
        ]);
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'created_by' => $owner->id,
            'order_number' => 'ORD-FEFO-001',
            'channel' => 'dine_in',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 30000,
            'total_amount' => 30000,
        ]);
        OrderItem::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 10000,
            'line_total' => 30000,
            'status' => 'pending',
        ]);

        $service = app(InventoryService::class);
        DB::transaction(fn () => $service->deductInventoryForOrder($order, $owner));

        $oldBatch->refresh();
        $newBatch->refresh();
        $inventory->refresh();

        $this->assertEquals(0.0, (float) $oldBatch->quantity_remaining);
        $this->assertEquals('depleted', $oldBatch->status);
        $this->assertEquals(4.0, (float) $newBatch->quantity_remaining);
        $this->assertEquals(4.0, (float) $inventory->quantity_on_hand);
        $this->assertEquals(2, InventoryBatchAllocation::where('direction', 'out')->count());

        DB::transaction(fn () => $service->restoreStockForOrder($order));

        $oldBatch->refresh();
        $newBatch->refresh();
        $inventory->refresh();
        $this->assertEquals(2.0, (float) $oldBatch->quantity_remaining);
        $this->assertEquals(5.0, (float) $newBatch->quantity_remaining);
        $this->assertEquals(7.0, (float) $inventory->quantity_on_hand);
        $this->assertEquals(2, InventoryBatchAllocation::where('direction', 'in')->count());

        $insufficientOrder = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'created_by' => $owner->id,
            'order_number' => 'ORD-FEFO-INSUFFICIENT',
            'channel' => 'dine_in',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 80000,
            'total_amount' => 80000,
        ]);
        OrderItem::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $insufficientOrder->id,
            'product_id' => $product->id,
            'quantity' => 8,
            'unit_price' => 10000,
            'line_total' => 80000,
            'status' => 'pending',
        ]);

        try {
            DB::transaction(fn () => $service->deductInventoryForOrder($insufficientOrder, $owner));
            $this->fail('Expected the order to be rejected when stock is insufficient.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Thit bo FEFO', $exception->getMessage());
            $this->assertStringContainsString('thiếu', $exception->getMessage());
        }

        $inventory->refresh();
        $oldBatch->refresh();
        $newBatch->refresh();
        $this->assertEquals(7.0, (float) $inventory->quantity_on_hand);
        $this->assertEquals(2.0, (float) $oldBatch->quantity_remaining);
        $this->assertEquals(5.0, (float) $newBatch->quantity_remaining);
    }
}
