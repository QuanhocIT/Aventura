<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $restaurantName,
        public string $jobTitle,
        public string $verificationUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Mời nhận việc tại ' . $this->restaurantName . ' - Aventura POS',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.employees.verify',
        );
    }
}
