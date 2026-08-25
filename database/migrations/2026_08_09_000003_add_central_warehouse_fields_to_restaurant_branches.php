<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('restaurant_branches', 'is_central_warehouse')) {
            Schema::table('restaurant_branches', function (Blueprint $table) {
                $table->boolean('is_central_warehouse')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('restaurant_branches', 'is_central_warehouse')) {
            Schema::table('restaurant_branches', function (Blueprint $table) {
                $table->dropColumn('is_central_warehouse');
            });
        }
    }
};
