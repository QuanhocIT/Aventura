<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_transactions') || ! Schema::hasColumn('inventory_transactions', 'idempotency_key')) {
            return;
        }

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->unique(
                ['restaurant_id', 'idempotency_key'],
                'inventory_transactions_restaurant_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_transactions') || ! Schema::hasColumn('inventory_transactions', 'idempotency_key')) {
            return;
        }

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropUnique('inventory_transactions_restaurant_idempotency_unique');
            $table->unique('idempotency_key');
        });
    }
};
