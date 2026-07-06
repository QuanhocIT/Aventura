<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Số tháng partition dựng sẵn về quá khứ/tương lai khi tạo bảng.
     * `db:manage-partitions` sẽ tự bổ sung thêm partition tương lai định kỳ.
     */
    private const MONTHS_BACK = 24;
    private const MONTHS_FORWARD = 6;

    public function up(): void
    {
        // Không dùng foreignId()->constrained() ở đây: InnoDB không cho phép
        // PARTITION BY trên bảng có foreign key (dù là bảng cha hay con).
        // Đây là bảng lưu trữ lịch sử (archive), không tham gia cascade delete.
        Schema::create('orders_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id');
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('table_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('cashier_user_id')->nullable();
            $table->string('order_number', 50);
            $table->string('channel', 20)->default('dine_in');
            $table->string('status', 20)->default('completed');
            $table->string('payment_status', 20)->default('paid');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->text('refund_reason')->nullable();
            $table->text('note')->nullable();
            $table->text('online_notes')->nullable();
            $table->boolean('is_split')->default(false);
            $table->unsignedBigInteger('split_from_order_id')->nullable();
            $table->boolean('is_red_flagged')->default(false);
            $table->boolean('is_override_split_penalty')->default(false);
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedBigInteger('refunded_by')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('archived_at')->useCurrent();

            // MySQL yêu cầu cột partition (created_at) nằm trong mọi unique/primary key.
            $table->primary(['id', 'created_at']);
            $table->index(['restaurant_id', 'created_at'], 'orders_archive_restaurant_created_index');
        });

        Schema::create('order_items_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id');
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->string('status', 20)->default('served');
            $table->string('notes', 500)->nullable();
            $table->dateTime('sent_to_kitchen_at')->nullable();
            $table->dateTime('prepared_at')->nullable();
            $table->dateTime('served_at')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('archived_at')->useCurrent();

            $table->primary(['id', 'created_at']);
            $table->index(['order_id', 'created_at'], 'order_items_archive_order_created_index');
            $table->index(['restaurant_id', 'product_id'], 'order_items_archive_restaurant_product_index');
        });

        // PARTITION BY là cú pháp riêng của MySQL/MariaDB — bỏ qua trên sqlite (test suite),
        // bảng archive vẫn được tạo bình thường, chỉ không phân vùng.
        if (DB::connection()->getDriverName() === 'mysql') {
            $partitions = $this->buildMonthlyPartitionSql(self::MONTHS_BACK, self::MONTHS_FORWARD);

            // RANGE COLUMNS không hỗ trợ kiểu TIMESTAMP (chỉ DATE/DATETIME/số/chuỗi),
            // nên dùng RANGE theo UNIX_TIMESTAMP(created_at) thay vì RANGE COLUMNS.
            DB::statement("ALTER TABLE orders_archive PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) ({$partitions})");
            DB::statement("ALTER TABLE order_items_archive PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) ({$partitions})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items_archive');
        Schema::dropIfExists('orders_archive');
    }

    /**
     * Sinh danh sách PARTITION p{YYYY}_{MM} VALUES LESS THAN (UNIX_TIMESTAMP('YYYY-MM-01'))
     * theo tháng, cộng thêm 1 partition pFuture bắt mọi giá trị vượt biên (MAXVALUE).
     */
    private function buildMonthlyPartitionSql(int $monthsBack, int $monthsForward): string
    {
        $start = now()->startOfMonth()->subMonths($monthsBack);
        $end = now()->startOfMonth()->addMonths($monthsForward);

        $parts = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $boundary = $cursor->copy()->addMonth()->format('Y-m-d');
            $name = 'p' . $cursor->format('Y_m');
            $parts[] = "PARTITION {$name} VALUES LESS THAN (UNIX_TIMESTAMP('{$boundary}'))";
            $cursor = $cursor->addMonth();
        }

        $parts[] = 'PARTITION pFuture VALUES LESS THAN (MAXVALUE)';

        return implode(', ', $parts);
    }
};
