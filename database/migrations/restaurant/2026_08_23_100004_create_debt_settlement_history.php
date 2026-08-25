<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_payable_payments')) {
            Schema::create('account_payable_payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('account_payable_id')->constrained('account_payables')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
                $table->decimal('amount', 15, 2);
                $table->string('payment_method', 40);
                $table->string('payment_reference', 150)->nullable();
                $table->dateTime('paid_at');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->string('idempotency_key', 180);
                $table->timestamps();

                $table->unique(['restaurant_id', 'idempotency_key']);
                $table->index(['account_payable_id', 'paid_at'], 'app_payable_paid_idx');
            });
        }

        if (! Schema::hasTable('account_receivable_payments')) {
            Schema::create('account_receivable_payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('account_receivable_id')->constrained('account_receivables')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
                $table->decimal('amount', 15, 2);
                $table->string('payment_method', 40);
                $table->string('payment_reference', 150)->nullable();
                $table->dateTime('received_at');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->string('idempotency_key', 180);
                $table->timestamps();

                $table->unique(['restaurant_id', 'idempotency_key']);
                $table->index(['account_receivable_id', 'received_at'], 'arp_receivable_received_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_receivable_payments');
        Schema::dropIfExists('account_payable_payments');
    }
};
