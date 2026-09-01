<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('central_supply_requests', 'transporter_id')) {
            return;
        }

        Schema::table('central_supply_requests', function (Blueprint $table): void {
            $table->foreignId('transporter_id')
                ->nullable()
                ->after('handover_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('delivery_confirmed_by')
                ->nullable()
                ->after('transporter_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('delivery_confirmed_at')->nullable()->after('delivery_confirmed_by');
            $table->text('delivery_confirmed_notes')->nullable()->after('delivery_confirmed_at');
            $table->index(['transporter_id', 'delivery_confirmed_at'], 'supply_requests_delivery_tracking_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('central_supply_requests', 'transporter_id')) {
            return;
        }

        Schema::table('central_supply_requests', function (Blueprint $table): void {
            $table->dropIndex('supply_requests_delivery_tracking_idx');
            $table->dropForeign(['transporter_id']);
            $table->dropForeign(['delivery_confirmed_by']);
            $table->dropColumn([
                'transporter_id',
                'delivery_confirmed_by',
                'delivery_confirmed_at',
                'delivery_confirmed_notes',
            ]);
        });
    }
};
