<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->decimal('conversion_factor_to_base', 12, 6)
                ->default(1)
                ->after('type');
        });

        // Existing tenants did not have a conversion factor. Seed the common
        // culinary units without changing the meaning of custom units.
        DB::table('units')->whereIn('symbol', ['kg', 'KG', 'kilogram', 'Kilogram'])->update([
            'conversion_factor_to_base' => 1000,
        ]);
        DB::table('units')->whereIn('symbol', ['l', 'L', 'liter', 'Liter', 'lit'])->update([
            'conversion_factor_to_base' => 1000,
        ]);

        Schema::table('inventories', function (Blueprint $table): void {
            $table->timestamp('opening_balance_reconciled_at')->nullable()->after('last_counted_at');
            $table->foreignId('opening_balance_reconciled_by')->nullable()
                ->after('opening_balance_reconciled_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('inventory_batches', function (Blueprint $table): void {
            $table->timestamp('reconciled_at')->nullable()->after('status');
            $table->foreignId('reconciled_by')->nullable()
                ->after('reconciled_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('inventory_restored_at')->nullable()->after('refunded_by');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('unpaid','partial','paid','partial_refund','refunded') NOT NULL DEFAULT 'unpaid'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('unpaid','partial','paid','refunded') NOT NULL DEFAULT 'unpaid'");
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('inventory_restored_at');
        });

        Schema::table('inventory_batches', function (Blueprint $table): void {
            $table->dropForeign(['reconciled_by']);
            $table->dropColumn(['reconciled_at', 'reconciled_by']);
        });

        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropForeign(['opening_balance_reconciled_by']);
            $table->dropColumn(['opening_balance_reconciled_at', 'opening_balance_reconciled_by']);
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('conversion_factor_to_base');
        });
    }
};
