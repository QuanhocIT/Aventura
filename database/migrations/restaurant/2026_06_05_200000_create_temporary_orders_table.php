<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('temporary_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('table_id')->constrained('restaurant_tables')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete(); // links to real order once confirmed
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->enum('status', ['waiting_verification', 'escalated', 'confirmed', 'cancelled'])->default('waiting_verification');
            $table->json('cart_data'); // JSON payload of ordered items: product_id, quantity, unit_price, notes
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('notes', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status'], 'temp_orders_res_status_index');
            $table->index('table_id', 'temp_orders_table_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_orders');
    }
};
