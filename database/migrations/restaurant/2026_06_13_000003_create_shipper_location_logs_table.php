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
            $table->foreignId('shipper_id')->constrained('shippers')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_kmh', 6, 2)->nullable();
            $table->dateTime('logged_at');

            $table->index(['shipper_id', 'logged_at'], 'shipper_location_logs_shipper_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipper_location_logs');
    }
};
