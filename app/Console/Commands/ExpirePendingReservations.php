<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpirePendingReservations extends Command
{
    protected $signature = 'reservations:expire-pending';

    protected $description = 'Cancel pending reservations whose requested time has already passed';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subMinutes(30);
        $expired = 0;

        // withoutGlobalScopes: lệnh chạy cho MỌI nhà hàng. Nếu một ngày nào đó
        // lệnh được gọi từ trong request hoặc queue job có tenant context, global
        // scope 'restaurant' sẽ âm thầm thu hẹp phạm vi xuống một nhà hàng.
        TableReservation::withoutGlobalScopes()
            ->where('status', 'pending')
            ->where(function ($query) use ($cutoff): void {
                $query->whereDate('reservation_date', '<', $cutoff->toDateString())
                    ->orWhere(function ($q) use ($cutoff): void {
                        $q->whereDate('reservation_date', $cutoff->toDateString())
                            ->where('reservation_time', '<', $cutoff->format('H:i:s'));
                    });
            })
            ->orderBy('id')
            ->get()
            ->each(function (TableReservation $reservation) use (&$expired): void {
                try {
                    DB::transaction(function () use ($reservation): void {
                        $locked = TableReservation::withoutGlobalScopes()->whereKey($reservation->id)->lockForUpdate()->first();
                        if (! $locked || $locked->status !== 'pending') {
                            return;
                        }

                        $locked->update([
                            'status' => 'cancelled',
                            'cancellation_reason' => 'Tự động hủy: quá thời gian chờ xác nhận.',
                            'cancelled_at' => now(),
                        ]);

                        if ($locked->table_id) {
                            RestaurantTable::withoutGlobalScopes()->whereKey($locked->table_id)
                                ->where('status', 'reserved')
                                ->update(['status' => 'available']);
                        }

                        AuditLog::log(
                            'reservation_expired',
                            'updated',
                            $locked,
                            ['status' => 'pending'],
                            ['status' => 'cancelled'],
                        );
                    });
                    $expired++;
                } catch (\Throwable $e) {
                    Log::error('Failed to expire pending reservation', [
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        $this->info("Expired {$expired} pending reservations.");

        return self::SUCCESS;
    }
}
