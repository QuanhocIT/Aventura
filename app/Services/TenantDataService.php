<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\Employee;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantDataService
{
    /**
     * Export all data of a single restaurant into an array (JSON structure).
     */
    public function export(Restaurant $restaurant): array
    {
        return [
            'restaurant' => $restaurant->only([
                'name', 'code', 'slug', 'status', 'address', 'phone', 'email', 'tax_code'
            ]),
            'branches' => $restaurant->branches->map(fn($b) => $b->only(['name', 'address', 'phone', 'status'])),
            'tables' => RestaurantTable::where('restaurant_id', $restaurant->id)->get()->map(fn($t) => $t->only(['name', 'capacity', 'status', 'qr_code_path'])),
            'employees' => $restaurant->employees->map(fn($e) => $e->only(['name', 'email', 'phone', 'role', 'status'])),
            'products' => $restaurant->products->map(fn($p) => [
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => $p->price,
                'status' => $p->status,
                'category_name' => $p->category?->name,
            ]),
        ];
    }

    /**
     * Clone a restaurant's structural data into a new Sandbox Restaurant.
     */
    public function cloneSandbox(Restaurant $restaurant): Restaurant
    {
        return DB::transaction(function () use ($restaurant) {
            // 1. Clone Restaurant
            $sandboxCode = strtoupper(Str::random(6));
            $sandboxSlug = $restaurant->slug . '-sandbox-' . strtolower(Str::random(4));
            
            $sandbox = Restaurant::create([
                'name' => '[Sandbox] ' . $restaurant->name,
                'code' => $sandboxCode,
                'slug' => $sandboxSlug,
                'status' => 'active',
                'address' => $restaurant->address,
                'phone' => $restaurant->phone,
                'email' => 'sandbox_' . $restaurant->email,
                'tax_code' => $restaurant->tax_code,
                'plan_id' => $restaurant->plan_id,
                'owner_user_id' => $restaurant->owner_user_id,
                'sandbox_mode' => true,
                'sandbox_template' => 'cloned',
                'sandbox_seeded_at' => now(),
            ]);

            // 2. Clone Branches
            $branchMap = [];
            foreach ($restaurant->branches as $branch) {
                $newBranch = RestaurantBranch::create([
                    'restaurant_id' => $sandbox->id,
                    'name' => $branch->name,
                    'address' => $branch->address,
                    'phone' => $branch->phone,
                    'status' => $branch->status,
                ]);
                $branchMap[$branch->id] = $newBranch->id;
            }

            // 3. Clone Tables
            $tables = RestaurantTable::where('restaurant_id', $restaurant->id)->get();
            foreach ($tables as $table) {
                RestaurantTable::create([
                    'restaurant_id' => $sandbox->id,
                    'branch_id' => isset($branchMap[$table->branch_id]) ? $branchMap[$table->branch_id] : null,
                    'name' => $table->name,
                    'capacity' => $table->capacity,
                    'status' => $table->status,
                    'qr_token' => Str::random(32),
                ]);
            }

            // 4. Clone Categories & Products
            $categoryMap = [];
            foreach ($restaurant->products as $product) {
                $catId = $product->category_id;
                $newCatId = null;
                
                if ($catId) {
                    if (!isset($categoryMap[$catId])) {
                        $cat = ProductCategory::find($catId);
                        if ($cat) {
                            $newCat = ProductCategory::create([
                                'restaurant_id' => $sandbox->id,
                                'name' => $cat->name,
                                'slug' => $cat->slug . '-' . Str::random(3),
                                'description' => $cat->description,
                            ]);
                            $categoryMap[$catId] = $newCat->id;
                        }
                    }
                    $newCatId = $categoryMap[$catId] ?? null;
                }

                Product::create([
                    'restaurant_id' => $sandbox->id,
                    'category_id' => $newCatId,
                    'name' => $product->name,
                    'sku' => $product->sku . '-SB',
                    'price' => $product->price,
                    'status' => $product->status,
                ]);
            }

            // 5. Clone Employees & create Sandbox Users
            foreach ($restaurant->employees as $employee) {
                $sandboxEmail = 'sb_' . Str::random(3) . '_' . $employee->email;
                $newUser = null;
                
                if ($employee->user_id) {
                    $origUser = User::find($employee->user_id);
                    if ($origUser) {
                        $newUser = User::create([
                            'name' => '[SB] ' . $origUser->name,
                            'email' => $sandboxEmail,
                            'password' => bcrypt('password123'), // Mật khẩu mặc định sandbox
                            'email_verified_at' => now(),
                            'restaurant_id' => $sandbox->id,
                        ]);
                        // Assign the same roles if possible
                        foreach ($origUser->roles as $role) {
                            $newUser->assignRole($role->name);
                        }
                    }
                }

                Employee::create([
                    'restaurant_id' => $sandbox->id,
                    'branch_id' => isset($branchMap[$employee->branch_id]) ? $branchMap[$employee->branch_id] : null,
                    'user_id' => $newUser ? $newUser->id : null,
                    'name' => $employee->name,
                    'email' => $sandboxEmail,
                    'phone' => $employee->phone,
                    'role' => $employee->role,
                    'status' => $employee->status,
                ]);
            }

            return $sandbox;
        });
    }
}
