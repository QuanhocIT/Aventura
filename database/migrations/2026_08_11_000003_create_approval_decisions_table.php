<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ phê duyệt — bản ghi chỉ ghi thêm (append-only), không có route sửa/xóa.
 * Đây là nguồn dữ liệu cho màn hình hậu kiểm "Quản lý đã duyệt gì" của Chủ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_decisions')) {
            return;
        }

        Schema::create('approval_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decided_by_name');            // giữ tên tại thời điểm duyệt, phòng khi tài khoản bị xóa
            $table->string('decided_by_role', 50)->nullable();
            $table->string('decision', 20);               // approved | rejected | escalated | overridden | acknowledged
            $table->string('operation_type', 50);
            $table->decimal('amount_involved', 15, 2)->nullable();

            // Hạn mức đang áp dụng tại thời điểm ra quyết định — chính sách có thể đổi sau này.
            $table->string('authority_basis', 30);        // owner_inherent | policy_delegated | super_admin
            $table->json('policy_snapshot')->nullable();
            $table->text('reason')->nullable();

            // Hậu kiểm của Chủ doanh nghiệp.
            $table->timestamp('owner_reviewed_at')->nullable();
            $table->foreignId('owner_reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['restaurant_id', 'created_at'], 'approval_decisions_restaurant_time_index');
            $table->index(['restaurant_id', 'decided_by', 'created_at'], 'approval_decisions_actor_index');
            $table->index(['restaurant_id', 'operation_type', 'created_at'], 'approval_decisions_operation_index');
            $table->index('approval_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
    }
};
