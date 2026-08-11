<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ SỰ CỐ KHẨN CẤP theo chi nhánh: tai nạn / ngộ độc / cháy nổ / an ninh / hỏng
 * thiết bị. Sự cố nghiêm trọng TỰ ĐỘNG báo lên Chủ (escalated). Bắt buộc có báo cáo
 * xử lý khi đóng. KHÔNG cho xoá (bằng chứng pháp lý) — khoá ở model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', [
                'accident', 'food_poisoning', 'fire', 'security',
                'equipment_failure', 'theft', 'other',
            ]);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->string('title');
            $table->text('description');
            $table->string('location')->nullable();
            $table->dateTime('occurred_at');
            $table->text('immediate_action')->nullable();   // xử lý ngay tại chỗ
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('injured_count')->default(0);
            $table->boolean('needs_shift_cover')->default(false); // cần thay ca gấp

            $table->enum('status', ['open', 'investigating', 'escalated', 'resolved'])->default('open');

            // Escalation lên Chủ
            $table->boolean('escalated')->default(false);
            $table->dateTime('escalated_at')->nullable();
            $table->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();

            // Tiếp nhận
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();

            // Đóng sự cố — bắt buộc báo cáo
            $table->text('resolution_report')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['restaurant_id', 'branch_id', 'status']);
            $table->index(['restaurant_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
