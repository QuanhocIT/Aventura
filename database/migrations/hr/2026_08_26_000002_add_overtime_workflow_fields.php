<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->after('employee_id')->constrained('users')->nullOnDelete();
            $table->string('request_source', 20)->default('employee')->after('hours_approved');
            $table->string('employee_response', 20)->nullable()->after('request_source');
            $table->dateTime('employee_responded_at')->nullable()->after('employee_response');
            $table->string('rejection_reason', 500)->nullable()->after('reason');

            $table->index(['restaurant_id', 'branch_id', 'status'], 'overtime_requests_branch_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropIndex('overtime_requests_branch_status_index');
            $table->dropConstrainedForeignId('requested_by');
            $table->dropColumn([
                'request_source',
                'employee_response',
                'employee_responded_at',
                'rejection_reason',
            ]);
        });
    }
};
