<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('tracking_token', 64)
                ->nullable()
                ->after('order_number')
                ->unique('orders_tracking_token_unique');
        });

        DB::table('orders')
            ->whereNull('tracking_token')
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update([
                            'tracking_token' => hash(
                                'sha256',
                                $order->restaurant_id.'|'.$order->order_number.'|'.Str::random(64)
                            ),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_tracking_token_unique');
            $table->dropColumn('tracking_token');
        });
    }
};
