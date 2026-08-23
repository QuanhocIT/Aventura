<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'reconciled_at')) {
                $table->dateTime('reconciled_at')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('payments', 'reconciled_by')) {
                $table->foreignId('reconciled_by')->nullable()->after('reconciled_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'reconciliation_note')) {
                $table->string('reconciliation_note', 255)->nullable()->after('reconciled_by');
            }

            $table->index(['restaurant_id', 'payment_method', 'reconciled_at'], 'payments_recon_scope_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_recon_scope_index');
            if (Schema::hasColumn('payments', 'reconciled_by')) {
                $table->dropForeign(['reconciled_by']);
            }
            $table->dropColumn(array_filter([
                Schema::hasColumn('payments', 'reconciled_at') ? 'reconciled_at' : null,
                Schema::hasColumn('payments', 'reconciled_by') ? 'reconciled_by' : null,
                Schema::hasColumn('payments', 'reconciliation_note') ? 'reconciliation_note' : null,
            ]));
        });
    }
};
