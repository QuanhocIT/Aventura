<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('billing_invoices', 'payment_status')) {
            Schema::table('billing_invoices', function (Blueprint $table): void {
                $table->string('payment_status', 20)->default('unpaid')->after('status');
                $table->timestamp('paid_at')->nullable()->after('payment_status');
                $table->index(['payment_status', 'created_at'], 'billing_invoices_payment_state_index');
            });
        }

        // A payment_success invoice is created only after the gateway webhook
        // has been accepted. Preserve document-generation state in `status`
        // and keep cash state in this separate field.
        DB::table('billing_invoices')
            ->where('type', 'payment_success')
            ->update(['payment_status' => 'paid']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('billing_invoices', 'payment_status')) {
            Schema::table('billing_invoices', function (Blueprint $table): void {
                $table->dropIndex('billing_invoices_payment_state_index');
                $table->dropColumn(['payment_status', 'paid_at']);
            });
        }
    }
};
