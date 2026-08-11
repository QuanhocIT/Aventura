<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đếm tiền mù: thu ngân nhập số đếm được TRƯỚC khi hệ thống lộ số kỳ vọng.
 *
 * Trước đây giao diện tự điền sẵn actual_cash = expected_cash, nên chênh lệch
 * luôn bằng 0 và việc chốt ca không phát hiện được thất thoát nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_counts')) {
            return;
        }

        Schema::create('cash_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->date('closing_date');
            $table->string('area_name', 150)->nullable();

            $table->foreignId('counted_by')->constrained('users')->cascadeOnDelete();
            // 1 = đếm lần đầu (mù), 2+ = đếm lại sau khi đã biết số kỳ vọng.
            // Mọi lần đếm đều giữ lại, không ghi đè.
            $table->unsignedTinyInteger('sequence')->default(1);

            // {"500000": 3, "200000": 5, ...} — nhập theo mệnh giá khó bịa cho
            // khớp hơn nhiều so với gõ thẳng một con số tổng.
            $table->json('denominations')->nullable();
            $table->decimal('total_counted', 12, 2);

            // Thời điểm hệ thống lộ số kỳ vọng, và số đã lộ. Sau mốc này số đếm
            // của lần này không sửa được nữa.
            $table->timestamp('expected_revealed_at')->nullable();
            $table->decimal('expected_cash_at_reveal', 12, 2)->nullable();

            $table->timestamp('counted_at');
            $table->timestamps();

            $table->index(['restaurant_id', 'branch_id', 'closing_date'], 'cash_counts_scope_index');
            $table->index(['restaurant_id', 'shift_id', 'closing_date'], 'cash_counts_shift_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
    }
};
