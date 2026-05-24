<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->enum('status', ['trial', 'active', 'expired', 'cancelled'])->default('trial');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('renewal_at')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status'], 'restaurant_subscriptions_restaurant_status_index');
            $table->index('plan_id', 'restaurant_subscriptions_plan_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_subscriptions');
    }
};
