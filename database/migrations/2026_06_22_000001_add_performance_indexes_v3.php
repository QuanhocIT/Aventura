<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm composite indexes cho các query nặng chưa được index:
     * - orders: completed_at (dùng trong getShiftRevenue - query 7 ngày)
     * - order_items: join với orders theo restaurant + ngày
     * - inventories: ingredient_id để tăng tốc JOIN low-stock
     * - schedule_assignments: check_in_at cho dashboard feed
     * - restaurant_revenue_summaries: lookup theo restaurant + ngày (dùng nhiều nhất)
     */
    public function up(): void
    {
        // orders: completed_at index cho getShiftRevenue bulk query
        if (! $this->indexExists('orders', 'orders_res_completed_at_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['restaurant_id', 'completed_at'], 'orders_res_completed_at_index');
            });
        }

        // order_items: tăng tốc getTopProductsChartData JOIN
        if (! $this->indexExists('order_items', 'order_items_order_product_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index(['order_id', 'product_id'], 'order_items_order_product_index');
            });
        }

        // restaurant_revenue_summaries: query thường xuyên nhất (dashboard + reports)
        if (! $this->indexExists('restaurant_revenue_summaries', 'rrs_res_type_date_index')) {
            Schema::table('restaurant_revenue_summaries', function (Blueprint $table) {
                $table->index(
                    ['restaurant_id', 'summary_type', 'summary_date'],
                    'rrs_res_type_date_index'
                );
            });
        }

        // schedule_assignments: check_in_at cho activity feed dashboard
        if (! $this->indexExists('schedule_assignments', 'sa_res_checkin_index')) {
            Schema::table('schedule_assignments', function (Blueprint $table) {
                $table->index(['restaurant_id', 'check_in_at'], 'sa_res_checkin_index');
            });
        }

        // support_tickets: query theo status + priority + tenant cho SLA escalation
        if (! $this->indexExists('support_tickets', 'st_tenant_status_priority_index')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->index(
                    ['restaurant_id', 'status', 'priority'],
                    'st_tenant_status_priority_index'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_res_completed_at_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_product_index');
        });

        Schema::table('restaurant_revenue_summaries', function (Blueprint $table) {
            $table->dropIndex('rrs_res_type_date_index');
        });

        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->dropIndex('sa_res_checkin_index');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex('st_tenant_status_priority_index');
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
