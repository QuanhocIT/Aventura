<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Support\Partitioning\PartitionHelper;
use Illuminate\Console\Command;

class ManagePartitions extends Command
{
    protected $signature = 'db:manage-partitions
        {--forward= : Số tháng partition tương lai cần đảm bảo luôn có sẵn (mặc định lấy từ config(partitioning.months_forward))}
        {--prune : Dọn các partition đã quá hạn retention_months cấu hình (bỏ qua bảng retention_months=null — giữ vĩnh viễn)}
        {--dry-run : Chỉ hiển thị partition đủ điều kiện dọn, không thay đổi database}
        {--table= : Chỉ chạy cho 1 bảng cụ thể thay vì toàn bộ danh sách trong config(partitioning.tables)}';

    protected $description = 'Đảm bảo mọi bảng đã đăng ký trong config(partitioning.tables) luôn có sẵn partition cho các tháng tới; tùy chọn --prune để dọn partition quá hạn retention';

    /**
     * `orders` KHÔNG có trong config(partitioning.tables) — cố tình loại trừ vì
     * còn 12 bảng khác giữ FK trỏ vào (xem config/partitioning.php). Bảng này chỉ
     * thực sự partition nếu 1 migration TƯƠNG LAI gỡ được các FK đó (xem
     * database/migrations/restaurant/2026_07_08_100001_partition_orders_hot_table.php,
     * hiện tự bỏ qua). Giữ lại đây để lệnh vẫn tự bổ sung partition cho nó
     * MIỄN LÀ nó đã được partition từ trước — an toàn vì ensureFuturePartitions()
     * tự bỏ qua bảng chưa partition.
     */
    private const LEGACY_TABLES = [
        'orders' => 'timestamp',
    ];

    public function handle(PartitionHelper $helper): int
    {
        $config = config('partitioning');
        $forward = (int) ($this->option('forward') ?? $config['months_forward']);
        $only = $this->option('table');

        $tables = $only
            ? array_intersect_key($config['tables'], [$only => true])
            : $config['tables'];

        if ($only && empty($tables) && ! array_key_exists($only, self::LEGACY_TABLES)) {
            $this->error("{$only}: không có trong config(partitioning.tables).");

            return self::FAILURE;
        }

        if (! $this->option('dry-run')) {
            foreach ($tables as $table => $columnConfig) {
                $this->ensureFuture($helper, $table, $columnConfig['type'], $forward);
            }
        }

        if (! $only && ! $this->option('dry-run')) {
            foreach (self::LEGACY_TABLES as $table => $type) {
                $this->ensureFuture($helper, $table, $type, $forward);
            }
        }

        if ($this->option('prune')) {
            foreach ($tables as $table => $columnConfig) {
                if ($columnConfig['retention_months'] === null) {
                    continue;
                }
                $retention = $table === 'audit_logs'
                    ? (int) SystemSetting::get('audit_retention_months', $columnConfig['retention_months'])
                    : $columnConfig['retention_months'];
                $this->prune($helper, $table, $retention, (bool) $this->option('dry-run'));
            }
        }

        return self::SUCCESS;
    }

    private function ensureFuture(PartitionHelper $helper, string $table, string $type, int $monthsForward): void
    {
        $added = $helper->ensureFuturePartitions($table, $type, $monthsForward);

        if ($added > 0) {
            $this->info("{$table}: đã bổ sung {$added} partition tương lai.");
        } else {
            $this->line("{$table}: partition tương lai đã đủ (hoặc bảng chưa được partition).");
        }
    }

    private function prune(PartitionHelper $helper, string $table, int $retentionMonths, bool $dryRun): void
    {
        $eligible = $helper->oldPartitions($table, $retentionMonths);

        if (empty($eligible)) {
            $this->info("{$table}: không có partition nào cần dọn (retention {$retentionMonths} tháng).");

            return;
        }

        if ($dryRun) {
            $this->warn("{$table}: sẽ DROP ".count($eligible)." partition cũ hơn {$retentionMonths} tháng (".implode(', ', $eligible).') — dry-run.');

            return;
        }

        $dropped = $helper->pruneOldPartitions($table, $retentionMonths);
        $this->warn("{$table}: đã DROP ".count($dropped)." partition cũ hơn {$retentionMonths} tháng (".implode(', ', $dropped).').');
    }
}
