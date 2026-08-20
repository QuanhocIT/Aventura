<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_batches', 'location_id')) {
            Schema::table('inventory_batches', function (Blueprint $table): void {
                $table->foreignId('location_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('warehouse_locations')
                    ->nullOnDelete();

                $table->index(['branch_id', 'location_id', 'ingredient_id'], 'inventory_batches_location_lookup_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_batches', 'location_id')) {
            Schema::table('inventory_batches', function (Blueprint $table): void {
                $table->dropForeign(['location_id']);
                $table->dropIndex('inventory_batches_location_lookup_index');
                $table->dropColumn('location_id');
            });
        }
    }
};
