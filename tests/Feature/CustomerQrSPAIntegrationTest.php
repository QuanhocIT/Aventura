<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Unit;
use App\Models\Employee;
use App\Models\User;
use App\Models\CustomerFeedback;
use App\Events\TableInteraction;
use App\Events\OrderStatusUpdated;
use App\Events\ProductStockUpdated;
use App\Events\NewFeedbackReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerQrSPAIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function setupBasicData()
    {
        $restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
        ]);

        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Chi nhánh chính',
        ]);

        $area = \App\Models\Area::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Khu vực chính',
            'code' => 'main-area',
        ]);

        $table = RestaurantTable::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'area_id' => $area->id,
            'name' => 'Bàn A1',
            'capacity' => 4,
            'status' => 'available',
            'qr_token' => 'token-a1',
        ]);

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
            'name' => 'Beef',
            'sku' => 'beef-sku',
            'status' => 'active',
        ]);

        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 100.0,
            'theoretical_quantity' => 100.0,
        ]);

        $product = Product::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'code' => 'p-001',
            'name' => 'Beef Steak',
            'slug' => 'beef-steak',
            'price' => 150000,
            'is_active' => true,
            'is_available' => true,
            'track_inventory' => true,
        ]);

        $recipe = ProductRecipe::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'quantity' => 10.0,
            'waste_rate' => 0.0,
        ]);

        return compact('restaurant', 'branch', 'table', 'unit', 'ingredient', 'inventory', 'product', 'recipe');
    }

    public function test_guest_can_load_menu_and_see_correct_stock_availability()
    {
        $data = $this->setupBasicData();
        
        // Case 1: Stock is sufficient (100.0 on hand, 10.0 required)
        $response = $this->get(route('qr.order.show', [
            'restaurant' => $data['restaurant']->id,
            'table_token' => $data['table']->qr_token,
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('orders/QrOrder')
            ->has('products', 1)
            ->where('products.0.is_available', true)
            ->where('products.0.is_out_of_stock', false)
        );

        // Case 2: Stock is insufficient
        $data['inventory']->update(['quantity_on_hand' => 5.0]);

        $response2 = $this->get(route('qr.order.show', [
            'restaurant' => $data['restaurant']->id,
            'table_token' => $data['table']->qr_token,
        ]));

        $response2->assertStatus(200);
        $response2->assertInertia(fn ($page) => $page
            ->component('orders/QrOrder')
            ->where('products.0.is_available', false)
            ->where('products.0.is_out_of_stock', true)
        );
    }

    public function test_submitting_order_checks_stock_and_creates_inventory_reservations()
    {
        $data = $this->setupBasicData();

        // 10.0 required for recipe. quantity = 2. Total used = 20.0. Stock = 100.0 (Sufficient)
        $response = $this->postJson(route('qr.order.submit', [
            'restaurant' => $data['restaurant']->id,
            'table_token' => $data['table']->qr_token,
        ]), [
            'note' => 'Ăn tái',
            'items' => [
                [
                    'product_id' => $data['product']->id,
                    'quantity' => 2,
                    'notes' => 'Không hành',
                ]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Verify reservation was created
        $this->assertDatabaseHas('inventory_reservations', [
            'restaurant_id' => $data['restaurant']->id,
            'ingredient_id' => $data['ingredient']->id,
            'reserved_quantity' => 20.0,
            'status' => 'holding',
        ]);

        // Case 3: Out of stock submission fails
        $data['inventory']->update(['quantity_on_hand' => 5.0]);

        $response2 = $this->postJson(route('qr.order.submit', [
            'restaurant' => $data['restaurant']->id,
            'table_token' => $data['table']->qr_token,
        ]), [
            'items' => [
                [
                    'product_id' => $data['product']->id,
                    'quantity' => 1,
                ]
            ]
        ]);

        $response2->assertStatus(422);
    }

    public function test_calling_staff_and_requesting_payment_triggers_events()
    {
        Event::fake();

        $data = $this->setupBasicData();

        $response1 = $this->postJson(route('qr.order.call-staff', [
            'restaurant' => $data['restaurant']->id,
            'table_token' => $data['table']->qr_token,
        ]));

        $response1->assertStatus(200);
        Event::assertDispatched(TableInteraction::class, function ($event) use ($data) {
            return $event->table->id === $data['table']->id && $event->type === 'call_staff';
        });

        $response2 = $this->postJson(route('qr.order.request-payment', [
            'restaurant' => $data['restaurant']->id,
            'table_token' => $data['table']->qr_token,
        ]));

        $response2->assertStatus(200);
        Event::assertDispatched(TableInteraction::class, function ($event) use ($data) {
            return $event->table->id === $data['table']->id && $event->type === 'request_payment';
        });
    }

    public function test_customer_can_submit_feedback_with_employee_rating_and_dish_feedback()
    {
        Event::fake();

        $data = $this->setupBasicData();

        $user = User::create([
            'name' => 'Staff Member',
            'email' => 'staff@aventura.test',
            'password' => bcrypt('password123'),
            'restaurant_id' => $data['restaurant']->id,
            'status' => 'active',
        ]);

        $staffRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $employee = Employee::create([
            'restaurant_id' => $data['restaurant']->id,
            'branch_id' => $data['branch']->id,
            'user_id' => $user->id,
            'full_name' => 'Staff Member',
            'employee_code' => 'EMP-001',
            'role_id' => $staffRole->id,
        ]);

        $response = $this->postJson(route('feedback.store'), [
            'rating' => 4,
            'content' => 'Ngon, phục vụ hơi lâu chút',
            'is_anonymous' => false,
            'submitted_by_name' => 'Nguyễn Văn A',
            'submitted_by_phone' => '0987654321',
            'restaurant_id' => $data['restaurant']->id,
            'table_id' => $data['table']->id,
            'employee_id' => $employee->id,
            'employee_rating' => 4,
            'items_feedback' => [
                [
                    'product_id' => $data['product']->id,
                    'product_name' => $data['product']->name,
                    'rating' => 5,
                    'comment' => 'Ngon tuyệt cú mèo!',
                ]
            ]
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('customer_feedback', [
            'restaurant_id' => $data['restaurant']->id,
            'employee_id' => $employee->id,
            'employee_rating' => 4,
            'submitted_by_name' => 'Nguyễn Văn A',
        ]);

        Event::assertDispatched(NewFeedbackReceived::class);
    }
}
