<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('order_items', 'client_item_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('client_item_id', 100)->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'client_item_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('client_item_id');
            });
        }
    }
};
