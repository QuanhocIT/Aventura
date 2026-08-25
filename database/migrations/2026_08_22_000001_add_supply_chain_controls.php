<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            if (! Schema::hasColumn('ingredients', 'batch_tracking_required')) {
                $table->boolean('batch_tracking_required')->default(false)->after('status');
            }
            if (! Schema::hasColumn('ingredients', 'storage_temperature_min_c')) {
                $table->decimal('storage_temperature_min_c', 6, 2)->nullable()->after('batch_tracking_required');
            }
            if (! Schema::hasColumn('ingredients', 'storage_temperature_max_c')) {
                $table->decimal('storage_temperature_max_c', 6, 2)->nullable()->after('storage_temperature_min_c');
            }
            if (! Schema::hasColumn('ingredients', 'lead_time_days')) {
                $table->unsignedSmallInteger('lead_time_days')->default(0)->after('reorder_level');
            }
            if (! Schema::hasColumn('ingredients', 'safety_stock_quantity')) {
                $table->decimal('safety_stock_quantity', 12, 3)->default(0)->after('lead_time_days');
            }
        });

        Schema::create('ingredient_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->decimal('minimum_order_quantity', 12, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['ingredient_id', 'supplier_id'], 'ingredient_supplier_unique');
            $table->index(['restaurant_id', 'ingredient_id', 'is_active'], 'ingredient_supplier_lookup_index');
        });

        Schema::table('warehouse_receiving_vouchers', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'temperature_min_c')) {
                $table->decimal('temperature_min_c', 6, 2)->nullable()->after('quality_notes');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'temperature_max_c')) {
                $table->decimal('temperature_max_c', 6, 2)->nullable()->after('temperature_min_c');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'temperature_status')) {
                $table->string('temperature_status', 20)->default('not_recorded')->after('temperature_max_c');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'three_way_match_status')) {
                $table->string('three_way_match_status', 20)->default('not_applicable')->after('temperature_status');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'disposition')) {
                $table->string('disposition', 30)->default('pending')->after('three_way_match_status');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'disposition_reason')) {
                $table->text('disposition_reason')->nullable()->after('disposition');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'disposed_by')) {
                $table->foreignId('disposed_by')->nullable()->after('disposition_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'disposed_at')) {
                $table->timestamp('disposed_at')->nullable()->after('disposed_by');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'disposition_evidence_paths')) {
                $table->json('disposition_evidence_paths')->nullable()->after('disposed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_receiving_vouchers', function (Blueprint $table): void {
            $columns = collect([
                'temperature_min_c', 'temperature_max_c', 'temperature_status',
                'three_way_match_status', 'disposition', 'disposition_reason',
                'disposed_by', 'disposed_at', 'disposition_evidence_paths',
            ])->filter(fn (string $column): bool => Schema::hasColumn('warehouse_receiving_vouchers', $column))->values()->all();

            if (Schema::hasColumn('warehouse_receiving_vouchers', 'disposed_by')) {
                $table->dropForeign(['disposed_by']);
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('ingredient_suppliers');

        Schema::table('ingredients', function (Blueprint $table): void {
            $columns = collect([
                'batch_tracking_required', 'storage_temperature_min_c', 'storage_temperature_max_c',
                'lead_time_days', 'safety_stock_quantity',
            ])->filter(fn (string $column): bool => Schema::hasColumn('ingredients', $column))->values()->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
