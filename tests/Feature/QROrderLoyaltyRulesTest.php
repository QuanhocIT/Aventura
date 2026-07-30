<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantArea;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QROrderLoyaltyRulesTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private RestaurantTable $table;
    private Product $phoProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create(['name' => 'Aventura Bistro']);
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $area = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $branch->id,
            'code'          => 'AREA-01',
            'name'          => 'Khu Vực Tầng 1',
        ]);
        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $branch->id,
            'area_id'       => $area->id,
            'name'          => 'Bàn 05',
            'capacity'      => 4,
            'qr_token'      => 'token-table-05',
            'status'        => 'available',
        ]);

        $category = ProductCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Thực Đơn Món Ăn',
            'slug'          => 'thuc-don',
            'display_order' => 1,
            'status'        => 'active',
        ]);

        $this->phoProduct = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id'   => $category->id,
            'code'          => 'PHO-01',
            'name'          => 'Phở Bò Gia Truyền',
            'slug'          => 'pho-bo',
            'price'         => 50000,
            'earn_points'   => 30,
            'redeem_points' => 300,
            'is_active'     => true,
            'is_available'  => true,
        ]);
    }

    public function test_guest_can_access_qr_menu_and_order_without_login(): void
    {
        // 1. Guest accesses QR menu without authentication
        $response = $this->get(route('customer.qr-order.show', [
            'restaurant' => $this->restaurant->id,
            'token'      => $this->table->qr_token,
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('customers/QROrder')
            ->where('restaurant.name', 'Aventura Bistro')
            ->where('customer', null) // Guest is null
            ->has('products')
        );
    }

    public function test_authenticated_customer_sees_customer_data_and_can_redeem(): void
    {
        // 2. Create customer and user account
        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'phone'         => '0999888777',
            'status'        => 'active',
        ]);
        $user->assignRole($customerRole);

        $customer = Customer::create([
            'restaurant_id'  => $this->restaurant->id,
            'full_name'      => 'Trần Văn Nam',
            'phone'          => '0999888777',
            'loyalty_points' => 450,
        ]);

        // Access QR menu as logged in customer
        $this->actingAs($user);

        $response = $this->get(route('customer.qr-order.show', [
            'restaurant' => $this->restaurant->id,
            'token'      => $this->table->qr_token,
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('customers/QROrder')
            ->where('customer.full_name', 'Trần Văn Nam')
            ->where('customer.loyalty_points', 450)
        );
    }
}
