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
            $table->foreignId('shipper_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->enum('status', ['pending', 'dispatched', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->unsignedTinyInteger('total_orders')->default(0);
            $table->json('optimized_route')->nullable();
            $table->unsignedSmallInteger('estimated_duration_minutes')->nullable();
            $table->unsignedSmallInteger('total_distance_km')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status'], 'db_res_status_idx');
            $table->index(['shipper_id', 'status'], 'db_shipper_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_batches');
    }
};
