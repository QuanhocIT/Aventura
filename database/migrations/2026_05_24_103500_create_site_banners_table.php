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
        if (Schema::hasTable('site_banners')) {
            return;
        }

        Schema::create('site_banners', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 32)->default('hero'); // hero | promo
            $table->string('image_path');
            $table->string('title', 120)->nullable();
            $table->string('subtitle', 200)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['slot', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_banners');
    }
};
