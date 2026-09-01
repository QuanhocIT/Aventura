<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_receiving_vouchers', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'external_receipt_reason')) {
                $table->string('external_receipt_reason', 40)->nullable()->after('branch_id');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'external_source_name')) {
                $table->string('external_source_name', 150)->nullable()->after('external_receipt_reason');
            }
            if (! Schema::hasColumn('warehouse_receiving_vouchers', 'external_reference')) {
                $table->string('external_reference', 100)->nullable()->after('external_source_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_receiving_vouchers', function (Blueprint $table): void {
            $columns = collect([
                'external_receipt_reason',
                'external_source_name',
                'external_reference',
            ])->filter(fn (string $column): bool => Schema::hasColumn('warehouse_receiving_vouchers', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
