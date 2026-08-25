<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventory flows already use transfer and inventory_count in production
     * code. Keep the ledger extensible so a valid transaction cannot fail
     * because the initial enum list is stale.
     */
    public function up(): void
    {
        if (! Schema::hasTable('inventory_transactions')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inventory_transactions MODIFY type VARCHAR(30) NOT NULL');
        }
    }

    public function down(): void
    {
        // Deliberately do not shrink the column: existing transfer/count
        // ledger rows would make a rollback destructive.
    }
};
