<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            $table->integer('total_order_count')->default(0)->after('order_count');
            $table->integer('cash_order_count')->default(0)->after('total_order_count');
            $table->integer('transfer_order_count')->default(0)->after('cash_order_count');
            $table->integer('cancelled_order_count')->default(0)->after('transfer_order_count');
            $table->decimal('cancelled_total_amount', 12, 2)->default(0)->after('cancelled_order_count');
            $table->integer('refunded_order_count')->default(0)->after('cancelled_total_amount');
            $table->decimal('refunded_total_amount', 12, 2)->default(0)->after('refunded_order_count');
        });
    }

    public function down(): void
    {
        Schema::table('shift_closings', function (Blueprint $table) {
            $table->dropColumn([
                'total_order_count',
                'cash_order_count',
                'transfer_order_count',
                'cancelled_order_count',
                'cancelled_total_amount',
                'refunded_order_count',
                'refunded_total_amount',
            ]);
        });
    }
};
