<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->string('overtime_type', 20)->default('normal')->after('request_source');
            $table->dateTime('scheduled_start_at')->nullable()->after('overtime_type');
            $table->dateTime('scheduled_end_at')->nullable()->after('scheduled_start_at');
            $table->dateTime('check_in_at')->nullable()->after('scheduled_end_at');
            $table->dateTime('check_out_at')->nullable()->after('check_in_at');
            $table->decimal('worked_hours', 5, 2)->default(0)->after('check_out_at');
            $table->decimal('hourly_rate', 12, 2)->nullable()->after('worked_hours');
            $table->decimal('overtime_multiplier', 4, 2)->nullable()->after('hourly_rate');
            $table->decimal('estimated_amount', 12, 2)->default(0)->after('overtime_multiplier');
            $table->decimal('actual_amount', 12, 2)->default(0)->after('estimated_amount');
            $table->string('payroll_status', 24)->default('not_ready')->after('actual_amount');
            $table->string('attendance_note', 500)->nullable()->after('payroll_status');

            $table->index(
                ['employee_id', 'scheduled_date', 'status', 'payroll_status'],
                'overtime_requests_payroll_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropIndex('overtime_requests_payroll_status_index');
            $table->dropColumn([
                'overtime_type',
                'scheduled_start_at',
                'scheduled_end_at',
                'check_in_at',
                'check_out_at',
                'worked_hours',
                'hourly_rate',
                'overtime_multiplier',
                'estimated_amount',
                'actual_amount',
                'payroll_status',
                'attendance_note',
            ]);
        });
    }
};
