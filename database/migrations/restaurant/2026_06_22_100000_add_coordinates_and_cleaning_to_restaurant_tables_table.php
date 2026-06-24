<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->integer('x_pos')->default(50)->after('area_id');
            $table->integer('y_pos')->default(50)->after('x_pos');
            $table->string('status', 30)->default('available')->change();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropColumn(['x_pos', 'y_pos']);
        });
    }
};
