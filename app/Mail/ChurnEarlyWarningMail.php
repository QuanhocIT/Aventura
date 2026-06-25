<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChurnEarlyWarningMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $restaurantName,
        public string $ownerName,
        public string $promoCode,
        public array $reasons = []
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '💝 Đồng hành cùng phát triển & Món quà tri ân từ Aventura POS',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.churn.early_warning',
            with: [
                'restaurantName' => $this->restaurantName,
                'ownerName' => $this->ownerName,
                'promoCode' => $this->promoCode,
                'reasons' => $this->reasons,
            ],
        );
    }
}
