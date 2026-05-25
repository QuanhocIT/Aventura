<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Thêm 2 cột hỗ trợ QR code thật:
     * - qr_token: token ngắn dùng để build URL /order/{restaurantId}/{token}
     * - qr_code_path: đường dẫn file ảnh SVG lưu trong disk 'public'
     */
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            // Token duy nhất cho URL đặt món tại bàn (quét QR -> trỏ tới URL này)
            $table->string('qr_token', 64)->nullable()->after('qr_code');
            // Đường dẫn file SVG trong Storage::disk('public'), vd: qrcodes/restaurant_1_t1_abc123.svg
            $table->string('qr_code_path')->nullable()->after('qr_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropColumn(['qr_token', 'qr_code_path']);
        });
    }
};
