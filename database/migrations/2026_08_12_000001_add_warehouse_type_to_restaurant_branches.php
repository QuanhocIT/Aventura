<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_branches', function (Blueprint $table) {
            // Phân loại Kho Tổng vs chi nhánh kinh doanh
            if (! Schema::hasColumn('restaurant_branches', 'warehouse_type')) {
                $table->string('warehouse_type', 20)->default('business')->after('is_central_warehouse');
                // 'business' = chi nhánh kinh doanh bình thường
                // 'central'  = Kho Tổng (trung tâm phân phối)
            }

            // Định mức cấp phát tối đa theo tháng (VND, null = không giới hạn)
            if (! Schema::hasColumn('restaurant_branches', 'monthly_supply_limit')) {
                $table->decimal('monthly_supply_limit', 15, 2)->nullable()->after('warehouse_type');
            }
        });

        // Backfill: branch nào đang is_central_warehouse = true → warehouse_type = 'central'
        DB::statement("UPDATE restaurant_branches SET warehouse_type = 'central' WHERE is_central_warehouse = 1");
    }

    public function down(): void
    {
        Schema::table('restaurant_branches', function (Blueprint $table) {
            $table->dropColumn(['warehouse_type', 'monthly_supply_limit']);
        });
    }
};
