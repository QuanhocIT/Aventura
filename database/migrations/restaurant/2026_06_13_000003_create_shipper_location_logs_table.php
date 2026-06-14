<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipper_location_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipper_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_kmh', 6, 2)->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['shipper_id', 'logged_at'], 'sll_shipper_time_idx');
            $table->index(['restaurant_id', 'logged_at'], 'sll_res_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipper_location_logs');
    }
};
