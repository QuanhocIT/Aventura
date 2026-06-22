<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('downtime_start');
            $table->dateTime('downtime_end');
            $table->json('services'); // list of service keys to put into maintenance (e.g. mysql, chatbot_service, etc.)
            $table->text('banner_message')->nullable();
            $table->enum('status', ['scheduled', 'active', 'completed', 'cancelled'])->default('scheduled');
            $table->boolean('notified_24h')->default(false);
            $table->boolean('notified_1h')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_maintenance_schedules');
    }
};
