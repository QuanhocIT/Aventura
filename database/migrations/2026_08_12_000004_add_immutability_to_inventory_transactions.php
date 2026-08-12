<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            // Mã chứng từ duy nhất (dạng TXN-20260812-0001)
            if (! Schema::hasColumn('inventory_transactions', 'document_code')) {
                $table->string('document_code', 50)->nullable()->unique()->after('id');
            }

            // Nguồn nghiệp vụ tạo ra giao dịch
            if (! Schema::hasColumn('inventory_transactions', 'source_type')) {
                $table->string('source_type', 40)->nullable()->after('document_code');
                // 'supply_request', 'purchase_order', 'inventory_count',
                // 'waste', 'transfer', 'reversal', 'manual_adjustment', 'order_consumption'
            }

            if (! Schema::hasColumn('inventory_transactions', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }

            // Số lượng tồn trước/sau giao dịch (audit trail)
            if (! Schema::hasColumn('inventory_transactions', 'quantity_before')) {
                $table->decimal('quantity_before', 12, 3)->default(0)->after('source_id');
            }

            if (! Schema::hasColumn('inventory_transactions', 'quantity_after')) {
                $table->decimal('quantity_after', 12, 3)->default(0)->after('quantity_before');
            }

            // Idempotency key: chống ghi trùng khi retry
            if (! Schema::hasColumn('inventory_transactions', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->unique()->after('quantity_after');
            }

            // Giao dịch đảo chiều (reversal)
            if (! Schema::hasColumn('inventory_transactions', 'is_reversal')) {
                $table->boolean('is_reversal')->default(false)->after('idempotency_key');
            }

            if (! Schema::hasColumn('inventory_transactions', 'reversed_transaction_id')) {
                $table->unsignedBigInteger('reversed_transaction_id')->nullable()->after('is_reversal');
            }

            // Lock sau khi kỳ kế toán đóng (chặn sửa/xóa)
            if (! Schema::hasColumn('inventory_transactions', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('reversed_transaction_id');
            }

            $table->index(['source_type', 'source_id']);
            $table->index(['restaurant_id', 'branch_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropForeign(['reversed_transaction_id']);
            $table->dropColumn([
                'document_code', 'source_type', 'source_id',
                'quantity_before', 'quantity_after',
                'idempotency_key', 'is_reversal', 'reversed_transaction_id', 'is_locked',
            ]);
        });
    }
};
