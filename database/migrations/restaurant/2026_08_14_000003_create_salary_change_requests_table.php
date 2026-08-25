<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_change_requests')) {
            Schema::create('salary_change_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('restaurant_id');
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('employee_id');
                $table->decimal('old_base_salary', 15, 2)->default(0);
                $table->decimal('new_base_salary', 15, 2)->default(0);
                $table->decimal('old_pay_rate', 15, 2)->default(0);
                $table->decimal('new_pay_rate', 15, 2)->default(0);
                $table->string('old_compensation_type', 50)->nullable();
                $table->string('new_compensation_type', 50)->nullable();
                $table->date('effective_date')->nullable();
                $table->unsignedBigInteger('proposed_by');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->string('status', 20)->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'employee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_change_requests');
    }
};
