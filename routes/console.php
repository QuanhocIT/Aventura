<?php

use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Models\Salary;
use App\Services\BillingService;
use App\Services\GoalTrackingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

Artisan::command('billing:sync-statuses', function (BillingService $billing) {
    $billing->markExpiredAndSuspended();
    $this->info('Billing statuses synced.');
})->purpose('Sync expired and suspended tenant statuses');

Artisan::command('billing:send-reminders', function (BillingService $billing) {
    $sent = $billing->sendExpiryReminders();
    $this->info("Billing reminders queued: {$sent}");
})->purpose('Queue reminders for subscriptions nearing expiration');

// Lịch chạy định kỳ (Task Scheduler) đã chuyển sang routes/schedule.php, đăng ký
// qua bootstrap/app.php ->withSchedule(). File này chỉ còn giữ định nghĩa lệnh
// Artisan::command() — xem routes/schedule.php để biết lịch chạy của từng lệnh.

Artisan::command('system:audit-consistency', function () {
    $this->info('Starting consistency audit...');

    // 1. Kiểm tra đơn hàng vs thanh toán
    $inconsistentOrders = [];
    $rows = DB::table('orders as o')
        ->leftJoin('payments as p', function ($join) {
            $join->on('p.order_id', '=', 'o.id')
                ->where('p.status', '=', 'paid');
        })
        ->where('o.status', 'completed')
        ->where('o.payment_status', 'paid')
        ->select(
            'o.id',
            'o.order_number',
            'o.total_amount',
            'o.refund_amount',
            DB::raw('COALESCE(SUM(p.amount), 0) as payments_total')
        )
        ->groupBy('o.id', 'o.order_number', 'o.total_amount', 'o.refund_amount')
        ->get();

    foreach ($rows as $row) {
        $expected = max(0.0, (float) $row->total_amount - (float) ($row->refund_amount ?? 0));
        $paymentsTotal = (float) $row->payments_total;

        if (abs($paymentsTotal - $expected) > 0.01) {
            $inconsistentOrders[] = [
                'order_id' => $row->id,
                'order_number' => $row->order_number,
                'expected' => $expected,
                'actual' => $paymentsTotal,
            ];
        }
    }

    // 2. Kiểm tra bảng lương vs adjustments
    $inconsistentSalaries = [];
    Salary::withoutGlobalScopes()
        ->with(['adjustments', 'employee'])
        ->chunkById(100, function ($salaries) use (&$inconsistentSalaries) {
            foreach ($salaries as $salary) {
                $adjustments = $salary->adjustments;

                $expectedBonuses = (float) $adjustments->where('type', 'bonus')->sum('amount');
                $expectedDeductions = (float) $adjustments
                    ->whereIn('type', ['penalty', 'cash_shortage', 'inventory_loss', 'violation', 'advance'])
                    ->where('status', 'applied')
                    ->sum('amount');

                $expectedNet = max(0.0, (float) $salary->base_salary + $expectedBonuses - $expectedDeductions);

                $bonusDiff = abs((float) $salary->bonus_amount - $expectedBonuses) > 0.01;
                $deductionDiff = abs((float) $salary->deduction_amount - $expectedDeductions) > 0.01;
                $netDiff = abs((float) $salary->net_salary - $expectedNet) > 0.01;

                if ($bonusDiff || $deductionDiff || $netDiff) {
                    $inconsistentSalaries[] = [
                        'salary_id' => $salary->id,
                        'employee_name' => $salary->employee?->full_name ?? 'N/A',
                        'salary_bonus' => $salary->bonus_amount,
                        'expected_bonus' => $expectedBonuses,
                        'salary_deduction' => $salary->deduction_amount,
                        'expected_deduction' => $expectedDeductions,
                        'salary_net' => $salary->net_salary,
                        'expected_net' => $expectedNet,
                    ];
                }
            }
        });

    // Báo cáo kết quả
    if (! empty($inconsistentOrders) || ! empty($inconsistentSalaries)) {
        $errorMsg = "Phát hiện bất đồng nhất dữ liệu hệ thống: \n";
        if (! empty($inconsistentOrders)) {
            $errorMsg .= '- Đơn hàng bất nhất: '.json_encode($inconsistentOrders, JSON_UNESCAPED_UNICODE)."\n";
        }
        if (! empty($inconsistentSalaries)) {
            $errorMsg .= '- Lương bất nhất: '.json_encode($inconsistentSalaries, JSON_UNESCAPED_UNICODE)."\n";
        }

        Log::error($errorMsg);

        // Ghi nhận vào AuditLog hệ thống để báo cáo
        AuditLog::create([
            'restaurant_id' => null, // Hệ thống
            'event' => 'audit_failed',
            'action' => 'system_audit_inconsistency',
            'new_values' => [
                'orders' => $inconsistentOrders,
                'salaries' => $inconsistentSalaries,
            ],
            'notes' => 'Phát hiện bất đồng nhất dữ liệu trong quá trình kiểm toán định kỳ.',
        ]);

        $this->error('Consistency audit completed with errors.');
    } else {
        $this->info('Consistency audit completed successfully. All data is consistent.');
    }
})->purpose('Audit data consistency between orders/payments and salaries/adjustments');

Artisan::command('goals:sync', function () {
    $this->info('Starting business goals sync...');

    $tracker = app(GoalTrackingService::class);
    $totalSynced = 0;

    Restaurant::where('status', 'active')->chunkById(50, function ($restaurants) use ($tracker, &$totalSynced) {
        foreach ($restaurants as $restaurant) {
            $count = $tracker->syncAllActive($restaurant->id);
            $totalSynced += $count;
        }
    });

    $this->info("Completed business goals sync. Synced {$totalSynced} active goals across all restaurants.");
})->purpose('Synchronize all active business goals/OKRs progress');
