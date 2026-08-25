<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_bank_accounts')) {
            Schema::create('financial_bank_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
                $table->string('name', 150);
                $table->string('bank_name', 150)->nullable();
                $table->string('account_number', 100)->nullable();
                $table->string('account_holder', 150)->nullable();
                $table->string('account_type', 30)->default('bank');
                $table->string('financial_account_code', 30)->default('1121');
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->date('opening_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['restaurant_id', 'is_active']);
                $table->index(['restaurant_id', 'account_type']);
            });
        }

        if (! Schema::hasTable('bank_statement_lines')) {
            Schema::create('bank_statement_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('financial_bank_account_id')->constrained('financial_bank_accounts')->cascadeOnDelete();
                $table->date('transaction_date');
                $table->date('value_date')->nullable();
                $table->string('external_reference', 180)->nullable();
                $table->text('description')->nullable();
                $table->decimal('amount_in', 15, 2)->default(0);
                $table->decimal('amount_out', 15, 2)->default(0);
                $table->decimal('balance', 15, 2)->nullable();
                $table->decimal('fee_amount', 15, 2)->default(0);
                $table->string('status', 20)->default('unmatched');
                $table->string('matched_type', 150)->nullable();
                $table->unsignedBigInteger('matched_id')->nullable();
                $table->string('idempotency_key', 180);
                $table->json('raw_payload')->nullable();
                $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('imported_at')->nullable();
                $table->timestamps();

                $table->unique(['restaurant_id', 'idempotency_key']);
                $table->index(['financial_bank_account_id', 'transaction_date', 'status'], 'bsl_account_date_status_idx');
                $table->index(['matched_type', 'matched_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('financial_bank_accounts');
    }
};
