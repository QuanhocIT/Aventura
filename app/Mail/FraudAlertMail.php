<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FraudAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $restaurantName,
        public array $alertData
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🚨 CẢNH BÁO RỦI RO GIAN LẬN: '.$this->alertData['violation_type'].' - Aventura',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.fraud.alert',
            with: [
                'restaurantName' => $this->restaurantName,
                'alert' => $this->alertData,
            ],
        );
    }
}
