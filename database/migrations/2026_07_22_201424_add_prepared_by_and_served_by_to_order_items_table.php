<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        // Drop view first to avoid SQLite constraints during alter table
        DB::statement('DROP VIEW IF EXISTS order_items_unified');

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('served_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('order_items_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('prepared_by')->nullable();
            $table->unsignedBigInteger('served_by')->nullable();
        });

        // Recreate the view including the new columns
        DB::statement('
            CREATE '.($isSqlite ? '' : 'OR REPLACE ').'VIEW order_items_unified AS
            SELECT
                id, restaurant_id, order_id, product_id, quantity,
                unit_price, discount_amount, line_total, status,
                notes, sent_to_kitchen_at, prepared_at, served_at,
                prepared_by, served_by,
                created_at, updated_at
            FROM order_items

            UNION ALL

            SELECT
                id, restaurant_id, order_id, product_id, quantity,
                unit_price, discount_amount, line_total, status,
                notes, sent_to_kitchen_at, prepared_at, served_at,
                prepared_by, served_by,
                created_at, updated_at
            FROM order_items_archive
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        DB::statement('DROP VIEW IF EXISTS order_items_unified');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['prepared_by']);
            $table->dropColumn('prepared_by');
            $table->dropForeign(['served_by']);
            $table->dropColumn('served_by');
        });

        Schema::table('order_items_archive', function (Blueprint $table) {
            $table->dropColumn(['prepared_by', 'served_by']);
        });

        // Recreate old view without the new columns
        DB::statement('
            CREATE '.($isSqlite ? '' : 'OR REPLACE ').'VIEW order_items_unified AS
            SELECT
                id, restaurant_id, order_id, product_id, quantity,
                unit_price, discount_amount, line_total, status,
                notes, sent_to_kitchen_at, prepared_at, served_at,
                created_at, updated_at
            FROM order_items

            UNION ALL

            SELECT
                id, restaurant_id, order_id, product_id, quantity,
                unit_price, discount_amount, line_total, status,
                notes, sent_to_kitchen_at, prepared_at, served_at,
                created_at, updated_at
            FROM order_items_archive
        ');
    }
};
