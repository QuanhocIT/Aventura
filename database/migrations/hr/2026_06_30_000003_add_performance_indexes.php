<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // schedule_assignments: hay query theo employee+date+status
        Schema::table('schedule_assignments', function (Blueprint $table) {
            if (! $this->indexExists('schedule_assignments', 'idx_sa_employee_date_status')) {
                $table->index(['employee_id', 'scheduled_date', 'status'], 'idx_sa_employee_date_status');
            }
            if (! $this->indexExists('schedule_assignments', 'idx_sa_restaurant_date_status')) {
                $table->index(['restaurant_id', 'scheduled_date', 'status'], 'idx_sa_restaurant_date_status');
            }
        });

        // overtime_requests: hay query theo employee+date+status
        if (Schema::hasTable('overtime_requests')) {
            Schema::table('overtime_requests', function (Blueprint $table) {
                if (! $this->indexExists('overtime_requests', 'idx_ot_employee_date_status')) {
                    $table->index(['employee_id', 'scheduled_date', 'status'], 'idx_ot_employee_date_status');
                }
            });
        }

        // employee_kpi_metrics: hay query theo kpi_id + metric_name
        if (Schema::hasTable('employee_kpi_metrics')) {
            Schema::table('employee_kpi_metrics', function (Blueprint $table) {
                if (! $this->indexExists('employee_kpi_metrics', 'idx_ekm_kpi_metric')) {
                    $table->index(['employee_kpi_id', 'metric_name'], 'idx_ekm_kpi_metric');
                }
            });
        }

        // employee_kpis: hay query theo employee_id + period
        if (Schema::hasTable('employee_kpis')) {
            Schema::table('employee_kpis', function (Blueprint $table) {
                if (! $this->indexExists('employee_kpis', 'idx_ek_employee_period')) {
                    $table->index(['employee_id', 'period'], 'idx_ek_employee_period');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('schedule_assignments', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_sa_employee_date_status');
            $table->dropIndexIfExists('idx_sa_restaurant_date_status');
        });

        if (Schema::hasTable('overtime_requests')) {
            Schema::table('overtime_requests', function (Blueprint $table) {
                $table->dropIndexIfExists('idx_ot_employee_date_status');
            });
        }

        if (Schema::hasTable('employee_kpi_metrics')) {
            Schema::table('employee_kpi_metrics', function (Blueprint $table) {
                $table->dropIndexIfExists('idx_ekm_kpi_metric');
            });
        }

        if (Schema::hasTable('employee_kpis')) {
            Schema::table('employee_kpis', function (Blueprint $table) {
                $table->dropIndexIfExists('idx_ek_employee_period');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        // Schema::getIndexes() works for MySQL, SQLite, Postgres etc.
        return collect(Schema::getIndexes($table))
            ->pluck('name')
            ->contains($indexName);
    }
};
