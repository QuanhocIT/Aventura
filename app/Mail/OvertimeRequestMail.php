<?php

namespace App\Mail;

use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OvertimeRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OvertimeRequest $overtime) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Yêu cầu tăng ca đột xuất - Aventura',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.employees.overtime-request');
    }
}
