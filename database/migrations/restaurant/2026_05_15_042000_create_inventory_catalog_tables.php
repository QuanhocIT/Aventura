<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['restaurant_id', 'slug'], 'product_categories_restaurant_slug_unique');
            $table->index(['restaurant_id', 'status'], 'product_categories_restaurant_status_index');
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->nullable();
            $table->string('name', 50);
            $table->string('symbol', 20);
            $table->enum('type', ['mass', 'volume', 'count'])->default('count');
            $table->timestamps();

            $table->unique(['restaurant_id', 'symbol'], 'units_scope_symbol_unique');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['restaurant_id', 'status'], 'suppliers_restaurant_status_index');
            $table->index('branch_id', 'suppliers_branch_index');
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name');
            $table->string('sku', 100)->nullable();
            $table->string('category_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('min_stock_level', 12, 3)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->decimal('average_cost', 12, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['restaurant_id', 'sku'], 'ingredients_restaurant_sku_unique');
            $table->index(['restaurant_id', 'status'], 'ingredients_restaurant_status_index');
            $table->index('branch_id', 'ingredients_branch_index');
            $table->index('supplier_id', 'ingredients_supplier_index');
            $table->index('unit_id', 'ingredients_unit_index');
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->decimal('theoretical_quantity', 12, 3)->default(0);
            $table->dateTime('last_counted_at')->nullable();
            $table->decimal('last_cost', 12, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'ingredient_id'], 'inventories_branch_ingredient_unique');
            $table->index(['restaurant_id', 'ingredient_id'], 'inventories_restaurant_ingredient_index');
            $table->index('updated_by', 'inventories_updated_by_index');
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->enum('type', ['purchase', 'usage', 'adjustment', 'waste', 'return', 'stocktake']);
            $table->enum('direction', ['in', 'out']);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('reference_code', 100)->nullable();
            $table->string('invoice_file_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['restaurant_id', 'type', 'occurred_at'], 'inventory_transactions_restaurant_type_date_index');
            $table->index('order_id', 'inventory_transactions_order_index');
            $table->index('ingredient_id', 'inventory_transactions_ingredient_index');
            $table->index('inventory_id', 'inventory_transactions_inventory_index');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->unsignedInteger('preparation_time_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('track_inventory')->default(true);
            $table->timestamps();

            $table->unique(['restaurant_id', 'code'], 'products_restaurant_code_unique');
            $table->unique(['restaurant_id', 'slug'], 'products_restaurant_slug_unique');
            $table->index(['restaurant_id', 'is_active', 'is_available'], 'products_restaurant_status_index');
            $table->index('category_id', 'products_category_index');
            $table->index('branch_id', 'products_branch_index');
        });

        Schema::create('product_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('waste_rate', 5, 2)->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'ingredient_id'], 'product_recipes_product_ingredient_unique');
            $table->index(['restaurant_id', 'product_id'], 'product_recipes_restaurant_product_index');
            $table->index('unit_id', 'product_recipes_unit_index');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->string('full_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->dateTime('last_order_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'phone'], 'customers_restaurant_phone_index');
            $table->index('branch_id', 'customers_branch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('product_recipes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('units');
        Schema::dropIfExists('product_categories');
    }
};
