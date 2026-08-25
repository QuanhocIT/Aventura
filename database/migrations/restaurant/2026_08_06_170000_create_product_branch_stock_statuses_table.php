<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_branch_stock_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('available_portions')->default(0);
            $table->boolean('is_sold_out')->default(false);
            $table->timestamp('sold_out_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'branch_id', 'product_id'], 'product_branch_stock_status_unique');
            $table->index(['restaurant_id', 'branch_id', 'is_sold_out'], 'product_branch_stock_status_sold_out_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_branch_stock_statuses');
    }
};
