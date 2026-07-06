<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Restaurant;
use App\Models\Supplier;
use App\Models\SupplierPriceHistory;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SupplierPortalSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Get first restaurant
        $restaurant = Restaurant::first();
        if (!$restaurant) {
            $restaurant = Restaurant::create([
                'name' => 'Aventura Diner',
                'status' => 'active',
            ]);
        }

        // 2. Create supplier role
        $supplierRole = Role::firstOrCreate([
            'name' => 'supplier',
            'guard_name' => 'web',
        ]);

        // 3. Create sample supplier
        $supplier = Supplier::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'email' => 'contact@dalatveggies.com'],
            [
                'name' => 'Hợp tác xã Rau sạch Đà Lạt',
                'contact_name' => 'Nguyễn Thị Thảo',
                'phone' => '0912345678',
                'address' => '45 Tự Phước, Phường 11, Đà Lạt, Lâm Đồng',
                'notes' => 'Đối tác cung ứng rau củ quả tươi sạch tiêu chuẩn VietGAP.',
                'status' => 'active',
            ]
        );

        // 4. Create supplier user
        $supplierUser = User::where('email', 'supplier@example.com')->first();
        if (!$supplierUser) {
            $supplierUser = User::create([
                'name' => 'Đại diện Đà Lạt Veggies',
                'email' => 'supplier@example.com',
                'password' => bcrypt('password'),
                'restaurant_id' => $restaurant->id,
                'supplier_id' => $supplier->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $supplierUser->assignRole($supplierRole);
        } else {
            $supplierUser->update([
                'supplier_id' => $supplier->id,
                'restaurant_id' => $restaurant->id,
            ]);
            $supplierUser->assignRole($supplierRole);
        }

        // 5. Create units if not existing
        $unitKg = Unit::where('symbol', 'kg')->first();
        if (!$unitKg) {
            $unitKg = Unit::create([
                'restaurant_id' => $restaurant->id,
                'name' => 'Kilôgam',
                'symbol' => 'kg',
                'type' => 'mass',
            ]);
        }

        // 6. Create materials (ingredients) supplied by this supplier
        $ingredientsData = [
            [
                'name' => 'Rau xà lách Romaine',
                'sku' => 'ROM-SAL-01',
                'category_name' => 'Rau củ tươi',
                'description' => 'Rau xà lách giòn ngon ngọt Đà Lạt, đóng gói 500g bảo quản lạnh.',
                'price' => 35000, // current listed price
            ],
            [
                'name' => 'Cà chua Beef',
                'sku' => 'BEEF-TOM-02',
                'category_name' => 'Rau củ tươi',
                'description' => 'Cà chua to mọng nước, chuẩn sạch VietGAP ăn sống ngon.',
                'price' => 28000,
            ],
            [
                'name' => 'Khoai tây Đà Lạt',
                'sku' => 'DL-POTATO-03',
                'category_name' => 'Rau củ tươi',
                'description' => 'Khoai tây đỏ vỏ mỏng ruột vàng dẻo bùi.',
                'price' => 22000,
            ]
        ];

        foreach ($ingredientsData as $ingData) {
            $ingredient = Ingredient::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->where('sku', $ingData['sku'])
                ->first();

            if (!$ingredient) {
                $ingredient = Ingredient::create([
                    'restaurant_id' => $restaurant->id,
                    'supplier_id' => $supplier->id,
                    'unit_id' => $unitKg->id,
                    'name' => $ingData['name'],
                    'sku' => $ingData['sku'],
                    'category_name' => $ingData['category_name'],
                    'description' => $ingData['description'],
                    'average_cost' => $ingData['price'],
                    'min_stock_level' => 10,
                    'reorder_level' => 15,
                    'status' => 'active',
                ]);
            } else {
                $ingredient->update([
                    'supplier_id' => $supplier->id,
                    'average_cost' => $ingData['price'],
                ]);
            }

            // Seed historical price logs for this material (price analytics)
            // Generate 6 months of historical prices
            $basePrice = $ingData['price'];
            for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
                $date = Carbon::now()->subMonths($monthOffset)->startOfMonth();
                // Introduce slight random pricing fluctuation
                $flucPercent = ($monthOffset * 3.5) - 6; // creates a nice slope
                $historicPrice = $basePrice * (1 + ($flucPercent / 100));

                SupplierPriceHistory::firstOrCreate(
                    [
                        'supplier_id' => $supplier->id,
                        'ingredient_id' => $ingredient->id,
                        'effective_date' => $date,
                    ],
                    [
                        'price' => round($historicPrice, 2),
                        'created_by' => $supplierUser->id,
                    ]
                );
            }
        }

        // 7. Seed some demo purchase orders
        // Order 1: Delivered
        $po1 = PurchaseOrder::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'po_number' => 'PO-20260601-00001'],
            [
                'supplier_id' => $supplier->id,
                'status' => 'delivered',
                'total_amount' => 186000,
                'invoice_total_amount' => 186000,
                'created_by' => $supplierUser->id,
                'approved_by' => $supplierUser->id,
                'notes' => 'Giao hàng buổi sáng trước 8h.',
            ]
        );
        
        $romaine = Ingredient::withoutGlobalScopes()->where('sku', 'ROM-SAL-01')->first();
        if ($romaine) {
            $po1->items()->firstOrCreate(
                ['ingredient_id' => $romaine->id],
                [
                    'quantity_ordered' => 2.0,
                    'quantity_received' => 2.0,
                    'price_per_unit' => 35000,
                    'invoice_price_per_unit' => 35000,
                    'total_cost' => 70000,
                ]
            );
        }

        // Order 2: Approved / Preparing
        $po2 = PurchaseOrder::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'po_number' => 'PO-20260604-00002'],
            [
                'supplier_id' => $supplier->id,
                'status' => 'approved',
                'total_amount' => 143000,
                'created_by' => $supplierUser->id,
                'approved_by' => $supplierUser->id,
                'notes' => 'Hàng tươi trong ngày.',
            ]
        );
        
        $tomato = Ingredient::withoutGlobalScopes()->where('sku', 'BEEF-TOM-02')->first();
        if ($tomato) {
            $po2->items()->firstOrCreate(
                ['ingredient_id' => $tomato->id],
                [
                    'quantity_ordered' => 5.0,
                    'price_per_unit' => 28000,
                    'total_cost' => 140000,
                ]
            );
        }
    }
}
