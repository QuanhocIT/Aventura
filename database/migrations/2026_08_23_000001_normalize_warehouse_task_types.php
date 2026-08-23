<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('warehouse_task_assignments')
            ->whereIn('task_type', ['inventory_count'])
            ->update(['task_type' => 'counting']);

        DB::table('warehouse_task_assignments')
            ->whereIn('task_type', ['discrepancy_resolution', 'incident_resolution'])
            ->update(['task_type' => 'incident']);

        DB::table('warehouse_task_assignments')
            ->whereIn('task_type', ['shift_handover'])
            ->update(['task_type' => 'handover']);
    }

    public function down(): void
    {
        DB::table('warehouse_task_assignments')
            ->where('task_type', 'counting')
            ->update(['task_type' => 'inventory_count']);

        DB::table('warehouse_task_assignments')
            ->where('task_type', 'handover')
            ->update(['task_type' => 'shift_handover']);
    }
};
