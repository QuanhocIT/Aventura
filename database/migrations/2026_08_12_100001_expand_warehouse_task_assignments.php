<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_task_assignments', function (Blueprint $table) {
            // Thêm tracking lifecycle
            $table->dateTime('started_at')->nullable()->after('due_at');
            $table->dateTime('completed_at')->nullable()->after('started_at');
            $table->text('result_notes')->nullable()->after('notes');

            // Chứng từ: ảnh hóa đơn, ảnh hàng (JSON array of file paths)
            $table->json('evidence_paths')->nullable()->after('result_notes');

            // Log các lần quét mã: [{code, type, scanned_at, result}]
            $table->json('scan_log')->nullable()->after('evidence_paths');

            // Liên kết đa chiều — cất hàng/nhận hàng không cần gắn supply request
            $table->unsignedBigInteger('receiving_voucher_id')->nullable()->after('supply_request_id');
            $table->unsignedBigInteger('count_session_id')->nullable()->after('receiving_voucher_id');

            // Index bổ sung
            $table->index(['assigned_to', 'started_at']);
            $table->index(['restaurant_id', 'task_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_task_assignments', function (Blueprint $table) {
            $table->dropIndex(['assigned_to', 'started_at']);
            $table->dropIndex(['restaurant_id', 'task_type', 'status']);
            $table->dropColumn([
                'started_at',
                'completed_at',
                'result_notes',
                'evidence_paths',
                'scan_log',
                'receiving_voucher_id',
                'count_session_id',
            ]);
        });
    }
};
