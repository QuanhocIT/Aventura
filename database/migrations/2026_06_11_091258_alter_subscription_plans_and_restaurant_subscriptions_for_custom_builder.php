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
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('is_custom')->default(false)->after('restaurant_id');
            $table->unsignedInteger('max_dishes')->nullable()->after('max_users');
            $table->string('billing_cycle', 50)->default('monthly')->change();
        });

        Schema::table('restaurant_subscriptions', function (Blueprint $table) {
            $table->string('billing_cycle', 50)->default('monthly')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn(['restaurant_id', 'is_custom', 'max_dishes']);
        });
    }
};
