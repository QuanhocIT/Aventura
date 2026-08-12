<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_receiving_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('warehouse_receiving_vouchers')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->unsignedBigInteger('location_id')->nullable()->index();
            $table->decimal('expected_qty', 12, 3)->default(0);
            $table->decimal('actual_qty', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('item_status', 30)->default('ok');
            $table->text('discrepancy_reason')->nullable();
            $table->string('expiry_date', 20)->nullable();
            $table->string('lot_number', 100)->nullable();
            $table->timestamps();
            $table->index(['voucher_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_receiving_voucher_items');
    }
};
