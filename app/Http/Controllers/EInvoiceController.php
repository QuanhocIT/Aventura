<?php

namespace App\Http\Controllers;

use App\Models\EInvoice;
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
        $order->loadMissing('customer');
        $invoice = EInvoice::firstOrCreate(
            ['restaurant_id' => $order->restaurant_id, 'order_id' => $order->id],
            [
                'status' => 'draft',
                'provider' => config('services.einvoice.provider'),
                'issue_date' => ($order->completed_at ?? $order->created_at)?->toDateString(),
                'customer_tax_code' => $order->customer?->tax_code,
                'tax_rate' => $order->tax_rate ?? 8,
                'subtotal' => $order->subtotal,
                'tax_amount' => $order->tax_amount,
                'total_amount' => $order->total_amount,
                'created_by' => $request->user()->id,
            ],
        );
        $invoice->update(['xml_payload' => $xml]);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=hoadon-{$order->order_number}.xml",
        ]);
    }
}
