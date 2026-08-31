<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'superadmin.audit.manage',
            'guard_name' => 'web',
        ]);

        foreach (['system_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'superadmin.audit.manage')
            ->where('guard_name', 'web')
            ->first();
        if (! $permission) {
            return;
        }

        Role::whereHas('permissions', fn ($query) => $query->whereKey($permission->id))
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo($permission));
        $permission->delete();
    }
};
