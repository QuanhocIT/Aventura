<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_supply_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_request_id')->constrained('central_supply_requests')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('requested_quantity', 12, 3);
            $table->decimal('approved_quantity', 12, 3)->nullable();
            $table->decimal('received_quantity', 12, 3)->nullable();
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->string('unit_symbol')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('supply_request_id');
            $table->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_supply_request_items');
    }
};
