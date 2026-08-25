<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_accounts')) {
            Schema::create('financial_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
                $table->string('code', 30);
                $table->string('name', 150);
                $table->string('type', 20);
                $table->string('normal_balance', 6);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['restaurant_id', 'code']);
                $table->index(['restaurant_id', 'type', 'is_active']);
            });
        }

        if (! Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 20)->default('open');
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('closed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['restaurant_id', 'period_start', 'period_end']);
                $table->index(['restaurant_id', 'status', 'period_start']);
            });
        }

        if (! Schema::hasTable('financial_journal_entries')) {
            Schema::create('financial_journal_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('accounting_period_id')->constrained('accounting_periods')->restrictOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
                $table->string('entry_number', 50);
                $table->date('entry_date');
                $table->string('status', 20)->default('posted');
                $table->string('source_type', 150)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('idempotency_key', 180)->nullable();
                $table->string('currency', 3)->default('VND');
                $table->decimal('total_debit', 15, 2)->default(0);
                $table->decimal('total_credit', 15, 2)->default(0);
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('posted_at')->nullable();
                $table->foreignId('reversal_of_id')->nullable()->constrained('financial_journal_entries')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['restaurant_id', 'entry_number']);
                $table->unique(['restaurant_id', 'idempotency_key'], 'fje_restaurant_idempotency_unique');
                $table->index(['restaurant_id', 'entry_date', 'status'], 'fje_restaurant_date_status_idx');
                $table->index(['restaurant_id', 'source_type', 'source_id'], 'fje_restaurant_source_idx');
            });
        }

        if (! Schema::hasTable('financial_journal_lines')) {
            Schema::create('financial_journal_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained('financial_journal_entries')->cascadeOnDelete();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
                $table->string('description', 500)->nullable();
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('credit', 15, 2)->default(0);
                $table->string('counterparty_type', 150)->nullable();
                $table->unsignedBigInteger('counterparty_id')->nullable();
                $table->string('cost_center', 100)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['journal_entry_id', 'financial_account_id'], 'fjl_entry_account_idx');
                $table->index(['counterparty_type', 'counterparty_id'], 'fjl_counterparty_idx');
                $table->index(['branch_id', 'financial_account_id'], 'fjl_branch_account_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_journal_lines');
        Schema::dropIfExists('financial_journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('financial_accounts');
    }
};
