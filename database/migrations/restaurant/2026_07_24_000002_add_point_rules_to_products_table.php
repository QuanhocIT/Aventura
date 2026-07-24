<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'earn_points')) {
                $table->unsignedInteger('earn_points')->default(0)->after('price');
            }
            if (!Schema::hasColumn('products', 'redeem_points')) {
                $table->unsignedInteger('redeem_points')->default(0)->after('earn_points');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'earn_points')) {
                $table->dropColumn('earn_points');
            }
            if (Schema::hasColumn('products', 'redeem_points')) {
                $table->dropColumn('redeem_points');
            }
        });
    }
};
