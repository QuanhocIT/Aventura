<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('stock_transfer_requests', 'request_group_id')) {
            return;
        }

        Schema::table('stock_transfer_requests', function (Blueprint $table): void {
            $table->uuid('request_group_id')->nullable()->after('idempotency_key');
            $table->index(
                ['restaurant_id', 'request_group_id'],
                'stock_transfer_restaurant_request_group_index',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('stock_transfer_requests', 'request_group_id')) {
            return;
        }

        Schema::table('stock_transfer_requests', function (Blueprint $table): void {
            $table->dropIndex('stock_transfer_restaurant_request_group_index');
            $table->dropColumn('request_group_id');
        });
    }
};
