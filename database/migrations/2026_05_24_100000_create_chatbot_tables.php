<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('category')->index();
            $table->text('question');
            $table->text('answer');
            $table->json('alt_questions')->nullable();
            $table->json('keywords')->nullable();
            $table->json('suggested_questions')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('unhelpful_count')->default(0);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::create('chatbot_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('widget');
            $table->json('messages')->nullable();
            $table->tinyInteger('rating')->nullable();
            $table->timestamps();

            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_sessions');
        Schema::dropIfExists('chatbot_knowledge');
    }
};
