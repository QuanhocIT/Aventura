<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_warehouse_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['restaurant_id', 'branch_id'], 'central_warehouse_assignment_branch_unique');
        });

        $restaurants = DB::table('restaurant_branches')
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->where('is_central_warehouse', true)
                    ->orWhere('warehouse_type', 'central');
            })
            ->orderBy('restaurant_id')
            ->orderBy('id')
            ->get(['restaurant_id', 'id']);

        foreach ($restaurants->groupBy('restaurant_id') as $restaurantId => $branches) {
            $centralBranchId = (int) $branches->first()->id;
            $now = now();

            DB::table('central_warehouse_assignments')->insert([
                'restaurant_id' => (int) $restaurantId,
                'branch_id' => $centralBranchId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $otherCentralIds = $branches->skip(1)->pluck('id')->all();
            if ($otherCentralIds !== []) {
                DB::table('restaurant_branches')
                    ->whereIn('id', $otherCentralIds)
                    ->update([
                        'is_central_warehouse' => false,
                        'warehouse_type' => 'business',
                        'updated_at' => $now,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('central_warehouse_assignments');
    }
};
