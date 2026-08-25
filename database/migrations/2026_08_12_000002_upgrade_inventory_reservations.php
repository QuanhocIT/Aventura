<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table) {
            // Liên kết với supply request (ngoài order)
            if (! Schema::hasColumn('inventory_reservations', 'supply_request_id')) {
                $table->foreignId('supply_request_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('central_supply_requests')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('inventory_reservations', 'quantity')) {
                $table->decimal('quantity', 12, 3)->default(0)->after('ingredient_id');
            }

            // Loại reservation: 'order' (dự trữ cho đơn bán) | 'supply_request' (giữ chỗ khi duyệt cấp phát)
            if (! Schema::hasColumn('inventory_reservations', 'reservation_type')) {
                $table->string('reservation_type', 30)->default('order')->after('supply_request_id');
            }

            // Thời điểm reservation tự hết hạn (null = không hết hạn)
            if (! Schema::hasColumn('inventory_reservations', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('reservation_type');
            }

            // Thời điểm reservation được giải phóng (khi xuất kho hoặc hủy)
            if (! Schema::hasColumn('inventory_reservations', 'released_at')) {
                $table->timestamp('released_at')->nullable()->after('expires_at');
            }

            // Người tạo reservation
            if (! Schema::hasColumn('inventory_reservations', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
            }

            // Chi nhánh nguồn (kho nào giữ hàng)
            if (! Schema::hasColumn('inventory_reservations', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('created_by')->constrained('restaurant_branches')->nullOnDelete();
            }

            $table->index(['ingredient_id', 'reservation_type', 'released_at'], 'inv_res_ing_type_rel_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->dropForeign(['supply_request_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['branch_id']);
            $table->dropIndex('inv_res_ing_type_rel_idx');
            $table->dropColumn(['supply_request_id', 'reservation_type', 'expires_at', 'released_at', 'created_by', 'branch_id']);
        });
    }
};
