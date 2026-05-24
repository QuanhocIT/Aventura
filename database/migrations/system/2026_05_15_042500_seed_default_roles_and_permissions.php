<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->upsert([
            ['id' => 1, 'name' => 'manage_tenants', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'manage_restaurants', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'manage_staff', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'manage_menu', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'manage_inventory', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'create_order', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'view_order', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'name' => 'payment_order', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9, 'name' => 'view_kitchen_order', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'name' => 'update_food_status', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => 'view_report', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'name' => 'manage_salary', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 13, 'name' => 'manage_schedule', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 14, 'name' => 'manage_feedback', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 15, 'name' => 'view_audit_log', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
        ], ['id'], ['name', 'guard_name', 'updated_at']);

        DB::table('roles')->upsert([
            ['id' => 1, 'name' => 'super_admin', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'owner', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'manager', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'cashier', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'kitchen', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'inventory_staff', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'customer', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
        ], ['id'], ['name', 'guard_name', 'updated_at']);

        DB::table('role_has_permissions')->upsert([
            ['permission_id' => 1, 'role_id' => 1], ['permission_id' => 2, 'role_id' => 1], ['permission_id' => 3, 'role_id' => 1],
            ['permission_id' => 4, 'role_id' => 1], ['permission_id' => 5, 'role_id' => 1], ['permission_id' => 6, 'role_id' => 1],
            ['permission_id' => 7, 'role_id' => 1], ['permission_id' => 8, 'role_id' => 1], ['permission_id' => 9, 'role_id' => 1],
            ['permission_id' => 10, 'role_id' => 1], ['permission_id' => 11, 'role_id' => 1], ['permission_id' => 12, 'role_id' => 1],
            ['permission_id' => 13, 'role_id' => 1], ['permission_id' => 14, 'role_id' => 1], ['permission_id' => 15, 'role_id' => 1],

            ['permission_id' => 2, 'role_id' => 2], ['permission_id' => 3, 'role_id' => 2], ['permission_id' => 4, 'role_id' => 2],
            ['permission_id' => 5, 'role_id' => 2], ['permission_id' => 6, 'role_id' => 2], ['permission_id' => 7, 'role_id' => 2],
            ['permission_id' => 8, 'role_id' => 2], ['permission_id' => 9, 'role_id' => 2], ['permission_id' => 10, 'role_id' => 2],
            ['permission_id' => 11, 'role_id' => 2], ['permission_id' => 12, 'role_id' => 2], ['permission_id' => 13, 'role_id' => 2],
            ['permission_id' => 14, 'role_id' => 2], ['permission_id' => 15, 'role_id' => 2],

            ['permission_id' => 3, 'role_id' => 3], ['permission_id' => 4, 'role_id' => 3], ['permission_id' => 5, 'role_id' => 3],
            ['permission_id' => 6, 'role_id' => 3], ['permission_id' => 7, 'role_id' => 3], ['permission_id' => 8, 'role_id' => 3],
            ['permission_id' => 9, 'role_id' => 3], ['permission_id' => 10, 'role_id' => 3], ['permission_id' => 11, 'role_id' => 3],
            ['permission_id' => 12, 'role_id' => 3], ['permission_id' => 13, 'role_id' => 3], ['permission_id' => 14, 'role_id' => 3],
            ['permission_id' => 15, 'role_id' => 3],

            ['permission_id' => 6, 'role_id' => 4], ['permission_id' => 7, 'role_id' => 4], ['permission_id' => 8, 'role_id' => 4],
            ['permission_id' => 11, 'role_id' => 4],

            ['permission_id' => 7, 'role_id' => 5], ['permission_id' => 9, 'role_id' => 5], ['permission_id' => 10, 'role_id' => 5],

            ['permission_id' => 5, 'role_id' => 6], ['permission_id' => 7, 'role_id' => 6],

            ['permission_id' => 7, 'role_id' => 7],
        ], ['permission_id', 'role_id'], []);
    }

    public function down(): void
    {
        DB::table('role_has_permissions')->whereIn('role_id', [1, 2, 3, 4, 5, 6, 7])->delete();
        DB::table('roles')->whereIn('id', [1, 2, 3, 4, 5, 6, 7])->delete();
        DB::table('permissions')->whereIn('id', range(1, 15))->delete();
    }
};
