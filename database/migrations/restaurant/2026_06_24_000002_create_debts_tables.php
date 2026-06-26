<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update purchase_orders table (payment_status already exists)
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'payment_terms')) {
                $table->string('payment_terms', 50)->nullable()->after('status');
            }
            if (!Schema::hasColumn('purchase_orders', 'due_date')) {
                $table->date('due_date')->nullable()->after('payment_terms');
            }
        });

        // 2. Update customers table
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'is_vip')) {
                $table->boolean('is_vip')->default(false)->after('loyalty_points');
            }
            if (!Schema::hasColumn('customers', 'is_b2b')) {
                $table->boolean('is_b2b')->default(false)->after('is_vip');
            }
            if (!Schema::hasColumn('customers', 'credit_limit')) {
                $table->decimal('credit_limit', 12, 2)->default(0)->after('is_b2b');
            }
            if (!Schema::hasColumn('customers', 'current_debt')) {
                $table->decimal('current_debt', 12, 2)->default(0)->after('credit_limit');
            }
        });

        // 3. Create account_payables table (Supplier Debt)
        if (!Schema::hasTable('account_payables')) {
            Schema::create('account_payables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->date('due_date');
                $table->string('status', 20)->default('unpaid'); // unpaid, partially_paid, paid
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'status', 'due_date']);
            });
        }

        // 4. Create account_receivables table (Customer Debt)
        if (!Schema::hasTable('account_receivables')) {
            Schema::create('account_receivables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->decimal('received_amount', 12, 2)->default(0);
                $table->date('due_date');
                $table->string('status', 20)->default('unpaid'); // unpaid, partially_paid, paid
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'status', 'due_date']);
            });
        }

        // 5. Update payments table payment_method column to allow 'debt'
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method', 50)->default('cash')->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_receivables');
        Schema::dropIfExists('account_payables');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['is_vip', 'is_b2b', 'credit_limit', 'current_debt']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_terms', 'due_date']);
        });
    }
};
