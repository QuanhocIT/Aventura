<?php

use App\Services\BillingService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

Artisan::command('billing:sync-statuses', function (BillingService $billing) {
    $billing->markExpiredAndSuspended();
    $this->info('Billing statuses synced.');
})->purpose('Sync expired and suspended tenant statuses');

Artisan::command('billing:send-reminders', function (BillingService $billing) {
    $sent = $billing->sendExpiryReminders();
    $this->info("Billing reminders queued: {$sent}");
})->purpose('Queue reminders for subscriptions nearing expiration');

// Scheduling
app(Schedule::class)->command('billing:send-reminders')->dailyAt('08:00');
app(Schedule::class)->command('billing:sync-statuses')->hourly();
app(Schedule::class)->command('reports:generate-daily')->dailyAt('23:59');
app(Schedule::class)->command('dashboard:send-scheduled-reports')->dailyAt('07:30');
app(Schedule::class)->command('news:publish-scheduled')->everyFiveMinutes();
app(Schedule::class)->command('expenses:process-recurring')->dailyAt('00:05');
app(Schedule::class)->command('debts:send-reminders')->dailyAt('08:30');
app(Schedule::class)->command('kpis:calculate')->dailyAt('01:00');

app(Schedule::class)->call(function () {
    app(\App\Services\SupportPortalService::class)->evaluateAlerts();
})->everyFiveMinutes();

app(Schedule::class)->command('services:check-health')->everyFiveMinutes();
app(Schedule::class)->command('system:check-maintenance')->everyMinute();

app(Schedule::class)->command('db:backup')->dailyAt('02:00');
app(Schedule::class)->command('db:optimize')->weeklyOn(0, '03:00');
app(Schedule::class)->command('db:manage-partitions')->monthlyOn(1, '03:15');
app(Schedule::class)->command('orders:archive-old')->monthlyOn(1, '03:30');

app(Schedule::class)->command('loyalty:expire-points')->dailyAt('00:30');
app(Schedule::class)->command('loyalty:birthday-bonuses')->dailyAt('09:00');
app(Schedule::class)->command('inventory:check-expiry')->dailyAt('07:00');
app(Schedule::class)->command('checklist:send-reminders')->dailyAt('10:00');
app(Schedule::class)->command('checklist:send-reminders')->dailyAt('16:00');

Artisan::command('system:audit-consistency', function () {
    $this->info('Starting consistency audit...');

    // 1. Kiểm tra đơn hàng vs thanh toán
    $inconsistentOrders = [];
    $orders = \App\Models\Order::withoutGlobalScopes()
        ->where('status', 'completed')
        ->where('payment_status', 'paid')
        ->get();

    foreach ($orders as $order) {
        $paymentsTotal = (float) \App\Models\Payment::withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->where('status', 'paid')
            ->sum('amount');
        
        $expected = (float) $order->total_amount;
        $refunded = (float) ($order->refund_amount ?? 0);
        $expected = max(0.0, $expected - $refunded);

        if (abs($paymentsTotal - $expected) > 0.01) {
            $inconsistentOrders[] = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'expected' => $expected,
                'actual' => $paymentsTotal,
            ];
        }
    }

    // 2. Kiểm tra bảng lương vs adjustments
    $inconsistentSalaries = [];
    $salaries = \App\Models\Salary::withoutGlobalScopes()->get();
    foreach ($salaries as $salary) {
        $adjustments = \App\Models\SalaryAdjustment::withoutGlobalScopes()
            ->where('salary_id', $salary->id)
            ->get();

        $expectedBonuses = (float) $adjustments->where('type', 'bonus')->sum('amount');
        $expectedDeductions = (float) $adjustments
            ->whereIn('type', ['penalty', 'cash_shortage', 'inventory_loss', 'violation', 'advance'])
            ->where('status', 'applied')
            ->sum('amount');

        $expectedNet = max(0.0, (float) $salary->base_salary + $expectedBonuses - $expectedDeductions);

        $bonusDiff = abs((float)$salary->bonus_amount - $expectedBonuses) > 0.01;
        $deductionDiff = abs((float)$salary->deduction_amount - $expectedDeductions) > 0.01;
        $netDiff = abs((float)$salary->net_salary - $expectedNet) > 0.01;

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

    // Báo cáo kết quả
    if (!empty($inconsistentOrders) || !empty($inconsistentSalaries)) {
        $errorMsg = "Phát hiện bất đồng nhất dữ liệu hệ thống: \n";
        if (!empty($inconsistentOrders)) {
            $errorMsg .= "- Đơn hàng bất nhất: " . json_encode($inconsistentOrders, JSON_UNESCAPED_UNICODE) . "\n";
        }
        if (!empty($inconsistentSalaries)) {
            $errorMsg .= "- Lương bất nhất: " . json_encode($inconsistentSalaries, JSON_UNESCAPED_UNICODE) . "\n";
        }

        \Illuminate\Support\Facades\Log::error($errorMsg);

        // Ghi nhận vào AuditLog hệ thống để báo cáo
        \App\Models\AuditLog::create([
            'restaurant_id' => null, // Hệ thống
            'event'         => 'audit_failed',
            'action'        => 'system_audit_inconsistency',
            'new_values'    => [
                'orders' => $inconsistentOrders,
                'salaries' => $inconsistentSalaries,
            ],
            'notes'         => 'Phát hiện bất đồng nhất dữ liệu trong quá trình kiểm toán định kỳ.',
        ]);

        $this->error('Consistency audit completed with errors.');
    } else {
        $this->info('Consistency audit completed successfully. All data is consistent.');
    }
})->purpose('Audit data consistency between orders/payments and salaries/adjustments');

app(Schedule::class)->command('system:audit-consistency')->dailyAt('03:00');

