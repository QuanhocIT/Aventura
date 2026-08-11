<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tạm ngưng bán món theo RIÊNG TỪNG CHI NHÁNH (không dùng cờ chung ở cấp món). Quản lý
 * chi nhánh khóa món chỉ ở chi nhánh mình; mở lại phải qua bước DUYỆT (owner/manager).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_branch_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('reason');
            $table->dateTime('paused_until')->nullable(); // null = cho đến khi mở lại
            $table->foreignId('paused_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', ['active', 'reopen_requested', 'reopened'])->default('active');
            $table->foreignId('reopen_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reopen_requested_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reopened_at')->nullable();

            $table->timestamps();
            $table->index(['restaurant_id', 'branch_id', 'product_id', 'status'], 'pbp_branch_product_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_branch_pauses');
    }
};
