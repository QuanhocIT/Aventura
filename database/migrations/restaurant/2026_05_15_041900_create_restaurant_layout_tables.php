<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50);
            $table->unsignedInteger('display_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['branch_id', 'code'], 'areas_branch_code_unique');
            $table->index(['restaurant_id', 'status'], 'areas_restaurant_status_index');
        });

        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('qr_code')->nullable();
            $table->unsignedInteger('capacity')->default(2);
            $table->enum('status', ['available', 'occupied', 'reserved', 'inactive'])->default('available');
            $table->timestamps();

            $table->unique(['area_id', 'name'], 'restaurant_tables_area_name_unique');
            $table->index(['restaurant_id', 'status'], 'restaurant_tables_restaurant_status_index');
            $table->index('branch_id', 'restaurant_tables_branch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('areas');
    }
};
