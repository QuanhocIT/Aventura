<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_branches')) {
            return;
        }

        // Only copy a branch when the related area provides an unambiguous
        // source. Areas without a branch, and tables whose area is also
        // unresolved, intentionally remain NULL for manual review.
        DB::table('restaurant_tables')
            ->whereNull('branch_id')
            ->orderBy('id')
            ->get(['id', 'area_id'])
            ->each(function ($table): void {
                $branchId = DB::table('areas')->where('id', $table->area_id)->value('branch_id');

                if ($branchId !== null) {
                    DB::table('restaurant_tables')->where('id', $table->id)->update(['branch_id' => $branchId]);
                }
            });

        // Do not make these columns NOT NULL here: unresolved legacy rows
        // must survive the migration so the audit command can list them for
        // manual assignment instead of silently choosing a branch.
    }

    public function down(): void
    {
        // Backfill migration: resolved rows are intentionally not reverted.
    }
};
