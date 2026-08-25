<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('central_supply_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('central_supply_request_items', 'non_fefo_reason')) {
                $table->text('non_fefo_reason')->nullable()->after('shortage_notes');
            }
            if (! Schema::hasColumn('central_supply_request_items', 'warehouse_location_id')) {
                $table->foreignId('warehouse_location_id')->nullable()->after('batch_id')
                    ->constrained('warehouse_locations')->nullOnDelete();
            }
        });

        Schema::table('central_supply_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('central_supply_requests', 'seal_photo_path')) {
                $table->string('seal_photo_path')->nullable()->after('seal_code');
            }
            if (! Schema::hasColumn('central_supply_requests', 'handover_photo_path')) {
                $table->string('handover_photo_path')->nullable()->after('seal_photo_path');
            }
            if (! Schema::hasColumn('central_supply_requests', 'carrier_name')) {
                $table->string('carrier_name')->nullable()->after('handover_photo_path');
            }
            if (! Schema::hasColumn('central_supply_requests', 'carrier_signature_path')) {
                $table->string('carrier_signature_path')->nullable()->after('carrier_name');
            }
            if (! Schema::hasColumn('central_supply_requests', 'package_count')) {
                $table->integer('package_count')->nullable()->after('carrier_signature_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('central_supply_requests', function (Blueprint $table) {
            $table->dropColumn([
                'seal_photo_path',
                'handover_photo_path',
                'carrier_name',
                'carrier_signature_path',
                'package_count',
            ]);
        });

        Schema::table('central_supply_request_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_location_id']);
            $table->dropColumn(['non_fefo_reason', 'warehouse_location_id']);
        });
    }
};
