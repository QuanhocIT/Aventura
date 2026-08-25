<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lưu vết từng lượt áp mã khuyến mãi vào đơn hàng.
     *
     * Trước đây việc "đã áp mã này chưa" được kiểm tra bằng str_contains trên
     * orders.note — nhân viên sửa ghi chú là bypass được, và không có cách nào
     * truy ra voucher nào tạo ra bao nhiêu doanh thu (snapshot analytics luôn
     * ghi promotion_id = null). Bảng này là nguồn sự thật cho cả hai việc đó.
     */
    public function up(): void
    {
        Schema::create('promotion_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('order_subtotal', 12, 2)->default(0);
            $table->boolean('used_bypass')->default(false);
            $table->timestamps();

            // Một đơn không thể áp cùng một mã hai lần.
            $table->unique(['order_id', 'promotion_id'], 'promotion_usages_order_promotion_unique');
            $table->index(['restaurant_id', 'promotion_id'], 'promotion_usages_restaurant_promotion_index');
            $table->index(['restaurant_id', 'created_at'], 'promotion_usages_restaurant_created_index');
            $table->index('branch_id', 'promotion_usages_branch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_usages');
    }
};
