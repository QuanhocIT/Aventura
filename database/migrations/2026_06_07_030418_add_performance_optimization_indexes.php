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
        Schema::table('products', function (Blueprint $table) {
            $table->index(['restaurant_id', 'category_id', 'is_active', 'is_available', 'deleted_at'], 'products_res_cat_status_deleted_index');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->index(['restaurant_id', 'status', 'deleted_at'], 'employees_res_status_deleted_index');
        });

        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->index(['employee_id', 'scheduled_date'], 'schedule_assignments_emp_date_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['restaurant_id', 'user_id', 'action', 'created_at'], 'audit_logs_res_user_action_date_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['restaurant_id', 'status', 'deleted_at', 'created_at'], 'orders_res_status_deleted_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_res_cat_status_deleted_index');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_res_status_deleted_index');
        });

        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->dropIndex('schedule_assignments_emp_date_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_res_user_action_date_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_res_status_deleted_created_index');
        });
    }
};
