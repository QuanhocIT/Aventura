<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. kpi_metrics table (KPI configuration rules per restaurant)
        if (! Schema::hasTable('kpi_metrics')) {
            Schema::create('kpi_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->string('role', 50); // waiter, kitchen, cashier
                $table->string('metric_code', 100); // e.g., waiter_orders_served
                $table->string('metric_name', 255);
                $table->decimal('weight', 5, 2)->default(0); // weight percent, e.g. 40.00
                $table->decimal('target', 12, 2)->default(0);
                $table->string('target_type', 50)->default('more_is_better'); // more_is_better, less_is_better
                $table->decimal('bonus_amount', 12, 2)->default(0); // flat cash bonus on target achievement
                $table->decimal('commission_rate', 5, 2)->default(0); // % commission on processed volume (if applicable)
                $table->timestamps();

                $table->unique(['restaurant_id', 'role', 'metric_code'], 'kpi_metrics_restaurant_role_code_unique');
                $table->index(['restaurant_id', 'role']);
            });
        }

        // 2. employee_kpis table (Monthly overall KPI score of employee)
        if (! Schema::hasTable('employee_kpis')) {
            Schema::create('employee_kpis', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('period', 7); // YYYY-MM
                $table->decimal('total_score', 5, 2)->default(0); // overall weighted score (0 - 100 or up to 120)
                $table->decimal('total_bonus', 12, 2)->default(0);
                $table->decimal('total_commission', 12, 2)->default(0);
                $table->string('status', 20)->default('draft'); // draft, finalized
                $table->dateTime('finalized_at')->nullable();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'period'], 'employee_kpis_employee_period_unique');
                $table->index(['restaurant_id', 'period']);
            });
        }

        // 3. employee_kpi_metrics table (Detailed calculated metric values)
        if (! Schema::hasTable('employee_kpi_metrics')) {
            Schema::create('employee_kpi_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('employee_kpi_id')->constrained('employee_kpis')->cascadeOnDelete();
                $table->foreignId('kpi_metric_id')->nullable()->constrained('kpi_metrics')->nullOnDelete();
                $table->string('metric_code', 100);
                $table->string('metric_name', 255);
                $table->decimal('actual_value', 12, 2)->default(0);
                $table->decimal('target_value', 12, 2)->default(0);
                $table->decimal('score', 5, 2)->default(0); // 0 - 120 scale
                $table->boolean('is_achieved')->default(false);
                $table->decimal('bonus_earned', 12, 2)->default(0);
                $table->decimal('commission_earned', 12, 2)->default(0);
                $table->timestamps();

                $table->index(['employee_kpi_id']);
            });
        }

        // 4. performance_reviews table (360-degree reviews)
        if (! Schema::hasTable('performance_reviews')) {
            Schema::create('performance_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
                $table->string('reviewer_type', 20)->default('peer'); // self, manager, peer
                $table->string('period', 7); // YYYY-MM
                $table->json('ratings'); // ratings for various criteria: {"communication": 4, "teamwork": 5,...}
                $table->decimal('average_score', 3, 2)->default(0); // 1.00 - 5.00
                $table->text('comments')->nullable();
                $table->string('status', 20)->default('draft'); // draft, submitted
                $table->timestamps();

                $table->index(['restaurant_id', 'employee_id', 'period'], 'perf_reviews_restaurant_emp_period_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('employee_kpi_metrics');
        Schema::dropIfExists('employee_kpis');
        Schema::dropIfExists('kpi_metrics');
    }
};
