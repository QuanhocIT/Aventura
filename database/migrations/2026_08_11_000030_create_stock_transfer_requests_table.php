<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yêu cầu điều chuyển hàng LIÊN CHI NHÁNH có định tuyến của Chủ + bàn giao hai bước.
 * Luồng: Quản lý chi nhánh thiếu TẠO yêu cầu → Chủ ĐỊNH TUYẾN chọn chi nhánh thừa +
 * sinh mã giao nhận → chi nhánh thừa XUẤT (trừ kho) → chi nhánh thiếu NHẬN bằng mã
 * (cộng kho). Người xuất và người nhận phải KHÁC nhau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            // Chi nhánh THIẾU (người yêu cầu). Chi nhánh THỪA do Chủ chọn khi định tuyến.
            $table->foreignId('to_branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
            $table->foreignId('from_branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();

            $table->decimal('quantity_requested', 12, 3);
            $table->decimal('quantity_dispatched', 12, 3)->nullable();
            $table->decimal('quantity_received', 12, 3)->nullable();
            $table->string('reason');

            $table->enum('status', ['requested', 'routed', 'dispatched', 'received', 'rejected', 'cancelled'])
                ->default('requested');

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('routed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('routed_at')->nullable();
            $table->string('owner_note')->nullable();

            $table->string('handover_code', 12)->nullable();

            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->string('reject_reason')->nullable();

            $table->timestamps();
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_requests');
    }
};
