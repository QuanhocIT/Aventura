<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManagePartitions extends Command
{
    protected $signature = 'db:manage-partitions
        {--forward=6 : Số tháng partition tương lai cần đảm bảo luôn có sẵn}
        {--prune-audit-logs-months= : Nếu đặt, DROP các partition audit_logs cũ hơn N tháng (dọn dẹp retention)}';

    protected $description = 'Đảm bảo các bảng partition (orders_archive, order_items_archive, audit_logs) luôn có sẵn partition cho các tháng tới; tùy chọn dọn dẹp partition audit_logs quá hạn retention';

    private const PARTITIONED_TABLES = ['orders_archive', 'order_items_archive', 'audit_logs'];

    public function handle(): int
    {
        $forward = (int) $this->option('forward');

        foreach (self::PARTITIONED_TABLES as $table) {
            $this->ensureFuturePartitions($table, $forward);
        }

        $pruneMonths = $this->option('prune-audit-logs-months');
        if ($pruneMonths !== null) {
            $this->pruneOldPartitions('audit_logs', (int) $pruneMonths);
        }

        return self::SUCCESS;
    }

    private function ensureFuturePartitions(string $table, int $monthsForward): void
    {
        $existing = $this->getPartitions($table);

        if (empty($existing)) {
            $this->warn("{$table}: bảng chưa được partition, bỏ qua.");

            return;
        }

        $monthly = collect($existing)->filter(fn ($p) => $p->name !== 'pFuture')->values();

        if ($monthly->isEmpty() || !collect($existing)->contains('name', 'pFuture')) {
            $this->warn("{$table}: không tìm thấy partition pFuture hợp lệ, bỏ qua.");

            return;
        }

        $lastName = $monthly->last()->name;
        [$year, $month] = array_map('intval', explode('_', substr($lastName, 1)));
        $cursor = Carbon::createFromDate($year, $month, 1)->addMonth();

        $target = now()->startOfMonth()->addMonths($monthsForward);

        $newPartitions = [];
        while ($cursor->lessThanOrEqualTo($target)) {
            $boundary = $cursor->copy()->addMonth()->format('Y-m-d');
            $name = 'p' . $cursor->format('Y_m');
            $newPartitions[] = "PARTITION {$name} VALUES LESS THAN (UNIX_TIMESTAMP('{$boundary}'))";
            $cursor = $cursor->addMonth();
        }

        if (empty($newPartitions)) {
            $this->info("{$table}: partition tương lai đã đủ, không cần bổ sung.");

            return;
        }

        $newPartitions[] = 'PARTITION pFuture VALUES LESS THAN (MAXVALUE)';
        $sql = implode(', ', $newPartitions);

        DB::statement("ALTER TABLE {$table} REORGANIZE PARTITION pFuture INTO ({$sql})");

        $this->info("{$table}: đã bổ sung " . (count($newPartitions) - 1) . ' partition tương lai.');
    }

    private function pruneOldPartitions(string $table, int $retentionMonths): void
    {
        $cutoff = now()->subMonths($retentionMonths)->startOfMonth();
        $existing = $this->getPartitions($table);
        $toDrop = [];

        foreach ($existing as $partition) {
            if ($partition->name === 'pFuture') {
                continue;
            }

            [$year, $month] = array_map('intval', explode('_', substr($partition->name, 1)));
            $partitionMonth = Carbon::createFromDate($year, $month, 1);

            if ($partitionMonth->lessThan($cutoff)) {
                $toDrop[] = $partition->name;
            }
        }

        if (empty($toDrop)) {
            $this->info("{$table}: không có partition nào cần dọn (retention {$retentionMonths} tháng).");

            return;
        }

        $list = implode(', ', $toDrop);
        DB::statement("ALTER TABLE {$table} DROP PARTITION {$list}");

        $this->warn("{$table}: đã DROP " . count($toDrop) . " partition cũ hơn {$retentionMonths} tháng ({$list}).");
    }

    private function getPartitions(string $table): array
    {
        return DB::select(
            'SELECT PARTITION_NAME as name
             FROM information_schema.partitions
             WHERE table_schema = DATABASE() AND table_name = ? AND PARTITION_NAME IS NOT NULL
             ORDER BY PARTITION_ORDINAL_POSITION',
            [$table]
        );
    }
}
