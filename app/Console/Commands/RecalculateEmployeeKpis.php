<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\KpiService;
use Illuminate\Console\Command;

class RecalculateEmployeeKpis extends Command
{
    protected $signature = 'kpis:recalculate';
    protected $description = 'Recalculate KPIs for all active employees for the current month';

    public function handle(KpiService $kpiService): void
    {
        $period = now()->format('Y-m');
        $employees = Employee::withoutGlobalScopes()
            ->where('status', 'active')
            ->get();

        $this->info("Recalculating KPIs for {$employees->count()} employees for period {$period}...");

        foreach ($employees as $employee) {
            try {
                $kpiService->calculateKpi($employee, $period);
                $this->info("Calculated KPI for employee ID: {$employee->id}");
            } catch (\Exception $e) {
                $this->error("Failed to calculate KPI for employee ID {$employee->id}: {$e->getMessage()}");
            }
        }

        $this->info('KPI recalculation completed.');
    }
}
