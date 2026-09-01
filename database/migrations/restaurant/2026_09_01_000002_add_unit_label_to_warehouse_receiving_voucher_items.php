<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_receiving_voucher_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_receiving_voucher_items', 'unit_label')) {
                $table->string('unit_label', 50)->nullable()->after('ingredient_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_receiving_voucher_items', function (Blueprint $table): void {
            if (Schema::hasColumn('warehouse_receiving_voucher_items', 'unit_label')) {
                $table->dropColumn('unit_label');
            }
        });
    }
};
