<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shippers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('vehicle_type', ['bike', 'motorbike', 'car'])->default('motorbike');
            $table->string('vehicle_plate', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('max_orders_per_batch')->default(5);
            $table->decimal('max_capacity_kg', 8, 2)->default(20);
            $table->timestamps();

            $table->unique('employee_id');
            $table->index(['restaurant_id', 'is_active'], 'shippers_restaurant_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shippers');
    }
};
