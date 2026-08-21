<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('central_supply_request_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('central_supply_request_items', 'received_good_quantity')) {
                $table->decimal('received_good_quantity', 12, 3)->nullable()->after('received_quantity');
            }
            if (! Schema::hasColumn('central_supply_request_items', 'received_damaged_quantity')) {
                $table->decimal('received_damaged_quantity', 12, 3)->default(0)->after('received_good_quantity');
            }
            if (! Schema::hasColumn('central_supply_request_items', 'received_expired_quantity')) {
                $table->decimal('received_expired_quantity', 12, 3)->default(0)->after('received_damaged_quantity');
            }
            if (! Schema::hasColumn('central_supply_request_items', 'received_wrong_item_quantity')) {
                $table->decimal('received_wrong_item_quantity', 12, 3)->default(0)->after('received_expired_quantity');
            }
            if (! Schema::hasColumn('central_supply_request_items', 'received_condition')) {
                $table->string('received_condition', 30)->nullable()->after('received_wrong_item_quantity');
            }
            if (! Schema::hasColumn('central_supply_request_items', 'received_note')) {
                $table->text('received_note')->nullable()->after('received_condition');
            }
            if (! Schema::hasColumn('central_supply_request_items', 'received_evidence_path')) {
                $table->string('received_evidence_path', 500)->nullable()->after('received_note');
            }
            if (! Schema::hasColumn('central_supply_request_items', 'received_temperature_min_c')) {
                $table->decimal('received_temperature_min_c', 6, 2)->nullable()->after('received_evidence_path');
            }
            if (! Schema::hasColumn('central_supply_request_items', 'received_temperature_max_c')) {
                $table->decimal('received_temperature_max_c', 6, 2)->nullable()->after('received_temperature_min_c');
            }
            if (! Schema::hasColumn('central_supply_request_items', 'received_batch_id')) {
                $table->unsignedBigInteger('received_batch_id')->nullable()->after('received_temperature_max_c');
            }
            if (! Schema::hasColumn('central_supply_request_items', 'quarantine_id')) {
                $table->unsignedBigInteger('quarantine_id')->nullable()->after('received_batch_id');
            }
        });

        Schema::create('inventory_quarantines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('source_item_id')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->string('condition', 30)->default('damaged');
            $table->string('status', 30)->default('open');
            $table->string('reason', 500);
            $table->text('notes')->nullable();
            $table->json('evidence_paths')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->string('disposition', 30)->nullable();
            $table->text('disposition_reason')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status', 'branch_id'], 'inventory_quarantine_queue_index');
            $table->index(['source_type', 'source_id'], 'inventory_quarantine_source_index');
        });

        Schema::create('inventory_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('return_code', 40);
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('from_branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('status', 30)->default('requested');
            $table->string('reason', 500);
            $table->text('notes')->nullable();
            $table->json('evidence_paths')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'return_code'], 'inventory_returns_restaurant_code_unique');
            $table->index(['restaurant_id', 'status'], 'inventory_returns_queue_index');
            $table->index(['source_type', 'source_id'], 'inventory_returns_source_index');
        });

        Schema::create('inventory_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_id')->constrained('inventory_returns')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->foreignId('quarantine_id')->nullable()->constrained('inventory_quarantines')->nullOnDelete();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('received_quantity', 12, 3)->nullable();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('condition', 30)->default('damaged');
            $table->string('disposition', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('shipment_type', 40);
            $table->unsignedBigInteger('shipment_id');
            $table->string('event_type', 40);
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vehicle_number', 50)->nullable();
            $table->string('carrier_name', 150)->nullable();
            $table->string('seal_code', 100)->nullable();
            $table->decimal('temperature_min_c', 6, 2)->nullable();
            $table->decimal('temperature_max_c', 6, 2)->nullable();
            $table->string('evidence_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['restaurant_id', 'shipment_type', 'shipment_id'], 'warehouse_shipment_event_lookup_index');
            $table->index(['restaurant_id', 'event_type', 'occurred_at'], 'warehouse_shipment_event_queue_index');
        });

        Schema::create('supplier_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('carrier_name', 150)->nullable();
            $table->string('status', 30)->default('open');
            $table->string('reason', 500);
            $table->decimal('loss_amount', 15, 2)->default(0);
            $table->string('requested_action', 40)->nullable();
            $table->text('response_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->json('evidence_paths')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status'], 'supplier_claim_queue_index');
            $table->index(['source_type', 'source_id'], 'supplier_claim_source_index');
        });

        Schema::table('stock_transfer_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_transfer_requests', 'quantity_received_good')) {
                $table->decimal('quantity_received_good', 12, 3)->nullable()->after('quantity_received');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'quantity_received_damaged')) {
                $table->decimal('quantity_received_damaged', 12, 3)->default(0)->after('quantity_received_good');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'quantity_received_expired')) {
                $table->decimal('quantity_received_expired', 12, 3)->default(0)->after('quantity_received_damaged');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'source_batch_id')) {
                $table->unsignedBigInteger('source_batch_id')->nullable()->after('dispatch_unit_cost');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'destination_batch_id')) {
                $table->unsignedBigInteger('destination_batch_id')->nullable()->after('source_batch_id');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'quarantine_id')) {
                $table->unsignedBigInteger('quarantine_id')->nullable()->after('destination_batch_id');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'transport_temperature_min_c')) {
                $table->decimal('transport_temperature_min_c', 6, 2)->nullable()->after('quarantine_id');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'transport_temperature_max_c')) {
                $table->decimal('transport_temperature_max_c', 6, 2)->nullable()->after('transport_temperature_min_c');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'vehicle_number')) {
                $table->string('vehicle_number', 50)->nullable()->after('transport_temperature_max_c');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'carrier_name')) {
                $table->string('carrier_name', 150)->nullable()->after('vehicle_number');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'disposition')) {
                $table->string('disposition', 30)->nullable()->after('carrier_name');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'disposition_notes')) {
                $table->text('disposition_notes')->nullable()->after('disposition');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'disposition_evidence_path')) {
                $table->string('disposition_evidence_path', 500)->nullable()->after('disposition_notes');
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'disposition_by')) {
                $table->foreignId('disposition_by')->nullable()->after('disposition_evidence_path')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_transfer_requests', 'disposition_at')) {
                $table->dateTime('disposition_at')->nullable()->after('disposition_by');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_transfer_requests MODIFY status ENUM('requested','routed','dispatched','received','discrepancy','quarantined','return_requested','returned','destroyed','rejected','cancelled') NOT NULL DEFAULT 'requested'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_claims');
        Schema::dropIfExists('warehouse_shipment_events');
        Schema::dropIfExists('inventory_return_items');
        Schema::dropIfExists('inventory_returns');
        Schema::dropIfExists('inventory_quarantines');

        Schema::table('stock_transfer_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_transfer_requests', 'disposition_by')) {
                $table->dropForeign(['disposition_by']);
            }
            $columns = [
                'quantity_received_good', 'quantity_received_damaged', 'quantity_received_expired',
                'source_batch_id', 'destination_batch_id', 'quarantine_id',
                'transport_temperature_min_c', 'transport_temperature_max_c',
                'vehicle_number', 'carrier_name', 'disposition', 'disposition_notes',
                'disposition_evidence_path', 'disposition_by', 'disposition_at',
            ];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('stock_transfer_requests', $column)));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });

        Schema::table('central_supply_request_items', function (Blueprint $table): void {
            $columns = [
                'received_good_quantity', 'received_damaged_quantity', 'received_expired_quantity',
                'received_wrong_item_quantity', 'received_condition', 'received_note',
                'received_evidence_path', 'received_temperature_min_c', 'received_temperature_max_c',
                'received_batch_id', 'quarantine_id',
            ];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('central_supply_request_items', $column)));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_transfer_requests MODIFY status ENUM('requested','routed','dispatched','received','discrepancy','rejected','cancelled') NOT NULL DEFAULT 'requested'");
        }
    }
};
