<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_count_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_count_sessions', 'period_start')) {
                $table->date('period_start')->nullable()->after('type');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'period_end')) {
                $table->date('period_end')->nullable()->after('period_start');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'total_expected_quantity')) {
                $table->decimal('total_expected_quantity', 15, 3)->default(0)->after('total_variance_value');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'total_counted_quantity')) {
                $table->decimal('total_counted_quantity', 15, 3)->default(0)->after('total_expected_quantity');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'total_expected_value')) {
                $table->decimal('total_expected_value', 15, 2)->default(0)->after('total_counted_quantity');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'total_counted_value')) {
                $table->decimal('total_counted_value', 15, 2)->default(0)->after('total_expected_value');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'total_shortage_quantity')) {
                $table->decimal('total_shortage_quantity', 15, 3)->default(0)->after('total_counted_value');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'total_surplus_quantity')) {
                $table->decimal('total_surplus_quantity', 15, 3)->default(0)->after('total_shortage_quantity');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'total_shortage_value')) {
                $table->decimal('total_shortage_value', 15, 2)->default(0)->after('total_surplus_quantity');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'total_surplus_value')) {
                $table->decimal('total_surplus_value', 15, 2)->default(0)->after('total_shortage_value');
            }

            $table->index(['restaurant_id', 'type', 'period_start', 'period_end'], 'inventory_count_period_index');
        });

        Schema::table('inventory_count_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_count_items', 'opening_quantity')) {
                $table->decimal('opening_quantity', 12, 3)->default(0)->after('expected_quantity');
            }
            if (! Schema::hasColumn('inventory_count_items', 'inbound_quantity')) {
                $table->decimal('inbound_quantity', 12, 3)->default(0)->after('opening_quantity');
            }
            if (! Schema::hasColumn('inventory_count_items', 'outbound_quantity')) {
                $table->decimal('outbound_quantity', 12, 3)->default(0)->after('inbound_quantity');
            }
            if (! Schema::hasColumn('inventory_count_items', 'inbound_value')) {
                $table->decimal('inbound_value', 15, 2)->default(0)->after('outbound_quantity');
            }
            if (! Schema::hasColumn('inventory_count_items', 'outbound_value')) {
                $table->decimal('outbound_value', 15, 2)->default(0)->after('inbound_value');
            }
            if (! Schema::hasColumn('inventory_count_items', 'unit_cost')) {
                $table->decimal('unit_cost', 15, 2)->default(0)->after('outbound_value');
            }
            if (! Schema::hasColumn('inventory_count_items', 'expected_value')) {
                $table->decimal('expected_value', 15, 2)->default(0)->after('unit_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_count_items', function (Blueprint $table): void {
            $columns = [
                'opening_quantity',
                'inbound_quantity',
                'outbound_quantity',
                'inbound_value',
                'outbound_value',
                'unit_cost',
                'expected_value',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('inventory_count_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('inventory_count_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_count_sessions', 'period_start')) {
                $table->dropIndex('inventory_count_period_index');
            }

            $columns = [
                'period_start',
                'period_end',
                'total_expected_quantity',
                'total_counted_quantity',
                'total_expected_value',
                'total_counted_value',
                'total_shortage_quantity',
                'total_surplus_quantity',
                'total_shortage_value',
                'total_surplus_value',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('inventory_count_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
