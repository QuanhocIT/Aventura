<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_periods')) {
            Schema::table('accounting_periods', function (Blueprint $table): void {
                if (! Schema::hasColumn('accounting_periods', 'close_checklist')) {
                    $table->json('close_checklist')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('accounting_periods', 'reopened_by')) {
                    $table->foreignId('reopened_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('accounting_periods', 'reopened_at')) {
                    $table->dateTime('reopened_at')->nullable()->after('reopened_by');
                }
                if (! Schema::hasColumn('accounting_periods', 'reopen_reason')) {
                    $table->text('reopen_reason')->nullable()->after('reopened_at');
                }
            });
        }

        if (Schema::hasTable('financial_journal_entries') && ! Schema::hasColumn('financial_journal_entries', 'approval_request_id')) {
            Schema::table('financial_journal_entries', function (Blueprint $table): void {
                $table->foreignId('approval_request_id')->nullable()->after('reversal_of_id')->constrained('approval_requests')->nullOnDelete();
            });
        }

        if (Schema::hasTable('cash_transactions') && ! Schema::hasColumn('cash_transactions', 'approval_request_id')) {
            // cash_transactions may be partitioned, so deliberately do not add a FK.
            Schema::table('cash_transactions', function (Blueprint $table): void {
                $table->unsignedBigInteger('approval_request_id')->nullable()->after('reversal_of_id');
                $table->index(['restaurant_id', 'approval_request_id'], 'cash_tx_approval_idx');
            });
        }

        if (Schema::hasTable('operating_expenses')) {
            Schema::table('operating_expenses', function (Blueprint $table): void {
                if (! Schema::hasColumn('operating_expenses', 'financial_account_code')) {
                    $table->string('financial_account_code', 30)->default('6271')->after('category_id');
                }
            });

            DB::table('operating_expenses')
                ->whereNull('financial_account_code')
                ->update(['financial_account_code' => '6271']);
        }

        if (Schema::hasTable('fixed_assets')) {
            Schema::table('fixed_assets', function (Blueprint $table): void {
                if (! Schema::hasColumn('fixed_assets', 'account_payable_id')) {
                    $table->foreignId('account_payable_id')->nullable()->after('status')->constrained('account_payables')->nullOnDelete();
                }
                if (! Schema::hasColumn('fixed_assets', 'supplier_id')) {
                    $table->foreignId('supplier_id')->nullable()->after('supplier')->constrained('suppliers')->nullOnDelete();
                }
                if (! Schema::hasColumn('fixed_assets', 'disposal_reason')) {
                    $table->text('disposal_reason')->nullable()->after('disposed_at');
                }
                if (! Schema::hasColumn('fixed_assets', 'disposal_proceeds')) {
                    $table->decimal('disposal_proceeds', 15, 2)->nullable()->after('disposal_reason');
                }
                if (! Schema::hasColumn('fixed_assets', 'disposal_journal_entry_id')) {
                    $table->foreignId('disposal_journal_entry_id')->nullable()->after('disposal_proceeds')->constrained('financial_journal_entries')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('account_payables')) {
            Schema::table('account_payables', function (Blueprint $table): void {
                if (Schema::hasColumn('account_payables', 'supplier_id')) {
                    $table->foreignId('supplier_id')->nullable()->change();
                }
                if (! Schema::hasColumn('account_payables', 'fixed_asset_id')) {
                    $table->foreignId('fixed_asset_id')->nullable()->after('purchase_order_id')->constrained('fixed_assets')->nullOnDelete();
                    $table->index(['restaurant_id', 'fixed_asset_id'], 'account_payables_fixed_asset_idx');
                }
            });
        }

        if (Schema::hasTable('bank_statement_lines')) {
            Schema::table('bank_statement_lines', function (Blueprint $table): void {
                if (! Schema::hasColumn('bank_statement_lines', 'matched_at')) {
                    $table->dateTime('matched_at')->nullable()->after('matched_id');
                }
                if (! Schema::hasColumn('bank_statement_lines', 'matched_by')) {
                    $table->foreignId('matched_by')->nullable()->after('matched_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('bank_statement_lines', 'unmatched_reason')) {
                    $table->text('unmatched_reason')->nullable()->after('matched_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bank_statement_lines')) {
            Schema::table('bank_statement_lines', function (Blueprint $table): void {
                if (Schema::hasColumn('bank_statement_lines', 'matched_by')) {
                    $table->dropForeign(['matched_by']);
                }
                $columns = array_values(array_filter(['matched_at', 'matched_by', 'unmatched_reason'], fn (string $column): bool => Schema::hasColumn('bank_statement_lines', $column)));
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('account_payables') && Schema::hasColumn('account_payables', 'fixed_asset_id')) {
            Schema::table('account_payables', function (Blueprint $table): void {
                $table->dropIndex('account_payables_fixed_asset_idx');
                $table->dropForeign(['fixed_asset_id']);
                $table->dropColumn('fixed_asset_id');
                if (Schema::hasColumn('account_payables', 'supplier_id')) {
                    $table->foreignId('supplier_id')->nullable(false)->change();
                }
            });
        }

        if (Schema::hasTable('fixed_assets')) {
            Schema::table('fixed_assets', function (Blueprint $table): void {
                foreach (['account_payable_id', 'supplier_id', 'disposal_journal_entry_id'] as $foreign) {
                    if (Schema::hasColumn('fixed_assets', $foreign)) {
                        $table->dropForeign([$foreign]);
                    }
                }
                $columns = array_values(array_filter(['account_payable_id', 'supplier_id', 'disposal_reason', 'disposal_proceeds', 'disposal_journal_entry_id'], fn (string $column): bool => Schema::hasColumn('fixed_assets', $column)));
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('operating_expenses') && Schema::hasColumn('operating_expenses', 'financial_account_code')) {
            Schema::table('operating_expenses', fn (Blueprint $table): mixed => $table->dropColumn('financial_account_code'));
        }

        if (Schema::hasTable('cash_transactions') && Schema::hasColumn('cash_transactions', 'approval_request_id')) {
            Schema::table('cash_transactions', function (Blueprint $table): void {
                $table->dropIndex('cash_tx_approval_idx');
                $table->dropColumn('approval_request_id');
            });
        }

        if (Schema::hasTable('financial_journal_entries') && Schema::hasColumn('financial_journal_entries', 'approval_request_id')) {
            Schema::table('financial_journal_entries', function (Blueprint $table): void {
                $table->dropForeign(['approval_request_id']);
                $table->dropColumn('approval_request_id');
            });
        }

        if (Schema::hasTable('accounting_periods')) {
            Schema::table('accounting_periods', function (Blueprint $table): void {
                if (Schema::hasColumn('accounting_periods', 'reopened_by')) {
                    $table->dropForeign(['reopened_by']);
                }
                $columns = array_values(array_filter(['close_checklist', 'reopened_by', 'reopened_at', 'reopen_reason'], fn (string $column): bool => Schema::hasColumn('accounting_periods', $column)));
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
