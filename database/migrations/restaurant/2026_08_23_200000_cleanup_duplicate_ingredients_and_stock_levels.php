<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ingredients')) {
            return;
        }

        // 1. Chỉnh sửa ngưỡng min_stock_level = 0 về ngưỡng mặc định hợp lý (5.0)
        // để kích hoạt hệ thống cảnh báo sắp hết hàng.
        DB::table('ingredients')
            ->where('min_stock_level', 0)
            ->update(['min_stock_level' => 5.0]);

        // 2. Chuẩn hóa dữ liệu tồn kho ảo ~1.000.000 về mức thực tế (50.0)
        if (Schema::hasTable('inventories')) {
            DB::table('inventories')
                ->where('quantity_on_hand', '>=', 900000)
                ->update([
                    'quantity_on_hand' => 50.0,
                    'theoretical_quantity' => 50.0,
                ]);
        }

        // 3. Gộp các nguyên liệu trùng tên/công thức (Gạo / Gao; Thịt bò / Thit bo)
        // Gạo (id: 148 / 80) -> gộp 80 về 148
        $gaoOld = DB::table('ingredients')->where('sku', 'ING-RICE-001')->first();
        $gaoNew = DB::table('ingredients')->where('sku', 'ING-GAO')->first();

        if ($gaoOld && $gaoNew) {
            if (Schema::hasTable('product_recipes')) {
                DB::table('product_recipes')
                    ->where('ingredient_id', $gaoOld->id)
                    ->update(['ingredient_id' => $gaoNew->id]);
            }
            if (Schema::hasTable('inventory_transactions')) {
                DB::table('inventory_transactions')
                    ->where('ingredient_id', $gaoOld->id)
                    ->update(['ingredient_id' => $gaoNew->id]);
            }
            if (Schema::hasTable('inventories')) {
                DB::table('inventories')->where('ingredient_id', $gaoOld->id)->delete();
            }
            DB::table('ingredients')->where('id', $gaoOld->id)->delete();
        }

        // Thịt bò (id: 146 / 78) -> gộp 78 về 146
        $boOld = DB::table('ingredients')->where('sku', 'ING-BEEF-001')->first();
        $boNew = DB::table('ingredients')->where('sku', 'ING-THIT-BO')->first();

        if ($boOld && $boNew) {
            if (Schema::hasTable('product_recipes')) {
                DB::table('product_recipes')
                    ->where('ingredient_id', $boOld->id)
                    ->update(['ingredient_id' => $boNew->id]);
            }
            if (Schema::hasTable('inventory_transactions')) {
                DB::table('inventory_transactions')
                    ->where('ingredient_id', $boOld->id)
                    ->update(['ingredient_id' => $boNew->id]);
            }
            if (Schema::hasTable('inventories')) {
                DB::table('inventories')->where('ingredient_id', $boOld->id)->delete();
            }
            DB::table('ingredients')->where('id', $boOld->id)->delete();
        }
    }

    public function down(): void
    {
        // No-op rollback for data cleanup migration
    }
};
