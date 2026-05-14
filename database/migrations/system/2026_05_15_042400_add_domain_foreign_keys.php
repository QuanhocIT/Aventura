<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('restaurant_id', 'users_restaurant_id_foreign')
                ->references('id')
                ->on('restaurants')
                ->nullOnDelete();

            $table->foreign('branch_id', 'users_branch_id_foreign')
                ->references('id')
                ->on('restaurant_branches')
                ->nullOnDelete();
        });

        Schema::table('units', function (Blueprint $table) {
            $table->foreign('restaurant_id', 'units_restaurant_id_foreign')
                ->references('id')
                ->on('restaurants')
                ->nullOnDelete();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreign('order_id', 'inventory_transactions_order_id_foreign')
                ->references('id')
                ->on('orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropForeign('inventory_transactions_order_id_foreign');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign('units_restaurant_id_foreign');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_restaurant_id_foreign');
            $table->dropForeign('users_branch_id_foreign');
        });
    }
};
