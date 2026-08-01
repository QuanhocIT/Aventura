<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\InventoryTransaction;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ScheduleAssignment;
use App\Models\ShiftClosing;
use App\Models\ViolationReport;
use App\Services\SalaryService;
use Carbon\Carbon;

class SalaryRecalculationObserver
{
    protected SalaryService $salaryService;

    public function __construct(SalaryService $salaryService)
    {
        $this->salaryService = $salaryService;
    }

    public function saved($model): void
    {
        if ($model instanceof ViolationReport) {
            $employee = $model->employee;
            if ($employee) {
                $dateStr = $model->occurred_at instanceof Carbon
                    ? $model->occurred_at->toDateString()
                    : Carbon::parse($model->occurred_at)->toDateString();
                $salary = $this->salaryService->getOrCreateDraft($model->restaurant_id, $employee, $dateStr);
                $this->salaryService->sweepAdjustments($salary, $employee);
            }
        } elseif ($model instanceof ShiftClosing) {
            if ($model->cashier_user_id) {
                $employee = Employee::withoutGlobalScopes()
                    ->where('restaurant_id', $model->restaurant_id)
                    ->where('user_id', $model->cashier_user_id)
                    ->first();
                if ($employee) {
                    $dateStr = $model->closing_date instanceof Carbon
                        ? $model->closing_date->toDateString()
                        : Carbon::parse($model->closing_date)->toDateString();
                    $salary = $this->salaryService->getOrCreateDraft($model->restaurant_id, $employee, $dateStr);
                    $this->salaryService->sweepAdjustments($salary, $employee);
                }
            }
        } elseif ($model instanceof InventoryTransaction && $model->type === 'waste') {
            $activeAssignments = ScheduleAssignment::findEmployeesOnShiftAt($model->occurred_at, $model->restaurant_id);
            foreach ($activeAssignments as $assignment) {
                $employee = $assignment->employee;
                if ($employee) {
                    $dateStr = $model->occurred_at instanceof Carbon
                        ? $model->occurred_at->toDateString()
                        : Carbon::parse($model->occurred_at)->toDateString();
                    $salary = $this->salaryService->getOrCreateDraft($model->restaurant_id, $employee, $dateStr);
                    $this->salaryService->sweepAdjustments($salary, $employee);
                }
            }
        }
    }

    public function deleted($model): void
    {
        $referenceType = get_class($model);
        $referenceId = $model->id;

        $adjustments = SalaryAdjustment::withoutGlobalScopes()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->get();

        foreach ($adjustments as $adjustment) {
            $salary = Salary::withoutGlobalScopes()->find($adjustment->salary_id);
            $adjustment->delete();
            if ($salary) {
                $this->salaryService->recalculate($salary);
            }
        }
    }
}
