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
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('phone', 20);
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->enum('delivery_status', ['pending', 'assigned', 'picked_up', 'delivered', 'failed'])->default('pending');
            $table->text('delivery_notes')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'delivery_status'], 'dd_res_status_idx');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_details');
    }
};
