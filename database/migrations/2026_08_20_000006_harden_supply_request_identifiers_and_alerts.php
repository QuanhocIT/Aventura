<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('central_supply_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('central_supply_requests', 'last_overdue_alert_at')) {
                $table->timestamp('last_overdue_alert_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('central_supply_requests', 'last_overdue_alert_stage')) {
                $table->string('last_overdue_alert_stage', 30)->nullable()->after('last_overdue_alert_at');
            }
        });

        DB::statement('UPDATE central_supply_requests SET parent_request_id = backorder_of WHERE parent_request_id IS NULL AND backorder_of IS NOT NULL');

        Schema::table('central_supply_requests', function (Blueprint $table): void {
            $table->dropUnique('central_supply_requests_request_code_unique');
            $table->unique(['restaurant_id', 'request_code'], 'central_supply_requests_restaurant_code_unique');
        });

        Schema::table('delivery_manifests', function (Blueprint $table): void {
            $table->dropUnique('delivery_manifests_manifest_code_unique');
            $table->unique(['restaurant_id', 'manifest_code'], 'delivery_manifests_restaurant_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('central_supply_requests', function (Blueprint $table): void {
            $table->dropUnique('central_supply_requests_restaurant_code_unique');
            $table->unique('request_code', 'central_supply_requests_request_code_unique');
        });

        Schema::table('delivery_manifests', function (Blueprint $table): void {
            $table->dropUnique('delivery_manifests_restaurant_code_unique');
            $table->unique('manifest_code', 'delivery_manifests_manifest_code_unique');
        });

        Schema::table('central_supply_requests', function (Blueprint $table): void {
            $columns = collect(['last_overdue_alert_at', 'last_overdue_alert_stage'])
                ->filter(fn ($column) => Schema::hasColumn('central_supply_requests', $column))
                ->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
