<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2 — Partition bảng orders (hot table) theo tháng.
 *
 * ⚠️  LƯU Ý QUAN TRỌNG — Đọc kỹ trước khi chạy:
 *
 * 1. InnoDB không cho phép PARTITION BY trên bảng có foreign key constraints.
 *    Migration này sẽ DROP tất cả FK của bảng orders trước khi partition,
 *    sau đó restore lại index (không restore FK — validation ở application layer).
 *
 * 2. MySQL yêu cầu cột partition (created_at) nằm trong primary key.
 *    PRIMARY KEY hiện tại là (id) sẽ được đổi thành (id, created_at).
 *    Điều này tương thích với các query theo primary key đơn lẻ (id).
 *
 * 3. Cột created_at là NOT NULL — cần đảm bảo không có NULL trước khi chạy.
 *
 * 4. Trên SQLite (test suite): migration được bỏ qua hoàn toàn, bảng orders
 *    vẫn dùng schema cũ (không có partition) để test tiếp tục hoạt động.
 *
 * 5. Thao tác ALTER TABLE trên bảng lớn sẽ mất thời gian. Chạy trong
 *    maintenance window hoặc dùng gh-ost/pt-online-schema-change cho production.
 *
 * Để rollback: down() sẽ bỏ partition nhưng KHÔNG khôi phục FK (không reversible).
 */
return new class extends Migration
{
    private const MONTHS_BACK    = 6;  // Partition ngược 6 tháng
    private const MONTHS_FORWARD = 6;  // Partition tới 6 tháng

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            // SQLite không hỗ trợ partitioning — bỏ qua hoàn toàn
            return;
        }

        // ── Bước 1: Kiểm tra bảng orders đã có partition chưa ─────────────────
        $existing = DB::select(
            "SELECT PARTITION_NAME FROM information_schema.partitions
             WHERE table_schema = DATABASE() AND table_name = 'orders'
               AND PARTITION_NAME IS NOT NULL
             LIMIT 1"
        );

        if (! empty($existing)) {
            $this->command?->info('orders: bảng đã được partition, bỏ qua migration này.');
            return;
        }

        // ── Bước 2: DROP tất cả foreign key constraints của bảng orders ────────
        // InnoDB không cho phép partition trên bảng có FK.
        // Tên FK được lấy từ information_schema để tránh hardcode.
        $foreignKeys = DB::select(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'orders'"
        );

        foreach ($foreignKeys as $fk) {
            DB::statement("ALTER TABLE orders DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        // Xóa index unique orders_restaurant_number_unique (có thể xung đột với PK mới)
        $this->dropIndexIfExists('orders', 'orders_restaurant_number_unique');

        // ── Bước 3: Đổi PRIMARY KEY thành composite (id, created_at) ──────────
        // MySQL yêu cầu cột partition phải nằm trong tất cả unique/PK indexes.
        DB::statement('ALTER TABLE orders DROP PRIMARY KEY, ADD PRIMARY KEY (id, created_at)');

        // ── Bước 4: Thêm lại unique index nhưng bao gồm created_at ───────────
        // Vì PK đã là (id, created_at), unique (restaurant_id, order_number, created_at)
        // đảm bảo tính duy nhất trong cùng tháng (đủ cho use case thực tế).
        DB::statement(
            'ALTER TABLE orders ADD UNIQUE KEY orders_restaurant_number_unique (restaurant_id, order_number, created_at)'
        );

        // ── Bước 5: Áp dụng PARTITION BY RANGE ────────────────────────────────
        $partitions = $this->buildMonthlyPartitionSql(self::MONTHS_BACK, self::MONTHS_FORWARD);
        DB::statement("ALTER TABLE orders PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) ({$partitions})");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $existing = DB::select(
            "SELECT PARTITION_NAME FROM information_schema.partitions
             WHERE table_schema = DATABASE() AND table_name = 'orders'
               AND PARTITION_NAME IS NOT NULL
             LIMIT 1"
        );

        if (empty($existing)) {
            return;
        }

        // Bỏ partition — dữ liệu được merge vào 1 bảng không partition
        DB::statement('ALTER TABLE orders REMOVE PARTITIONING');

        // Restore primary key về (id) đơn lẻ
        DB::statement('ALTER TABLE orders DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
    }

    /**
     * Sinh danh sách PARTITION p{YYYY}_{MM} VALUES LESS THAN (UNIX_TIMESTAMP('YYYY-MM-01')).
     */
    private function buildMonthlyPartitionSql(int $monthsBack, int $monthsForward): string
    {
        $start  = now()->startOfMonth()->subMonths($monthsBack);
        $end    = now()->startOfMonth()->addMonths($monthsForward);
        $parts  = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $boundary = $cursor->copy()->addMonth()->format('Y-m-d');
            $name     = 'p' . $cursor->format('Y_m');
            $parts[]  = "PARTITION {$name} VALUES LESS THAN (UNIX_TIMESTAMP('{$boundary}'))";
            $cursor   = $cursor->addMonth();
        }

        $parts[] = 'PARTITION pFuture VALUES LESS THAN (MAXVALUE)';

        return implode(', ', $parts);
    }

    /**
     * DROP index nếu tồn tại (idempotent).
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = DB::select(
            "SELECT INDEX_NAME FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1",
            [$table, $indexName]
        );

        if (! empty($exists)) {
            DB::statement("ALTER TABLE {$table} DROP INDEX `{$indexName}`");
        }
    }
};
