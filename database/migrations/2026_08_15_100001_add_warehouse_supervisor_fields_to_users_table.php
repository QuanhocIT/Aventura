<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supervisor_user_id')->nullable()->after('restaurant_id')->constrained('users')->nullOnDelete();
            $table->foreignId('warehouse_branch_id')->nullable()->after('supervisor_user_id')->constrained('restaurant_branches')->nullOnDelete();
            $table->string('warehouse_staff_status', 20)->default('active')->after('warehouse_branch_id'); // active, paused, inactive

            $table->index(['restaurant_id', 'warehouse_branch_id']);
            $table->index(['supervisor_user_id', 'warehouse_staff_status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['supervisor_user_id']);
            $table->dropForeign(['warehouse_branch_id']);
            $table->dropIndex(['restaurant_id', 'warehouse_branch_id']);
            $table->dropIndex(['supervisor_user_id', 'warehouse_staff_status']);
            $table->dropColumn(['supervisor_user_id', 'warehouse_branch_id', 'warehouse_staff_status']);
        });
    }
};
