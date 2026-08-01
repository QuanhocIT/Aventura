<?php

namespace App\Support\Partitioning;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logic dùng chung cho việc partition theo tháng (MySQL RANGE partitioning) —
 * dùng bởi migration ban đầu VÀ app/Console/Commands/ManagePartitions.php
 * (đảm bảo luôn có sẵn partition tương lai + dọn dẹp partition quá hạn),
 * tổng quát hoá đúng công thức đã áp dụng cho audit_logs/orders_archive.
 *
 * 2 biến thể tuỳ kiểu cột (bắt buộc, không thể dùng chung 1 cú pháp):
 *   - 'timestamp': PARTITION BY RANGE (UNIX_TIMESTAMP(col)) — chỉ hợp lệ trên
 *     cột TIMESTAMP thật.
 *   - 'datetime': PARTITION BY RANGE COLUMNS(col) — bắt buộc cho DATETIME/DATE
 *     vì UNIX_TIMESTAMP() không phải partition expression hợp lệ trên chúng.
 */
class PartitionHelper
{
    /**
     * Bảng khác có khoá ngoại TRỎ VÀO $table không — nếu có thì KHÔNG được
     * partition (InnoDB cấm FK cùng lúc với partition). Cùng cơ chế tự bảo vệ
     * đã có sẵn ở migration partition_orders_hot_table.php cho bảng orders.
     */
    public function hasIncomingForeignKeys(string $table): bool
    {
        $count = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('REFERENCED_TABLE_SCHEMA', DB::getDatabaseName())
            ->where('REFERENCED_TABLE_NAME', $table)
            ->count();

        return $count > 0;
    }

    /**
     * DDL để partition 1 bảng lần đầu — từ $monthsBack tháng trước tới
     * $monthsForward tháng sau, cộng 1 partition pFuture hứng phần còn lại.
     */
    public function buildInitialPartitionSql(string $column, string $type, int $monthsBack, int $monthsForward): string
    {
        $start = now()->startOfMonth()->subMonths($monthsBack);
        $end = now()->startOfMonth()->addMonths($monthsForward);

        $parts = [];
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            $boundary = $cursor->copy()->addMonth()->format('Y-m-d');
            $name = 'p'.$cursor->format('Y_m');
            $parts[] = $this->partitionClause($name, $boundary, $type);
            // App dùng CarbonImmutable toàn cục (Date::use trong AppServiceProvider)
            // — addMonth() trả về instance MỚI, không sửa $cursor tại chỗ. Quên gán
            // lại sẽ khiến vòng lặp treo vô hạn tới khi hết bộ nhớ (đã tự bắt được lỗi
            // này qua test tích hợp trước khi chạy migration thật).
            $cursor = $cursor->addMonth();
        }
        $parts[] = 'PARTITION pFuture VALUES LESS THAN (MAXVALUE)';

        $expr = $type === 'timestamp' ? "RANGE (UNIX_TIMESTAMP({$column}))" : "RANGE COLUMNS({$column})";

        return "PARTITION BY {$expr} (".implode(', ', $parts).')';
    }

    private function partitionClause(string $name, string $boundary, string $type): string
    {
        $value = $type === 'timestamp' ? "UNIX_TIMESTAMP('{$boundary}')" : "'{$boundary}'";

        return "PARTITION {$name} VALUES LESS THAN ({$value})";
    }

    /** @return array<int, object{name: string}> */
    public function getPartitions(string $table): array
    {
        // information_schema.partitions là metadata cấu trúc bảng dùng chung mọi tenant,
        // không có khái niệm restaurant_id/branch_id.
        return DB::select( // @bypass-tenant-check
            'SELECT PARTITION_NAME as name
             FROM information_schema.partitions
             WHERE table_schema = DATABASE() AND table_name = ? AND PARTITION_NAME IS NOT NULL
             ORDER BY PARTITION_ORDINAL_POSITION',
            [$table]
        );
    }

    /**
     * Bổ sung partition tương lai còn thiếu (REORGANIZE pFuture). Trả về số
     * partition mới đã thêm (0 nếu bảng chưa partition hoặc đã đủ).
     */
    public function ensureFuturePartitions(string $table, string $type, int $monthsForward): int
    {
        $existing = $this->getPartitions($table);

        if (empty($existing) || ! collect($existing)->contains('name', 'pFuture')) {
            return 0;
        }

        $monthly = collect($existing)->filter(fn ($p) => $p->name !== 'pFuture')->values();
        if ($monthly->isEmpty()) {
            return 0;
        }

        $lastName = $monthly->last()->name;
        [$year, $month] = array_map('intval', explode('_', substr($lastName, 1)));
        $cursor = Carbon::createFromDate($year, $month, 1)->addMonth();
        $target = now()->startOfMonth()->addMonths($monthsForward);

        $newParts = [];
        while ($cursor->lessThanOrEqualTo($target)) {
            $boundary = $cursor->copy()->addMonth()->format('Y-m-d');
            $newParts[] = $this->partitionClause('p'.$cursor->format('Y_m'), $boundary, $type);
            // Xem ghi chú trong buildInitialPartitionSql() — gán lại tường minh dù
            // $cursor ở đây tình cờ là Carbon (mutable) qua createFromDate(), để
            // không phụ thuộc ngầm vào việc import class nào.
            $cursor = $cursor->addMonth();
        }

        if (empty($newParts)) {
            return 0;
        }

        $newParts[] = 'PARTITION pFuture VALUES LESS THAN (MAXVALUE)';
        $sql = implode(', ', $newParts);
        DB::statement("ALTER TABLE {$table} REORGANIZE PARTITION pFuture INTO ({$sql})"); // @bypass-tenant-check: DDL cấu trúc bảng, không phải truy vấn dữ liệu tenant

        return count($newParts) - 1;
    }

    /**
     * DROP các partition cũ hơn $retentionMonths. Trả về danh sách tên
     * partition đã xoá.
     *
     * @return array<int, string>
     */
    public function pruneOldPartitions(string $table, int $retentionMonths): array
    {
        $cutoff = now()->subMonths($retentionMonths)->startOfMonth();
        $existing = $this->getPartitions($table);
        $toDrop = [];

        foreach ($existing as $partition) {
            if ($partition->name === 'pFuture') {
                continue;
            }

            $parts = explode('_', substr($partition->name, 1));
            if (count($parts) < 2) {
                continue;
            }

            [$year, $month] = array_map('intval', $parts);
            $partitionMonth = Carbon::createFromDate($year, $month, 1);

            if ($partitionMonth->lessThan($cutoff)) {
                $toDrop[] = $partition->name;
            }
        }

        if (empty($toDrop)) {
            return [];
        }

        $list = implode(', ', $toDrop);
        DB::statement("ALTER TABLE {$table} DROP PARTITION {$list}"); // @bypass-tenant-check: DDL cấu trúc bảng, không phải truy vấn dữ liệu tenant

        return $toDrop;
    }
}
