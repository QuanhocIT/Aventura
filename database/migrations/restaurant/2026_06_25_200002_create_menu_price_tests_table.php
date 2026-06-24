<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_price_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('original_price', 12, 2);
            $table->decimal('test_price', 12, 2);
            $table->enum('status', ['draft', 'running', 'completed', 'cancelled'])->default('draft');
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->unsignedInteger('orders_original')->default(0);
            $table->unsignedInteger('orders_test')->default(0);
            $table->decimal('revenue_original', 12, 2)->default(0);
            $table->decimal('revenue_test', 12, 2)->default(0);
            $table->json('results_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_price_tests');
    }
};
