<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Khôi phục AUTO_INCREMENT cho các bảng bị mất thuộc tính này.
 *
 * Nguyên nhân thường gặp: khôi phục database từ file .sql được xuất bằng công
 * cụ không giữ AUTO_INCREMENT, hoặc import bằng cách tạo bảng thủ công. Hậu quả
 * rất khó lần ra vì bảng vẫn còn khóa chính: mọi INSERT không kèm id đều lỗi
 * "Field 'id' doesn't have a default value", kể cả bảng `migrations` — khiến
 * không migrate được nữa.
 *
 * Lệnh chỉ sửa bảng có khóa chính đơn gồm đúng một cột `id` kiểu số nguyên.
 * Bảng khóa chính tổ hợp và bảng pivot được bỏ qua.
 */
class RepairAutoIncrement extends Command
{
    protected $signature = 'db:repair-auto-increment
                            {--dry-run : Chỉ liệt kê bảng hỏng, không sửa}';

    protected $description = 'Khôi phục AUTO_INCREMENT cho các bảng bị mất sau khi import database';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->warn('Lệnh này chỉ áp dụng cho MySQL/MariaDB.');

            return self::SUCCESS;
        }

        $broken = $this->findBrokenTables();

        if ($broken === []) {
            $this->info('Không có bảng nào thiếu AUTO_INCREMENT.');

            return self::SUCCESS;
        }

        $this->warn('Phát hiện '.count($broken).' bảng thiếu AUTO_INCREMENT.');

        if ($this->option('dry-run')) {
            foreach ($broken as $table) {
                $this->line('  - '.$table['name'].' ('.$table['type'].')');
            }

            return self::SUCCESS;
        }

        $repaired = 0;
        $failed = [];

        // Tắt kiểm tra khóa ngoại: nhiều bảng trong danh sách tham chiếu lẫn nhau
        // và MySQL không cho đổi định nghĩa cột đang được khóa ngoại trỏ tới.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($broken as $table) {
                $name = $table['name'];

                try {
                    DB::statement("ALTER TABLE `{$name}` MODIFY `id` {$table['type']} NOT NULL AUTO_INCREMENT");

                    $next = (int) DB::table($name)->max('id') + 1;
                    DB::statement("ALTER TABLE `{$name}` AUTO_INCREMENT = {$next}");

                    $repaired++;
                    $this->line("  <fg=green>✓</> {$name} (tiếp theo: {$next})");
                } catch (\Throwable $e) {
                    $failed[$name] = $e->getMessage();
                    $this->line("  <fg=red>✗</> {$name}: ".$e->getMessage());
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info("Đã sửa {$repaired}/".count($broken).' bảng.');

        if ($failed !== []) {
            $this->error('Còn '.count($failed).' bảng chưa sửa được — cần xử lý thủ công.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{name:string,type:string}>
     */
    private function findBrokenTables(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT c.TABLE_NAME AS table_name, c.COLUMN_TYPE AS column_type
            FROM information_schema.COLUMNS c
            JOIN information_schema.STATISTICS s
              ON  s.TABLE_SCHEMA = c.TABLE_SCHEMA
              AND s.TABLE_NAME   = c.TABLE_NAME
              AND s.COLUMN_NAME  = c.COLUMN_NAME
              AND s.INDEX_NAME   = 'PRIMARY'
            WHERE c.TABLE_SCHEMA = DATABASE()
              AND c.COLUMN_NAME  = 'id'
              AND c.DATA_TYPE IN ('int', 'bigint', 'smallint', 'mediumint')
              AND c.EXTRA NOT LIKE '%auto_increment%'
              -- chỉ bảng có khóa chính gồm đúng một cột
              AND (
                SELECT COUNT(*) FROM information_schema.STATISTICS s2
                WHERE s2.TABLE_SCHEMA = c.TABLE_SCHEMA
                  AND s2.TABLE_NAME   = c.TABLE_NAME
                  AND s2.INDEX_NAME   = 'PRIMARY'
              ) = 1
            ORDER BY c.TABLE_NAME
        SQL);

        return array_map(
            fn ($r) => ['name' => $r->table_name, 'type' => $r->column_type],
            $rows,
        );
    }
}
