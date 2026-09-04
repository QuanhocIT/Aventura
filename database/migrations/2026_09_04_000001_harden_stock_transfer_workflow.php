<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfer_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_transfer_requests', 'backorder_quantity')) {
                $table->decimal('backorder_quantity', 12, 3)->default(0)->after('quantity_requested');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'shortage_quantity')) {
                $table->decimal('shortage_quantity', 12, 3)->default(0)->after('discrepancy_quantity');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'shortage_action')) {
                $table->string('shortage_action', 40)->nullable()->after('shortage_quantity');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'shortage_resolution')) {
                $table->text('shortage_resolution')->nullable()->after('shortage_action');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'shortage_resolved_by')) {
                $table->foreignId('shortage_resolved_by')->nullable()->after('shortage_resolution')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'shortage_resolved_at')) {
                $table->dateTime('shortage_resolved_at')->nullable()->after('shortage_resolved_by');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'dispatch_evidence_path')) {
                $table->string('dispatch_evidence_path', 500)->nullable()->after('dispatch_note');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'document_code')) {
                $table->string('document_code', 80)->nullable()->after('handover_code');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'sla_escalated_at')) {
                $table->dateTime('sla_escalated_at')->nullable()->after('disposition_at');
            }

            $table->index(['restaurant_id', 'status', 'sla_escalated_at'], 'stock_transfer_sla_queue_index');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_transfer_requests', 'shortage_resolved_by')) {
                $table->dropForeign(['shortage_resolved_by']);
            }
            if (Schema::hasColumn('stock_transfer_requests', 'sla_escalated_at')) {
                $table->dropIndex('stock_transfer_sla_queue_index');
            }

            $columns = [
                'backorder_quantity',
                'shortage_quantity',
                'shortage_action',
                'shortage_resolution',
                'shortage_resolved_by',
                'shortage_resolved_at',
                'dispatch_evidence_path',
                'document_code',
                'sla_escalated_at',
            ];
            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('stock_transfer_requests', $column),
            ));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
