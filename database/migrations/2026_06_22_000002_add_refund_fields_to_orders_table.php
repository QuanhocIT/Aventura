<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm các trường quản lý hoàn tiền vào bảng orders.
     * - refund_amount:  Số tiền hoàn trả
     * - refund_reason:  Lý do hoàn tiền (bắt buộc)
     * - refunded_at:    Thời điểm hoàn tiền
     * - refunded_by:    ID người xử lý hoàn tiền
     * Cập nhật payment_status ENUM để hỗ trợ 'refunded' và 'partial_refund'.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('refund_amount', 12, 2)->nullable()->after('total_amount');
            $table->text('refund_reason')->nullable()->after('refund_amount');
            $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
            $table->unsignedBigInteger('refunded_by')->nullable()->after('refunded_at');

            $table->foreign('refunded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['refunded_by']);
            $table->dropColumn(['refund_amount', 'refund_reason', 'refunded_at', 'refunded_by']);
        });
    }
};
