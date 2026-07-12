<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 50)->nullable();
            $table->decimal('quantity_remaining', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->date('purchased_at');
            $table->date('expiry_date')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->enum('status', ['active', 'depleted', 'expired'])->default('active');
            $table->timestamps();

            $table->index(['restaurant_id', 'ingredient_id', 'expiry_date']);
            $table->index(['restaurant_id', 'status', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
