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
        if (! Schema::hasTable('search_query_logs')) {
            Schema::create('search_query_logs', function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->string('keyword');
                $blueprint->string('index_name');
                $blueprint->decimal('latency_ms', 8, 2);
                $blueprint->unsignedBigInteger('restaurant_id')->nullable();
                $blueprint->unsignedBigInteger('user_id')->nullable();
                $blueprint->timestamps();

                $blueprint->index('index_name');
                $blueprint->index('keyword');
                $blueprint->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_query_logs');
    }
};
