<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ingredient_price_histories')) {
            return;
        }

        // Lịch sử thay đổi đơn giá nguyên liệu
        Schema::create('ingredient_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();

            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2)->nullable();

            // Phần trăm thay đổi (+ tăng / - giảm)
            $table->decimal('change_percent', 8, 4)->default(0);

            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_reason')->nullable();

            // Tham chiếu đơn mua hàng gần nhất để so sánh
            $table->foreignId('reference_purchase_order_id')
                ->nullable()
                ->constrained('purchase_orders')
                ->nullOnDelete();

            // Cờ vượt ngưỡng (cần owner duyệt)
            $table->boolean('requires_owner_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 20)->default('approved');
            // 'pending_approval' | 'approved' | 'rejected'

            $table->timestamps();

            $table->index(['restaurant_id', 'ingredient_id', 'created_at'], 'ing_price_hist_rest_ing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_price_histories');
    }
};
