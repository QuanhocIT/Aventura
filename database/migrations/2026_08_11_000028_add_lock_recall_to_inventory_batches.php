<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Khóa lô & thu hồi: quản lý có thể KHÓA một lô nguyên liệu (không cho tiêu thụ nữa)
 * và GỬI YÊU CẦU kho thu hồi để xử lý. Lô 'locked'/'recalled' bị loại khỏi tiêu thụ
 * (consumeBatches chỉ lấy 'active'/'expired').
 */
return new class extends Migration
{
    public function up(): void
    {
        // Chỉ MySQL mới có ENUM ràng buộc; SQLite (test) lưu status là TEXT tự do
        // nên đã nhận 'locked'/'recalled' — không cần (và không thể) MODIFY.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_batches MODIFY status ENUM('active','depleted','expired','locked','recalled') NOT NULL DEFAULT 'active'");
        }

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->string('lock_reason')->nullable()->after('status');
            $table->foreignId('locked_by')->nullable()->after('lock_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->timestamp('recall_requested_at')->nullable()->after('locked_at');
            $table->foreignId('recall_requested_by')->nullable()->after('recall_requested_at')->constrained('users')->nullOnDelete();
            $table->string('recall_note')->nullable()->after('recall_requested_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropConstrainedForeignId('recall_requested_by');
            $table->dropColumn(['lock_reason', 'locked_at', 'recall_requested_at', 'recall_note']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_batches MODIFY status ENUM('active','depleted','expired') NOT NULL DEFAULT 'active'");
        }
    }
};
