<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng "materialized view" tổng quát cho dữ liệu dạng chuỗi/mảng (biểu đồ,
 * top-N, ma trận...) — restaurant_revenue_summaries chỉ hợp với số tiền vô
 * hướng theo ngày/scope, KHÔNG hợp cho payload dạng mảng như chart 30 ngày.
 * Xem app/Support/MaterializedViews/Stores/JsonRollupStore.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->string('view_key', 64);
            $table->string('scope_key', 120)->default('restaurant');
            $table->date('period_start');
            $table->date('period_end')->nullable();
            $table->json('payload');
            $table->dateTime('calculated_at');
            $table->enum('source', ['system', 'manual', 'rebuild'])->default('system');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedTinyInteger('version')->default(1);
            $table->timestamps();

            $table->unique(
                ['restaurant_id', 'view_key', 'scope_key', 'period_start'],
                'analytics_rollups_unique_scope'
            );
            $table->index(['view_key', 'calculated_at'], 'analytics_rollups_view_calc_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_rollups');
    }
};
