<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Printing\ReceiptPrinterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceiptPrintController extends Controller
{
    public function __construct(private ReceiptPrinterService $printer) {}

    /** Gửi hóa đơn tới máy in nhiệt mặc định của nhà hàng. */
    public function print(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->restaurant_id === $request->user()->restaurant_id, 403);

        [$ok, $message] = $this->printer->print($order);

        return $ok ? back()->with('success', $message) : back()->withErrors(['printer' => $message]);
    }

    /** Tải file ESC/POS thô (.bin) — dùng gửi tay tới máy in hoặc debug. */
    public function download(Request $request, Order $order): Response
    {
        abort_unless($order->restaurant_id === $request->user()->restaurant_id, 403);

        $bytes = $this->printer->buildReceipt($order);

        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=receipt-{$order->order_number}.bin",
        ]);
    }
}
