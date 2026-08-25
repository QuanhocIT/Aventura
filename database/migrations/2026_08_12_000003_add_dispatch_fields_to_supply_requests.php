<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Thêm fields dispatch 2 lớp vào central_supply_requests ──────────────
        Schema::table('central_supply_requests', function (Blueprint $table) {
            // Người soạn hàng (warehouse_staff)
            if (! Schema::hasColumn('central_supply_requests', 'prepared_by')) {
                $table->foreignId('prepared_by')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('central_supply_requests', 'prepared_at')) {
                $table->timestamp('prepared_at')->nullable()->after('prepared_by');
            }

            // Người trưởng kho duyệt số lượng xuất (khác người soạn)
            if (! Schema::hasColumn('central_supply_requests', 'dispatch_approved_by')) {
                $table->foreignId('dispatch_approved_by')->nullable()->after('prepared_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('central_supply_requests', 'dispatch_approved_at')) {
                $table->timestamp('dispatch_approved_at')->nullable()->after('dispatch_approved_by');
            }

            // Người thực hiện bàn giao vật lý (khác người duyệt và soạn)
            if (! Schema::hasColumn('central_supply_requests', 'handover_by')) {
                $table->foreignId('handover_by')->nullable()->after('dispatch_approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('central_supply_requests', 'handover_at')) {
                $table->timestamp('handover_at')->nullable()->after('handover_by');
            }

            // Ảnh và chữ ký bàn giao / nhận hàng
            if (! Schema::hasColumn('central_supply_requests', 'receipt_photo_path')) {
                $table->string('receipt_photo_path')->nullable()->after('handover_at');
            }
            if (! Schema::hasColumn('central_supply_requests', 'receiver_signature_path')) {
                $table->string('receiver_signature_path')->nullable()->after('receipt_photo_path');
            }
            if (! Schema::hasColumn('central_supply_requests', 'received_notes')) {
                $table->text('received_notes')->nullable()->after('receiver_signature_path');
            }

            // Backorder: đơn bổ sung cho đơn cũ chưa giao đủ
            if (! Schema::hasColumn('central_supply_requests', 'backorder_of')) {
                $table->foreignId('backorder_of')->nullable()->after('received_notes')
                    ->references('id')->on('central_supply_requests')->nullOnDelete();
            }

            // Lý do hủy
            if (! Schema::hasColumn('central_supply_requests', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('backorder_of');
            }

            // Giới hạn định mức (snapshot lúc tạo đơn)
            if (! Schema::hasColumn('central_supply_requests', 'branch_monthly_limit_snapshot')) {
                $table->decimal('branch_monthly_limit_snapshot', 15, 2)->nullable()->after('cancel_reason');
            }

            // Tổng đã cấp phát trong tháng tính đến lúc tạo đơn này
            if (! Schema::hasColumn('central_supply_requests', 'branch_monthly_total_before')) {
                $table->decimal('branch_monthly_total_before', 15, 2)->default(0)->after('branch_monthly_limit_snapshot');
            }

            // Người yêu cầu vượt định mức (nếu có)
            if (! Schema::hasColumn('central_supply_requests', 'overlimit_reason')) {
                $table->text('overlimit_reason')->nullable()->after('branch_monthly_total_before');
            }
        });

        // ── Nâng cấp status ENUM (MySQL cần ALTER, SQLite bỏ qua) ───────────────
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE central_supply_requests MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        }

        // ── Nâng cấp supply request items ────────────────────────────────────────
        Schema::table('central_supply_request_items', function (Blueprint $table) {
            // Số lượng thực xuất (có thể khác approved_quantity)
            if (! Schema::hasColumn('central_supply_request_items', 'actual_dispatched_quantity')) {
                $table->decimal('actual_dispatched_quantity', 10, 3)->nullable()->after('approved_quantity');
            }

            // Lô hàng được chọn khi xuất (FEFO)
            if (! Schema::hasColumn('central_supply_request_items', 'batch_id')) {
                $table->foreignId('batch_id')->nullable()->after('actual_dispatched_quantity')
                    ->constrained('inventory_batches')->nullOnDelete();
            }

            // Ghi chú thiếu hụt từng dòng
            if (! Schema::hasColumn('central_supply_request_items', 'shortage_notes')) {
                $table->text('shortage_notes')->nullable()->after('batch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('central_supply_request_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn(['actual_dispatched_quantity', 'batch_id', 'shortage_notes']);
        });

        Schema::table('central_supply_requests', function (Blueprint $table) {
            $table->dropForeign(['prepared_by', 'dispatch_approved_by', 'handover_by', 'backorder_of']);
            $table->dropColumn([
                'prepared_by', 'prepared_at',
                'dispatch_approved_by', 'dispatch_approved_at',
                'handover_by', 'handover_at',
                'receipt_photo_path', 'receiver_signature_path', 'received_notes',
                'backorder_of', 'cancel_reason',
                'branch_monthly_limit_snapshot', 'branch_monthly_total_before', 'overlimit_reason',
            ]);
        });
    }
};
