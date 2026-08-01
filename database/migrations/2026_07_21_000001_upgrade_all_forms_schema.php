<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Suppliers Table Upgrades
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'tax_code')) {
                $table->string('tax_code')->nullable()->after('email');
            }
            if (! Schema::hasColumn('suppliers', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('tax_code');
            }
            if (! Schema::hasColumn('suppliers', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('suppliers', 'bank_account_holder')) {
                $table->string('bank_account_holder')->nullable()->after('bank_account_number');
            }
            if (! Schema::hasColumn('suppliers', 'payment_terms')) {
                $table->string('payment_terms')->default('cod')->after('bank_account_holder'); // cod, net7, net15, net30, prepaid
            }
            if (! Schema::hasColumn('suppliers', 'category')) {
                $table->string('category')->nullable()->after('payment_terms'); // fresh_food, dry_goods, beverage, packaging, other
            }
        });

        // 2. Purchase Orders Table Upgrades
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'payment_method')) {
                $table->string('payment_method')->default('banking')->after('notes'); // banking, cod, credit
            }
            if (! Schema::hasColumn('purchase_orders', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('payment_method');
            }
            if (! Schema::hasColumn('purchase_orders', 'shipping_method')) {
                $table->string('shipping_method')->default('supplier_delivery')->after('discount_percent'); // supplier_delivery, self_pickup, express
            }
            if (! Schema::hasColumn('purchase_orders', 'mismatch_reason')) {
                $table->string('mismatch_reason')->nullable()->after('shipping_method');
            }
            if (! Schema::hasColumn('purchase_orders', 'resolution_action')) {
                $table->string('resolution_action')->nullable()->after('mismatch_reason');
            }
        });

        // 3. Business Goals Table Upgrades
        Schema::table('business_goals', function (Blueprint $table) {
            if (! Schema::hasColumn('business_goals', 'owner_name')) {
                $table->string('owner_name')->nullable()->after('title');
            }
            if (! Schema::hasColumn('business_goals', 'unit_name')) {
                $table->string('unit_name')->nullable()->after('metric');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['tax_code', 'bank_name', 'bank_account_number', 'bank_account_holder', 'payment_terms', 'category']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'discount_percent', 'shipping_method', 'mismatch_reason', 'resolution_action']);
        });

        Schema::table('business_goals', function (Blueprint $table) {
            $table->dropColumn(['owner_name', 'unit_name']);
        });
    }
};
