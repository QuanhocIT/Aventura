<?php

namespace App\Console\Commands;

use App\Models\TableReservation;
use App\Services\EmailMicroserviceClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendReservationReminders extends Command
{
    protected $signature = 'reservations:send-reminders';

    protected $description = 'Send email reminders to guests 2 hours before their confirmed reservations';

    public function handle(EmailMicroserviceClient $emailClient)
    {
        $this->info('Starting reservations reminder sender...');
        $now = Carbon::now();
        $twoHoursFromNow = Carbon::now()->addHours(2);

        $upcomingReservations = TableReservation::with(['restaurant', 'table'])
            ->where('status', 'confirmed')
            ->where('reminder_sent', false)
            ->whereNotNull('guest_email')
            ->where(function ($query) {
                $query->whereDate('reservation_date', today()->toDateString())
                    ->orWhereDate('reservation_date', today()->addDay()->toDateString());
            })
            ->get();

        $sentCount = 0;

        foreach ($upcomingReservations as $res) {
            try {
                $resDateTimeStr = $res->reservation_date->format('Y-m-d').' '.$res->reservation_time;
                $resDateTime = Carbon::parse($resDateTimeStr);

                if ($resDateTime->isAfter($now) && $resDateTime->isBefore($twoHoursFromNow)) {
                    $this->info("Sending reminder to {$res->guest_email} for reservation at {$resDateTimeStr}");

                    $sent = $emailClient->sendReservationReminder([
                        'recipient_email' => $res->guest_email,
                        'recipient_name' => $res->guest_name,
                        'restaurant_name' => $res->restaurant ? $res->restaurant->name : 'Aventura Restaurant',
                        'reservation_date' => $res->reservation_date->format('d/m/Y'),
                        'reservation_time' => Carbon::parse($res->reservation_time)->format('H:i'),
                        'party_size' => $res->party_size,
                        'table_name' => $res->table ? $res->table->name : null,
                        'special_requests' => $res->special_requests,
                    ]);

                    if ($sent) {
                        $res->update([
                            'reminder_sent' => true,
                            'reminder_sent_at' => Carbon::now(),
                        ]);
                        $sentCount++;
                    } else {
                        Log::error("Failed to send reservation reminder to {$res->guest_email} (ID: {$res->id})");
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Error sending reservation reminder for reservation ID {$res->id}: ".$e->getMessage());
                $this->error("Error sending reservation reminder for reservation ID {$res->id}: ".$e->getMessage());
            }
        }

        $this->info("Completed sending {$sentCount} reminders.");

        return Command::SUCCESS;
    }
}
