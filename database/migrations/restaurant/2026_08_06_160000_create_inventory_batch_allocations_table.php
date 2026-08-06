<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batch_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('inventory_batch_id')->constrained('inventory_batches')->cascadeOnDelete();
            // inventory_transactions is partitioned in MySQL, so it cannot
            // be referenced by a foreign key. Keep the indexed ID for the
            // audit link and enforce the relationship in application code.
            $table->unsignedBigInteger('inventory_transaction_id');
            $table->enum('direction', ['in', 'out']);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['inventory_batch_id', 'direction'], 'inventory_batch_allocations_batch_direction_index');
            $table->index('inventory_transaction_id', 'inventory_batch_allocations_transaction_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batch_allocations');
    }
};
