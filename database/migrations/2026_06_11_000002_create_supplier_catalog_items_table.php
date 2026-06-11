<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('packaging_spec')->nullable();
            $table->text('description')->nullable();
            $table->decimal('current_price', 12, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['restaurant_id', 'supplier_id', 'status']);
            $table->index('ingredient_id');
            $table->index('unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_catalog_items');
    }
};
