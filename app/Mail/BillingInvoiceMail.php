<?php

namespace App\Mail;

use App\Models\BillingInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BillingInvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public BillingInvoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aventura Billing - '.$this->invoice->invoice_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.billing.invoice',
            with: ['invoice' => $this->invoice],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->invoice->pdf_path) {
            $attachments[] = Attachment::fromStorageDisk('local', $this->invoice->pdf_path);
        }

        if ($this->invoice->excel_path) {
            $attachments[] = Attachment::fromStorageDisk('local', $this->invoice->excel_path);
        }

        return $attachments;
    }
}
