<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_archive', function (Blueprint $table) {
            $table->json('archive_metadata')->nullable()->after('archived_at');
        });

        Schema::table('order_items_archive', function (Blueprint $table) {
            $table->json('archive_metadata')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders_archive', function (Blueprint $table) {
            $table->dropColumn('archive_metadata');
        });

        Schema::table('order_items_archive', function (Blueprint $table) {
            $table->dropColumn('archive_metadata');
        });
    }
};
