<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_negative_case_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('negative_case_id')->constrained('inventory_negative_cases')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['restaurant_id', 'negative_case_id', 'created_at'], 'inventory_negative_case_events_timeline_index');
            $table->index(['restaurant_id', 'event_type', 'created_at'], 'inventory_negative_case_events_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_negative_case_events');
    }
};
