<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('customer_name', 255);
            $table->string('phone', 20)->nullable();
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('estimated_distance_km', 8, 2)->nullable();
            $table->enum('delivery_status', ['pending', 'assigned', 'in_progress', 'delivered', 'failed'])->default('pending');
            $table->dateTime('estimated_delivery_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['restaurant_id', 'delivery_status'], 'delivery_details_restaurant_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_details');
    }
};
