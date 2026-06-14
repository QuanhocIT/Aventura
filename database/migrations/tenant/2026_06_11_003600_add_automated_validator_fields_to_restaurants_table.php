<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->timestamp('last_active_at')->nullable()->after('status');
            $table->boolean('is_inactive_flagged')->default(false)->after('last_active_at');
            $table->timestamp('inactive_flagged_at')->nullable()->after('is_inactive_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['last_active_at', 'is_inactive_flagged', 'inactive_flagged_at']);
        });
    }
};
