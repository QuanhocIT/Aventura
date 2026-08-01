<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'superadmin.dashboard.view',
        'superadmin.tenant.view',
        'superadmin.tenant.manage',
        'superadmin.content.manage',
        'superadmin.crm.manage',
        'superadmin.churn.manage',
        'superadmin.billing.view',
        'superadmin.billing.manage',
        'superadmin.support.manage',
        'superadmin.system.manage',
        'superadmin.security.manage',
        'superadmin.backup.manage',
        'superadmin.audit.view',
        'superadmin.audit.export',
    ];

    private array $rolePermissions = [
        'billing_admin' => [
            'superadmin.dashboard.view',
            'superadmin.billing.view',
            'superadmin.billing.manage',
        ],
        'support_specialist' => [
            'superadmin.dashboard.view',
            'superadmin.support.manage',
        ],
        'system_admin' => [
            'superadmin.dashboard.view',
            'superadmin.tenant.view',
            'superadmin.tenant.manage',
            'superadmin.content.manage',
            'superadmin.crm.manage',
            'superadmin.churn.manage',
            'superadmin.billing.view',
            'superadmin.billing.manage',
            'superadmin.support.manage',
            'superadmin.system.manage',
            'superadmin.security.manage',
            'superadmin.backup.manage',
            'superadmin.audit.view',
            'superadmin.audit.export',
        ],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach ($this->rolePermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }
    }

    public function down(): void
    {
        // Restore the pre-refinement matrix for the sub-roles.
        $legacyRolePermissions = [
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

        foreach ($legacyRolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();

            if ($role) {
                $role->syncPermissions($permissions);
            }
        }

        Permission::whereIn('name', array_diff($this->permissions, [
            'superadmin.dashboard.view',
            'superadmin.billing.manage',
            'superadmin.support.manage',
            'superadmin.system.manage',
        ]))->delete();
    }
};
