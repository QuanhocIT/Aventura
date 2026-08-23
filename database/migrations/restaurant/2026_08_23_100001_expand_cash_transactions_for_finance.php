<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_transactions')) {
            return;
        }

        Schema::table('cash_transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('cash_transactions', 'payment_id')) {
                // cash_transactions là partitioned table — không dùng FK constraint
                $table->unsignedBigInteger('payment_id')->nullable()->after('cash_register_id');
            }
            if (! Schema::hasColumn('cash_transactions', 'status')) {
                $table->string('status', 20)->default('posted')->after('source');
            }
            if (! Schema::hasColumn('cash_transactions', 'idempotency_key')) {
                $table->string('idempotency_key', 180)->nullable()->after('reference_type');
            }
            if (! Schema::hasColumn('cash_transactions', 'reversal_of_id')) {
                // No FK — partitioned table không hỗ trợ foreign key
                $table->unsignedBigInteger('reversal_of_id')->nullable()->after('idempotency_key');
            }
        });

        Schema::table('cash_transactions', function (Blueprint $table): void {
            // Partitioned table: không dùng UNIQUE, chuyển sang index thông thường
            $table->index(['restaurant_id', 'payment_id'], 'cash_transactions_payment_idx');
            $table->index(['restaurant_id', 'idempotency_key'], 'cash_transactions_idempotency_idx');
            $table->index(['restaurant_id', 'status', 'occurred_at'], 'cash_transactions_status_date_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cash_transactions')) {
            return;
        }

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropIndex('cash_transactions_payment_idx');
            $table->dropIndex('cash_transactions_idempotency_idx');
            $table->dropIndex('cash_transactions_status_date_index');
            $table->dropColumn(['payment_id', 'status', 'idempotency_key', 'reversal_of_id']);
        });
    }
};
