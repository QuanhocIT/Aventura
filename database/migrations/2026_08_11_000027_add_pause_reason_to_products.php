<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lý do tạm ngưng phục vụ món — để hiển thị cho bếp/quản lý biết vì sao món bị khoá,
 * và ghi vào nhật ký khi tạm ngưng/mở lại (truy vết trách nhiệm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('pause_reason')->nullable()->after('paused_until');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('pause_reason');
        });
    }
};
