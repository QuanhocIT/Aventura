<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_reservations') && Schema::hasColumn('inventory_reservations', 'order_id')) {
            try {
                Schema::table('inventory_reservations', function (Blueprint $table) {
                    $table->unsignedBigInteger('order_id')->nullable()->change();
                });
            } catch (\Throwable $e) {
                // Fallback direct SQL statement for MySQL if change() isn't supported directly
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE inventory_reservations MODIFY order_id BIGINT UNSIGNED NULL;');
                }
            }
        }
    }

    public function down(): void
    {
        // No down migration needed for nullability relaxation
    }
};
