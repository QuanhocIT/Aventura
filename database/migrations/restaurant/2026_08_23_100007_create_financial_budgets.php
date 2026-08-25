<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_budgets')) {
            Schema::create('financial_budgets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
                $table->string('name', 180);
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('version')->default(1);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'period_start', 'period_end', 'status'], 'fb_restaurant_period_status_idx');
            });
        }

        if (! Schema::hasTable('financial_budget_lines')) {
            Schema::create('financial_budget_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('financial_budget_id')->constrained('financial_budgets')->cascadeOnDelete();
                $table->date('period_month');
                $table->string('account_code', 30);
                $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
                $table->string('cost_center', 100)->nullable();
                $table->decimal('budget_amount', 15, 2);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['financial_budget_id', 'period_month', 'account_code', 'category_id', 'cost_center'], 'financial_budget_line_unique');
                $table->index(['restaurant_id', 'period_month', 'account_code'], 'fbl_restaurant_period_code_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_budget_lines');
        Schema::dropIfExists('financial_budgets');
    }
};
