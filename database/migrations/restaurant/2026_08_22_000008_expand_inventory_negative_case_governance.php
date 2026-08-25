<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_negative_cases')) {
            return;
        }

        Schema::table('inventory_negative_cases', function (Blueprint $table): void {
            $table->string('source_type', 40)->nullable()->after('source_transaction_id');
            $table->string('severity', 20)->default('medium')->after('estimated_value');
            $table->text('root_cause')->nullable()->after('handling_plan');
            $table->boolean('owner_approval_required')->default(false)->after('root_cause');
            $table->string('owner_approval_status', 20)->nullable()->after('owner_approval_required');
            $table->text('approval_note')->nullable()->after('owner_approval_status');
            $table->foreignId('approved_by')->nullable()->after('approval_note')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->index(
                ['restaurant_id', 'branch_id', 'severity', 'status'],
                'inventory_negative_cases_risk_scope_index'
            );
            $table->index(
                ['restaurant_id', 'source_type', 'detected_at'],
                'inventory_negative_cases_source_detected_index'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_negative_cases')) {
            return;
        }

        Schema::table('inventory_negative_cases', function (Blueprint $table): void {
            $table->dropForeign(['approved_by']);
            $table->dropIndex('inventory_negative_cases_risk_scope_index');
            $table->dropIndex('inventory_negative_cases_source_detected_index');
            $table->dropColumn([
                'source_type',
                'severity',
                'root_cause',
                'owner_approval_required',
                'owner_approval_status',
                'approval_note',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};
