<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bàn giao tiền cuối ca: người giao và người nhận cùng ký, kèm ảnh.
 *
 * Không có bước này thì tiền "biến mất" giữa hai ca mà không ai chịu trách nhiệm,
 * vì hệ thống chỉ biết ca trước đã chốt bao nhiêu chứ không biết ai đã nhận.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_handovers')) {
            return;
        }

        Schema::create('cash_handovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('shift_closing_id')->nullable()->constrained('shift_closings')->nullOnDelete();

            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);

            // Chữ ký lưu dạng ảnh (data URI đã ghi ra file), mỗi bên ký riêng.
            $table->string('from_signature_path', 500)->nullable();
            $table->string('to_signature_path', 500)->nullable();
            $table->timestamp('from_signed_at')->nullable();
            $table->timestamp('to_signed_at')->nullable();

            // Ảnh két/phong bì tiền tại thời điểm bàn giao.
            $table->string('photo_path', 500)->nullable();

            $table->string('status', 20)->default('pending'); // pending | completed | disputed
            $table->text('notes')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'branch_id', 'status'], 'cash_handovers_scope_index');
            $table->index('shift_closing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_handovers');
    }
};
