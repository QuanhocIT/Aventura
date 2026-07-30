<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->string('category', 50)->default('general'); // service_plan, pos_system, customer_support, feature_request, general
            $table->text('content');
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->float('sentiment_score')->nullable();
            $table->string('status', 30)->default('pending'); // pending, reviewed, resolved
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['restaurant_id', 'created_at']);
            $table->index(['rating', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_feedbacks');
    }
};
