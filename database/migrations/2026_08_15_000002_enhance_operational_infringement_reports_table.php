<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_infringement_reports', function (Blueprint $table) {
            $table->enum('severity_level', ['minor', 'moderate', 'severe', 'critical'])->default('moderate')->after('description');
            $table->date('remediation_deadline')->nullable()->after('penalty_amount');
            $table->text('remediation_plan')->nullable()->after('remediation_deadline');
            $table->string('remediation_proof_url')->nullable()->after('remediation_plan');
            $table->text('reinspection_notes')->nullable()->after('remediation_proof_url');
            $table->foreignId('assigned_to')->nullable()->after('reinspection_notes')->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable()->after('closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('operational_infringement_reports', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['closed_by']);
            $table->dropColumn([
                'severity_level',
                'remediation_deadline',
                'remediation_plan',
                'remediation_proof_url',
                'reinspection_notes',
                'assigned_to',
                'closed_by',
                'closed_at',
            ]);
        });
    }
};
