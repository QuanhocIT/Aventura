<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quỹ lương theo chi nhánh + bậc lương do CHỦ doanh nghiệp quy định.
 *
 * - wage_tiers: các mức lương cố định (theo giờ/ca/tháng) chủ đặt sẵn; Quản lý khi
 *   tạo nhân viên chỉ được CHỌN từ đây (không tự nhập mức tuỳ ý).
 * - branch_payroll_budgets: quỹ lương tháng cho từng chi nhánh; tổng lương nhân
 *   viên đang hoạt động của chi nhánh không được vượt quỹ này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wage_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->cascadeOnDelete();
            $table->string('name', 120);
            $table->enum('compensation_type', ['hourly', 'shift', 'fixed']); // fixed = lương tháng cố định (khớp employees)
            $table->decimal('rate', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['restaurant_id', 'branch_id', 'is_active'], 'wage_tiers_scope_index');
        });

        Schema::create('branch_payroll_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
            $table->date('effective_month'); // luôn là ngày 1 của tháng áp dụng
            $table->decimal('budget_amount', 14, 2)->default(0);
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'effective_month'], 'branch_payroll_budgets_month_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_payroll_budgets');
        Schema::dropIfExists('wage_tiers');
    }
};
