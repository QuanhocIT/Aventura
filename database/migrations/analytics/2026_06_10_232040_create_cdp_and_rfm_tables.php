<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table for digital footprints
        Schema::create('customer_behavior_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->index();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->string('event_type')->index(); // e.g. view_menu, view_product, add_to_cart, remove_from_cart, submit_order, payment_request
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->json('meta_data')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'event_type', 'created_at']);
        });

        // Table for RFM calculation caching
        Schema::create('customer_rfm_analysis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->integer('recency_days')->default(0);
            $table->integer('frequency_count')->default(0);
            $table->decimal('monetary_amount', 12, 2)->default(0.00);
            $table->unsignedTinyInteger('recency_score')->default(1);
            $table->unsignedTinyInteger('frequency_score')->default(1);
            $table->unsignedTinyInteger('monetary_score')->default(1);
            $table->string('rfm_score_code', 10)->default('111');
            $table->string('rfm_segment', 50)->default('New');
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'customer_id'], 'rfm_restaurant_customer_unique');
            $table->index(['restaurant_id', 'rfm_segment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_rfm_analysis');
        Schema::dropIfExists('customer_behavior_logs');
    }
};
