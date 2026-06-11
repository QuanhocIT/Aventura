<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_price_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('supplier_catalog_item_id')
                ->constrained('supplier_catalog_items')
                ->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('old_price', 12, 2)->default(0);
            $table->decimal('new_price', 12, 2);
            $table->string('note')->nullable();
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->index(['restaurant_id', 'supplier_catalog_item_id', 'effective_at'], 'sph_restaurant_item_date_idx');
            $table->index(['supplier_id', 'effective_at'], 'sph_supplier_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_price_histories');
    }
};
