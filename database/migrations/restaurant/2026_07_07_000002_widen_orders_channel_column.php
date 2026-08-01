<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Đổi orders.channel từ ENUM cố định sang VARCHAR để chứa các kênh mới
 * (reservation_deposit, grabfood, shopeefood...) mà không phải sửa enum
 * mỗi lần thêm kênh. ENUM cũ sẽ từ chối giá trị ngoài danh sách trên MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (môi trường test) lưu enum như text nên không cần đổi.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY channel VARCHAR(30) NOT NULL DEFAULT 'dine_in'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY channel ENUM('dine_in','takeaway','delivery','qr') NOT NULL DEFAULT 'dine_in'");
    }
};
