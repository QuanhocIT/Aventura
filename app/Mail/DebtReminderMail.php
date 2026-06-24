<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DebtReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public mixed $debt,
        public string $type,
        public int $daysToDueDate
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->type === 'payable'
            ? '⏰ NHẮC NHỞ CÔNG NỢ PHẢI TRẢ (PO #' . ($this->debt->purchaseOrder?->po_number ?? 'N/A') . ') - Aventura'
            : '⏰ NHẮC NHỞ THANH TOÁN CÔNG NỢ (Hóa đơn #' . ($this->debt->order?->order_number ?? 'N/A') . ') - Aventura';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.debts.reminder',
            with: [
                'debt' => $this->debt,
                'type' => $this->type,
                'daysToDueDate' => $this->daysToDueDate,
            ],
        );
    }
}
