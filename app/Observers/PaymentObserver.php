<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\WorkShift;
use App\Services\CashPostingService;
use App\Services\FinancialPostingService;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $this->handlePayment($payment);
    }

    public function updated(Payment $payment): void
    {
        $this->handlePayment($payment);
    }

    protected function handlePayment(Payment $payment): void
    {
        if (! in_array($payment->status, ['paid', 'refunded'], true)) {
            return;
        }

        $isRefund = $payment->status === 'refunded';
        $userId = $payment->processed_by ?? auth()->id();
        $account = match (strtolower((string) $payment->payment_method)) {
            'cash' => '1111',
            'bank_transfer', 'vietqr', 'vnpay' => '1121',
            'card' => '1122',
            'ewallet', 'momo', 'zalopay' => '1123',
            default => '1121',
        };
        $orderNumber = $payment->order?->order_number ?? $payment->order_id;
        $table = $payment->order?->table;
        $areaId = $table
            && (int) $table->restaurant_id === (int) $payment->restaurant_id
            && ($table->branch_id === null || (int) $table->branch_id === (int) $payment->branch_id)
            ? $table->area_id
            : null;
        $shiftId = WorkShift::withoutGlobalScopes()
            ->where('restaurant_id', $payment->restaurant_id)
            ->where('status', 'active')
            ->where(function ($query) use ($payment): void {
                $query->where('branch_id', $payment->branch_id)->orWhereNull('branch_id');
            })
            ->orderBy('id')
            ->value('id');
        $postingKey = 'payment:'.($isRefund ? 'refund' : 'paid').':'.$payment->id;

        if ($payment->payment_method === 'cash') {
            app(CashPostingService::class)->record([
                'restaurant_id' => $payment->restaurant_id,
                'branch_id' => $payment->branch_id,
                'area_id' => $areaId,
                'shift_id' => $shiftId,
                'cashier_user_id' => $userId,
                'auto_open_if_missing' => ! $isRefund && $payment->branch_id !== null,
                'enforce_cash_balance' => $isRefund,
                'payment_id' => $payment->id,
                'type' => $isRefund ? 'out' : 'in',
                'amount' => $payment->amount,
                'source' => $isRefund ? 'refund' : 'order',
                'reference_id' => $payment->order_id,
                'reference_type' => Order::class,
                'idempotency_key' => $postingKey,
                'journal_idempotency_key' => $postingKey,
                'debit_account' => $isRefund ? '5211' : '1111',
                'credit_account' => $isRefund ? '1111' : '5111',
                'journal_source_type' => Payment::class,
                'journal_source_id' => $payment->id,
                'journal_description' => ($isRefund ? 'Hoàn tiền' : 'Thanh toán').' đơn hàng #'.$orderNumber,
                'notes' => ($isRefund ? 'Hoàn tiền' : 'Thanh toán').' đơn hàng #'.$orderNumber,
                'created_by' => $userId,
                'occurred_at' => $payment->paid_at ?? now(),
            ]);

            return;
        }

        app(FinancialPostingService::class)->post([
            'restaurant_id' => $payment->restaurant_id,
            'branch_id' => $payment->branch_id,
            'entry_date' => $payment->paid_at ?? now(),
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'idempotency_key' => $postingKey,
            'description' => ($isRefund ? 'Hoàn tiền' : 'Thanh toán').' đơn hàng #'.$orderNumber,
            'created_by' => $userId,
            'posted_by' => $userId,
            'lines' => $isRefund ? [
                ['account' => '5211', 'debit' => $payment->amount, 'credit' => 0],
                ['account' => $account, 'debit' => 0, 'credit' => $payment->amount],
            ] : [
                ['account' => $account, 'debit' => $payment->amount, 'credit' => 0],
                ['account' => '5111', 'debit' => 0, 'credit' => $payment->amount],
            ],
        ]);
    }
}
