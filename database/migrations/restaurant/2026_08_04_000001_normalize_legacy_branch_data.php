<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branch-aware tables were introduced after legacy demo/production rows
     * already existed. A NULL branch on an order is not a chain-wide order;
     * it is an unresolved legacy row and must be assigned before branch
     * filtering can be trusted.
     */
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_branches')) {
            return;
        }

        $restaurants = DB::table('restaurant_branches')
            ->select('restaurant_id')
            ->where('status', 'active')
            ->groupBy('restaurant_id')
            ->get();

        foreach ($restaurants as $restaurant) {
            $branchIds = DB::table('restaurant_branches')
                ->where('restaurant_id', $restaurant->restaurant_id)
                ->where('status', 'active')
                ->orderBy('id')
                ->pluck('id');

            if ($branchIds->count() === 0) {
                continue;
            }

            $this->resolveOrders((int) $restaurant->restaurant_id, $branchIds->all());
            $this->resolveSimpleBranchTables((int) $restaurant->restaurant_id, $branchIds->all());
            $this->resolvePayments((int) $restaurant->restaurant_id);
            $this->copySingleBranchSummaries((int) $restaurant->restaurant_id, $branchIds->all());
        }
    }

    private function resolveOrders(int $restaurantId, array $branchIds): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'branch_id')) {
            return;
        }

        DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->whereNull('branch_id')
            ->orderBy('id')
            ->get(['id', 'table_id', 'created_by', 'cashier_user_id'])
            ->each(function ($order) use ($restaurantId, $branchIds): void {
                $branchId = null;

                if ($order->table_id && Schema::hasTable('restaurant_tables')) {
                    $branchId = DB::table('restaurant_tables')
                        ->where('id', $order->table_id)
                        ->value('branch_id');
                }

                if ($branchId === null && $order->created_by && Schema::hasTable('users')) {
                    $branchId = DB::table('users')->where('id', $order->created_by)->value('branch_id');
                }

                if ($branchId === null && $order->cashier_user_id && Schema::hasTable('users')) {
                    $branchId = DB::table('users')->where('id', $order->cashier_user_id)->value('branch_id');
                }

                // With exactly one active branch the assignment is
                // unambiguous. With multiple branches, leave unresolved
                // rows untouched for the audit/manual assignment flow.
                if ($branchId === null && count($branchIds) === 1) {
                    $branchId = $branchIds[0];
                }

                if ($branchId !== null && in_array((int) $branchId, array_map('intval', $branchIds), true)) {
                    DB::table('orders')->where('id', $order->id)->update(['branch_id' => $branchId]);
                }
            });
    }

    private function resolveSimpleBranchTables(int $restaurantId, array $branchIds): void
    {
        if (count($branchIds) !== 1) {
            return;
        }

        $branchId = $branchIds[0];
        foreach ([
            'areas',
            'restaurant_tables',
            'ingredients',
            'inventories',
            'inventory_transactions',
            'inventory_reservations',
            'purchase_orders',
            'temporary_orders',
            'cash_registers',
            'cash_transactions',
            'schedule_assignments',
            'shift_closings',
            'leave_requests',
            'schedule_registrations',
            'shift_swaps',
            'salaries',
            'violation_reports',
            'checklist_completions',
            'operating_expenses',
            'recurring_expenses',
            'request_for_proposals',
            'orders_archive',
        ] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            DB::table($table)
                ->where('restaurant_id', $restaurantId)
                ->whereNull('branch_id')
                ->update(['branch_id' => $branchId]);
        }
    }

    private function resolvePayments(int $restaurantId): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'branch_id')) {
            return;
        }

        DB::table('payments')
            ->where('payments.restaurant_id', $restaurantId)
            ->whereNull('payments.branch_id')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->whereNotNull('orders.branch_id')
            ->update(['payments.branch_id' => DB::raw('orders.branch_id')]);
    }

    private function copySingleBranchSummaries(int $restaurantId, array $branchIds): void
    {
        if (count($branchIds) !== 1 || ! Schema::hasTable('restaurant_revenue_summaries')) {
            return;
        }

        $branchId = $branchIds[0];
        $summaries = DB::table('restaurant_revenue_summaries')
            ->where('restaurant_id', $restaurantId)
            ->where('scope_key', 'restaurant')
            ->whereNull('branch_id')
            ->get();

        foreach ($summaries as $summary) {
            $values = (array) $summary;
            unset($values['id']);
            $values['branch_id'] = $branchId;
            $values['scope_key'] = "branch:{$branchId}";

            DB::table('restaurant_revenue_summaries')->updateOrInsert(
                [
                    'restaurant_id' => $restaurantId,
                    'scope_key' => $values['scope_key'],
                    'summary_type' => $values['summary_type'],
                    'summary_date' => $values['summary_date'],
                ],
                $values,
            );
        }
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversed.
    }
};
