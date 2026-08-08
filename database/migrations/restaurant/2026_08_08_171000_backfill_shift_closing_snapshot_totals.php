<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rows created before the single-slip format do not have the new
        // snapshot columns. Fill them from the legacy area totals so history
        // remains readable after the consolidation migration.
        DB::table('shift_closings')
            ->whereNull('period_start_at')
            ->orderBy('id')
            ->chunkById(200, function ($closings): void {
                foreach ($closings as $closing) {
                    $cashSales = (float) ($closing->cash_sales_amount ?? 0);
                    $cashSales = $cashSales > 0 ? $cashSales : (float) $closing->expected_cash;
                    $transfer = (float) ($closing->transfer_amount ?? 0);
                    $refunds = (float) ($closing->refunded_total_amount ?? 0);
                    $gross = max(0, $cashSales + $transfer - $refunds);

                    DB::table('shift_closings')
                        ->where('id', $closing->id)
                        ->update([
                            'cash_sales_amount' => $cashSales,
                            'actual_transfer_amount' => (float) ($closing->actual_transfer_amount ?? 0) > 0
                                ? (float) $closing->actual_transfer_amount
                                : $transfer,
                            'transfer_difference' => (float) ($closing->transfer_difference ?? 0),
                            'gross_revenue_amount' => $gross,
                            'discount_amount' => 0,
                            'net_revenue_amount' => $gross,
                            'total_difference' => (float) ($closing->cash_difference ?? 0),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Snapshot values are intentionally retained if this migration is
        // rolled back; the preceding migration owns the columns.
    }
};
