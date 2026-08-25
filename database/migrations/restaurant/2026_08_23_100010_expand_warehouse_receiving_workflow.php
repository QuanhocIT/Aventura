<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_receiving_vouchers', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'invoice_series')) {
                $table->string('invoice_series', 80)->nullable()->after('invoice_number');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'invoice_date')) {
                $table->date('invoice_date')->nullable()->after('invoice_series');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'invoice_total_amount')) {
                $table->decimal('invoice_total_amount', 14, 2)->default(0)->after('invoice_date');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'vat_amount')) {
                $table->decimal('vat_amount', 14, 2)->default(0)->after('invoice_total_amount');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'carrier_name')) {
                $table->string('carrier_name', 120)->nullable()->after('vehicle_number');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'receiving_dock')) {
                $table->string('receiving_dock', 60)->nullable()->after('carrier_name');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'storage_notes')) {
                $table->text('storage_notes')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'submitted_at')) {
                $table->dateTime('submitted_at')->nullable()->after('submitted_by');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'rejected_at')) {
                $table->dateTime('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'putaway_started_at')) {
                $table->dateTime('putaway_started_at')->nullable()->after('rejection_reason');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'putaway_completed_at')) {
                $table->dateTime('putaway_completed_at')->nullable()->after('putaway_started_at');
            }
        });

        Schema::table('inventory_batches', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_batches', 'stored_at')) {
                $table->dateTime('stored_at')->nullable()->after('purchased_at');
            }
        });

        Schema::table('warehouse_receiving_voucher_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_receiving_voucher_items', 'manufactured_date')) {
                $table->date('manufactured_date')->nullable()->after('expiry_date');
            }
        });

        if (! Schema::hasTable('warehouse_receiving_documents')) {
            Schema::create('warehouse_receiving_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('voucher_id')->constrained('warehouse_receiving_vouchers')->cascadeOnDelete();
                $table->string('document_type', 30)->default('other');
                $table->string('original_name', 255);
                $table->string('storage_path', 500);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('sha256', 64)->nullable();
                $table->foreignId('uploaded_by')->constrained('users');
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('verified_at')->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'document_type']);
                $table->index(['voucher_id', 'document_type']);
                $table->unique(['voucher_id', 'sha256'], 'warehouse_receiving_documents_voucher_hash_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_receiving_documents');

        Schema::table('inventory_batches', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_batches', 'stored_at')) {
                $table->dropColumn('stored_at');
            }
        });

        Schema::table('warehouse_receiving_voucher_items', function (Blueprint $table): void {
            if (Schema::hasColumn('warehouse_receiving_voucher_items', 'manufactured_date')) {
                $table->dropColumn('manufactured_date');
            }
        });

        Schema::table('warehouse_receiving_vouchers', function (Blueprint $table): void {
            foreach (['submitted_by', 'rejected_by'] as $foreign) {
                if (Schema::hasColumn('warehouse_receiving_vouchers', $foreign)) {
                    $table->dropForeign([$foreign]);
                }
            }

            $columns = collect([
                'invoice_series', 'invoice_date', 'invoice_total_amount', 'vat_amount',
                'carrier_name', 'receiving_dock', 'storage_notes', 'submitted_by',
                'submitted_at', 'rejected_by', 'rejected_at', 'rejection_reason',
                'putaway_started_at', 'putaway_completed_at',
            ])->filter(fn (string $column): bool => Schema::hasColumn('warehouse_receiving_vouchers', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
