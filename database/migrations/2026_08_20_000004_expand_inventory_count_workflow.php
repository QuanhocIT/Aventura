<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_count_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_count_sessions', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'rejected_at')) {
                $table->dateTime('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('rejected_at');
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancel_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('cancelled_by');
            }
        });

        Schema::table('inventory_count_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_count_items', 'reconciliation_status')) {
                $table->string('reconciliation_status', 20)->default('not_required')->after('final_quantity');
            }
            if (! Schema::hasColumn('inventory_count_items', 'reconciliation_notes')) {
                $table->text('reconciliation_notes')->nullable()->after('reconciliation_status');
            }
            if (! Schema::hasColumn('inventory_count_items', 'reconciled_by')) {
                $table->foreignId('reconciled_by')->nullable()->after('reconciliation_notes')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('inventory_count_items', 'reconciled_at')) {
                $table->dateTime('reconciled_at')->nullable()->after('reconciled_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_count_items', function (Blueprint $table): void {
            $columns = collect([
                'reconciliation_status',
                'reconciliation_notes',
                'reconciled_by',
                'reconciled_at',
            ])->filter(fn (string $column): bool => Schema::hasColumn('inventory_count_items', $column))->all();

            if ($columns !== []) {
                if (Schema::hasColumn('inventory_count_items', 'reconciled_by')) {
                    $table->dropForeign(['reconciled_by']);
                }
                $table->dropColumn($columns);
            }
        });

        Schema::table('inventory_count_sessions', function (Blueprint $table): void {
            $columns = collect([
                'rejection_reason',
                'rejected_by',
                'rejected_at',
                'cancel_reason',
                'cancelled_by',
                'cancelled_at',
            ])->filter(fn (string $column): bool => Schema::hasColumn('inventory_count_sessions', $column))->all();

            if ($columns !== []) {
                if (Schema::hasColumn('inventory_count_sessions', 'rejected_by')) {
                    $table->dropForeign(['rejected_by']);
                }
                if (Schema::hasColumn('inventory_count_sessions', 'cancelled_by')) {
                    $table->dropForeign(['cancelled_by']);
                }
                $table->dropColumn($columns);
            }
        });
    }
};
