<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['leave_requests', 'schedule_registrations', 'shift_swaps'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'branch_id')) {
                $indexName = 'hr_'.$tableName.'_restaurant_branch_index';
                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->foreignId('branch_id')->nullable()->after('restaurant_id')->constrained('restaurant_branches')->nullOnDelete();
                    $table->index(['restaurant_id', 'branch_id'], $indexName);
                });
            }
        }

        if (Schema::hasTable('leave_requests')) {
            DB::table('leave_requests')->orderBy('id')->get(['id', 'employee_id'])->each(function ($row): void {
                $branchId = DB::table('employees')->where('id', $row->employee_id)->value('branch_id');
                DB::table('leave_requests')->where('id', $row->id)->update(['branch_id' => $branchId]);
            });
        }

        if (Schema::hasTable('schedule_registrations')) {
            DB::table('schedule_registrations')->orderBy('id')->get(['id', 'employee_id'])->each(function ($row): void {
                $branchId = DB::table('employees')->where('id', $row->employee_id)->value('branch_id');
                DB::table('schedule_registrations')->where('id', $row->id)->update(['branch_id' => $branchId]);
            });
        }

        if (Schema::hasTable('shift_swaps')) {
            DB::table('shift_swaps')->orderBy('id')->get(['id', 'requester_assignment_id'])->each(function ($row): void {
                $branchId = DB::table('schedule_assignments')->where('id', $row->requester_assignment_id)->value('branch_id');
                DB::table('shift_swaps')->where('id', $row->id)->update(['branch_id' => $branchId]);
            });
        }
    }

    public function down(): void
    {
        foreach (['leave_requests', 'schedule_registrations', 'shift_swaps'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'branch_id')) {
                $indexName = 'hr_'.$tableName.'_restaurant_branch_index';
                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                });
            }
        }
    }
};
