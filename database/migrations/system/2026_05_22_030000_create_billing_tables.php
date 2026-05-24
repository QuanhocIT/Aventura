<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('event_type', 100)->nullable();
            $table->string('transaction_code', 100)->nullable();
            $table->string('signature', 255)->nullable();
            $table->string('status', 30)->default('received');
            $table->json('headers')->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['provider', 'transaction_code'], 'payment_webhooks_provider_transaction_index');
            $table->index(['provider', 'status'], 'payment_webhooks_provider_status_index');
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->string('type', 30)->default('payment_success');
            $table->string('status', 30)->default('pending');
            $table->string('currency', 10)->default('VND');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->string('pdf_path', 2048)->nullable();
            $table->string('excel_path', 2048)->nullable();
            $table->string('recipient_email')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'type'], 'billing_invoices_restaurant_type_index');
            $table->index(['status', 'due_on'], 'billing_invoices_status_due_index');
        });

        Schema::create('billing_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->integer('days')->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('coupon_code', 100)->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'type'], 'billing_adjustments_restaurant_type_index');
        });

        Schema::table('restaurant_subscriptions', function (Blueprint $table) {
            $table->string('transaction_code', 100)->nullable()->after('renewal_at');
            $table->timestamp('grace_ends_at')->nullable()->after('transaction_code');
            $table->timestamp('last_notified_at')->nullable()->after('grace_ends_at');
            $table->timestamp('last_paid_at')->nullable()->after('last_notified_at');
            $table->json('billing_meta')->nullable()->after('meta');

            $table->unique('transaction_code', 'restaurant_subscriptions_transaction_code_unique');
            $table->index(['status', 'renewal_at'], 'restaurant_subscriptions_status_renewal_index');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_subscriptions', function (Blueprint $table) {
            $table->dropUnique('restaurant_subscriptions_transaction_code_unique');
            $table->dropIndex('restaurant_subscriptions_status_renewal_index');
            $table->dropColumn([
                'transaction_code',
                'grace_ends_at',
                'last_notified_at',
                'last_paid_at',
                'billing_meta',
            ]);
        });

        Schema::dropIfExists('billing_adjustments');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('payment_webhooks');
    }
};
