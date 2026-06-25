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
        // order_items: product_id index
        if (! $this->indexExists('order_items', 'order_items_product_id_idx')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index('product_id', 'order_items_product_id_idx');
            });
        }

        // inventories: ingredient_id index
        if (! $this->indexExists('inventories', 'inventories_ingredient_id_idx')) {
            Schema::table('inventories', function (Blueprint $table) {
                $table->index('ingredient_id', 'inventories_ingredient_id_idx');
            });
        }

        // schedule_assignments: shift_id index
        if (! $this->indexExists('schedule_assignments', 'schedule_assignments_shift_id_idx')) {
            Schema::table('schedule_assignments', function (Blueprint $table) {
                $table->index('shift_id', 'schedule_assignments_shift_id_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists('order_items', 'order_items_product_id_idx')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropIndex('order_items_product_id_idx');
            });
        }

        if ($this->indexExists('inventories', 'inventories_ingredient_id_idx')) {
            Schema::table('inventories', function (Blueprint $table) {
                $table->dropIndex('inventories_ingredient_id_idx');
            });
        }

        if ($this->indexExists('schedule_assignments', 'schedule_assignments_shift_id_idx')) {
            Schema::table('schedule_assignments', function (Blueprint $table) {
                $table->dropIndex('schedule_assignments_shift_id_idx');
            });
        }
    }

    /**
     * Check if index exists dynamically (supports SQLite & MySQL).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'sqlite') {
            try {
                $results = DB::select("PRAGMA index_list(`{$table}`)");
                foreach ($results as $row) {
                    if ($row->name === $indexName) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                return false;
            }
            return false;
        }

        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
