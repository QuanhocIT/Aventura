<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_discrepancy_disputes', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_discrepancy_disputes', 'claim_collected_amount')) {
                $table->decimal('claim_collected_amount', 15, 2)->nullable()->after('claim_status');
            }
            if (! Schema::hasColumn('inventory_discrepancy_disputes', 'claim_collected_at')) {
                $table->dateTime('claim_collected_at')->nullable()->after('claim_collected_amount');
            }
            if (! Schema::hasColumn('inventory_discrepancy_disputes', 'claim_collected_by')) {
                $table->foreignId('claim_collected_by')->nullable()->after('claim_collected_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('inventory_discrepancy_disputes', 'claim_notes')) {
                $table->text('claim_notes')->nullable()->after('claim_collected_by');
            }
        });

        Schema::table('shift_handovers', function (Blueprint $table): void {
            if (! Schema::hasColumn('shift_handovers', 'dispute_resolved_at')) {
                $table->dateTime('dispute_resolved_at')->nullable()->after('dispute_reason');
            }
            if (! Schema::hasColumn('shift_handovers', 'dispute_resolved_by')) {
                $table->foreignId('dispute_resolved_by')->nullable()->after('dispute_resolved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('shift_handovers', 'dispute_resolution_notes')) {
                $table->text('dispute_resolution_notes')->nullable()->after('dispute_resolved_by');
            }
            if (! Schema::hasColumn('shift_handovers', 'final_cash_amount')) {
                $table->decimal('final_cash_amount', 15, 2)->nullable()->after('dispute_resolution_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_discrepancy_disputes', function (Blueprint $table): void {
            $table->dropForeign(['claim_collected_by']);
            $table->dropColumn([
                'claim_collected_amount',
                'claim_collected_at',
                'claim_collected_by',
                'claim_notes',
            ]);
        });

        Schema::table('shift_handovers', function (Blueprint $table): void {
            $table->dropForeign(['dispute_resolved_by']);
            $table->dropColumn([
                'dispute_resolved_at',
                'dispute_resolved_by',
                'dispute_resolution_notes',
                'final_cash_amount',
            ]);
        });
    }
};
