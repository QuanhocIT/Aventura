<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('display_order')->default(0)->after('is_featured');
            $table->string('time_slot', 20)->nullable()->after('display_order');
            $table->string('season', 20)->nullable()->after('time_slot');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['display_order', 'time_slot', 'season']);
        });
    }
};
