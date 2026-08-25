<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đánh dấu phiếu đếm đã được dùng cho phiếu chốt ca nào.
 *
 * Không có cột này thì một phiếu đếm cũ có thể bị dùng lại cho ca sau, và chế
 * độ đếm mù mất tác dụng từ ca thứ hai trở đi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_counts') || Schema::hasColumn('cash_counts', 'shift_closing_id')) {
            return;
        }

        Schema::table('cash_counts', function (Blueprint $table): void {
            $table->foreignId('shift_closing_id')->nullable()->after('area_name')
                ->constrained('shift_closings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cash_counts') || ! Schema::hasColumn('cash_counts', 'shift_closing_id')) {
            return;
        }

        Schema::table('cash_counts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shift_closing_id');
        });
    }
};
