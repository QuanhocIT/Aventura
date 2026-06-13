<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->integer('grace_period_minutes')->default(10)->after('timezone');
            $table->decimal('ot_multiplier', 3, 2)->default(1.50)->after('grace_period_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['grace_period_minutes', 'ot_multiplier']);
        });
    }
};
