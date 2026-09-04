<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_batch_lineages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('stock_transfer_request_id')->constrained('stock_transfer_requests')->cascadeOnDelete();
            $table->foreignId('source_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->foreignId('destination_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->unsignedBigInteger('inventory_transaction_id')->nullable();
            $table->foreignId('quarantine_id')->nullable()->constrained('inventory_quarantines')->nullOnDelete();
            $table->string('quality', 20)->default('good');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['restaurant_id', 'stock_transfer_request_id'], 'stock_transfer_lineage_transfer_index');
            $table->index(['source_batch_id', 'destination_batch_id'], 'stock_transfer_lineage_batch_index');
            $table->index('inventory_transaction_id', 'stock_transfer_lineage_tx_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_batch_lineages');
    }
};
