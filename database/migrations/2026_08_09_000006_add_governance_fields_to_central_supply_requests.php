<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('central_supply_requests', 'seal_code')) {
            Schema::table('central_supply_requests', function (Blueprint $table) {
                $table->string('seal_code', 100)->nullable()->after('status');
                $table->boolean('discrepancy_flag')->default(false)->after('seal_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('central_supply_requests', 'seal_code')) {
            Schema::table('central_supply_requests', function (Blueprint $table) {
                $table->dropColumn(['seal_code', 'discrepancy_flag']);
            });
        }
    }
};
