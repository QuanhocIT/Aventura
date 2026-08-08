<?php

namespace App\Console\Commands;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\ScheduleAssignment;
use App\Models\ShiftClosing;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCloseExpiredShifts extends Command
{
    protected $signature = 'shifts:auto-close-expired';

    protected $description = 'Automatically close open/active shifts for today if not already closed by cashiers';

    public function handle()
    {
        $this->info('Starting auto shift close...');
        $todayStr = Carbon::today()->toDateString();
        $closingDate = Carbon::today();

        // Fetch active shifts across all restaurants
        $shifts = WorkShift::withoutGlobalScopes()->where('status', 'active')->get();
        $closedCount = 0;

        foreach ($shifts as $shift) {
            try {
                $branchId = $shift->branch_id;

                // Check if shift is already closed for today
                $exists = ShiftClosing::withoutGlobalScopes()
                    ->where('restaurant_id', $shift->restaurant_id)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->where('shift_id', $shift->id)
                    ->whereDate('closing_date', $todayStr)
                    ->exists();

                if ($exists) {
                    $this->info("Shift {$shift->name} (ID: {$shift->id}) already closed for today. Skipping.");

                    continue;
                }

                $this->info("Closing shift {$shift->name} (ID: {$shift->id}) for restaurant ID {$shift->restaurant_id}");

                // 1. Calculate time range
                $startDt = Carbon::parse($todayStr.' '.$shift->start_time);
                $endDt = $shift->is_overnight
                    ? Carbon::parse(Carbon::today()->addDay()->toDateString().' '.$shift->end_time)
                    : Carbon::parse($todayStr.' '.$shift->end_time);

                // 2. Calculate revenue (same logic as ShiftClosingController::calculateShiftRevenue)
                $orderIds = Order::withoutGlobalScopes()
                    ->where('restaurant_id', $shift->restaurant_id)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$startDt, $endDt])
                    ->pluck('id');

                $payments = Payment::withoutGlobalScopes()
                    ->where('restaurant_id', $shift->restaurant_id)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [$startDt, $endDt])
                    ->whereIn('order_id', $orderIds->all())
                    ->get(['payment_method', 'amount']);

                $splitPenaltyTotal = Order::withoutGlobalScopes()
                    ->where('restaurant_id', $shift->restaurant_id)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->where('is_split', true)
                    ->where('is_override_split_penalty', false)
                    ->where(function ($q) use ($startDt, $endDt) {
                        $q->where(fn ($q2) => $q2->where('status', 'completed')->whereBetween('completed_at', [$startDt, $endDt]))
                            ->orWhere(fn ($q2) => $q2->where('payment_status', 'unpaid')->whereBetween('created_at', [$startDt, $endDt]));
                    })
                    ->sum('total_amount');

                $expectedCash = (float) $payments->where('payment_method', 'cash')->sum('amount');
                $expectedCash = max(0.0, $expectedCash - $splitPenaltyTotal);

                $transferAmount = (float) $payments->whereIn('payment_method', ['bank_transfer', 'card', 'ewallet', 'mixed'])->sum('amount');

                // 3. Map cashier: scheduled employee check-in or owner
                $cashierUserId = null;
                $assignment = ScheduleAssignment::withoutGlobalScopes()
                    ->where('restaurant_id', $shift->restaurant_id)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->where('shift_id', $shift->id)
                    ->whereDate('scheduled_date', $todayStr)
                    ->first();

                if ($assignment) {
                    $employee = Employee::withoutGlobalScopes()
                        ->where('restaurant_id', $shift->restaurant_id)
                        ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                        ->find($assignment->employee_id);
                    if ($employee && $employee->user_id) {
                        $cashierUserId = $employee->user_id;
                    }
                }

                if (! $cashierUserId) {
                    $restaurant = Restaurant::find($shift->restaurant_id);
                    if ($restaurant) {
                        $cashierUserId = $restaurant->owner_user_id;
                    }
                }

                // 4. Create ShiftClosing record
                $closing = ShiftClosing::create([
                    'restaurant_id' => $shift->restaurant_id,
                    'branch_id' => $shift->branch_id,
                    'shift_id' => $shift->id,
                    'closing_date' => $todayStr,
                    'period_start_at' => $startDt,
                    'cashier_user_id' => $cashierUserId,
                    'area_name' => 'Toàn bộ khu vực',
                    'total_order_count' => $orderIds->count(),
                    'order_count' => $orderIds->count(),
                    'cash_sales_amount' => $expectedCash,
                    'expected_cash' => $expectedCash,
                    'actual_cash' => $expectedCash, // Prevents cash shortage adjustment
                    'cash_difference' => 0.0,
                    'transfer_amount' => $transferAmount,
                    'actual_transfer_amount' => $transferAmount,
                    'transfer_difference' => 0.0,
                    'gross_revenue_amount' => (float) $payments->sum('amount'),
                    'net_revenue_amount' => (float) $payments->sum('amount'),
                    'total_difference' => 0.0,
                    'responsibility_amount' => 0.0,
                    'other_expense_amount' => 0.0,
                    'notes' => 'Hệ thống tự động chốt ca cuối ngày.',
                    'status' => 'confirmed',
                    'closed_at' => Carbon::now(),
                ]);

                // 5. Create Audit Log
                AuditLog::create([
                    'restaurant_id' => $shift->restaurant_id,
                    'event' => 'created',
                    'action' => 'auto_shift_close',
                    'subject_type' => ShiftClosing::class,
                    'subject_id' => $closing->id,
                    'new_values' => [
                        'status' => 'confirmed',
                        'expected_cash' => $expectedCash,
                    ],
                ]);

                $closedCount++;
            } catch (\Throwable $e) {
                Log::error("Error auto-closing shift ID {$shift->id}: ".$e->getMessage());
                $this->error("Error auto-closing shift ID {$shift->id}: ".$e->getMessage());
            }
        }

        // Clean up unapproved shift_checkin requests and mark past unapproved shifts as absent (coi như nghỉ)
        ApprovalRequest::withoutGlobalScopes()
            ->where('operation_type', 'shift_checkin')
            ->where('status', 'pending')
            ->whereDate('created_at', '<', $todayStr)
            ->update([
                'status' => 'rejected',
                'rejection_reason' => 'Yêu cầu vào ca đã hết hạn do Chủ doanh nghiệp không xác nhận trong ngày (Coi như nghỉ).',
            ]);

        ScheduleAssignment::withoutGlobalScopes()
            ->whereIn('status', ['scheduled', 'pending_checkin'])
            ->whereDate('scheduled_date', '<', $todayStr)
            ->update(['status' => 'absent']);

        $this->info("Completed auto-closing {$closedCount} shifts.");

        return Command::SUCCESS;
    }
}
