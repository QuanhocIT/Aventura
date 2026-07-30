<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantRevenueSummary;
use App\Models\RestaurantTable;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuickDemoSeeder extends Seeder
{
    private array $emails = [
        'free@test.com',
        'starter@test.com',
        'pro@test.com',
        'enterprise@test.com',
    ];

    public function run(): void
    {
        foreach ($this->emails as $email) {
            $user = User::where('email', $email)->first();
            if (!$user || !$user->restaurant_id) {
                $this->command->warn("Skip {$email} — không tìm thấy");
                continue;
            }

            $restaurant = $user->restaurant;
            $this->command->info("Seeding: {$email} → {$restaurant->name} (ID:{$restaurant->id})");
            $this->seedForRestaurant($restaurant);
        }

        $this->command->newLine();
        $this->command->info('Hoàn tất! 4 tài khoản có đầy đủ dữ liệu mẫu.');
    }

    private function seedForRestaurant(Restaurant $restaurant): void
    {
        $rid = $restaurant->id;

        $unitG = Unit::firstOrCreate(
            ['restaurant_id' => $rid, 'symbol' => 'g'],
            ['name' => 'Gram', 'type' => 'mass']
        );
        $unitChai = Unit::firstOrCreate(
            ['restaurant_id' => $rid, 'symbol' => 'chai'],
            ['name' => 'Chai', 'type' => 'count']
        );

        $area = Area::firstOrCreate(
            ['restaurant_id' => $rid, 'code' => 'MAIN'],
            ['name' => 'Khu chính', 'display_order' => 1, 'status' => 'active']
        );

        for ($i = 1; $i <= 8; $i++) {
            RestaurantTable::firstOrCreate(
                ['restaurant_id' => $rid, 'name' => "B{$i}"],
                ['area_id' => $area->id, 'capacity' => rand(2, 6), 'status' => 'available', 'qr_token' => Str::random(20)]
            );
        }

        $cats = [];
        foreach (['Món chính' => 1, 'Đồ uống' => 2, 'Tráng miệng' => 3] as $catName => $order) {
            $cats[$catName] = ProductCategory::firstOrCreate(
                ['restaurant_id' => $rid, 'slug' => Str::slug($catName)],
                ['name' => $catName, 'display_order' => $order]
            );
        }

        $products = [];
        $menuItems = [
            ['Phở Bò', 'PHO-BO', 55000, 18000, 'Món chính'],
            ['Cơm Tấm Sườn', 'COM-TAM', 50000, 16000, 'Món chính'],
            ['Bún Chả Hà Nội', 'BUN-CHA', 60000, 20000, 'Món chính'],
            ['Gỏi Cuốn (2 cuốn)', 'GOI-CUON', 35000, 10000, 'Món chính'],
            ['Bánh Mì Thịt', 'BANH-MI', 30000, 8000, 'Món chính'],
            ['Cà Phê Sữa Đá', 'CA-PHE', 25000, 6000, 'Đồ uống'],
            ['Trà Đào Cam Sả', 'TRA-DAO', 35000, 10000, 'Đồ uống'],
            ['Sinh Tố Bơ', 'SINH-TO', 40000, 12000, 'Đồ uống'],
            ['Nước Suối', 'NUOC-SUOI', 10000, 3000, 'Đồ uống'],
            ['Bia Tiger', 'BIA-TIGER', 25000, 12000, 'Đồ uống'],
            ['Chè Thái', 'CHE-THAI', 30000, 8000, 'Tráng miệng'],
            ['Bánh Flan', 'BANH-FLAN', 20000, 5000, 'Tráng miệng'],
        ];

        foreach ($menuItems as [$name, $sku, $price, $cost, $cat]) {
            $products[] = Product::firstOrCreate(
                ['restaurant_id' => $rid, 'code' => $sku],
                ['name' => $name, 'slug' => Str::slug($name), 'price' => $price, 'cost_price' => $cost, 'category_id' => $cats[$cat]->id, 'is_active' => true, 'is_available' => true]
            );
        }

        $ingredients = [];
        foreach (['Thịt bò' => 320, 'Thịt heo' => 180, 'Gạo' => 35, 'Rau sống' => 25, 'Bún' => 30] as $ingName => $cost) {
            $ing = Ingredient::firstOrCreate(
                ['restaurant_id' => $rid, 'sku' => 'ING-' . Str::upper(Str::slug($ingName))],
                ['name' => $ingName, 'unit_id' => $unitG->id, 'category_name' => 'Nguyên liệu', 'min_stock_level' => 500, 'reorder_level' => 1000, 'average_cost' => $cost, 'status' => 'active']
            );
            $ingredients[] = $ing;

            Inventory::firstOrCreate(
                ['restaurant_id' => $rid, 'ingredient_id' => $ing->id],
                ['quantity_on_hand' => rand(500, 5000), 'last_cost' => $cost]
            );
        }

        for ($i = 0; $i < 10; $i++) {
            Customer::firstOrCreate(
                ['restaurant_id' => $rid, 'phone' => '09' . rand(10000000, 99999999)],
                ['full_name' => fake('vi_VN')->name(), 'membership_level' => ['silver', 'gold', 'platinum'][rand(0, 2)], 'loyalty_points' => rand(0, 500), 'total_spent' => rand(100000, 5000000)]
            );
        }

        $tables = RestaurantTable::where('restaurant_id', $rid)->get();
        $now = now();

        for ($day = 30; $day >= 0; $day--) {
            $date = $now->copy()->subDays($day);
            $ordersPerDay = rand(8, 25);
            $dayRevenue = 0;
            $dayOrders = 0;
            $dayCompleted = 0;

            for ($o = 0; $o < $ordersPerDay; $o++) {
                $table = $tables->random();
                $status = rand(1, 10) <= 8 ? 'completed' : (rand(1, 10) <= 5 ? 'pending' : 'cancelled');
                $channel = ['dine_in', 'dine_in', 'dine_in', 'takeaway', 'delivery'][rand(0, 4)];
                $orderNumber = 'ORD-' . $date->format('ymd') . '-' . str_pad($o + 1, 3, '0', STR_PAD_LEFT);

                $order = Order::firstOrCreate(
                    ['restaurant_id' => $rid, 'order_number' => $orderNumber],
                    [
                        'table_id' => $channel === 'dine_in' ? $table->id : null,
                        'status' => $status,
                        'payment_status' => $status === 'completed' ? 'paid' : 'unpaid',
                        'channel' => $channel,
                        'created_at' => $date->copy()->setHour(rand(7, 21))->setMinute(rand(0, 59)),
                        'completed_at' => $status === 'completed' ? $date->copy()->setHour(rand(8, 22)) : null,
                    ]
                );

                if (!$order->wasRecentlyCreated) {
                    continue;
                }

                $itemCount = rand(1, 4);
                $orderTotal = 0;

                for ($it = 0; $it < $itemCount; $it++) {
                    $product = $products[array_rand($products)];
                    $qty = rand(1, 3);
                    $lineTotal = $product->price * $qty;
                    $orderTotal += $lineTotal;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'restaurant_id' => $rid,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $product->price,
                        'line_total' => $lineTotal,
                        'status' => $status === 'completed' ? 'served' : 'pending',
                    ]);
                }

                $order->update([
                    'subtotal' => $orderTotal,
                    'total_amount' => $orderTotal,
                ]);

                if ($status === 'completed') {
                    Payment::create([
                        'order_id' => $order->id,
                        'restaurant_id' => $rid,
                        'amount' => $orderTotal,
                        'payment_method' => ['cash', 'bank_transfer', 'ewallet'][rand(0, 2)],
                        'status' => 'paid',
                        'paid_at' => $order->completed_at,
                    ]);
                    $dayRevenue += $orderTotal;
                    $dayCompleted++;
                }
                $dayOrders++;
            }

            RestaurantRevenueSummary::updateOrCreate(
                ['restaurant_id' => $rid, 'branch_id' => null, 'summary_date' => $date->toDateString()],
                [
                    'summary_type' => 'daily',
                    'order_count' => $dayOrders,
                    'completed_order_count' => $dayCompleted,
                    'cancelled_order_count' => $dayOrders - $dayCompleted,
                    'net_revenue' => $dayRevenue,
                    'gross_revenue' => $dayRevenue * 1.05,
                    'discount_total' => $dayRevenue * 0.03,
                    'cogs_amount' => $dayRevenue * 0.35,
                    'gross_profit' => $dayRevenue * 0.65,
                    'average_order_value' => $dayCompleted > 0 ? round($dayRevenue / $dayCompleted) : 0,
                    'first_order_at' => $date->copy()->setHour(7),
                    'last_order_at' => $date->copy()->setHour(21),
                    'calculated_at' => now(),
                ]
            );
        }

        $this->command->info("  → {$rid}: Products=" . count($products) . ", ~" . (31 * 15) . " orders, 31 ngày revenue");
    }
}
