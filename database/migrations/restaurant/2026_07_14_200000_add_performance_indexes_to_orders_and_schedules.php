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
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['restaurant_id', 'status', 'completed_at'], 'orders_perf_idx_1');
                $table->index(['restaurant_id', 'branch_id', 'status', 'created_at'], 'orders_perf_idx_2');
            });
        } catch (\Exception $e) {
            // Index might already exist or driver limitations
        }

        try {
            Schema::table('orders_archive', function (Blueprint $table) {
                $table->index(['restaurant_id', 'status', 'completed_at'], 'orders_arch_perf_idx_1');
            });
        } catch (\Exception $e) {
            // Index might already exist or driver limitations
        }

        try {
            Schema::table('schedule_assignments', function (Blueprint $table) {
                $table->index(['restaurant_id', 'scheduled_date'], 'sched_assign_perf_idx_1');
            });
        } catch (\Exception $e) {
            // Index might already exist or driver limitations
        }

        try {
            Schema::table('schedule_registrations', function (Blueprint $table) {
                $table->index(['restaurant_id', 'scheduled_date'], 'sched_reg_perf_idx_1');
            });
        } catch (\Exception $e) {
            // Index might already exist or driver limitations
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_perf_idx_1');
                $table->dropIndex('orders_perf_idx_2');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('orders_archive', function (Blueprint $table) {
                $table->dropIndex('orders_arch_perf_idx_1');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('schedule_assignments', function (Blueprint $table) {
                $table->dropIndex('sched_assign_perf_idx_1');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('schedule_registrations', function (Blueprint $table) {
                $table->dropIndex('sched_reg_perf_idx_1');
            });
        } catch (\Exception $e) {}
    }
};
