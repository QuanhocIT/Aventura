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
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence_order')->default(1);
            $table->enum('status', ['pending', 'picked_up', 'delivered', 'failed'])->default('pending');
            $table->timestamp('eta')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'sequence_order'], 'dbi_batch_seq_idx');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_batch_items');
    }
};
