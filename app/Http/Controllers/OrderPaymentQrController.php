<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderPaymentQrController extends Controller
{
    /**
     * Sinh link VietQR động cho đơn hàng.
     */
    public function paymentQr(Request $request, Order $order): JsonResponse
    {
        $restaurant = $order->restaurant;
        
        $bank = $restaurant->qr_bank_id ?: 'mbbank';
        $accountNumber = $restaurant->qr_account_number ?: '1234567890';
        $accountName = $restaurant->qr_account_name ?: 'AVENTURA RESTAURANT';
        
        $amount = (int) $order->total_amount;
        $description = "AVTORD" . $order->id;
        
        $qrUrl = "https://img.vietqr.io/image/{$bank}-{$accountNumber}-compact.png?" . http_build_query([
            'amount' => $amount,
            'addInfo' => $description,
            'accountName' => $accountName
        ]);

        return response()->json([
            'success' => true,
            'qr_url' => $qrUrl,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total_amount' => $amount,
            'description' => $description,
            'qr_enabled' => (bool) $restaurant->qr_enabled
        ]);
    }

    /**
     * Kiểm tra trạng thái thanh toán hiện tại của đơn hàng.
     */
    public function paymentStatus(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'payment_status' => $order->payment_status,
            'status' => $order->status,
            'is_paid' => $order->payment_status === 'paid'
        ]);
    }
}
