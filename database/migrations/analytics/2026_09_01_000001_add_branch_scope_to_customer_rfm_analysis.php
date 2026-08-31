<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_rfm_analysis') || Schema::hasColumn('customer_rfm_analysis', 'branch_id')) {
            return;
        }

        Schema::table('customer_rfm_analysis', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('customer_id')->constrained('restaurant_branches')->nullOnDelete();
            $table->index(['restaurant_id', 'branch_id', 'rfm_segment'], 'rfm_restaurant_branch_segment_index');
        });

        Schema::table('customer_rfm_analysis', function (Blueprint $table): void {
            $table->dropUnique('rfm_restaurant_customer_unique');
            $table->unique(['restaurant_id', 'customer_id', 'branch_id'], 'rfm_restaurant_customer_branch_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_rfm_analysis') || ! Schema::hasColumn('customer_rfm_analysis', 'branch_id')) {
            return;
        }

        Schema::table('customer_rfm_analysis', function (Blueprint $table): void {
            $table->dropUnique('rfm_restaurant_customer_branch_unique');
            $table->dropIndex('rfm_restaurant_branch_segment_index');
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
            $table->unique(['restaurant_id', 'customer_id'], 'rfm_restaurant_customer_unique');
        });
    }
};
