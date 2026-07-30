<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // API key cho developer truy cập REST API công khai (chỉ đọc)
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('key_prefix', 12); // phần đầu key để nhận diện trên UI
            $table->string('key_hash', 64)->unique(); // sha256 của key đầy đủ
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('restaurant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
