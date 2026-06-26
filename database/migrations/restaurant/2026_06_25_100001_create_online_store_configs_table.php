<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_store_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(false);
            $table->string('slug', 100)->unique();
            $table->string('banner_url', 500)->nullable();
            $table->text('description')->nullable();
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->decimal('delivery_fee_per_km', 10, 2)->default(5000);
            $table->decimal('delivery_base_fee', 10, 2)->default(15000);
            $table->decimal('max_delivery_km', 5, 1)->default(10.0);
            $table->boolean('enable_takeaway')->default(true);
            $table->boolean('enable_delivery')->default(true);
            $table->boolean('enable_preorder')->default(false);
            $table->json('accepted_payments')->nullable();
            $table->json('operating_hours')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_store_configs');
    }
};
