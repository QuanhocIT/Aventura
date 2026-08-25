<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Giới hạn lượt sử dụng — thiếu sót cơ bản của một hệ voucher: trước đây
     * một mã đã duyệt có thể bị áp vô hạn lần cho tới khi hết ngân sách (mà
     * ngân sách lại chưa có UI để nhập).
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedInteger('usage_limit')->nullable()->after('conditions');
            $table->unsignedInteger('usage_limit_per_customer')->nullable()->after('usage_limit');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['usage_limit', 'usage_limit_per_customer']);
        });
    }
};
