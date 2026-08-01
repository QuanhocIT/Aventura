<?php

namespace Tests\Feature;

use App\Support\Partitioning\PartitionHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Hàng rào tự động cho quyết định partitioning — chỉ chạy thật trên MySQL
 * (bỏ qua trên SQLite mặc định, xem tests/phpunit.xml). CI "mysql-drift"
 * (.github/workflows/tests.yml) chạy lại TOÀN BỘ suite trên MySQL 8 thật nên
 * class này được thực thi ở đó, biến audit thủ công thành cổng chặn tự động:
 * bảng nào đăng ký trong config('partitioning.tables') mà migration quên
 * partition thật sự (hoặc bị FK mới chặn âm thầm) sẽ làm CI đỏ.
 */
class PartitionAuditTest extends TestCase
{
    use RefreshDatabase;

    private function skipIfNotMysql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Partitioning là DDL riêng MySQL — chỉ verify được trên phpunit.mysql.xml / CI mysql-drift.');
        }
    }

    public function test_every_registered_table_exists_and_is_actually_partitioned(): void
    {
        $this->skipIfNotMysql();

        $helper = new PartitionHelper;
        $config = config('partitioning');

        foreach ($config['tables'] as $table => $columnConfig) {
            $this->assertTrue(Schema::hasTable($table), "Bảng '{$table}' khai báo trong config(partitioning.tables) nhưng không tồn tại trong schema.");

            if ($helper->hasIncomingForeignKeys($table)) {
                $this->fail("Bảng '{$table}' đăng ký partition trong config nhưng có FK trỏ vào — sẽ bị migration tự bỏ qua. Gỡ khỏi config hoặc gỡ FK trước.");
            }

            $partitions = $helper->getPartitions($table);
            $this->assertNotEmpty($partitions, "Bảng '{$table}' đăng ký trong config(partitioning.tables) nhưng chưa được partition thật (kiểm tra migration đã chạy 2026_07_30_010000_partition_log_tables.php hoặc tương đương chưa).");
            $this->assertGreaterThanOrEqual(
                $config['months_back'] + $config['months_forward'],
                count($partitions) - 1, // trừ pFuture
                "Bảng '{$table}' thiếu partition so với months_back+months_forward cấu hình."
            );
        }
    }

    public function test_orders_and_salaries_are_deliberately_excluded_because_of_incoming_foreign_keys(): void
    {
        $this->skipIfNotMysql();

        $helper = new PartitionHelper;

        $this->assertArrayNotHasKey('orders', config('partitioning.tables'), "'orders' cố tình KHÔNG nằm trong config(partitioning.tables) vì còn nhiều bảng khác FK trỏ vào.");
        $this->assertArrayNotHasKey('salaries', config('partitioning.tables'), "'salaries' cố tình KHÔNG nằm trong config(partitioning.tables) vì salary_adjustments FK trỏ vào.");

        $this->assertTrue($helper->hasIncomingForeignKeys('orders'), 'Nếu FK trỏ vào orders đã bị gỡ hết, quyết định loại trừ orders khỏi partitioning cần được xem xét lại.');
        $this->assertTrue($helper->hasIncomingForeignKeys('salaries'), 'Nếu FK trỏ vào salaries đã bị gỡ hết, quyết định loại trừ salaries khỏi partitioning cần được xem xét lại.');
    }

    public function test_ensure_future_partitions_is_idempotent(): void
    {
        $this->skipIfNotMysql();

        $helper = new PartitionHelper;
        $config = config('partitioning');
        $table = array_key_first($config['tables']);
        $type = $config['tables'][$table]['type'];

        $added = $helper->ensureFuturePartitions($table, $type, $config['months_forward']);

        $this->assertSame(0, $added, "Gọi ensureFuturePartitions() lần 2 liên tiếp cho '{$table}' phải là no-op (partition tương lai đã đủ sẵn từ migration ban đầu).");
    }
}
