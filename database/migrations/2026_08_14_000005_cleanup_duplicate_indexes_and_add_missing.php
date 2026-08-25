<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $driver = DB::getDriverName();
        $exists = false;

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $idx) {
                if ($idx->name === $indexName) {
                    $exists = true;
                    break;
                }
            }
        } elseif ($driver === 'mysql') {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            $exists = ! empty($indexes);
        }

        if ($exists) {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        }
    }

    public function up(): void
    {
        // 1. Drop duplicate index on order_items
        $this->dropIndexIfExists('order_items', 'order_items_product_id_idx');

        // 2. Drop duplicate index on inventories
        $this->dropIndexIfExists('inventories', 'inventories_ingredient_id_idx');

        // 3. Drop duplicate index on restaurant_revenue_summaries
        $this->dropIndexIfExists('restaurant_revenue_summaries', 'rrs_res_type_date_index');

        // 4. Drop duplicate indexes on schedule_assignments
        $this->dropIndexIfExists('schedule_assignments', 'schedule_assignments_res_date_status_index');
        $this->dropIndexIfExists('schedule_assignments', 'idx_sa_restaurant_date_status');

        // 5. Drop duplicate index on approval_requests
        $this->dropIndexIfExists('approval_requests', 'approval_requests_res_status_index');

        // 6. Drop duplicate index on operating_expenses
        $this->dropIndexIfExists('operating_expenses', 'op_expenses_perf_idx_1');

        // 7. Add missing index on violation_reports (restaurant_id, occurred_at)
        if (Schema::hasTable('violation_reports')) {
            Schema::table('violation_reports', function (Blueprint $table) {
                $table->index(['restaurant_id', 'occurred_at'], 'violation_reports_res_occurred_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('violation_reports')) {
            $this->dropIndexIfExists('violation_reports', 'violation_reports_res_occurred_idx');
        }
    }
};
