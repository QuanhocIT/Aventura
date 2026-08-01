<?php

namespace App\Jobs;

use App\Models\BillingInvoice;
use App\Services\EmailMicroserviceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendBillingInvoiceEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $invoiceId) {}

    public function handle(EmailMicroserviceClient $client): void
    {
        $invoice = BillingInvoice::query()
            ->with(['restaurant', 'restaurantSubscription.plan'])
            ->findOrFail($this->invoiceId);

        if (! $invoice->recipient_email) {
            Log::warning('SendBillingInvoiceEmail: không có recipient_email', ['invoice_id' => $this->invoiceId]);

            return;
        }

        $planName = $invoice->restaurantSubscription?->plan?->name ?? 'Pro';
        $pdfUrl = ($invoice->pdf_path !== null && $invoice->pdf_path !== '')
            ? config('app.url').'/storage/'.$invoice->pdf_path
            : null;

        $sent = $client->sendInvoice([
            'recipient_email' => $invoice->recipient_email,
            'invoice_number' => $invoice->invoice_number,
            'restaurant_name' => $invoice->restaurant?->name ?? '',
            'plan_name' => $planName,
            'amount' => (float) $invoice->total,
            'currency' => $invoice->currency ?? 'VND',
            'issued_on' => $invoice->issued_on?->format('d/m/Y') ?? now()->format('d/m/Y'),
            'due_on' => $invoice->due_on?->format('d/m/Y') ?? now()->addMonth()->format('d/m/Y'),
            'pdf_url' => $pdfUrl,
        ]);

        if ($sent) {
            $invoice->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }
    }
}
