<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\EInvoiceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EInvoiceController extends Controller
{
    /** Tải XML hóa đơn điện tử (định dạng TT78) của một đơn đã thanh toán. */
    public function download(Request $request, Order $order, EInvoiceService $service): Response
    {
        abort_unless($order->restaurant_id === $request->user()->restaurant_id, 403);
        abort_unless($order->payment_status === 'paid', 422, 'Chỉ xuất hóa đơn cho đơn đã thanh toán.');

        $xml = $service->buildXml($order);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=hoadon-{$order->order_number}.xml",
        ]);
    }
}
