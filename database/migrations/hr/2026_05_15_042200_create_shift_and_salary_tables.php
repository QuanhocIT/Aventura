<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 50);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_overnight')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['restaurant_id', 'code'], 'work_shifts_restaurant_code_unique');
            $table->index('branch_id', 'work_shifts_branch_index');
        });

        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('work_shifts')->cascadeOnDelete();
            $table->date('scheduled_date');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->enum('status', ['scheduled', 'checked_in', 'completed', 'absent', 'leave_approved'])->default('scheduled');
            $table->string('notes', 500)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'shift_id', 'scheduled_date'], 'schedule_assignments_unique');
            $table->index(['restaurant_id', 'scheduled_date', 'status'], 'schedule_assignments_restaurant_date_status_index');
            $table->index('branch_id', 'schedule_assignments_branch_index');
        });

        Schema::create('shift_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('shift_id')->constrained('work_shifts')->cascadeOnDelete();
            $table->date('closing_date');
            $table->foreignId('cashier_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('expected_cash', 12, 2)->default(0);
            $table->decimal('actual_cash', 12, 2)->default(0);
            $table->decimal('cash_difference', 12, 2)->default(0);
            $table->decimal('transfer_amount', 12, 2)->default(0);
            $table->decimal('other_expense_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'submitted', 'confirmed', 'disputed'])->default('draft');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'branch_id', 'shift_id', 'closing_date'], 'shift_closings_unique');
            $table->index(['restaurant_id', 'status', 'closing_date'], 'shift_closings_status_index');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('leave_type', ['annual', 'sick', 'unpaid', 'emergency', 'resignation'])->default('annual');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['restaurant_id', 'status'], 'leave_requests_restaurant_status_index');
            $table->index('employee_id', 'leave_requests_employee_index');
        });

        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('pay_period_start');
            $table->date('pay_period_end');
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('bonus_amount', 12, 2)->default(0);
            $table->decimal('deduction_amount', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'pay_period_start', 'pay_period_end'], 'salaries_employee_period_unique');
            $table->index(['restaurant_id', 'status'], 'salaries_restaurant_status_index');
            $table->index('branch_id', 'salaries_branch_index');
        });

        Schema::create('salary_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_id')->constrained('salaries')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('type', ['bonus', 'penalty', 'cash_shortage', 'inventory_loss', 'violation']);
            $table->decimal('amount', 12, 2);
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->timestamps();

            $table->index('salary_id', 'salary_adjustments_salary_index');
            $table->index(['restaurant_id', 'type'], 'salary_adjustments_restaurant_type_index');
        });

        Schema::create('violation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('violation_type', 100);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->text('description');
            $table->decimal('penalty_amount', 12, 2)->default(0);
            $table->dateTime('occurred_at');
            $table->enum('status', ['open', 'reviewed', 'resolved', 'dismissed'])->default('open');
            $table->timestamps();

            $table->index(['restaurant_id', 'status'], 'violation_reports_restaurant_status_index');
            $table->index('employee_id', 'violation_reports_employee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_reports');
        Schema::dropIfExists('salary_adjustments');
        Schema::dropIfExists('salaries');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('shift_closings');
        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('work_shifts');
    }
};
