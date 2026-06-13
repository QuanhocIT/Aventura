<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('delivery_batches')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('delivery_detail_id')->constrained('delivery_details')->restrictOnDelete();
            $table->unsignedTinyInteger('sequence_order')->default(1);
            $table->enum('status', ['pending', 'picked_up', 'delivered', 'failed'])->default('pending');
            $table->dateTime('picked_up_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['batch_id', 'sequence_order'], 'delivery_batch_items_batch_sequence_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_batch_items');
    }
};
