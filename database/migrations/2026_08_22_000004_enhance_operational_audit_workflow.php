<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_infringement_reports', function (Blueprint $table) {
            $table->text('remediation_notes')->nullable()->after('remediation_plan');
            $table->dateTime('remediation_submitted_at')->nullable()->after('remediation_proof_url');
            $table->string('reinspection_result')->nullable()->after('reinspection_notes');
            $table->foreignId('reinspected_by')->nullable()->after('reinspection_result')->constrained('users')->nullOnDelete();
            $table->dateTime('reinspected_at')->nullable()->after('reinspected_by');

            $table->index(['restaurant_id', 'remediation_deadline'], 'oir_restaurant_deadline_idx');
            $table->index(['restaurant_id', 'assigned_to', 'status'], 'oir_assignee_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('operational_infringement_reports', function (Blueprint $table) {
            $table->dropForeign(['reinspected_by']);
            $table->dropIndex('oir_restaurant_deadline_idx');
            $table->dropIndex('oir_assignee_status_idx');
            $table->dropColumn([
                'remediation_notes',
                'remediation_submitted_at',
                'reinspection_result',
                'reinspected_by',
                'reinspected_at',
            ]);
        });
    }
};
