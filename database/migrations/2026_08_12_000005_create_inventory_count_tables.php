<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Bảng phiên kiểm kê ────────────────────────────────────────────────────
        Schema::create('inventory_count_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();

            // Loại kiểm kê
            $table->string('type', 30)->default('periodic');
            // 'periodic'   = kiểm kê định kỳ
            // 'spot_check' = kiểm kê đột xuất
            // 'abc_cycle'  = chu kỳ theo nhóm ABC

            // Trạng thái phiên
            $table->string('status', 30)->default('draft');
            // 'draft' → 'in_progress' → 'pending_approval' → 'approved' / 'cancelled'

            // Kiểm đếm mù: không hiển thị số lượng dự kiến cho người đếm
            $table->boolean('blind_count')->default(false);

            // Người đếm 1 (bắt buộc)
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();

            // Người đếm 2 (xác nhận chéo, bắt buộc khi sai lệch > ngưỡng)
            $table->foreignId('second_counted_by')->nullable()->constrained('users')->nullOnDelete();

            // Người duyệt kết quả (phải khác người đếm)
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->text('notes')->nullable();

            // Ảnh/biên bản khi sai lệch lớn
            $table->string('variance_photo_path')->nullable();
            $table->string('variance_report_path')->nullable();

            // Tổng sai lệch giá trị (VND)
            $table->decimal('total_variance_value', 15, 2)->default(0);

            // Flag: cần phê duyệt owner (khi sai lệch vượt ngưỡng governance)
            $table->boolean('requires_owner_approval')->default(false);

            $table->timestamps();

            $table->index(['restaurant_id', 'branch_id', 'status']);
            $table->index(['restaurant_id', 'type', 'created_at']);
        });

        // ── Bảng các dòng kiểm kê ────────────────────────────────────────────────
        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('count_session_id')->constrained('inventory_count_sessions')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();

            // Lô hàng cụ thể (nullable nếu không theo dõi theo lô)
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();

            // Số lượng hệ thống ghi nhận (ẩn khi blind_count = true)
            $table->decimal('expected_quantity', 12, 3)->default(0);

            // Kết quả đếm của từng người
            $table->decimal('counted_quantity_1', 12, 3)->nullable();
            $table->decimal('counted_quantity_2', 12, 3)->nullable();

            // Số lượng cuối cùng được chấp nhận
            $table->decimal('final_quantity', 12, 3)->nullable();

            // Sai lệch = final_quantity - expected_quantity
            $table->decimal('variance_quantity', 12, 3)->default(0);
            $table->decimal('variance_percent', 8, 4)->default(0);

            // Giá trị sai lệch (VND)
            $table->decimal('variance_value', 15, 2)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['count_session_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_count_sessions');
    }
};
