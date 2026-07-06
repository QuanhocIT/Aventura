<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->unsignedInteger('custom_storage_limit_mb')->nullable()->after('status');
            $table->timestamp('storage_warning_sent_at')->nullable()->after('custom_storage_limit_mb');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['custom_storage_limit_mb', 'storage_warning_sent_at']);
        });
    }
};
