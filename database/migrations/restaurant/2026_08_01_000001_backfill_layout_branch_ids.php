<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_branches')) {
            return;
        }

        $defaultBranches = DB::table('restaurant_branches')
            ->select('restaurant_id', 'id')
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->groupBy('restaurant_id')
            ->map(fn ($branches) => (int) $branches->first()->id);

        foreach ($defaultBranches as $restaurantId => $defaultBranchId) {
            DB::table('areas')
                ->where('restaurant_id', $restaurantId)
                ->whereNull('branch_id')
                ->update(['branch_id' => $defaultBranchId]);

            DB::table('restaurant_tables')
                ->where('restaurant_id', $restaurantId)
                ->whereNull('branch_id')
                ->orderBy('id')
                ->get(['id', 'area_id'])
                ->each(function ($table) use ($defaultBranchId): void {
                    $branchId = DB::table('areas')->where('id', $table->area_id)->value('branch_id') ?: $defaultBranchId;
                    DB::table('restaurant_tables')->where('id', $table->id)->update(['branch_id' => $branchId]);
                });
        }

        // MySQL is the production engine. SQLite test databases keep the
        // historical nullable definition because SQLite cannot alter a
        // foreign-key column in place; application validation still rejects
        // new area/table writes without a branch.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE areas MODIFY branch_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE restaurant_tables MODIFY branch_id BIGINT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE areas MODIFY branch_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE restaurant_tables MODIFY branch_id BIGINT UNSIGNED NULL');
        }
    }
};
