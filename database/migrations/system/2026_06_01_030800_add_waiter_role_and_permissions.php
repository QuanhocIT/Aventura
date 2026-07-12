<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. Insert the waiter role
        $roleId = DB::table('roles')->insertGetId([
            'name'       => 'waiter',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Assign permissions: create_order (6) and view_order (7) to the waiter role
        DB::table('role_has_permissions')->insert([
            ['permission_id' => 6, 'role_id' => $roleId],
            ['permission_id' => 7, 'role_id' => $roleId],
        ]);

        // 3. Clear permission cache
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('name', 'waiter')->first();
        if ($role) {
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }

        // Clear permission cache
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Ignore
        }
    }
};
