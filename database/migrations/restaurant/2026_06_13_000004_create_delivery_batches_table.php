<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipper_id')->constrained('shippers')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'dispatched', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->unsignedSmallInteger('total_orders')->default(0);
            $table->decimal('total_weight_kg', 8, 2)->default(0);
            $table->json('optimized_route')->nullable();
            $table->decimal('estimated_distance_km', 8, 2)->nullable();
            $table->unsignedSmallInteger('estimated_duration_minutes')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status'], 'delivery_batches_restaurant_status_index');
            $table->index(['shipper_id', 'status'], 'delivery_batches_shipper_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_batches');
    }
};
