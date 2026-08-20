<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_receiving_vouchers', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'delivery_note_number')) {
                $table->string('delivery_note_number', 100)->nullable()->after('purchase_order_id');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'invoice_number')) {
                $table->string('invoice_number', 100)->nullable()->after('delivery_note_number');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'vehicle_number')) {
                $table->string('vehicle_number', 50)->nullable()->after('invoice_number');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'seal_code')) {
                $table->string('seal_code', 50)->nullable()->after('vehicle_number');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'quality_status')) {
                $table->string('quality_status', 20)->default('pending')->after('status');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'quality_notes')) {
                $table->text('quality_notes')->nullable()->after('quality_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_receiving_vouchers', function (Blueprint $table): void {
            $columns = collect([
                'delivery_note_number',
                'invoice_number',
                'vehicle_number',
                'seal_code',
                'quality_status',
                'quality_notes',
            ])->filter(fn (string $column): bool => Schema::hasColumn('warehouse_receiving_vouchers', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
