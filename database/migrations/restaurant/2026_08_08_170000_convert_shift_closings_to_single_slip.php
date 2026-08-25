<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            $table->dateTime('period_start_at')->nullable()->after('closing_date');
            $table->decimal('cash_sales_amount', 12, 2)->default(0)->after('expected_cash');
            $table->decimal('actual_transfer_amount', 12, 2)->default(0)->after('transfer_amount');
            $table->decimal('transfer_difference', 12, 2)->default(0)->after('actual_transfer_amount');
            $table->decimal('gross_revenue_amount', 12, 2)->default(0)->after('transfer_difference');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('gross_revenue_amount');
            $table->decimal('net_revenue_amount', 12, 2)->default(0)->after('discount_amount');
            $table->decimal('total_difference', 12, 2)->default(0)->after('net_revenue_amount');
            $table->decimal('responsibility_amount', 12, 2)->nullable()->after('total_difference');
            $table->text('responsibility_note')->nullable()->after('responsibility_amount');
        });

        // Older versions stored one row per area. Consolidate those rows before
        // restoring the invariant: one restaurant/branch/shift/date = one slip.
        DB::transaction(function (): void {
            $groups = DB::table('shift_closings')
                ->select(['restaurant_id', 'branch_id', 'shift_id', 'closing_date'])
                ->groupBy(['restaurant_id', 'branch_id', 'shift_id', 'closing_date'])
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($groups as $group) {
                $rows = DB::table('shift_closings')
                    ->where('restaurant_id', $group->restaurant_id)
                    ->where('shift_id', $group->shift_id)
                    ->whereDate('closing_date', $group->closing_date)
                    ->when(
                        is_null($group->branch_id),
                        fn ($query) => $query->whereNull('branch_id'),
                        fn ($query) => $query->where('branch_id', $group->branch_id),
                    )
                    ->orderBy('id')
                    ->get();

                $keeper = $rows->first();
                if (! $keeper) {
                    continue;
                }

                $sum = static fn (string $column): float => (float) $rows->sum(
                    fn ($row): float => (float) ($row->{$column} ?? 0),
                );

                DB::table('shift_closings')
                    ->where('id', $keeper->id)
                    ->update([
                        'area_id' => null,
                        'area_name' => 'Toàn bộ khu vực',
                        'order_count' => (int) $rows->sum('order_count'),
                        'total_order_count' => (int) $rows->sum('total_order_count'),
                        'cash_order_count' => (int) $rows->sum('cash_order_count'),
                        'transfer_order_count' => (int) $rows->sum('transfer_order_count'),
                        'cancelled_order_count' => (int) $rows->sum('cancelled_order_count'),
                        'cancelled_total_amount' => $sum('cancelled_total_amount'),
                        'refunded_order_count' => (int) $rows->sum('refunded_order_count'),
                        'refunded_total_amount' => $sum('refunded_total_amount'),
                        'expected_cash' => $sum('expected_cash'),
                        'actual_cash' => $sum('actual_cash'),
                        'cash_difference' => $sum('cash_difference'),
                        'transfer_amount' => $sum('transfer_amount'),
                        'cash_sales_amount' => $sum('cash_sales_amount'),
                        'actual_transfer_amount' => $sum('actual_transfer_amount'),
                        'transfer_difference' => $sum('transfer_difference'),
                        'gross_revenue_amount' => $sum('gross_revenue_amount'),
                        'discount_amount' => $sum('discount_amount'),
                        'net_revenue_amount' => $sum('net_revenue_amount'),
                        'total_difference' => $sum('total_difference'),
                    ]);

                DB::table('shift_closings')
                    ->whereIn('id', $rows->pluck('id')->reject(fn ($id) => $id === $keeper->id)->all())
                    ->delete();
            }
        });

        Schema::table('shift_closings', function (Blueprint $table) {
            $table->dropUnique('shift_closings_area_unique');
            $table->unique(
                ['restaurant_id', 'branch_id', 'shift_id', 'closing_date'],
                'shift_closings_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            $table->dropUnique('shift_closings_unique');
            $table->unique(
                ['restaurant_id', 'branch_id', 'shift_id', 'closing_date', 'area_name'],
                'shift_closings_area_unique',
            );
            $table->dropColumn([
                'period_start_at',
                'cash_sales_amount',
                'actual_transfer_amount',
                'transfer_difference',
                'gross_revenue_amount',
                'discount_amount',
                'net_revenue_amount',
                'total_difference',
                'responsibility_amount',
                'responsibility_note',
            ]);
        });
    }
};
