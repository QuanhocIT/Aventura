<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite rebuilds a table for ALTER TABLE. Drop the compatibility
        // view first so the rebuild does not fail on its dependency.
        DB::statement('DROP VIEW IF EXISTS order_items_unified');

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('order_items', 'cancelled_at')) {
                    $table->dateTime('cancelled_at')->nullable()->after('served_at');
                }
                if (! Schema::hasColumn('order_items', 'cancelled_by')) {
                    $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('order_items', 'cancellation_reason')) {
                    $table->string('cancellation_reason', 500)->nullable()->after('cancelled_by');
                }
            });
        }

        if (Schema::hasTable('order_items_archive')) {
            Schema::table('order_items_archive', function (Blueprint $table): void {
                if (! Schema::hasColumn('order_items_archive', 'cancelled_at')) {
                    $table->dateTime('cancelled_at')->nullable()->after('served_at');
                }
                if (! Schema::hasColumn('order_items_archive', 'cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
                }
                if (! Schema::hasColumn('order_items_archive', 'cancellation_reason')) {
                    $table->string('cancellation_reason', 500)->nullable()->after('cancelled_by');
                }
            });
        }

        if (Schema::hasTable('inventory_transactions') && ! Schema::hasColumn('inventory_transactions', 'order_item_id')) {
            Schema::table('inventory_transactions', function (Blueprint $table): void {
                $table->unsignedBigInteger('order_item_id')->nullable()->after('order_id');
                $table->index('order_item_id', 'inventory_transactions_order_item_index');
            });
        }

        if (Schema::hasTable('inventory_reservations') && ! Schema::hasColumn('inventory_reservations', 'order_item_id')) {
            Schema::table('inventory_reservations', function (Blueprint $table): void {
                $table->unsignedBigInteger('order_item_id')->nullable()->after('order_id');
                $table->index('order_item_id', 'inventory_reservations_order_item_index');
            });
        }

        if (Schema::hasTable('order_items')) {
            $itemSelect = 'SELECT id, restaurant_id, order_id, product_id, quantity, unit_price, discount_amount, line_total, status, notes, sent_to_kitchen_at, started_preparing_at, prepared_at, served_at, cancelled_at, cancelled_by, cancellation_reason, created_at, updated_at FROM order_items';
            if (Schema::hasTable('order_items_archive')) {
                $itemSelect .= ' UNION ALL SELECT id, restaurant_id, order_id, product_id, quantity, unit_price, discount_amount, line_total, status, notes, sent_to_kitchen_at, started_preparing_at, prepared_at, served_at, cancelled_at, cancelled_by, cancellation_reason, created_at, updated_at FROM order_items_archive';
            }
            DB::statement('CREATE VIEW order_items_unified AS '.$itemSelect);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_reservations') && Schema::hasColumn('inventory_reservations', 'order_item_id')) {
            Schema::table('inventory_reservations', function (Blueprint $table): void {
                $table->dropIndex('inventory_reservations_order_item_index');
                $table->dropColumn('order_item_id');
            });
        }

        if (Schema::hasTable('inventory_transactions') && Schema::hasColumn('inventory_transactions', 'order_item_id')) {
            Schema::table('inventory_transactions', function (Blueprint $table): void {
                $table->dropIndex('inventory_transactions_order_item_index');
                $table->dropColumn('order_item_id');
            });
        }

        foreach (['order_items', 'order_items_archive'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'cancelled_by') && $tableName === 'order_items') {
                    $table->dropForeign(['cancelled_by']);
                }
                foreach (['cancelled_at', 'cancelled_by', 'cancellation_reason'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
