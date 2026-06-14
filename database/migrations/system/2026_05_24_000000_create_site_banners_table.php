<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_banners', function (Blueprint $table) {
            $table->id();
            $table->string('slot');                          // 'hero' | 'promo'
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('image_path');                    // path trong public disk
            $table->string('link_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_banners');
    }
};
