<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('output_ingredient_id')->constrained('ingredients')->onDelete('cascade');
            $table->string('bom_code', 50)->unique();
            $table->string('name', 255);
            $table->decimal('standard_output_qty', 12, 4)->default(1.0000);
            $table->decimal('expected_yield_percent', 5, 2)->default(100.00); // 100%
            $table->decimal('allowed_wastage_percent', 5, 2)->default(5.00); // 5%
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('central_bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('central_bom_id')->constrained('central_boms')->onDelete('cascade');
            $table->foreignId('input_ingredient_id')->constrained('ingredients')->onDelete('cascade');
            $table->decimal('required_quantity', 12, 4);
            $table->string('unit_symbol', 30)->default('kg');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('restaurant_branches')->onDelete('cascade');
            $table->foreignId('central_bom_id')->nullable()->constrained('central_boms')->onDelete('set null');
            $table->string('work_order_code', 50)->unique();
            $table->foreignId('output_ingredient_id')->constrained('ingredients')->onDelete('cascade');
            $table->decimal('target_quantity', 12, 4);
            $table->decimal('actual_yield_quantity', 12, 4)->default(0.0000);
            $table->decimal('actual_wastage_quantity', 12, 4)->default(0.0000);
            $table->decimal('actual_yield_percent', 5, 2)->default(0.00);
            $table->string('status', 30)->default('draft'); // draft, in_progress, completed, cancelled
            $table->date('production_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('created_batch_code', 100)->nullable();
            $table->foreignId('created_batch_id')->nullable()->constrained('inventory_batches')->onDelete('set null');
            $table->foreignId('produced_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');
            $table->foreignId('input_ingredient_id')->constrained('ingredients')->onDelete('cascade');
            $table->decimal('planned_quantity', 12, 4);
            $table->decimal('actual_used_quantity', 12, 4);
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->onDelete('set null');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_items');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('central_bom_items');
        Schema::dropIfExists('central_boms');
    }
};
