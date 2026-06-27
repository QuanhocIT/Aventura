<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->boolean('sandbox_mode')->default(false)->after('status');
            $table->string('sandbox_template', 50)->nullable()->after('sandbox_mode');
            $table->timestamp('sandbox_seeded_at')->nullable()->after('sandbox_template');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['sandbox_mode', 'sandbox_template', 'sandbox_seeded_at']);
        });
    }
};
