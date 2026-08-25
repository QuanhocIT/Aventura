<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'started_preparing_at')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dateTime('started_preparing_at')->nullable()->after('sent_to_kitchen_at');
            });
        }

        if (Schema::hasTable('order_items_archive')
            && ! Schema::hasColumn('order_items_archive', 'started_preparing_at')) {
            Schema::table('order_items_archive', function (Blueprint $table): void {
                $table->dateTime('started_preparing_at')->nullable()->after('sent_to_kitchen_at');
            });
        }

        // Preserve the meaning of legacy in-progress items created before
        // this explicit kitchen-start marker was introduced.
        DB::table('order_items')
            ->whereNull('started_preparing_at')
            ->where('status', 'preparing')
            ->whereNull('prepared_at')
            ->update(['started_preparing_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'started_preparing_at')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropColumn('started_preparing_at');
            });
        }

        if (Schema::hasTable('order_items_archive')
            && Schema::hasColumn('order_items_archive', 'started_preparing_at')) {
            Schema::table('order_items_archive', function (Blueprint $table): void {
                $table->dropColumn('started_preparing_at');
            });
        }
    }
};
