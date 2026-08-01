<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->indexExists('orders', 'orders_restaurant_branch_created_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['restaurant_id', 'branch_id', 'created_at'], 'orders_restaurant_branch_created_index');
            });
        }

        if (! $this->indexExists('inventories', 'inventories_restaurant_branch_created_index')) {
            Schema::table('inventories', function (Blueprint $table) {
                $table->index(['restaurant_id', 'branch_id', 'created_at'], 'inventories_restaurant_branch_created_index');
            });
        }

        if (! $this->indexExists('cash_transactions', 'cash_tx_restaurant_branch_created_index')) {
            Schema::table('cash_transactions', function (Blueprint $table) {
                $table->index(['restaurant_id', 'branch_id', 'created_at'], 'cash_tx_restaurant_branch_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_restaurant_branch_created_index');
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropIndex('inventories_restaurant_branch_created_index');
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropIndex('cash_tx_restaurant_branch_created_index');
        });
    }

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
            } catch (Throwable $e) {
                return false;
            }

            return false;
        }

        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

            return count($indexes) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
};
