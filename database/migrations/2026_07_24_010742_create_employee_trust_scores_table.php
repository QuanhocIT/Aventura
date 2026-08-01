<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_trust_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('restaurant_id')
                ->constrained()
                ->cascadeOnDelete();

            // Điểm tín nhiệm tổng hợp (0–100)
            $table->decimal('score', 5, 2)->default(100.00);

            // Chi tiết tính điểm: { cash_penalty: -15, fraud_flags: -10, ... }
            $table->json('score_breakdown')->nullable();

            // Bộ đếm từng loại vi phạm (30 ngày gần nhất)
            $table->unsignedSmallInteger('cash_shortfall_count')->default(0);
            $table->unsignedSmallInteger('fraud_flag_count')->default(0);
            $table->unsignedSmallInteger('cancel_flag_count')->default(0);
            $table->unsignedSmallInteger('offhour_login_count')->default(0);
            $table->unsignedSmallInteger('voucher_abuse_count')->default(0);

            // Tổng giá trị các khoản phạt đề xuất
            $table->decimal('total_penalty_amount', 12, 2)->default(0);

            // Ngày tính điểm lần cuối
            $table->date('last_calculated_at')->nullable();

            $table->timestamps();

            // Mỗi nhân viên chỉ có 1 bản ghi score / nhà hàng
            $table->unique(['employee_id', 'restaurant_id']);

            // Index phục vụ truy vấn "nhân viên rủi ro nhất" trên từng nhà hàng
            $table->index(['restaurant_id', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_trust_scores');
    }
};
