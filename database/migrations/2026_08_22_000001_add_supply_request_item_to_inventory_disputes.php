<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('inventory_discrepancy_disputes', 'supply_request_item_id')) {
            return;
        }

        Schema::table('inventory_discrepancy_disputes', function (Blueprint $table): void {
            $table->foreignId('supply_request_item_id')
                ->nullable()
                ->after('supply_request_id')
                ->constrained('central_supply_request_items')
                ->nullOnDelete();
            $table->index(
                ['restaurant_id', 'supply_request_id', 'supply_request_item_id'],
                'inventory_disputes_request_item_index'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('inventory_discrepancy_disputes', 'supply_request_item_id')) {
            return;
        }

        Schema::table('inventory_discrepancy_disputes', function (Blueprint $table): void {
            $table->dropForeign(['supply_request_item_id']);
            $table->dropIndex('inventory_disputes_request_item_index');
            $table->dropColumn('supply_request_item_id');
        });
    }
};
