<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Services\KpiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateEmployeeKpiJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum number of attempts before giving up. */
    public int $tries = 3;

    /** Seconds before the job times out. */
    public int $timeout = 60;

    /** Seconds to wait between retry attempts. */
    public int $backoff = 30;

    protected Employee $employee;

    protected string $period;

    public function __construct(Employee $employee, string $period)
    {
        $this->employee = $employee;
        $this->period = $period;
        $this->onQueue('kpi');
    }

    /**
     * Unique lock key — prevents duplicate jobs for the same employee+period.
     */
    public function uniqueId(): string
    {
        return "kpi:{$this->employee->id}:{$this->period}";
    }

    public function handle(KpiService $kpiService): void
    {
        $kpiService->calculateKpi($this->employee, $this->period);
    }

    /**
     * Handle final failure after all retries are exhausted.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('CalculateEmployeeKpiJob failed permanently', [
            'employee_id' => $this->employee->id,
            'period' => $this->period,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
