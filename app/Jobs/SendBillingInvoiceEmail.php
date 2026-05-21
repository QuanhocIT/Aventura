<?php

namespace App\Jobs;

use App\Mail\BillingInvoiceMail;
use App\Models\BillingInvoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendBillingInvoiceEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $invoiceId) {}

    public function handle(): void
    {
        $invoice = BillingInvoice::query()->findOrFail($this->invoiceId);

        if (! $invoice->recipient_email) {
            return;
        }

        Mail::to($invoice->recipient_email)->send(new BillingInvoiceMail($invoice));

        $invoice->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}