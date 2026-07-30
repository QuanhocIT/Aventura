<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_unanswered_queries', function (Blueprint $table) {
            $table->id();
            $table->text('query');                          // câu hỏi người dùng đặt
            $table->string('query_hash', 64)->unique();     // SHA256 hash của query để upsert
            $table->float('best_score', 8, 4)->default(0); // điểm similarity cao nhất (dưới threshold)
            $table->string('session_id', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32)->default('widget');
            $table->unsignedInteger('occurrence_count')->default(1); // đếm số lần hỏi cùng query
            $table->timestamp('last_seen_at');
            $table->boolean('is_resolved')->default(false); // đánh dấu đã xử lý
            $table->timestamps();

            $table->index(['is_resolved', 'last_seen_at']);
            $table->index('occurrence_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_unanswered_queries');
    }
};
