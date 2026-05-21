<?php

namespace App\Jobs;

use App\Models\BillingInvoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateInvoiceDocuments implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $invoiceId) {}

    public function handle(): void
    {
        $invoice = BillingInvoice::query()->with(['restaurant', 'subscription.plan'])->findOrFail($this->invoiceId);

        $basePath = 'billing/invoices/'.$invoice->id;
        $pdfPath = $basePath.'/invoice-'.$invoice->invoice_number.'.pdf';
        $excelPath = $basePath.'/invoice-'.$invoice->invoice_number.'.csv';

        $pdfBody = implode(PHP_EOL, [
            'Aventura Invoice',
            'Invoice: '.$invoice->invoice_number,
            'Restaurant: '.($invoice->restaurant?->name ?? 'N/A'),
            'Plan: '.($invoice->subscription?->plan?->name ?? 'N/A'),
            'Type: '.$invoice->type,
            'Total: '.number_format((float) $invoice->total, 0, ',', '.').' '.$invoice->currency,
            'Due: '.optional($invoice->due_on)->format('d/m/Y'),
        ]);

        $csvBody = implode(',', ['invoice_number', 'restaurant', 'type', 'total', 'currency', 'due_on']).PHP_EOL;
        $csvBody .= implode(',', [
            $invoice->invoice_number,
            '"'.str_replace('"', '""', $invoice->restaurant?->name ?? 'N/A').'"',
            $invoice->type,
            $invoice->total,
            $invoice->currency,
            optional($invoice->due_on)->format('Y-m-d'),
        ]);

        Storage::disk('local')->put($pdfPath, $pdfBody);
        Storage::disk('local')->put($excelPath, $csvBody);

        $invoice->update([
            'pdf_path' => $pdfPath,
            'excel_path' => $excelPath,
            'status' => 'generated',
        ]);
    }
}