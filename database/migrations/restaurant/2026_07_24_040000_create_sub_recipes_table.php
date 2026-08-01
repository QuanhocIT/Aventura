<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ingredients', 'is_semi_finished')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->boolean('is_semi_finished')->default(false)->after('status');
            });
        }

        if (! Schema::hasTable('sub_recipes')) {
            Schema::create('sub_recipes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
                $table->foreignId('parent_ingredient_id')->constrained('ingredients')->onDelete('cascade');
                $table->foreignId('child_ingredient_id')->constrained('ingredients')->onDelete('cascade');
                $table->decimal('quantity', 12, 4);
                $table->decimal('waste_rate', 5, 2)->default(0);
                $table->timestamps();

                $table->unique(['parent_ingredient_id', 'child_ingredient_id'], 'sub_recipes_parent_child_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_recipes');

        if (Schema::hasColumn('ingredients', 'is_semi_finished')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->dropColumn('is_semi_finished');
            });
        }
    }
};
