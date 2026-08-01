<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('checklist_completions') && Schema::hasColumn('checklist_completions', 'branch_id')) {
            Schema::table('checklist_completions', function (Blueprint $table): void {
                // InnoDB may be using the old unique index to support the
                // item_id foreign key. Keep a dedicated index before changing
                // the uniqueness scope to include branch_id.
                $table->index('item_id', 'checklist_completions_item_index');
                $table->dropUnique('cc_item_date_unique');
                $table->unique(['item_id', 'checked_date', 'restaurant_id', 'branch_id'], 'cc_item_date_branch_unique');
            });
        }

        if (Schema::hasTable('recurring_expenses') && ! Schema::hasColumn('recurring_expenses', 'branch_id')) {
            Schema::table('recurring_expenses', function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('restaurant_id')
                    ->constrained('restaurant_branches')
                    ->nullOnDelete();
                $table->index(['restaurant_id', 'branch_id', 'is_active'], 'recurring_expenses_branch_scope_index');
            });
        }

        if (Schema::hasTable('operating_expenses') && ! Schema::hasColumn('operating_expenses', 'branch_id')) {
            Schema::table('operating_expenses', function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('restaurant_id')
                    ->constrained('restaurant_branches')
                    ->nullOnDelete();
                $table->index(['restaurant_id', 'branch_id', 'expense_date'], 'operating_expenses_branch_date_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('checklist_completions') && Schema::hasColumn('checklist_completions', 'branch_id')) {
            Schema::table('checklist_completions', function (Blueprint $table): void {
                $table->dropUnique('cc_item_date_branch_unique');
                $table->dropIndex('checklist_completions_item_index');
                $table->unique(['item_id', 'checked_date', 'restaurant_id'], 'cc_item_date_unique');
            });
        }

        if (Schema::hasTable('operating_expenses') && Schema::hasColumn('operating_expenses', 'branch_id')) {
            Schema::table('operating_expenses', function (Blueprint $table): void {
                $table->dropIndex('operating_expenses_branch_date_index');
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('recurring_expenses') && Schema::hasColumn('recurring_expenses', 'branch_id')) {
            Schema::table('recurring_expenses', function (Blueprint $table): void {
                $table->dropIndex('recurring_expenses_branch_scope_index');
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
