Hóa đơn {{ $invoice_number ?? $invoice->invoice_number }} từ Aventura.
Nhà hàng: {{ $restaurant_name ?? $invoice->restaurant?->name }}
@if(isset($plan_name))
Gói dịch vụ: {{ $plan_name }}
@elseif(isset($invoice->type))
Loại: {{ $invoice->type }}
@endif
Tổng tiền: {{ number_format((float) ($amount ?? $invoice->total), 0, ',', '.') }} {{ $currency ?? $invoice->currency }}
Hạn thanh toán: {{ $due_on ?? optional($invoice->due_on)->format('d/m/Y') }}
@if(isset($pdf_url))
Link tải hóa đơn PDF: {{ $pdf_url }}
@endif