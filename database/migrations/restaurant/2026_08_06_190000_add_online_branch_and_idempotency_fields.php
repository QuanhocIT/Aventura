<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('online_store_configs') && ! Schema::hasColumn('online_store_configs', 'branch_id')) {
            Schema::table('online_store_configs', function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('restaurant_id')
                    ->constrained('restaurant_branches')
                    ->nullOnDelete();
                $table->index(['restaurant_id', 'branch_id'], 'online_store_configs_branch_index');
            });
        }

        if (Schema::hasTable('online_store_configs') && Schema::hasTable('restaurant_branches')) {
            DB::table('online_store_configs')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->get(['id', 'restaurant_id'])
                ->each(function (object $config): void {
                    $branchId = DB::table('restaurant_branches')
                        ->where('restaurant_id', $config->restaurant_id)
                        ->where('status', 'active')
                        ->orderBy('id')
                        ->value('id');

                    if ($branchId !== null) {
                        DB::table('online_store_configs')
                            ->where('id', $config->id)
                            ->update(['branch_id' => $branchId]);
                    }
                });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'client_request_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('client_request_id', 100)->nullable()->after('order_number');
                $table->index(['restaurant_id', 'client_request_id'], 'orders_client_request_index');
            });
        }

        if (Schema::hasTable('table_reservations') && ! Schema::hasColumn('table_reservations', 'branch_id')) {
            Schema::table('table_reservations', function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('restaurant_id')
                    ->constrained('restaurant_branches')
                    ->nullOnDelete();
                $table->index(['restaurant_id', 'branch_id', 'reservation_date', 'status'], 'reservations_branch_date_status_index');
            });
        }

        if (Schema::hasTable('table_reservations') && Schema::hasTable('restaurant_tables')) {
            DB::table('table_reservations')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->get(['id', 'restaurant_id', 'table_id'])
                ->each(function (object $reservation): void {
                    $branchId = $reservation->table_id
                        ? DB::table('restaurant_tables')->where('id', $reservation->table_id)->value('branch_id')
                        : null;

                    $branchId ??= DB::table('restaurant_branches')
                        ->where('restaurant_id', $reservation->restaurant_id)
                        ->where('status', 'active')
                        ->orderBy('id')
                        ->value('id');

                    if ($branchId !== null) {
                        DB::table('table_reservations')
                            ->where('id', $reservation->id)
                            ->update(['branch_id' => $branchId]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('table_reservations') && Schema::hasColumn('table_reservations', 'branch_id')) {
            Schema::table('table_reservations', function (Blueprint $table): void {
                $table->dropForeign(['branch_id']);
                $table->dropIndex('reservations_branch_date_status_index');
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'client_request_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropIndex('orders_client_request_index');
                $table->dropColumn('client_request_id');
            });
        }

        if (Schema::hasTable('online_store_configs') && Schema::hasColumn('online_store_configs', 'branch_id')) {
            Schema::table('online_store_configs', function (Blueprint $table): void {
                $table->dropForeign(['branch_id']);
                $table->dropIndex('online_store_configs_branch_index');
                $table->dropColumn('branch_id');
            });
        }
    }
};
