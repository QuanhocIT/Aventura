<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_transfer_requests', 'idempotency_key')) {
            Schema::table('stock_transfer_requests', function (Blueprint $table): void {
                $table->string('idempotency_key', 100)->nullable()->after('reason');
                $table->unique(['restaurant_id', 'idempotency_key'], 'stock_transfer_restaurant_idempotency_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_transfer_requests', 'idempotency_key')) {
            Schema::table('stock_transfer_requests', function (Blueprint $table): void {
                $table->dropUnique('stock_transfer_restaurant_idempotency_unique');
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
