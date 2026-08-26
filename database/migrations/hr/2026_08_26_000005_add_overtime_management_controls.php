<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->string('name', 120)->default('Chính sách tăng ca mặc định');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('normal_multiplier', 4, 2)->default(1.50);
            $table->decimal('night_multiplier', 4, 2)->default(2.00);
            $table->decimal('rest_day_multiplier', 4, 2)->default(2.00);
            $table->decimal('holiday_multiplier', 4, 2)->default(3.00);
            $table->decimal('max_daily_hours', 5, 2)->default(4.00);
            $table->decimal('max_weekly_hours', 5, 2)->default(12.00);
            $table->decimal('max_monthly_hours', 6, 2)->default(40.00);
            $table->decimal('minimum_rest_hours', 5, 2)->default(11.00);
            $table->boolean('require_gps')->default(true);
            $table->boolean('require_qr')->default(false);
            $table->boolean('require_photo')->default(false);
            $table->boolean('employee_can_request')->default(true);
            $table->boolean('require_employee_acceptance')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['restaurant_id', 'branch_id', 'is_active'], 'overtime_policies_scope_index');
            $table->index(['restaurant_id', 'effective_from', 'effective_to'], 'overtime_policies_effective_index');
            $table->index(['employee_id', 'role_id'], 'overtime_policies_subject_index');
        });

        Schema::create('overtime_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('holiday_date');
            $table->string('name', 150);
            $table->decimal('multiplier', 4, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['restaurant_id', 'branch_id', 'holiday_date'], 'overtime_holidays_scope_date_unique');
            $table->index(['restaurant_id', 'holiday_date'], 'overtime_holidays_date_index');
        });

        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->dropUnique('overtime_requests_employee_date_unique');
            $table->index(['employee_id', 'scheduled_date'], 'overtime_requests_employee_date_index');
            $table->string('workflow_status', 32)->default('submitted')->after('status');
            $table->foreignId('salary_id')->nullable()->after('workflow_status')->constrained('salaries')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->after('salary_id')->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable()->after('cancelled_by');
            $table->string('cancel_reason', 500)->nullable()->after('cancelled_at');
            $table->dateTime('reviewed_at')->nullable()->after('cancel_reason');
            $table->dateTime('payroll_included_at')->nullable()->after('reviewed_at');
            $table->decimal('manager_adjusted_hours', 5, 2)->nullable()->after('payroll_included_at');
            $table->decimal('manager_adjusted_amount', 12, 2)->nullable()->after('manager_adjusted_hours');
            $table->string('adjustment_reason', 500)->nullable()->after('manager_adjusted_amount');
            $table->foreignId('attendance_verified_by')->nullable()->after('adjustment_reason')->constrained('users')->nullOnDelete();
            $table->dateTime('attendance_verified_at')->nullable()->after('attendance_verified_by');
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('attendance_verified_at');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_in_longitude');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            $table->decimal('gps_distance_meters', 8, 2)->nullable()->after('check_out_longitude');
            $table->string('check_in_method', 24)->nullable()->after('gps_distance_meters');
            $table->string('check_out_method', 24)->nullable()->after('check_in_method');
            $table->string('check_in_photo_path')->nullable()->after('check_out_method');
            $table->string('check_out_photo_path')->nullable()->after('check_in_photo_path');
            $table->dateTime('last_action_at')->nullable()->after('check_out_photo_path');
            $table->foreignId('last_action_by')->nullable()->after('last_action_at')->constrained('users')->nullOnDelete();

            $table->index(['restaurant_id', 'workflow_status'], 'overtime_requests_workflow_status_index');
            $table->index(['salary_id', 'payroll_status'], 'overtime_requests_salary_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->dropIndex('overtime_requests_employee_date_index');
            $table->unique(['employee_id', 'scheduled_date'], 'overtime_requests_employee_date_unique');
            $table->dropIndex('overtime_requests_workflow_status_index');
            $table->dropIndex('overtime_requests_salary_status_index');
            $table->dropConstrainedForeignId('salary_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('attendance_verified_by');
            $table->dropConstrainedForeignId('last_action_by');
            $table->dropColumn([
                'workflow_status', 'cancelled_at', 'cancel_reason', 'reviewed_at',
                'payroll_included_at', 'manager_adjusted_hours', 'manager_adjusted_amount',
                'adjustment_reason', 'attendance_verified_at', 'check_in_latitude',
                'check_in_longitude', 'check_out_latitude', 'check_out_longitude',
                'gps_distance_meters', 'check_in_method', 'check_out_method',
                'check_in_photo_path', 'check_out_photo_path', 'last_action_at',
            ]);
        });

        Schema::dropIfExists('overtime_holidays');
        Schema::dropIfExists('overtime_policies');
    }
};
