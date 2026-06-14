<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('po_number', 50)->unique();
            $table->enum('status', [
                'pending_approval',
                'approved',
                'preparing',
                'shipping',
                'delivered',
                'frozen',
                'cancelled'
            ])->default('pending_approval');
            $table->decimal('total_amount', 12, 2);
            $table->decimal('invoice_total_amount', 12, 2)->default(0);
            $table->string('invoice_file_url', 2048)->nullable();
            $table->boolean('is_frozen')->default(false);
            $table->json('discrepancy_details')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['restaurant_id', 'status'], 'po_restaurant_status_index');
            $table->index('supplier_id', 'po_supplier_index');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity_ordered', 12, 3);
            $table->decimal('quantity_received', 12, 3)->default(0);
            $table->decimal('price_per_unit', 12, 2);
            $table->decimal('invoice_price_per_unit', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
