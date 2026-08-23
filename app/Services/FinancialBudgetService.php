<?php

namespace App\Services;

use App\Models\FinancialBudget;
use App\Models\FinancialBudgetLine;
use App\Models\OperatingExpense;
use App\Models\Salary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FinancialBudgetService
{
    public function actualForLine(FinancialBudgetLine $line): float
    {
        $month = CarbonImmutable::parse($line->period_month);
        $start = $month->startOfMonth()->toDateString();
        $end = $month->endOfMonth()->toDateString();
        $branchId = $line->budget?->branch_id;

        return match ($line->account_code) {
            '6221' => (float) Salary::withoutGlobalScopes()
                ->where('restaurant_id', $line->restaurant_id)
                ->whereIn('status', ['approved', 'paid'])
                ->whereBetween('pay_period_end', [$start, $end])
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('net_salary'),
            '6211' => (float) DB::table('inventory_transactions')
                ->where('restaurant_id', $line->restaurant_id)
                ->where('type', 'usage')
                ->where('direction', 'out')
                ->whereBetween('occurred_at', [$month->startOfMonth(), $month->endOfMonth()])
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('total_cost'),
            default => (float) OperatingExpense::withoutGlobalScopes()
                ->where('restaurant_id', $line->restaurant_id)
                ->whereIn('status', ['approved', 'paid'])
                ->whereBetween('expense_date', [$start, $end])
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->when($line->category_id !== null, fn ($query) => $query->where('category_id', $line->category_id))
                ->sum('amount'),
        };
    }

    public function serialize(FinancialBudget $budget): array
    {
        $budget->loadMissing(['branch:id,name', 'lines.category:id,name']);

        return [
            'id' => $budget->id,
            'name' => $budget->name,
            'branch_id' => $budget->branch_id,
            'branch_name' => $budget->branch?->name,
            'period_start' => $budget->period_start->format('Y-m-d'),
            'period_end' => $budget->period_end->format('Y-m-d'),
            'status' => $budget->status,
            'total_amount' => (float) $budget->total_amount,
            'lines' => $budget->lines->map(function (FinancialBudgetLine $line): array {
                $actual = $this->actualForLine($line);

                return [
                    'id' => $line->id,
                    'period_month' => $line->period_month->format('Y-m'),
                    'account_code' => $line->account_code,
                    'category_name' => $line->category?->name,
                    'budget_amount' => (float) $line->budget_amount,
                    'actual_amount' => $actual,
                    'variance_amount' => round((float) $line->budget_amount - $actual, 2),
                ];
            })->values()->all(),
        ];
    }
}
