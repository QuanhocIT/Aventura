<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 100)->unique();
            $table->string('season', 50)->index();
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('discount_value', 12, 2)->default(10);
            $table->integer('default_duration_days')->default(7);
            $table->decimal('default_budget_cap', 12, 2)->nullable();
            $table->integer('default_max_uses')->nullable();
            $table->json('default_conditions')->nullable();
            $table->string('banner_image_url', 500)->nullable();
            $table->string('theme_color', 7)->nullable();
            $table->string('code_prefix', 10);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_templates');
    }
};
