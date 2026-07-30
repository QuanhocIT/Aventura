<?php

use App\Support\Partitioning\PartitionHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Áp dụng đúng công thức đã chứng minh ở audit_logs
 * (2026_07_06_000002_partition_audit_logs_table.php) cho các bảng log tăng
 * trưởng vô hạn còn lại — trước đây KHÔNG bảng nào trong số này được partition,
 * mọi truy vấn theo khoảng ngày (báo cáo, dashboard, audit) quét toàn bảng dù
 * chỉ cần dữ liệu 1-3 tháng gần nhất.
 *
 * KHÔNG đụng tới `orders` (giữ 12 FK trỏ vào, theo quyết định) hay `salaries`
 * (salary_adjustments trỏ vào) — cả 2 bị migration tự bỏ qua nhờ
 * PartitionHelper::hasIncomingForeignKeys(), không nằm trong danh sách dưới đây.
 *
 * onDelete của từng cột lấy ĐÚNG theo information_schema.REFERENTIAL_CONSTRAINTS
 * hiện có (không giả định tất cả đều nullOnDelete — thực tế trộn lẫn cascade/null).
 */
return new class extends Migration
{
    private const TABLES = [
        'search_query_logs' => [],
        'notifications' => [],
        'customer_behavior_logs' => [
            'customer_id' => ['table' => 'customers', 'onDelete' => 'cascade'],
            'product_id' => ['table' => 'products', 'onDelete' => 'null'],
            'restaurant_id' => ['table' => 'restaurants', 'onDelete' => 'cascade'],
        ],
        'webhook_deliveries' => [
            'webhook_endpoint_id' => ['table' => 'webhook_endpoints', 'onDelete' => 'cascade'],
        ],
        'commission_logs' => [
            'billing_invoice_id' => ['table' => 'billing_invoices', 'onDelete' => 'null'],
            'buyer_id' => ['table' => 'users', 'onDelete' => 'cascade'],
            'restaurant_id' => ['table' => 'restaurants', 'onDelete' => 'null'],
            'user_id' => ['table' => 'users', 'onDelete' => 'cascade'],
        ],
        'loyalty_transactions' => [
            'customer_id' => ['table' => 'customers', 'onDelete' => 'cascade'],
            'performed_by' => ['table' => 'users', 'onDelete' => 'null'],
            'restaurant_id' => ['table' => 'restaurants', 'onDelete' => 'cascade'],
        ],
        'cash_transactions' => [
            'branch_id' => ['table' => 'restaurant_branches', 'onDelete' => 'null'],
            'cash_register_id' => ['table' => 'cash_registers', 'onDelete' => 'cascade'],
            'created_by' => ['table' => 'users', 'onDelete' => 'null'],
            'restaurant_id' => ['table' => 'restaurants', 'onDelete' => 'cascade'],
        ],
        'inventory_transactions' => [
            'branch_id' => ['table' => 'restaurant_branches', 'onDelete' => 'null'],
            'ingredient_id' => ['table' => 'ingredients', 'onDelete' => 'cascade'],
            'inventory_id' => ['table' => 'inventories', 'onDelete' => 'null'],
            'order_id' => ['table' => 'orders', 'onDelete' => 'null'],
            'performed_by' => ['table' => 'users', 'onDelete' => 'null'],
            'restaurant_id' => ['table' => 'restaurants', 'onDelete' => 'cascade'],
            'supplier_id' => ['table' => 'suppliers', 'onDelete' => 'null'],
        ],
    ];

    public function up(): void
    {
        // Partitioning là cú pháp riêng MySQL/MariaDB — bỏ qua trên sqlite (test suite).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $helper = new PartitionHelper;
        $config = config('partitioning');

        foreach (self::TABLES as $table => $foreignKeys) {
            $columnConfig = $config['tables'][$table] ?? null;
            if (! $columnConfig) {
                continue;
            }

            // Tự bảo vệ giống partition_orders_hot_table.php: bảng nào có FK
            // TRỎ VÀO nó thì bỏ qua, không lỗi cứng — hiện tại không bảng nào
            // trong danh sách này bị vướng (đã xác nhận qua information_schema),
            // nhưng giữ kiểm tra để an toàn nếu schema đổi trong tương lai.
            if ($helper->hasIncomingForeignKeys($table)) {
                continue;
            }

            $column = $columnConfig['column'];
            $type = $columnConfig['type'];

            if (! empty($foreignKeys)) {
                Schema::table($table, function (Blueprint $t) use ($foreignKeys) {
                    foreach (array_keys($foreignKeys) as $col) {
                        $t->dropForeign([$col]);
                    }
                });
            }

            // MySQL yêu cầu cột partition nằm trong mọi unique/primary key.
            DB::statement("ALTER TABLE {$table} DROP PRIMARY KEY, ADD PRIMARY KEY (id, {$column})");

            $partitionSql = $helper->buildInitialPartitionSql(
                $column,
                $type,
                $config['months_back'],
                $config['months_forward'],
            );
            DB::statement("ALTER TABLE {$table} {$partitionSql}");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $config = config('partitioning');
        $helper = new PartitionHelper;

        foreach (self::TABLES as $table => $foreignKeys) {
            $columnConfig = $config['tables'][$table] ?? null;
            if (! $columnConfig) {
                continue;
            }

            // Idempotent: nếu 1 lần chạy down() trước đó đã dừng giữa chừng
            // (bảng này đã bỏ partition rồi), chỉ REMOVE PARTITIONING khi bảng
            // thực sự đang partition — tránh lỗi 1505 "not a partitioned table".
            if (! empty($helper->getPartitions($table))) {
                DB::statement("ALTER TABLE {$table} REMOVE PARTITIONING");
            }

            // Không dùng Schema::table()+dropPrimary()/primary(): Laravel biên dịch
            // thành 2 câu ALTER TABLE riêng biệt, để lộ khoảnh khắc cột id
            // AUTO_INCREMENT không thuộc khoá nào — MySQL báo lỗi 1075. Phải gộp
            // DROP+ADD trong 1 câu lệnh nguyên tử duy nhất, giống up() đã làm.
            $pk = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', 'PRIMARY')
                ->count();
            if ($pk !== 1) {
                DB::statement("ALTER TABLE {$table} DROP PRIMARY KEY, ADD PRIMARY KEY (id)");
            }

            if (! empty($foreignKeys)) {
                Schema::table($table, function (Blueprint $t) use ($foreignKeys) {
                    foreach ($foreignKeys as $col => $meta) {
                        $fk = $t->foreign($col)->references('id')->on($meta['table']);
                        $meta['onDelete'] === 'cascade' ? $fk->cascadeOnDelete() : $fk->nullOnDelete();
                    }
                });
            }
        }
    }
};
