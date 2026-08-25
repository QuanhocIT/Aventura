<?php

namespace App\Console\Commands;

use App\Models\InventoryReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dọn dẹp các bản ghi giữ kho đệm (inventory_reservations) đã hết hạn.
 *
 * Reservation hết hạn (expires_at < now()) vẫn giữ status 'holding' trong DB
 * dù InventoryAvailabilityService đã lọc qua expires_at. Job này đảm bảo
 * trạng thái được cập nhật chính xác và bảng không phình lên vô hạn.
 */
class CleanupExpiredReservations extends Command
{
    protected $signature = 'reservations:cleanup-expired';

    protected $description = 'Cập nhật trạng thái các inventory reservation đã hết hạn từ holding → expired';

    public function handle(): int
    {
        // withoutGlobalScopes: lệnh chạy cho MỌI nhà hàng. Nếu một ngày nào đó
        // lệnh được gọi từ trong request hoặc queue job có tenant context, global
        // scope 'restaurant' sẽ âm thầm thu hẹp phạm vi xuống một nhà hàng.
        $updated = InventoryReservation::withoutGlobalScopes()
            ->where('status', 'holding')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        if ($updated > 0) {
            Log::info("reservations:cleanup-expired: Đã cập nhật {$updated} reservation(s) hết hạn.");
            $this->info("Đã cập nhật {$updated} reservation(s) hết hạn.");
        } else {
            $this->info('Không có reservation nào hết hạn cần xử lý.');
        }

        return self::SUCCESS;
    }
}
