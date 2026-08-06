<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventories')
            ->where('quantity_on_hand', '>', 0)
            ->orderBy('id')
            ->eachById(function (object $inventory): void {
                $batchQuery = DB::table('inventory_batches')
                    ->where('restaurant_id', $inventory->restaurant_id)
                    ->where('ingredient_id', $inventory->ingredient_id);

                if ($inventory->branch_id === null) {
                    $batchQuery->whereNull('branch_id');
                } else {
                    $batchQuery->where('branch_id', $inventory->branch_id);
                }

                $batchedQuantity = (float) $batchQuery
                    ->whereIn('status', ['active', 'expired'])
                    ->sum('quantity_remaining');
                $missingQuantity = round((float) $inventory->quantity_on_hand - $batchedQuantity, 3);

                if ($missingQuantity <= 0) {
                    return;
                }

                DB::table('inventory_batches')->insert([
                    'restaurant_id' => $inventory->restaurant_id,
                    'branch_id' => $inventory->branch_id,
                    'ingredient_id' => $inventory->ingredient_id,
                    'batch_number' => 'LEGACY-'.$inventory->id,
                    'quantity_remaining' => $missingQuantity,
                    'unit_cost' => $inventory->last_cost ?? 0,
                    'purchased_at' => now()->toDateString(),
                    'expiry_date' => null,
                    'supplier_id' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('inventory_batches')
            ->where('batch_number', 'like', 'LEGACY-%')
            ->delete();
    }
};
