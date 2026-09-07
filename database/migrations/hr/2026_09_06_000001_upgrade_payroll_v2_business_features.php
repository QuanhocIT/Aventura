<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('salary_calculation_method', ['standard_days', 'fixed_package'])
                ->default('standard_days')
                ->after('compensation_type');
            $table->unsignedSmallInteger('standard_working_days')
                ->default(26)
                ->after('salary_calculation_method');
            
            // Allowances
            $table->decimal('allowance_meal', 12, 2)->default(0)->after('pay_rate');
            $table->decimal('allowance_transport', 12, 2)->default(0)->after('allowance_meal');
            $table->decimal('allowance_phone', 12, 2)->default(0)->after('allowance_transport');
            $table->decimal('allowance_responsibility', 12, 2)->default(0)->after('allowance_phone');
            $table->decimal('allowance_other', 12, 2)->default(0)->after('allowance_responsibility');

            // Bank details for payroll export
            $table->string('bank_name', 100)->nullable()->after('allowance_other');
            $table->string('bank_account_number', 50)->nullable()->after('bank_name');
            $table->string('bank_account_name', 150)->nullable()->after('bank_account_number');
        });

        Schema::table('salaries', function (Blueprint $table) {
            $table->decimal('allowance_amount', 12, 2)->default(0)->after('base_salary');
            $table->decimal('night_shift_amount', 12, 2)->default(0)->after('overtime_amount');
            $table->decimal('late_penalty_amount', 12, 2)->default(0)->after('night_shift_amount');
            $table->decimal('advance_amount', 12, 2)->default(0)->after('deduction_amount');

            // Workday tracking for pay stub clarity
            $table->decimal('actual_work_days', 5, 2)->default(0)->after('advance_amount');
            $table->unsignedSmallInteger('standard_days')->default(26)->after('actual_work_days');
            $table->decimal('paid_leave_days', 5, 2)->default(0)->after('standard_days');
            $table->decimal('unpaid_leave_days', 5, 2)->default(0)->after('paid_leave_days');

            $table->dateTime('email_sent_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropColumn([
                'allowance_amount',
                'night_shift_amount',
                'late_penalty_amount',
                'advance_amount',
                'actual_work_days',
                'standard_days',
                'paid_leave_days',
                'unpaid_leave_days',
                'email_sent_at',
            ]);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'salary_calculation_method',
                'standard_working_days',
                'allowance_meal',
                'allowance_transport',
                'allowance_phone',
                'allowance_responsibility',
                'allowance_other',
                'bank_name',
                'bank_account_number',
                'bank_account_name',
            ]);
        });
    }
};
