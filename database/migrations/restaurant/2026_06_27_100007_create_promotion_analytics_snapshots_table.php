<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('total_uses')->default(0);
            $table->decimal('total_discount_given', 12, 2)->default(0);
            $table->decimal('total_revenue_with_promo', 12, 2)->default(0);
            $table->unsignedInteger('unique_customers')->default(0);
            $table->unsignedInteger('new_customers_acquired')->default(0);
            $table->decimal('repeat_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->index(['restaurant_id', 'promotion_id', 'snapshot_date'], 'promo_analytics_rest_promo_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_analytics_snapshots');
    }
};
