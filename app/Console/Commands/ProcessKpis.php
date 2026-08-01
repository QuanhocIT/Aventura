<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\KpiService;
use Illuminate\Console\Command;

class ProcessKpis extends Command
{
    protected $signature = 'kpis:calculate {--period= : The period to calculate KPIs for (format: YYYY-MM)} {--restaurant= : Specific restaurant ID}';

    protected $description = 'Automatically compute role-based draft KPI scores and metrics for active employees';

    public function handle(KpiService $kpiService)
    {
        $period = $this->option('period') ?: now()->format('Y-m');
        $restaurantId = $this->option('restaurant');

        $this->info("Starting KPI calculation for period: {$period}");

        // Build base query for active employees
        $query = Employee::withoutGlobalScopes()->where('status', 'active');

        if ($restaurantId) {
            $query->where('restaurant_id', $restaurantId);
        }

        $employees = $query->with('role')->get();

        if ($employees->isEmpty()) {
            $this->info('No active employees found to calculate KPIs.');

            return Command::SUCCESS;
        }

        $processedCount = 0;
        $skippedCount = 0;

        foreach ($employees as $employee) {
            try {
                $kpi = $kpiService->calculateKpi($employee, $period);
                if ($kpi) {
                    $processedCount++;
                } else {
                    $skippedCount++;
                }
            } catch (\Throwable $e) {
                $this->error("Failed to calculate KPI for Employee ID {$employee->id}: ".$e->getMessage());
            }
        }

        $this->info("Completed KPI processing. Calculated: {$processedCount}, Skipped/No-rule: {$skippedCount}.");

        return Command::SUCCESS;
    }
}
