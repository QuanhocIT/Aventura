<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['restaurant_id', 'code'], 'restaurant_branches_restaurant_code_unique');
            $table->index(['restaurant_id', 'status'], 'restaurant_branches_restaurant_status_index');
            $table->index('manager_user_id', 'restaurant_branches_manager_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_branches');
    }
};
