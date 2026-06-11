<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_catalog_item_id')
                ->nullable()
                ->constrained('supplier_catalog_items')
                ->nullOnDelete();
            $table->foreignId('ingredient_id')
                ->nullable()
                ->constrained('ingredients')
                ->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();

            $table->string('item_name');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->string('note')->nullable();

            $table->timestamps();

            $table->index(['restaurant_id', 'purchase_order_id']);
            $table->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
