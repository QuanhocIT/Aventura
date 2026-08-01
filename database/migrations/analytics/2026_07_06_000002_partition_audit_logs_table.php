<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MONTHS_BACK = 24;

    private const MONTHS_FORWARD = 6;

    /**
     * audit_logs là bảng append-only, tăng trưởng nhanh nhất trong hệ thống.
     * Cả 3 FK hiện có đều là nullOnDelete (liên kết lỏng, chỉ để tham chiếu,
     * không có cascade nghiệp vụ phụ thuộc) nên có thể drop an toàn để đổi
     * sang PARTITION BY RANGE COLUMNS(created_at) — InnoDB không cho phép
     * partition trên bảng có foreign key.
     */
    public function up(): void
    {
        // Partitioning là cú pháp riêng của MySQL/MariaDB — bỏ qua toàn bộ trên
        // sqlite (test suite), audit_logs giữ nguyên FK/PK gốc trong môi trường test.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['user_id']);
        });

        // MySQL yêu cầu cột partition (created_at) nằm trong mọi unique/primary key.
        // audit_logs.id vẫn là PRIMARY KEY đơn từ trước, phải đổi thành khóa kép.
        DB::statement('ALTER TABLE audit_logs DROP PRIMARY KEY, ADD PRIMARY KEY (id, created_at)');

        $partitions = $this->buildMonthlyPartitionSql(self::MONTHS_BACK, self::MONTHS_FORWARD);

        // RANGE COLUMNS không hỗ trợ kiểu TIMESTAMP (audit_logs.created_at là TIMESTAMP),
        // nên dùng RANGE theo UNIX_TIMESTAMP(created_at) thay vì RANGE COLUMNS.
        DB::statement("ALTER TABLE audit_logs PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) ({$partitions})");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE audit_logs REMOVE PARTITIONING');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropPrimary(['id', 'created_at']);
            $table->primary('id');

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('restaurant_branches')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    private function buildMonthlyPartitionSql(int $monthsBack, int $monthsForward): string
    {
        $start = now()->startOfMonth()->subMonths($monthsBack);
        $end = now()->startOfMonth()->addMonths($monthsForward);

        $parts = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $boundary = $cursor->copy()->addMonth()->format('Y-m-d');
            $name = 'p'.$cursor->format('Y_m');
            $parts[] = "PARTITION {$name} VALUES LESS THAN (UNIX_TIMESTAMP('{$boundary}'))";
            $cursor = $cursor->addMonth();
        }

        $parts[] = 'PARTITION pFuture VALUES LESS THAN (MAXVALUE)';

        return implode(', ', $parts);
    }
};
