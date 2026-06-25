<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SuperAdminValidatorReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $checkedDate,
        public array $summary,
        public array $newlyFlagged,
        public array $activeTodayDetails,
        public array $inactiveTodayDetails,
        public int $inactiveDaysLimit
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔍 BÁO CÁO KIỂM ĐỊNH HOẠT ĐỘNG DOANH NGHIỆP - ' . $this->checkedDate,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.reports.superadmin-validator',
            with: [
                'checkedDate' => $this->checkedDate,
                'summary' => $this->summary,
                'newlyFlagged' => $this->newlyFlagged,
                'activeTodayDetails' => $this->activeTodayDetails,
                'inactiveTodayDetails' => $this->inactiveTodayDetails,
                'inactiveDaysLimit' => $this->inactiveDaysLimit,
                'dashboardUrl' => route('superadmin.dashboard'),
            ],
        );
    }
}
