<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeKpi;
use App\Models\EmployeeKpiMetric;
use App\Models\KpiMetric;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ScheduleAssignment;
use App\Models\CustomerFeedback;
use App\Models\ShiftClosing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiService
{
    /**
     * Resolve the KPI-applicable role of the employee.
     */
    public function getKpiRole(Employee $employee): ?string
    {
        $roleName = $employee->role?->name;
        if (in_array($roleName, ['waiter', 'kitchen', 'cashier'])) {
            return $roleName;
        }

        if ($employee->hasRole('waiter')) return 'waiter';
        if ($employee->hasRole('kitchen')) return 'kitchen';
        if ($employee->hasRole('cashier')) return 'cashier';

        $title = mb_strtolower($employee->job_title ?? '');
        if (str_contains($title, 'phục vụ') || str_contains($title, 'waiter') || str_contains($title, 'chạy bàn')) {
            return 'waiter';
        }
        if (str_contains($title, 'bếp') || str_contains($title, 'chef') || str_contains($title, 'cook') || str_contains($title, 'pha chế') || str_contains($title, 'bar')) {
            return 'kitchen';
        }
        if (str_contains($title, 'thu ngân') || str_contains($title, 'cashier') || str_contains($title, 'kế toán')) {
            return 'cashier';
        }

        return null;
    }

    /**
     * Compute metric scores and save the overall EmployeeKpi record for the period.
     */
    public function calculateKpi(Employee $employee, string $period): ?EmployeeKpi
    {
        $role = $this->getKpiRole($employee);
        if (!$role) {
            return null;
        }

        $restaurantId = $employee->restaurant_id;

        // Fetch metric configs for this restaurant and role
        $configs = KpiMetric::where('restaurant_id', $restaurantId)
            ->where('role', $role)
            ->get();

        if ($configs->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($employee, $period, $configs, $restaurantId, $role) {
            // Find or create parent KPI
            $kpi = EmployeeKpi::withoutGlobalScopes()->where('employee_id', $employee->id)
                ->where('period', $period)
                ->first();

            if ($kpi && $kpi->status === 'finalized') {
                return $kpi; // Do not recalculate if already finalized
            }

            if (!$kpi) {
                $kpi = EmployeeKpi::create([
                    'restaurant_id' => $restaurantId,
                    'employee_id' => $employee->id,
                    'period' => $period,
                    'status' => 'draft',
                ]);
            }

            // Delete old details
            EmployeeKpiMetric::where('employee_kpi_id', $kpi->id)->delete();

            $startOfMonth = Carbon::parse($period . '-01')->startOfMonth();
            $endOfMonth = Carbon::parse($period . '-01')->endOfMonth();
            $startStr = $startOfMonth->toDateString();
            $endStr = $endOfMonth->toDateString();

            $totalScore = 0.0;
            $totalBonus = 0.0;
            $totalCommission = 0.0;

            foreach ($configs as $config) {
                $actual = $this->calculateMetricValue($employee, $config->metric_code, $startOfMonth, $endOfMonth, $config->target);
                
                $score = $this->calculateScore($config->target_type, $actual, (float) $config->target);
                
                $isAchieved = false;
                if ($config->target_type === 'more_is_better') {
                    $isAchieved = $actual >= (float) $config->target;
                } else {
                    $isAchieved = $actual <= (float) $config->target;
                }

                $bonusEarned = $isAchieved ? (float) $config->bonus_amount : 0.0;
                
                // Commission is usually based on handled revenue volume for Cashiers/Waiters
                $commissionEarned = 0.0;
                if ($config->commission_rate > 0 && str_contains($config->metric_code, 'revenue')) {
                    $commissionEarned = round(($actual * (float)$config->commission_rate) / 100, 2);
                }

                EmployeeKpiMetric::create([
                    'restaurant_id' => $restaurantId,
                    'employee_kpi_id' => $kpi->id,
                    'kpi_metric_id' => $config->id,
                    'metric_code' => $config->metric_code,
                    'metric_name' => $config->metric_name,
                    'actual_value' => $actual,
                    'target_value' => $config->target,
                    'score' => $score,
                    'is_achieved' => $isAchieved,
                    'bonus_earned' => $bonusEarned,
                    'commission_earned' => $commissionEarned,
                ]);

                $totalScore += ($score * (float) $config->weight) / 100.0;
                $totalBonus += $bonusEarned;
                $totalCommission += $commissionEarned;
            }

            $kpi->update([
                'total_score' => round($totalScore, 2),
                'total_bonus' => $totalBonus,
                'total_commission' => $totalCommission,
            ]);

            return $kpi;
        });
    }

    /**
     * Core metric calculation lookup.
     */
    protected function calculateMetricValue(Employee $employee, string $code, Carbon $start, Carbon $end, float $target): float
    {
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();
        $startDt = $start->copy()->startOfDay();
        $endDt = $end->copy()->endOfDay();

        switch ($code) {
            // Waiter Metrics
            case 'waiter_orders_served':
                $shifts = ScheduleAssignment::where('employee_id', $employee->id)
                    ->whereBetween('scheduled_date', [$startStr, $endStr])
                    ->where('status', 'completed')
                    ->count();

                $orders = Order::where('restaurant_id', $employee->restaurant_id)
                    ->whereBetween('created_at', [$startDt, $endDt])
                    ->where('created_by', $employee->user_id)
                    ->count();

                return $shifts > 0 ? round($orders / $shifts, 2) : (float) $orders;

            case 'waiter_customer_rating':
                $orderIds = Order::where('restaurant_id', $employee->restaurant_id)
                    ->whereBetween('created_at', [$startDt, $endDt])
                    ->where('created_by', $employee->user_id)
                    ->pluck('id');

                if ($orderIds->isEmpty()) {
                    return 5.0; // fallback to 5.0 so they don't get penalized
                }

                $rating = CustomerFeedback::whereIn('order_id', $orderIds)->avg('rating');
                return $rating ? round($rating, 2) : 5.0;

            case 'waiter_service_speed':
                $orderIds = Order::where('restaurant_id', $employee->restaurant_id)
                    ->whereBetween('created_at', [$startDt, $endDt])
                    ->where('created_by', $employee->user_id)
                    ->pluck('id');

                $items = OrderItem::whereIn('order_id', $orderIds)
                    ->whereNotNull('prepared_at')
                    ->whereNotNull('served_at')
                    ->get();

                if ($items->isEmpty()) {
                    return $target; // fallback to target so they get exactly 100 points
                }

                $totalMinutes = 0;
                foreach ($items as $item) {
                    $totalMinutes += abs(Carbon::parse($item->served_at)->diffInMinutes(Carbon::parse($item->prepared_at)));
                }

                return round($totalMinutes / $items->count(), 2);

            // Chef / Kitchen Metrics
            case 'chef_prep_time':
                $shifts = ScheduleAssignment::where('employee_id', $employee->id)
                    ->whereBetween('scheduled_date', [$startStr, $endStr])
                    ->where('status', 'completed')
                    ->whereNotNull('check_in_at')
                    ->whereNotNull('check_out_at')
                    ->get();

                if ($shifts->isEmpty()) {
                    return $target;
                }

                $minCheckIn = $shifts->min('check_in_at');
                $maxCheckOut = $shifts->max('check_out_at');

                if (!$minCheckIn || !$maxCheckOut) {
                    return $target;
                }

                $items = OrderItem::where('restaurant_id', $employee->restaurant_id)
                    ->where('status', '!=', 'cancelled')
                    ->whereNotNull('sent_to_kitchen_at')
                    ->whereNotNull('prepared_at')
                    ->whereBetween('prepared_at', [$minCheckIn, $maxCheckOut])
                    ->with('order')
                    ->get();

                $filteredItems = $items->filter(function ($item) use ($shifts) {
                    foreach ($shifts as $shift) {
                        if ($item->prepared_at >= $shift->check_in_at && 
                            $item->prepared_at <= $shift->check_out_at && 
                            $item->order && 
                            $item->order->branch_id === $shift->branch_id) {
                            return true;
                        }
                    }
                    return false;
                });

                $totalMinutes = 0;
                $count = $filteredItems->count();

                foreach ($filteredItems as $item) {
                    $totalMinutes += abs(Carbon::parse($item->prepared_at)->diffInMinutes(Carbon::parse($item->sent_to_kitchen_at)));
                }

                return $count > 0 ? round($totalMinutes / $count, 2) : $target;

            case 'chef_rejection_rate':
                $shifts = ScheduleAssignment::where('employee_id', $employee->id)
                    ->whereBetween('scheduled_date', [$startStr, $endStr])
                    ->where('status', 'completed')
                    ->whereNotNull('check_in_at')
                    ->whereNotNull('check_out_at')
                    ->get();

                if ($shifts->isEmpty()) {
                    return 0.0;
                }

                $minCheckIn = $shifts->min('check_in_at');
                $maxCheckOut = $shifts->max('check_out_at');

                if (!$minCheckIn || !$maxCheckOut) {
                    return 0.0;
                }

                $items = OrderItem::where('restaurant_id', $employee->restaurant_id)
                    ->whereBetween('created_at', [$minCheckIn, $maxCheckOut])
                    ->with('order')
                    ->get();

                $filteredItems = $items->filter(function ($item) use ($shifts) {
                    foreach ($shifts as $shift) {
                        if ($item->created_at >= $shift->check_in_at && 
                            $item->created_at <= $shift->check_out_at && 
                            $item->order && 
                            $item->order->branch_id === $shift->branch_id) {
                            return true;
                        }
                    }
                    return false;
                });

                $totalCount = $filteredItems->count();
                $cancelledCount = $filteredItems->where('status', 'cancelled')->count();

                return $totalCount > 0 ? round(($cancelledCount / $totalCount) * 100, 2) : 0.0;

            case 'chef_food_rating':
                $shifts = ScheduleAssignment::where('employee_id', $employee->id)
                    ->whereBetween('scheduled_date', [$startStr, $endStr])
                    ->where('status', 'completed')
                    ->whereNotNull('check_in_at')
                    ->whereNotNull('check_out_at')
                    ->get();

                if ($shifts->isEmpty()) {
                    return 5.0;
                }

                $minCheckIn = $shifts->min('check_in_at');
                $maxCheckOut = $shifts->max('check_out_at');

                if (!$minCheckIn || !$maxCheckOut) {
                    return 5.0;
                }

                $items = OrderItem::where('restaurant_id', $employee->restaurant_id)
                    ->whereNotNull('prepared_at')
                    ->whereBetween('prepared_at', [$minCheckIn, $maxCheckOut])
                    ->with('order')
                    ->get();

                $filteredItems = $items->filter(function ($item) use ($shifts) {
                    foreach ($shifts as $shift) {
                        if ($item->prepared_at >= $shift->check_in_at && 
                            $item->prepared_at <= $shift->check_out_at && 
                            $item->order && 
                            $item->order->branch_id === $shift->branch_id) {
                            return true;
                        }
                    }
                    return false;
                });

                $orderIds = $filteredItems->pluck('order_id')->unique();

                if ($orderIds->isEmpty()) {
                    return 5.0;
                }

                $rating = CustomerFeedback::whereIn('order_id', $orderIds)->avg('rating');
                return $rating ? round($rating, 2) : 5.0;

            // Cashier Metrics
            case 'cashier_processed_revenue':
                $revenue = Payment::where('processed_by', $employee->user_id)
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [$startDt, $endDt])
                    ->sum('amount');

                return (float) $revenue;

            case 'cashier_error_rate':
                $closings = ShiftClosing::where('cashier_user_id', $employee->user_id)
                    ->whereBetween('closing_date', [$startStr, $endStr])
                    ->get();

                if ($closings->isEmpty()) {
                    return 0.0;
                }

                $errorCount = $closings->where('cash_difference', '!=', 0)->count();
                return round(($errorCount / $closings->count()) * 100, 2);

            case 'cashier_checkout_speed':
                $payments = Payment::where('processed_by', $employee->user_id)
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$startDt, $endDt])
                    ->with('order')
                    ->get();

                if ($payments->isEmpty()) {
                    return $target;
                }

                $totalMinutes = 0;
                $count = 0;
                foreach ($payments as $payment) {
                    if ($payment->order) {
                        // checkout speed is difference between order creation and payment confirmation
                        $totalMinutes += abs(Carbon::parse($payment->created_at)->diffInMinutes(Carbon::parse($payment->order->created_at)));
                        $count++;
                    }
                }

                return $count > 0 ? round($totalMinutes / $count, 2) : $target;

            default:
                return 0.0;
        }
    }

    /**
     * Compute metric score normalized to 100.
     */
    protected function calculateScore(string $targetType, float $actual, float $target): float
    {
        if ($targetType === 'more_is_better') {
            if ($target <= 0) return 100.0;
            $score = ($actual / $target) * 100.0;
            return min(120.0, max(0.0, round($score, 2)));
        } else { // less_is_better
            if ($actual <= $target) return 100.0;
            if ($target <= 0) return 0.0;
            $score = 100.0 - (($actual - $target) / $target) * 100.0;
            return min(100.0, max(0.0, round($score, 2)));
        }
    }
}
