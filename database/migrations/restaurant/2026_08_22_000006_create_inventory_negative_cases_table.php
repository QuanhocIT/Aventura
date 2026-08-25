<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_negative_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->unsignedBigInteger('source_transaction_id')->nullable();

            // A case remains active until stock is replenished or an audited
            // adjustment brings the balance back to zero or above and a
            // responsible user confirms closure.
            $table->string('status', 20)->default('open');
            $table->decimal('negative_quantity', 12, 3)->default(0);
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->timestamp('detected_at')->nullable();
            $table->text('auto_plan')->nullable();
            $table->text('handling_plan')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expected_restock_at')->nullable();
            $table->string('resolution_type', 30)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'branch_id', 'status'], 'inventory_negative_cases_scope_status_index');
            $table->index(['restaurant_id', 'ingredient_id', 'status'], 'inventory_negative_cases_ingredient_status_index');
            $table->index('source_transaction_id', 'inventory_negative_cases_source_transaction_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_negative_cases');
    }
};
