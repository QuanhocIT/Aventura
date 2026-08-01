<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index(['restaurant_id', 'created_at'], 'order_items_perf_idx_1');
                $table->index(['restaurant_id', 'product_id', 'created_at'], 'order_items_perf_idx_2');
            });
        } catch (Exception $e) {
            // Index might already exist or driver limitations
        }

        try {
            Schema::table('operating_expenses', function (Blueprint $table) {
                $table->index(['restaurant_id', 'expense_date'], 'op_expenses_perf_idx_1');
            });
        } catch (Exception $e) {
            // Index might already exist or driver limitations
        }

        try {
            Schema::table('customer_feedback', function (Blueprint $table) {
                $table->index(['restaurant_id', 'rating', 'created_at'], 'cust_feedback_perf_idx_1');
            });
        } catch (Exception $e) {
            // Index might already exist or driver limitations
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropIndex('order_items_perf_idx_1');
                $table->dropIndex('order_items_perf_idx_2');
            });
        } catch (Exception $e) {
        }

        try {
            Schema::table('operating_expenses', function (Blueprint $table) {
                $table->dropIndex('op_expenses_perf_idx_1');
            });
        } catch (Exception $e) {
        }

        try {
            Schema::table('customer_feedback', function (Blueprint $table) {
                $table->dropIndex('cust_feedback_perf_idx_1');
            });
        } catch (Exception $e) {
        }
    }
};
