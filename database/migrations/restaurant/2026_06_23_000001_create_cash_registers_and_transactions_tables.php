<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->date('closing_date');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('closing_balance', 12, 2)->nullable();
            $table->decimal('expected_closing_balance', 12, 2)->nullable();
            $table->decimal('expense_budget', 12, 2)->default(0);
            $table->decimal('difference', 12, 2)->nullable();
            $table->string('status', 20)->default('open'); // open, closed
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status', 'closing_date']);
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->string('type', 10); // in, out
            $table->decimal('amount', 12, 2);
            $table->string('source', 20); // order, expense, other
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['cash_register_id', 'type']);
            $table->index(['restaurant_id', 'occurred_at']);
        });

        Schema::table('shift_closings', function (Blueprint $table) {
            $table->foreignId('cash_register_id')->nullable()->after('shift_id')->constrained('cash_registers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            if (Schema::hasColumn('shift_closings', 'cash_register_id')) {
                $table->dropForeign(['cash_register_id']);
                $table->dropColumn('cash_register_id');
            }
        });

        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('cash_registers');
    }
};
