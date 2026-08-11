<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hạn mức CHI TIÊU theo chi nhánh do Chủ đặt (theo tháng). Quản lý ghi chi phí không
 * được vượt hạn mức và (tuỳ cấu hình) BẮT BUỘC đính kèm hoá đơn. Chủ có thể vượt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_expense_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
            $table->date('effective_month'); // luôn là ngày đầu tháng
            $table->decimal('budget_amount', 14, 2);
            $table->boolean('require_receipt')->default(true);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'effective_month']);
            $table->index(['restaurant_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_expense_budgets');
    }
};
