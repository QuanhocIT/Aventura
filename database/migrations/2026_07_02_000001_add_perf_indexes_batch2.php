<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bổ sung index còn thiếu cho các bảng ghi/đọc tần suất cao (audit từ đợt rà soát
     * khả năng chịu tải): shipper_location_logs cần index thuần theo thời gian cho
     * truy vấn range không lọc theo shipper/restaurant; audit_logs và
     * inventory_transactions cần index (tenant, thời gian) cho báo cáo không lọc theo
     * action/type; payment_webhooks cần (status, created_at) cho logic retry.
     */
    public function up(): void
    {
        if (! $this->indexExists('shipper_location_logs', 'sll_logged_at_index')) {
            Schema::table('shipper_location_logs', function (Blueprint $table) {
                $table->index('logged_at', 'sll_logged_at_index');
            });
        }

        if (! $this->indexExists('shipper_location_logs', 'sll_res_shipper_time_idx')) {
            Schema::table('shipper_location_logs', function (Blueprint $table) {
                $table->index(['restaurant_id', 'shipper_id', 'logged_at'], 'sll_res_shipper_time_idx');
            });
        }

        if (! $this->indexExists('audit_logs', 'audit_logs_restaurant_created_index')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index(['restaurant_id', 'created_at'], 'audit_logs_restaurant_created_index');
            });
        }

        if (! $this->indexExists('inventory_transactions', 'inventory_transactions_restaurant_occurred_index')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->index(['restaurant_id', 'occurred_at'], 'inventory_transactions_restaurant_occurred_index');
            });
        }

        if (! $this->indexExists('payment_webhooks', 'payment_webhooks_status_created_index')) {
            Schema::table('payment_webhooks', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'payment_webhooks_status_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shipper_location_logs', function (Blueprint $table) {
            $table->dropIndex('sll_logged_at_index');
            $table->dropIndex('sll_res_shipper_time_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_restaurant_created_index');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('inventory_transactions_restaurant_occurred_index');
        });

        Schema::table('payment_webhooks', function (Blueprint $table) {
            $table->dropIndex('payment_webhooks_status_created_index');
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
