<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_revenue_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->enum('summary_type', ['daily', 'weekly', 'monthly'])->default('daily');
            $table->string('scope_key', 120)->default('restaurant');
            $table->date('summary_date');

            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('completed_order_count')->default(0);
            $table->unsignedInteger('cancelled_order_count')->default(0);

            $table->decimal('gross_revenue', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('service_charge_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('refund_total', 14, 2)->default(0);
            $table->decimal('net_revenue', 14, 2)->default(0);

            $table->decimal('cash_revenue', 14, 2)->default(0);
            $table->decimal('bank_transfer_revenue', 14, 2)->default(0);
            $table->decimal('card_revenue', 14, 2)->default(0);
            $table->decimal('ewallet_revenue', 14, 2)->default(0);
            $table->decimal('mixed_revenue', 14, 2)->default(0);

            $table->decimal('cogs_amount', 14, 2)->default(0);
            $table->decimal('gross_profit', 14, 2)->default(0);
            $table->decimal('average_order_value', 14, 2)->default(0);

            $table->dateTime('first_order_at')->nullable();
            $table->dateTime('last_order_at')->nullable();
            $table->dateTime('calculated_at')->nullable();
            $table->enum('source', ['system', 'manual', 'rebuild'])->default('system');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(
                ['restaurant_id', 'scope_key', 'summary_type', 'summary_date'],
                'restaurant_revenue_summaries_unique_scope'
            );
            $table->index(
                ['restaurant_id', 'summary_type', 'summary_date'],
                'restaurant_revenue_summaries_restaurant_type_date_index'
            );
            $table->index('branch_id', 'restaurant_revenue_summaries_branch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_revenue_summaries');
    }
};
