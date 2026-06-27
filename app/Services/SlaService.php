<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\User;
use App\Mail\SupportTicketEscalationMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SlaService
{
    /**
     * Get response SLA hours for a given plan code.
     */
    public function getSlaHoursForPlan(?string $planCode): int
    {
        return match ($planCode) {
            'enterprise' => 2,
            'pro'        => 12,
            'starter'    => 24,
            'free'       => 48,
            default      => 48,
        };
    }

    /**
     * Calculate and return the SLA due time for a ticket.
     */
    public function calculateSlaDueAt(SupportTicket $ticket): ?Carbon
    {
        $createdAt = $ticket->created_at ? Carbon::parse($ticket->created_at) : now();
        
        $restaurant = $ticket->restaurant;
        if ($restaurant && $restaurant->plan) {
            $slaHours = $this->getSlaHoursForPlan($restaurant->plan->code);
        } else {
            $slaHours = 48; // default SLA for system tickets
        }

        return $createdAt->addHours($slaHours);
    }

    /**
     * Scan for unresponded Enterprise tickets created more than 1 hour ago
     * and escalate them to Superadmins.
     */
    public function checkAndEscalateTickets(): array
    {
        $escalatedTickets = [];

        // Find tickets that meet escalation criteria:
        // - Enterprise plan
        // - Created >= 1 hour ago
        // - first_response_at is null
        // - status is not resolved/closed
        // - escalated_at is null
        $tickets = SupportTicket::whereNull('first_response_at')
            ->whereNull('escalated_at')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereHas('restaurant.plan', function ($query) {
                $query->where('code', 'enterprise');
            })
            ->where('created_at', '<=', now()->subHour())
            ->get();

        if ($tickets->isEmpty()) {
            return [];
        }

        // Fetch superadmins
        $superAdmins = User::whereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->get();

        if ($superAdmins->isEmpty()) {
            Log::warning('No superadmins found to escalate tickets to.');
            return [];
        }

        foreach ($tickets as $ticket) {
            try {
                // Send escalation email to each superadmin
                foreach ($superAdmins as $admin) {
                    if ($admin->email) {
                        Mail::to($admin->email)->send(new SupportTicketEscalationMail($ticket, $admin));
                    }
                }

                $ticket->update([
                    'escalated_at' => now(),
                ]);

                $escalatedTickets[] = $ticket;
                Log::info("Support ticket {$ticket->code} escalated successfully.");
            } catch (\Exception $e) {
                Log::error("Failed to escalate ticket {$ticket->code}: " . $e->getMessage());
            }
        }

        return $escalatedTickets;
    }

    /**
     * Calculate SLA compliance metrics for the support dashboard.
     */
    public function getSlaMetrics(): array
    {
        $totalChecked = SupportTicket::whereNotNull('sla_due_at')->count();

        $slaBreached = SupportTicket::whereNotNull('sla_due_at')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('first_response_at')
                      ->whereColumn('first_response_at', '>', 'sla_due_at');
                })->orWhere(function ($q) {
                    $q->whereNull('first_response_at')
                      ->where('sla_due_at', '<', now());
                });
            })->count();

        $slaWarning = SupportTicket::whereNotNull('sla_due_at')
            ->whereNull('first_response_at')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereBetween('sla_due_at', [now(), now()->addHour()])
            ->count();

        $slaFulfillRate = 100.0;
        if ($totalChecked > 0) {
            $slaFulfillRate = (($totalChecked - $slaBreached) / $totalChecked) * 100.0;
        }

        return [
            'total_checked' => $totalChecked,
            'sla_breached' => $slaBreached,
            'sla_warning' => $slaWarning,
            'sla_fulfill_rate' => round($slaFulfillRate, 1),
        ];
    }
}
