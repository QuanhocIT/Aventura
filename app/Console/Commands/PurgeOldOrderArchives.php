<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Xóa các partition cũ của orders_archive và order_items_archive
 * sau khi đã vượt qua retention period.
 *
 * Vì dùng MySQL Partition, lệnh DROP PARTITION cực nhanh — không scan từng row.
 *
 * Mặc định retention: 36 tháng (3 năm) theo yêu cầu báo cáo thuế.
 * Payments cần giữ lâu hơn (7 năm) — KHÔNG purge payments qua command này.
 *
 * Usage:
 *   php artisan orders:purge                     # dry-run, hiện danh sách sẽ xóa
 *   php artisan orders:purge --confirm           # thực sự xóa partition cũ
 *   php artisan orders:purge --months=24 --confirm   # retention 24 tháng
 */
class PurgeOldOrderArchives extends Command
{
    protected $signature = 'orders:purge
        {--months=36 : Số tháng retention (partition cũ hơn số này sẽ bị DROP)}
        {--confirm   : Thực sự DROP partition (không có flag này = dry-run)}';

    protected $description = 'DROP partition cũ của orders_archive và order_items_archive (MySQL only). Mặc định là dry-run, thêm --confirm để thực thi.';

    private const ARCHIVE_TABLES = ['orders_archive', 'order_items_archive'];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->warn('orders:purge chỉ hỗ trợ MySQL/MariaDB. Bỏ qua.');

            return self::SUCCESS;
        }

        $months = (int) $this->option('months');
        $confirm = (bool) $this->option('confirm');
        $cutoff = now()->subMonths($months)->startOfMonth();

        $this->info("orders:purge — retention {$months} tháng (cutoff: {$cutoff->format('Y-m')})");
        $this->info($confirm ? '⚠️  Chế độ THỰC THI — các partition sẽ bị DROP vĩnh viễn.' : '🔍 Chế độ DRY-RUN — không có thay đổi nào được thực hiện.');
        $this->newLine();

        $totalDropped = 0;

        foreach (self::ARCHIVE_TABLES as $table) {
            $dropped = $this->processTable($table, $cutoff, $confirm);
            $totalDropped += $dropped;
        }

        $this->newLine();
        $verb = $confirm ? 'đã DROP' : 'sẽ được DROP (dry-run)';
        $this->info("Hoàn tất: {$totalDropped} partition {$verb} trên tổng ".count(self::ARCHIVE_TABLES).' bảng.');

        if (! $confirm && $totalDropped > 0) {
            $this->comment('👆 Chạy lại với --confirm để thực sự xóa các partition trên.');
        }

        return self::SUCCESS;
    }

    private function processTable(string $table, Carbon $cutoff, bool $confirm): int
    {
        $partitions = $this->getPartitions($table);

        if (empty($partitions)) {
            $this->warn("  {$table}: không tìm thấy partition (bảng chưa được phân vùng?)");

            return 0;
        }

        $toDrop = [];

        foreach ($partitions as $partition) {
            if ($partition->name === 'pFuture') {
                continue;
            }

            // Tên partition dạng p2024_03 → year=2024, month=3
            $parts = explode('_', substr($partition->name, 1));

            if (count($parts) < 2) {
                continue;
            }

            [$year, $month] = array_map('intval', $parts);
            $partMonth = Carbon::createFromDate($year, $month, 1);

            if ($partMonth->lessThan($cutoff)) {
                $toDrop[] = $partition->name;
            }
        }

        if (empty($toDrop)) {
            $this->line("  ✓ {$table}: không có partition nào cần xóa (tất cả trong retention {$cutoff->diffInMonths(now())} tháng).");

            return 0;
        }

        $list = implode(', ', $toDrop);
        $this->line("  🗑  {$table}: ".count($toDrop)." partition sẽ bị DROP [{$list}]");

        // Hiển thị ước tính dữ liệu sẽ mất (thông tin từ information_schema)
        $this->showPartitionSizes($table, $toDrop);

        if ($confirm) {
            DB::statement("ALTER TABLE {$table} DROP PARTITION {$list}");
            $this->info("  ✅ {$table}: đã DROP ".count($toDrop).' partition thành công.');
        }

        return count($toDrop);
    }

    /**
     * Hiển thị thông tin kích thước partition (ước tính) trước khi DROP.
     */
    private function showPartitionSizes(string $table, array $partitionNames): void
    {
        $placeholders = implode(',', array_fill(0, count($partitionNames), '?'));

        $rows = DB::select(
            "SELECT PARTITION_NAME as name,
                    TABLE_ROWS as row_estimate,
                    DATA_LENGTH + INDEX_LENGTH as size_bytes
             FROM information_schema.partitions
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND PARTITION_NAME IN ({$placeholders})",
            array_merge([$table], $partitionNames)
        );

        $totalRows = 0;
        $totalBytes = 0;

        foreach ($rows as $row) {
            $totalRows += (int) $row->row_estimate;
            $totalBytes += (int) $row->size_bytes;
        }

        $sizeMb = round($totalBytes / 1024 / 1024, 2);
        $this->line("     → Ước tính: ~{$totalRows} rows, ~{$sizeMb} MB");
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
