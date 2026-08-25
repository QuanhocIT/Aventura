<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gắn nhân viên với bậc lương do Chủ quy định (wage_tiers). Khi tạo nhân viên qua
 * bậc lương, mức lương bị khoá theo bậc — Quản lý không tự nhập tuỳ ý.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('wage_tier_id')->nullable()->after('pay_rate')
                ->constrained('wage_tiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wage_tier_id');
        });
    }
};
