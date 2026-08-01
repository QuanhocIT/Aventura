<?php

namespace App\Observers;

use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\Order;
use App\Models\Payment;

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
        // Only proceed if payment is paid, method is cash, and has not been logged as a cash transaction yet
        if ($payment->status !== 'paid' || $payment->payment_method !== 'cash') {
            return;
        }

        // Avoid duplicate logging by checking if cash transaction for this order reference already exists
        $alreadyLogged = CashTransaction::where('reference_id', $payment->order_id)
            ->where('reference_type', Order::class)
            ->exists();

        if ($alreadyLogged) {
            return;
        }

        // Find the active open cash register for the branch/restaurant
        $register = CashRegister::where('restaurant_id', $payment->restaurant_id)
            ->where('branch_id', $payment->branch_id)
            ->where('status', 'open')
            ->first();

        if ($register) {
            $userId = $payment->processed_by ?? auth()->id();

            // Load order if not loaded to get order number
            $orderNumber = $payment->order_id;
            if ($payment->order) {
                $orderNumber = $payment->order->order_number;
            } else {
                $order = Order::find($payment->order_id);
                if ($order) {
                    $orderNumber = $order->order_number;
                }
            }

            CashTransaction::create([
                'restaurant_id' => $payment->restaurant_id,
                'branch_id' => $payment->branch_id,
                'cash_register_id' => $register->id,
                'type' => 'in',
                'amount' => $payment->amount,
                'source' => 'order',
                'reference_id' => $payment->order_id,
                'reference_type' => Order::class,
                'notes' => "Thanh toán đơn hàng #{$orderNumber}",
                'created_by' => $userId,
                'occurred_at' => $payment->paid_at ?? now(),
            ]);
        }
    }
}
