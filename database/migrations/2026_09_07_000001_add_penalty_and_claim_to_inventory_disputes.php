<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_discrepancy_disputes', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_discrepancy_disputes', 'penalty_amount')) {
                $table->decimal('penalty_amount', 15, 2)->nullable()->after('financial_loss_amount');
            }
            if (! Schema::hasColumn('inventory_discrepancy_disputes', 'waived_amount')) {
                $table->decimal('waived_amount', 15, 2)->default(0)->after('penalty_amount');
            }
            if (! Schema::hasColumn('inventory_discrepancy_disputes', 'claim_status')) {
                $table->string('claim_status')->nullable()->after('status');
            }
            if (! Schema::hasColumn('inventory_discrepancy_disputes', 'write_off_transaction_id')) {
                $table->unsignedBigInteger('write_off_transaction_id')->nullable()->after('claim_status');
            }
            if (! Schema::hasColumn('inventory_discrepancy_disputes', 'write_off_at')) {
                $table->dateTime('write_off_at')->nullable()->after('write_off_transaction_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_discrepancy_disputes', function (Blueprint $table): void {
            $table->dropColumn([
                'penalty_amount',
                'waived_amount',
                'claim_status',
                'write_off_transaction_id',
                'write_off_at',
            ]);
        });
    }
};
