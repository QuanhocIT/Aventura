<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These are operational/business records whose branch can be determined
     * from a parent record or the employee that created the record. Master
     * data such as units and expense categories intentionally remains
     * chain-wide.
     *
     * @var array<string, string>
     */
    private const TABLES = [
        'order_items' => 'order_items_branch_index',
        'purchase_order_items' => 'purchase_order_items_branch_index',
        'salary_adjustments' => 'salary_adjustments_branch_index',
        'employee_kpis' => 'employee_kpis_branch_index',
        'employee_kpi_metrics' => 'employee_kpi_metrics_branch_index',
        'performance_reviews' => 'performance_reviews_branch_index',
        'overtime_requests' => 'overtime_requests_branch_index',
        'inventory_batches' => 'inventory_batches_branch_index',
        'supplier_price_histories' => 'supplier_price_histories_branch_index',
        'equipment_maintenance_logs' => 'equipment_maintenance_logs_branch_index',
        'delivery_details' => 'delivery_details_branch_index',
        'shippers' => 'shippers_branch_index',
        'shipper_location_logs' => 'shipper_location_logs_branch_index',
        'delivery_batches' => 'delivery_batches_branch_index',
        'delivery_batch_items' => 'delivery_batch_items_branch_index',
        'account_payables' => 'account_payables_branch_index',
        'account_receivables' => 'account_receivables_branch_index',
        'checklist_templates' => 'checklist_templates_branch_index',
        'checklist_items' => 'checklist_items_branch_index',
        'business_goals' => 'business_goals_branch_index',
        'goal_milestones' => 'goal_milestones_branch_index',
        'goal_actions' => 'goal_actions_branch_index',
        'promotions' => 'promotions_branch_index',
        'promotion_triggers' => 'promotion_triggers_branch_index',
        'customer_coupons' => 'customer_coupons_branch_index',
        'promotion_analytics_snapshots' => 'promotion_analytics_snapshots_branch_index',
        'training_courses' => 'training_courses_branch_index',
        'training_lessons' => 'training_lessons_branch_index',
        'training_quizzes' => 'training_quizzes_branch_index',
        'training_enrollments' => 'training_enrollments_branch_index',
        'training_quiz_attempts' => 'training_quiz_attempts_branch_index',
        'rfp_items' => 'rfp_items_branch_index',
        'rfp_bids' => 'rfp_bids_branch_index',
        'rfp_bid_items' => 'rfp_bid_items_branch_index',
        'product_recipes' => 'product_recipes_branch_index',
        'sub_recipes' => 'sub_recipes_branch_index',
        'menu_price_tests' => 'menu_price_tests_branch_index',
        'loyalty_transactions' => 'loyalty_transactions_branch_index',
    ];

    public function up(): void
    {
        // SQLite cannot ALTER a table while a view references it. The view
        // is recreated after the schema change and includes the new branch
        // column for both hot and archived order items.
        $this->dropOrderItemsView();

        foreach (self::TABLES as $tableName => $indexName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->constrained('restaurant_branches')
                    ->nullOnDelete();
                $table->index('branch_id', $indexName);
            });
        }

        $this->addArchiveOrderItemBranchColumn();

        $this->backfillFromParent('order_items', 'order_id', 'orders');
        $this->backfillFromParent('order_items_archive', 'order_id', 'orders_archive');
        $this->backfillFromParent('purchase_order_items', 'purchase_order_id', 'purchase_orders');
        $this->backfillFromParent('salary_adjustments', 'salary_id', 'salaries');
        $this->backfillFromParent('employee_kpi_metrics', 'employee_kpi_id', 'employee_kpis');
        $this->backfillFromParent('delivery_details', 'order_id', 'orders');
        $this->backfillFromParent('shipper_location_logs', 'shipper_id', 'shippers');
        $this->backfillFromParent('delivery_batches', 'shipper_id', 'shippers');
        $this->backfillFromParent('delivery_batch_items', 'order_id', 'orders');
        $this->backfillFromParent('goal_milestones', 'goal_id', 'business_goals');
        $this->backfillFromParent('goal_actions', 'goal_id', 'business_goals');
        $this->backfillFromParent('promotion_triggers', 'promotion_id', 'promotions');
        $this->backfillFromParent('promotion_analytics_snapshots', 'promotion_id', 'promotions');
        $this->backfillFromParent('training_lessons', 'course_id', 'training_courses');
        $this->backfillFromParent('training_quizzes', 'course_id', 'training_courses');
        $this->backfillFromParent('training_quiz_attempts', 'enrollment_id', 'training_enrollments');
        $this->backfillFromParent('checklist_items', 'template_id', 'checklist_templates');
        $this->backfillFromParent('rfp_items', 'rfp_id', 'request_for_proposals');
        $this->backfillFromParent('rfp_bids', 'rfp_id', 'request_for_proposals');
        $this->backfillFromParent('rfp_bid_items', 'rfp_bid_id', 'rfp_bids');

        $this->backfillFromEmployee('employee_kpis');
        $this->backfillFromEmployee('performance_reviews');
        $this->backfillFromEmployee('overtime_requests');
        $this->backfillFromEmployee('training_enrollments');
        $this->backfillFromEmployee('shippers');

        $this->backfillFromIngredient('inventory_batches');
        $this->backfillFromIngredient('supplier_price_histories');
        $this->backfillFromRelatedBranch('product_recipes', 'product_id', 'products');
        $this->backfillFromRelatedBranch('product_recipes', 'ingredient_id', 'ingredients');
        $this->backfillFromRelatedBranch('sub_recipes', 'parent_ingredient_id', 'ingredients');
        $this->backfillFromRelatedBranch('sub_recipes', 'child_ingredient_id', 'ingredients');
        $this->backfillFromCustomer('account_receivables');
        $this->backfillFromCustomer('customer_coupons');
        $this->backfillFromCustomer('loyalty_transactions');
        $this->backfillFromEquipment('equipment_maintenance_logs');

        $this->backfillFromRelatedBranch('account_payables', 'purchase_order_id', 'purchase_orders');
        $this->backfillFromRelatedBranch('account_payables', 'supplier_id', 'suppliers');
        $this->backfillFromRelatedBranch('account_receivables', 'order_id', 'orders');
        $this->backfillFromRelatedBranch('delivery_batch_items', 'batch_id', 'delivery_batches');
        $this->backfillFromRelatedBranch('customer_coupons', 'trigger_id', 'promotion_triggers');
        $this->backfillFromRelatedBranch('menu_price_tests', 'product_id', 'products');
        $this->backfillFromRelatedBranch('operating_expenses', 'recurring_expense_id', 'recurring_expenses');

        // Records without a branch-bearing parent are still unambiguous for
        // a restaurant that has exactly one active branch.
        foreach (['checklist_templates', 'business_goals', 'promotions', 'training_courses', 'request_for_proposals', 'recurring_expenses'] as $tableName) {
            $this->backfillToSoleActiveBranch($tableName);
        }

        $this->restoreOrderItemsView();
    }

    public function down(): void
    {
        $this->dropOrderItemsView();

        foreach (array_reverse(array_keys(self::TABLES)) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('order_items_archive') && Schema::hasColumn('order_items_archive', 'branch_id')) {
            Schema::table('order_items_archive', function (Blueprint $table): void {
                $table->dropIndex('order_items_archive_branch_index');
                $table->dropColumn('branch_id');
            });
        }

        $this->restoreOrderItemsView(false);
    }

    private function backfillFromParent(string $tableName, string $foreignKey, string $parentTable): void
    {
        if (! $this->canBackfill($tableName, $foreignKey, $parentTable)) {
            return;
        }

        DB::table($tableName)
            ->whereNull('branch_id')
            ->orderBy('id')
            ->get(['id', $foreignKey])
            ->each(function (object $row) use ($tableName, $foreignKey, $parentTable): void {
                $branchId = DB::table($parentTable)->where('id', $row->{$foreignKey})->value('branch_id');
                if ($branchId !== null) {
                    DB::table($tableName)->where('id', $row->id)->update(['branch_id' => $branchId]);
                }
            });
    }

    private function backfillFromEmployee(string $tableName): void
    {
        if (! $this->canBackfill($tableName, 'employee_id', 'employees')) {
            return;
        }

        DB::table($tableName)
            ->whereNull('branch_id')
            ->orderBy('id')
            ->get(['id', 'employee_id'])
            ->each(function (object $row) use ($tableName): void {
                $branchId = DB::table('employees')->where('id', $row->employee_id)->value('branch_id');
                if ($branchId !== null) {
                    DB::table($tableName)->where('id', $row->id)->update(['branch_id' => $branchId]);
                }
            });
    }

    private function backfillFromIngredient(string $tableName): void
    {
        if (! $this->canBackfill($tableName, 'ingredient_id', 'ingredients')) {
            return;
        }

        DB::table($tableName)
            ->whereNull('branch_id')
            ->orderBy('id')
            ->get(['id', 'ingredient_id'])
            ->each(function (object $row) use ($tableName): void {
                $branchId = DB::table('ingredients')->where('id', $row->ingredient_id)->value('branch_id');
                if ($branchId !== null) {
                    DB::table($tableName)->where('id', $row->id)->update(['branch_id' => $branchId]);
                }
            });
    }

    private function backfillFromCustomer(string $tableName): void
    {
        if (! $this->canBackfill($tableName, 'customer_id', 'customers')) {
            return;
        }

        DB::table($tableName)
            ->whereNull('branch_id')
            ->orderBy('id')
            ->get(['id', 'customer_id'])
            ->each(function (object $row) use ($tableName): void {
                $branchId = DB::table('customers')->where('id', $row->customer_id)->value('branch_id');
                if ($branchId !== null) {
                    DB::table($tableName)->where('id', $row->id)->update(['branch_id' => $branchId]);
                }
            });
    }

    private function backfillFromEquipment(string $tableName): void
    {
        $this->backfillFromParent($tableName, 'equipment_id', 'equipment');
    }

    private function backfillFromRelatedBranch(string $tableName, string $foreignKey, string $parentTable): void
    {
        $this->backfillFromParent($tableName, $foreignKey, $parentTable);
    }

    private function backfillToSoleActiveBranch(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'restaurant_id')) {
            return;
        }

        $restaurantIds = DB::table($tableName)
            ->whereNull('branch_id')
            ->whereNotNull('restaurant_id')
            ->distinct()
            ->pluck('restaurant_id');

        foreach ($restaurantIds as $restaurantId) {
            $branchId = DB::table('restaurant_branches')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->orderBy('id')
                ->value('id');
            $branchCount = DB::table('restaurant_branches')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->count();

            if ($branchId !== null && $branchCount === 1) {
                DB::table($tableName)
                    ->where('restaurant_id', $restaurantId)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $branchId]);
            }
        }
    }

    private function canBackfill(string $tableName, string $foreignKey, string $parentTable): bool
    {
        return Schema::hasTable($tableName)
            && Schema::hasColumn($tableName, 'branch_id')
            && Schema::hasColumn($tableName, $foreignKey)
            && Schema::hasTable($parentTable)
            && Schema::hasColumn($parentTable, 'branch_id');
    }

    private function addArchiveOrderItemBranchColumn(): void
    {
        if (! Schema::hasTable('order_items_archive') || Schema::hasColumn('order_items_archive', 'branch_id')) {
            return;
        }

        // Archive tables are partitioned on MySQL and deliberately do not
        // have foreign keys. Keep the same convention here.
        Schema::table('order_items_archive', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->index('branch_id', 'order_items_archive_branch_index');
        });
    }

    private function dropOrderItemsView(): void
    {
        if (Schema::hasTable('order_items_archive')) {
            DB::statement('DROP VIEW IF EXISTS order_items_unified');
        }
    }

    private function restoreOrderItemsView(bool $includeBranch = true): void
    {
        if (! Schema::hasTable('order_items') || ! Schema::hasTable('order_items_archive')) {
            return;
        }

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $branchColumn = $includeBranch ? 'branch_id, ' : '';
        DB::statement('DROP VIEW IF EXISTS order_items_unified');
        DB::statement('
            CREATE '.($isSqlite ? '' : 'OR REPLACE ').'VIEW order_items_unified AS
            SELECT
                id, restaurant_id, order_id, '.$branchColumn.'product_id, quantity,
                unit_price, discount_amount, line_total, status,
                notes, sent_to_kitchen_at, prepared_at, served_at,
                created_at, updated_at
            FROM order_items

            UNION ALL

            SELECT
                id, restaurant_id, order_id, '.$branchColumn.'product_id, quantity,
                unit_price, discount_amount, line_total, status,
                notes, sent_to_kitchen_at, prepared_at, served_at,
                created_at, updated_at
            FROM order_items_archive
        ');
    }
};
