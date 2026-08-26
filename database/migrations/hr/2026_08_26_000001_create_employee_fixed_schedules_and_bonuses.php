<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_fixed_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('work_shifts')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO-8601: Monday = 1, Sunday = 7
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['employee_id', 'shift_id', 'weekday', 'effective_from'],
                'employee_fixed_schedules_unique'
            );
            $table->index(['restaurant_id', 'employee_id', 'is_active'], 'employee_fixed_schedules_lookup_index');
        });

        Schema::create('employee_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 500);
            $table->date('awarded_at');
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->timestamps();

            $table->index(['restaurant_id', 'employee_id', 'awarded_at'], 'employee_bonuses_payroll_index');
            $table->index(['restaurant_id', 'status'], 'employee_bonuses_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bonuses');
        Schema::dropIfExists('employee_fixed_schedules');
    }
};
