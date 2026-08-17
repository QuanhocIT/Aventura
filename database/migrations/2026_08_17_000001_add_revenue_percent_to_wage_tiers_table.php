<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wage_tiers') && ! Schema::hasColumn('wage_tiers', 'revenue_percent')) {
            Schema::table('wage_tiers', function (Blueprint $table) {
                $table->decimal('revenue_percent', 5, 2)->nullable()->after('rate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wage_tiers') && Schema::hasColumn('wage_tiers', 'revenue_percent')) {
            Schema::table('wage_tiers', function (Blueprint $table) {
                $table->dropColumn('revenue_percent');
            });
        }
    }
};
