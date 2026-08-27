<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consolidate the former driver and assistant-keeper roles into the
     * single operational role used by the warehouse workflows.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names', []);
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $modelRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $rolePermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        if (! Schema::hasTable($rolesTable)) {
            return;
        }

        DB::transaction(function () use ($rolesTable, $modelRolesTable, $rolePermissionsTable): void {
            $now = now();
            $canonicalRoleId = DB::table($rolesTable)
                ->where('name', 'warehouse_staff')
                ->where('guard_name', 'web')
                ->value('id');

            if (! $canonicalRoleId) {
                $canonicalRoleId = DB::table($rolesTable)->insertGetId([
                    'name' => 'warehouse_staff',
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (! Schema::hasTable($modelRolesTable) || ! Schema::hasTable($rolePermissionsTable)) {
                return;
            }

            $legacyRoleIds = DB::table($rolesTable)
                ->whereIn('name', ['logistics_driver', 'assistant_warehouse_keeper'])
                ->where('guard_name', 'web')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if ($legacyRoleIds === []) {
                return;
            }

            // Keep every permission attached to an old role.
            $permissionRows = DB::table($rolePermissionsTable)
                ->whereIn('role_id', $legacyRoleIds)
                ->pluck('permission_id')
                ->unique()
                ->map(static fn ($permissionId): array => [
                    'permission_id' => $permissionId,
                    'role_id' => $canonicalRoleId,
                ])
                ->values()
                ->all();

            if ($permissionRows !== []) {
                DB::table($rolePermissionsTable)->insertOrIgnore($permissionRows);
            }

            // Replace old assignments with the canonical role. The composite
            // primary key makes this safe for users already carrying it.
            $assignments = DB::table($modelRolesTable)
                ->whereIn('role_id', $legacyRoleIds)
                ->get(['model_type', 'model_id']);

            foreach ($assignments as $assignment) {
                DB::table($modelRolesTable)->insertOrIgnore([
                    'role_id' => $canonicalRoleId,
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                ]);
            }

            // Employee records keep a direct role_id as the source for
            // scheduling and leave matching. Update it before removing the
            // old role rows so the foreign key remains valid.
            if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'role_id')) {
                DB::table('employees')
                    ->whereIn('role_id', $legacyRoleIds)
                    ->update(['role_id' => $canonicalRoleId]);
            }

            if (Schema::hasTable('overtime_policies') && Schema::hasColumn('overtime_policies', 'role_id')) {
                DB::table('overtime_policies')
                    ->whereIn('role_id', $legacyRoleIds)
                    ->update(['role_id' => $canonicalRoleId]);
            }

            DB::table($modelRolesTable)->whereIn('role_id', $legacyRoleIds)->delete();
            DB::table($rolePermissionsTable)->whereIn('role_id', $legacyRoleIds)->delete();
            DB::table($rolesTable)->whereIn('id', $legacyRoleIds)->delete();
        });

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Individual legacy assignments cannot be reconstructed after a merge.
     * Recreate the old names only as a rollback boundary; application code no
     * longer exposes or accepts them.
     */
    public function down(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');

        if (! Schema::hasTable($rolesTable)) {
            return;
        }

        $now = now();
        foreach (['logistics_driver', 'assistant_warehouse_keeper'] as $roleName) {
            DB::table($rolesTable)->insertOrIgnore([
                'name' => $roleName,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
