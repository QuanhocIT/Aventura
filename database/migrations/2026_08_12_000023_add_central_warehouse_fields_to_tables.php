<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('central_supply_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('central_supply_requests', 'is_emergency')) {
                $table->boolean('is_emergency')->default(false)->after('status');
            }
            if (! Schema::hasColumn('central_supply_requests', 'emergency_reason')) {
                $table->text('emergency_reason')->nullable()->after('is_emergency');
            }
            if (! Schema::hasColumn('central_supply_requests', 'parent_request_id')) {
                $table->foreignId('parent_request_id')->nullable()->after('id')->constrained('central_supply_requests')->onDelete('set null');
            }
            if (! Schema::hasColumn('central_supply_requests', 'min_shelflife_override_reason')) {
                $table->text('min_shelflife_override_reason')->nullable()->after('emergency_reason');
            }
        });

        Schema::table('warehouse_governance_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_governance_rules', 'cutoff_time')) {
                $table->string('cutoff_time', 10)->default('17:00')->after('require_seal_code_on_dispatch');
            }
            if (! Schema::hasColumn('warehouse_governance_rules', 'min_shelflife_percent')) {
                $table->decimal('min_shelflife_percent', 5, 2)->default(20.00)->after('cutoff_time'); // 20%
            }
            if (! Schema::hasColumn('warehouse_governance_rules', 'auto_reorder_enabled')) {
                $table->boolean('auto_reorder_enabled')->default(true)->after('min_shelflife_percent');
            }
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_batches', 'batch_code')) {
                $table->string('batch_code', 100)->nullable()->after('ingredient_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('central_supply_requests', function (Blueprint $table) {
            $table->dropForeign(['parent_request_id']);
            $table->dropColumn([
                'is_emergency',
                'emergency_reason',
                'parent_request_id',
                'min_shelflife_override_reason',
            ]);
        });

        Schema::table('warehouse_governance_rules', function (Blueprint $table) {
            $table->dropColumn([
                'cutoff_time',
                'min_shelflife_percent',
                'auto_reorder_enabled',
            ]);
        });
    }
};
