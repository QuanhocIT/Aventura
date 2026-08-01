<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\TableReservation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkNoShowReservations extends Command
{
    protected $signature = 'reservations:mark-no-shows';

    protected $description = 'Automatically mark overdue confirmed reservations as no-show after 30 minutes';

    public function handle()
    {
        $this->info('Starting checking for overdue reservations...');
        $now = Carbon::now();
        $cutoff = Carbon::now()->subMinutes(30);

        // We find confirmed reservations that are overdue.
        // If reservation_date is in the past, or if it is today but reservation_time is older than 30 mins ago.
        $overdueReservations = TableReservation::where('status', 'confirmed')
            ->where(function ($query) use ($cutoff) {
                $query->whereDate('reservation_date', '<', today()->toDateString())
                    ->orWhere(function ($q) use ($cutoff) {
                        $q->whereDate('reservation_date', today()->toDateString())
                            ->where('reservation_time', '<', $cutoff->format('H:i:s'));
                    });
            })
            ->get();

        $markedCount = 0;

        foreach ($overdueReservations as $res) {
            try {
                $resDateTimeStr = $res->reservation_date->format('Y-m-d').' '.$res->reservation_time;
                $this->info("Marking reservation ID {$res->id} (time: {$resDateTimeStr}) as no-show");

                $res->update([
                    'status' => 'no_show',
                ]);

                // Record in audit log
                AuditLog::create([
                    'restaurant_id' => $res->restaurant_id,
                    'event' => 'updated',
                    'action' => 'auto_no_show',
                    'subject_type' => TableReservation::class,
                    'subject_id' => $res->id,
                    'old_values' => ['status' => 'confirmed'],
                    'new_values' => ['status' => 'no_show'],
                ]);

                $markedCount++;
            } catch (\Throwable $e) {
                Log::error("Error marking reservation ID {$res->id} as no-show: ".$e->getMessage());
                $this->error("Error marking reservation ID {$res->id} as no-show: ".$e->getMessage());
            }
        }

        $this->info("Completed marking {$markedCount} reservations as no-show.");

        return Command::SUCCESS;
    }
}
