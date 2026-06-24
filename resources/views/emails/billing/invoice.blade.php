Hóa đơn {{ $invoice->invoice_number }} từ Aventura.
Nhà hàng: {{ $invoice->restaurant?->name }}
Loại: {{ $invoice->type }}
Tổng tiền: {{ number_format((float) $invoice->total, 0, ',', '.') }} {{ $invoice->currency }}
Hạn thanh toán: {{ optional($invoice->due_on)->format('d/m/Y') }}