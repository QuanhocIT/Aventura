<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung index cho các truy vấn nóng còn thiếu (đã đối chiếu SHOW INDEX để không
 * thêm trùng). Đi kèm việc đổi whereDate → whereBetween ở cùng đợt: whereDate sinh
 * DATE(cot) = ? nên dù có index cũng không dùng được.
 */
return new class extends Migration
{
    public function up(): void
    {
        // buildStats() của module Giao hàng đếm delivered/failed theo ngày trên mỗi
        // lần tải trang + mỗi lần poll. Index cũ chỉ có (restaurant_id, delivery_status).
        Schema::table('delivery_details', function (Blueprint $table) {
            $table->index(['restaurant_id', 'delivery_status', 'delivered_at'], 'dd_res_status_delivered_idx');
        });

        // Phân khúc RFM/CDP lọc và sắp xếp khách theo lần mua gần nhất và tổng chi tiêu.
        Schema::table('customers', function (Blueprint $table) {
            $table->index(['restaurant_id', 'last_order_at'], 'customers_res_last_order_idx');
            $table->index(['restaurant_id', 'total_spent'], 'customers_res_total_spent_idx');
        });

        // Bảng lương: màn hình duyệt lương lọc theo kỳ lương của nhà hàng.
        Schema::table('salaries', function (Blueprint $table) {
            $table->index(['restaurant_id', 'pay_period_start'], 'salaries_res_period_idx');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_details', function (Blueprint $table) {
            $table->dropIndex('dd_res_status_delivered_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_res_last_order_idx');
            $table->dropIndex('customers_res_total_spent_idx');
        });

        Schema::table('salaries', function (Blueprint $table) {
            $table->dropIndex('salaries_res_period_idx');
        });
    }
};
