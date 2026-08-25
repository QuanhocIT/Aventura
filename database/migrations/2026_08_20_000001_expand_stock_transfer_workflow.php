<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_transfer_requests MODIFY status ENUM('requested','routed','dispatched','received','discrepancy','rejected','cancelled') NOT NULL DEFAULT 'requested'");
        }

        Schema::table('stock_transfer_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_transfer_requests', 'dispatch_note')) {
                $table->text('dispatch_note')->nullable()->after('dispatched_at');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'dispatch_unit_cost')) {
                $table->decimal('dispatch_unit_cost', 12, 2)->nullable()->after('dispatch_note');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'received_condition')) {
                $table->string('received_condition', 30)->nullable()->after('received_at');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'received_note')) {
                $table->text('received_note')->nullable()->after('received_condition');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'receiving_evidence_path')) {
                $table->string('receiving_evidence_path', 500)->nullable()->after('received_note');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'discrepancy_quantity')) {
                $table->decimal('discrepancy_quantity', 12, 3)->default(0)->after('quantity_received');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'discrepancy_reason')) {
                $table->text('discrepancy_reason')->nullable()->after('receiving_evidence_path');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'discrepancy_resolution')) {
                $table->text('discrepancy_resolution')->nullable()->after('discrepancy_reason');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'discrepancy_resolved_by')) {
                $table->foreignId('discrepancy_resolved_by')->nullable()->after('discrepancy_resolution')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'discrepancy_resolved_at')) {
                $table->dateTime('discrepancy_resolved_at')->nullable()->after('discrepancy_resolved_by');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('reject_reason');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancel_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('cancelled_by');
            }

            $table->index(['restaurant_id', 'status', 'to_branch_id'], 'stock_transfer_status_destination_index');
            $table->index(['restaurant_id', 'status', 'from_branch_id'], 'stock_transfer_status_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_requests', function (Blueprint $table): void {
            $table->dropIndex('stock_transfer_status_destination_index');
            $table->dropIndex('stock_transfer_status_source_index');

            foreach (['discrepancy_resolved_by', 'cancelled_by'] as $foreign) {
                if (Schema::hasColumn('stock_transfer_requests', $foreign)) {
                    $table->dropForeign([$foreign]);
                }
            }

            $columns = [
                'dispatch_note',
                'dispatch_unit_cost',
                'received_condition',
                'received_note',
                'receiving_evidence_path',
                'discrepancy_quantity',
                'discrepancy_reason',
                'discrepancy_resolution',
                'discrepancy_resolved_by',
                'discrepancy_resolved_at',
                'cancel_reason',
                'cancelled_by',
                'cancelled_at',
            ];

            $existingColumns = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('stock_transfer_requests', $column)));
            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_transfer_requests MODIFY status ENUM('requested','routed','dispatched','received','rejected','cancelled') NOT NULL DEFAULT 'requested'");
        }
    }
};
