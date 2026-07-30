<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();

            // Giới hạn giảm giá
            $table->decimal('max_discount_percent_staff', 5, 2)->default(10);
            $table->decimal('max_discount_percent_manager', 5, 2)->default(30);

            // Giới hạn hủy đơn
            $table->decimal('max_cancel_amount_staff', 12, 2)->default(0);
            $table->decimal('max_cancel_amount_manager', 12, 2)->default(500000);

            // Giới hạn truy cập dữ liệu
            $table->boolean('staff_view_revenue')->default(false);
            $table->boolean('staff_view_salary')->default(false);
            $table->boolean('staff_view_cost_price')->default(false);
            $table->boolean('manager_view_other_salary')->default(false);

            // Giới hạn thời gian
            $table->boolean('restrict_to_shift_hours')->default(false);

            // Audit
            $table->boolean('audit_all_changes')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_policies');
    }
};
