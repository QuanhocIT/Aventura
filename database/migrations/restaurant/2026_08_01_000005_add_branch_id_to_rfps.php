<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('request_for_proposals') || Schema::hasColumn('request_for_proposals', 'branch_id')) {
            return;
        }

        Schema::table('request_for_proposals', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('restaurant_id')->constrained('restaurant_branches')->nullOnDelete();
            $table->index(['restaurant_id', 'branch_id', 'status'], 'rfp_branch_status_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('request_for_proposals') && Schema::hasColumn('request_for_proposals', 'branch_id')) {
            Schema::table('request_for_proposals', function (Blueprint $table): void {
                $table->dropForeign(['branch_id']);
                $table->dropIndex('rfp_branch_status_index');
                $table->dropColumn('branch_id');
            });
        }
    }
};
