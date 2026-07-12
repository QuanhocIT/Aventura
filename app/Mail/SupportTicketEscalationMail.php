<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketEscalationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public User $admin
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🚨 CẢNH BÁO LEO THANG SLA: Ticket ' . $this->ticket->code . ' chưa được phản hồi! - Aventura',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support.escalation',
            with: [
                'ticket' => $this->ticket,
                'admin' => $this->admin,
            ],
        );
    }
}
