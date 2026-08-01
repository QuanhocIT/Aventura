<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_reservations') || Schema::hasColumn('inventory_reservations', 'branch_id')) {
            return;
        }

        Schema::table('inventory_reservations', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('restaurant_id')->constrained('restaurant_branches')->nullOnDelete();
            $table->index(['restaurant_id', 'branch_id', 'status'], 'inventory_reservations_branch_status_index');
        });

        if (Schema::hasColumn('orders', 'branch_id')) {
            DB::table('inventory_reservations')->whereNull('branch_id')->orderBy('id')->get(['id', 'order_id'])->each(function ($reservation): void {
                $branchId = DB::table('orders')->where('id', $reservation->order_id)->value('branch_id');
                DB::table('inventory_reservations')->where('id', $reservation->id)->update(['branch_id' => $branchId]);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_reservations') && Schema::hasColumn('inventory_reservations', 'branch_id')) {
            Schema::table('inventory_reservations', function (Blueprint $table): void {
                $table->dropIndex('inventory_reservations_branch_status_index');
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
