<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm indexes cho các query hay dùng nhất trong dashboard, middleware và reports.
     */
    public function up(): void
    {
        // payments: SUM revenue theo cashier + status + thời gian (DashboardController cashier view)
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['processed_by', 'status', 'paid_at'], 'payments_processed_status_paid_index');
        });

        // schedule_assignments: dashboard query theo restaurant + ngày + status
        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->index(['restaurant_id', 'scheduled_date', 'status'], 'schedule_assignments_res_date_status_index');
        });

        // inventories: low stock query dùng JOIN + WHERE trong dashboard
        Schema::table('inventories', function (Blueprint $table) {
            $table->index(['restaurant_id', 'quantity_on_hand'], 'inventories_res_qty_index');
        });

        // approval_requests: query trong Inertia shared middleware mỗi request
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->index(['restaurant_id', 'status'], 'approval_requests_res_status_index');
        });

        // orders: channel + status filter (cashier QR orders, delivery/takeaway queries)
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['restaurant_id', 'channel', 'status'], 'orders_res_channel_status_index');
        });

        // orders: cashier completed history query
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['restaurant_id', 'cashier_user_id', 'status'], 'orders_res_cashier_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_processed_status_paid_index');
        });

        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->dropIndex('schedule_assignments_res_date_status_index');
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropIndex('inventories_res_qty_index');
        });

        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropIndex('approval_requests_res_status_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_res_channel_status_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_res_cashier_status_index');
        });
    }
};
