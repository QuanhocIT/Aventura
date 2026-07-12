<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_daily_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->date('checked_date')->index();
            $table->integer('orders_count')->default(0);
            $table->integer('dishes_prepared_count')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->boolean('had_activity')->default(false);
            $table->string('subscription_status', 50)->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();

            $table->unique(['restaurant_id', 'checked_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_daily_checks');
    }
};
