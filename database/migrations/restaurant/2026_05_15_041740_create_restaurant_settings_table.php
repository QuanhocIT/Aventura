<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->cascadeOnDelete();
            $table->string('key_name', 100);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'branch_id', 'key_name'], 'restaurant_settings_scope_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_settings');
    }
};
