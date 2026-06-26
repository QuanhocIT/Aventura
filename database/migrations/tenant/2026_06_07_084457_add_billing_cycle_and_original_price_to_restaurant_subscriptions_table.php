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
        Schema::table('restaurant_subscriptions', function (Blueprint $table) {
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly')->after('price');
            $table->decimal('original_price', 12, 2)->default(0)->after('price');
            $table->string('coupon_code', 50)->nullable()->after('billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'original_price', 'coupon_code']);
        });
    }
};

