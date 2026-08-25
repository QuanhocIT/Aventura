<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'is_payment_requested')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->boolean('is_payment_requested')->default(false)->after('payment_status');
                $table->dateTime('payment_requested_at')->nullable()->after('is_payment_requested');
            });
        }

        if (Schema::hasTable('restaurant_tables') && ! Schema::hasColumn('restaurant_tables', 'is_payment_requested')) {
            Schema::table('restaurant_tables', function (Blueprint $table): void {
                $table->boolean('is_payment_requested')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'is_payment_requested')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn(['is_payment_requested', 'payment_requested_at']);
            });
        }

        if (Schema::hasTable('restaurant_tables') && Schema::hasColumn('restaurant_tables', 'is_payment_requested')) {
            Schema::table('restaurant_tables', function (Blueprint $table): void {
                $table->dropColumn('is_payment_requested');
            });
        }
    }
};
