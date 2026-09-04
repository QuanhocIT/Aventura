<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\StockTransferRequest;
use App\Models\User;
use App\Notifications\StockTransferStageNotification;
use Illuminate\Console\Command;

class CheckStockTransferSla extends Command
{
    protected $signature = 'stock-transfers:check-sla';

    protected $description = 'Cảnh báo và leo thang các phiếu điều chuyển nguyên liệu quá SLA.';

    /** @var array<string, int> */
    private const SLA_HOURS = [
        'requested' => 4,
        'routed' => 8,
        'dispatched' => 24,
        'discrepancy' => 24,
        'quarantined' => 24,
        'return_requested' => 48,
    ];

    public function handle(): int
    {
        $notified = 0;

        StockTransferRequest::query()
            ->whereIn('status', array_keys(self::SLA_HOURS))
            ->whereNull('sla_escalated_at')
            ->with(['requestedBy', 'fromBranch', 'toBranch', 'ingredient.unit'])
            ->chunkById(100, function ($transfers) use (&$notified): void {
                foreach ($transfers as $transfer) {
                    $startedAt = match ($transfer->status) {
                        'requested' => $transfer->created_at,
                        'routed' => $transfer->routed_at,
                        'dispatched' => $transfer->dispatched_at,
                        default => $transfer->received_at ?? $transfer->dispatched_at ?? $transfer->created_at,
                    };
                    if (! $startedAt || now()->diffInHours($startedAt) <= self::SLA_HOURS[$transfer->status]) {
                        continue;
                    }

                    $branchIds = array_values(array_filter([(int) $transfer->from_branch_id, (int) $transfer->to_branch_id]));
                    $recipients = User::query()
                        ->where('restaurant_id', $transfer->restaurant_id)
                        ->where(function ($query) use ($transfer, $branchIds): void {
                            $query->whereKey($transfer->requested_by)
                                ->orWhereHas('roles', fn ($roles) => $roles->whereIn('name', ['owner', 'warehouse_manager']))
                                ->orWhere(function ($manager) use ($branchIds): void {
                                    $manager->where(function ($branches) use ($branchIds): void {
                                        $branches->whereIn('branch_id', $branchIds)
                                            ->orWhereHas('employee', fn ($employee) => $employee->whereIn('branch_id', $branchIds));
                                    })->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['manager', 'quản lý', 'quan_ly', 'quanly']));
                                });
                        })
                        ->get();

                    foreach ($recipients as $recipient) {
                        $recipient->notify(new StockTransferStageNotification($transfer, 'sla_breached', 'Hệ thống'));
                        $notified++;
                    }

                    $transfer->update(['sla_escalated_at' => now()]);
                    AuditLog::log('stock_transfer_sla_breached', 'updated', $transfer, null, [
                        'status' => $transfer->status,
                        'sla_hours' => self::SLA_HOURS[$transfer->status],
                    ]);
                }
            });

        $this->info("Đã gửi {$notified} cảnh báo SLA điều chuyển.");

        return self::SUCCESS;
    }
}
