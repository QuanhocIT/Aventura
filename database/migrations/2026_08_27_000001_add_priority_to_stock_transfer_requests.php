<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('stock_transfer_requests', 'priority')) {
            return;
        }

        Schema::table('stock_transfer_requests', function (Blueprint $table): void {
            $table->string('priority', 20)->default('normal')->after('reason');
            $table->index(
                ['restaurant_id', 'priority', 'status'],
                'stock_transfer_priority_status_index',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('stock_transfer_requests', 'priority')) {
            return;
        }

        Schema::table('stock_transfer_requests', function (Blueprint $table): void {
            $table->dropIndex('stock_transfer_priority_status_index');
            $table->dropColumn('priority');
        });
    }
};
