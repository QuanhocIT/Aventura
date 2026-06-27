<?php

namespace App\Observers;

use App\Models\Order;
use App\Jobs\LogDiscountAppliedJob;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->isDirty('status') && $order->status === 'completed') {
            $order->loadMissing('customer');
            if ($order->customer && !empty($order->customer->email)) {
                \App\Jobs\SendReviewRequestEmail::dispatch($order->id)->delay(now()->addHours(2));
            }
        }

        if ($order->isDirty('discount_amount')) {
            $oldDiscount = (float) $order->getOriginal('discount_amount');
            $newDiscount = (float) $order->discount_amount;

            if ($oldDiscount !== $newDiscount) {
                $user = auth()->user();
                // Dự phòng nếu chạy ngầm không có user đăng nhập (ví dụ từ API hoặc Job khác), lấy cashier cũ
                $userId = $user ? $user->id : ($order->cashier_user_id ?? $order->created_by);

                if ($userId) {
                    LogDiscountAppliedJob::dispatch(
                        $order->id,
                        $userId,
                        [
                            'discount_amount' => $oldDiscount,
                            'subtotal' => (float) $order->getOriginal('subtotal'),
                            'total_amount' => (float) $order->getOriginal('total_amount'),
                        ],
                        [
                            'discount_amount' => $newDiscount,
                            'subtotal' => (float) $order->subtotal,
                            'total_amount' => (float) $order->total_amount,
                        ],
                        request()->ip(),
                        request()->userAgent()
                    );
                }
            }
        }
    }
}
