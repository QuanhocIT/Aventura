<?php

namespace App\Services;

use App\Models\RestaurantBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BranchDataAssignmentService
{
    private const BRANCH_REQUIRED_TABLES = [
        'approval_requests',
        'employees',
        'areas',
        'restaurant_tables',
        'orders',
        'payments',
        'ingredients',
        'inventories',
        'inventory_transactions',
        'inventory_reservations',
        'purchase_orders',
        'temporary_orders',
        'order_items',
        'order_items_archive',
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
        'salary_adjustments',
        'employee_kpis',
        'performance_reviews',
        'overtime_requests',
        'inventory_batches',
        'equipment_maintenance_logs',
        'delivery_details',
        'shippers',
        'shipper_location_logs',
        'delivery_batches',
        'account_payables',
        'account_receivables',
        'checklist_templates',
        'business_goals',
        'promotions',
        'promotion_triggers',
        'customer_coupons',
        'promotion_analytics_snapshots',
        'training_courses',
        'training_enrollments',
        'product_recipes',
        'sub_recipes',
        'menu_price_tests',
        'loyalty_transactions',
    ];

    private const BRANCH_SCOPED_USER_ROLES = [
        'manager',
        'cashier',
        'waiter',
        'kitchen',
        'inventory_staff',
        'staff',
    ];

    /**
     * Assign legacy chain-wide rows to the first branch when it is the only
     * active branch. NULL is a valid chain-wide scope for products and the
     * consolidated revenue row, so those tables are deliberately excluded.
     */
    public function assignLegacyRowsToSoleBranch(RestaurantBranch $branch): void
    {
        $activeBranchCount = DB::table('restaurant_branches')
            ->where('restaurant_id', $branch->restaurant_id)
            ->where('status', 'active')
            ->count();

        if ($activeBranchCount !== 1) {
            return;
        }

        $restaurantId = (int) $branch->restaurant_id;
        $branchId = (int) $branch->id;

        foreach (self::BRANCH_REQUIRED_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            DB::table($table)
                ->where('restaurant_id', $restaurantId)
                ->whereNull('branch_id')
                ->update(['branch_id' => $branchId]);
        }

        $this->assignBranchScopedUsers($restaurantId, $branchId);
        $this->copyConsolidatedSummaries($restaurantId, $branchId);
    }

    private function assignBranchScopedUsers(int $restaurantId, int $branchId): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'branch_id')
            || ! Schema::hasTable('model_has_roles')
            || ! Schema::hasTable('roles')
        ) {
            return;
        }

        DB::table('users as user')
            ->join('model_has_roles as user_role', function ($join): void {
                $join->on('user_role.model_id', '=', 'user.id')
                    ->where('user_role.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'user_role.role_id')
            ->where('user.restaurant_id', $restaurantId)
            ->whereNull('user.branch_id')
            ->whereIn('roles.name', self::BRANCH_SCOPED_USER_ROLES)
            ->update(['user.branch_id' => $branchId]);
    }

    private function copyConsolidatedSummaries(int $restaurantId, int $branchId): void
    {
        if (! Schema::hasTable('restaurant_revenue_summaries')) {
            return;
        }

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
}
