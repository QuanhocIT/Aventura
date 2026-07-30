<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DashboardReportMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public string $frequency,
        public \Carbon\CarbonInterface $generatedAt,
        public array $snapshot,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->frequency === 'monthly' ? 'hàng tháng' : 'hàng tuần';

        return new Envelope(
            subject: "Báo cáo tổng quan hệ thống Aventura ({$label}) - {$this->generatedAt->format('d/m/Y')}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.reports.dashboard-summary',
            with: [
                'frequency' => $this->frequency,
                'generatedAt' => $this->generatedAt,
                'snapshot' => $this->snapshot,
                'dashboardUrl' => route('superadmin.dashboard'),
                'reportUrl' => route('superadmin.dashboard.export.report'),
            ],
        );
    }
}
