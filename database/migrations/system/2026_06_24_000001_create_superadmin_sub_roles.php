<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'superadmin.dashboard.view',
        'superadmin.billing.manage',
        'superadmin.support.manage',
        'superadmin.system.manage',
    ];

    private array $rolePermissions = [
        'billing_admin' => [
            'superadmin.dashboard.view',
            'superadmin.billing.manage',
        ],
        'support_specialist' => [
            'superadmin.dashboard.view',
            'superadmin.support.manage',
        ],
        'system_admin' => [
            'superadmin.dashboard.view',
            'superadmin.billing.manage',
            'superadmin.support.manage',
            'superadmin.system.manage',
        ],
    ];

    public function up(): void
    {
        // Create permissions
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        foreach ($this->rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        // Existing super_admin users get system_admin role as well (backward compat)
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $systemAdminRole = Role::where('name', 'system_admin')->first();
            foreach ($superAdminRole->users as $user) {
                if (! $user->hasRole('system_admin')) {
                    $user->assignRole($systemAdminRole);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->rolePermissions) as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->syncPermissions([]);
                $role->delete();
            }
        }

        foreach ($this->permissions as $name) {
            Permission::where('name', $name)->delete();
        }
    }
};
