<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Tạo cả 2 roles cần thiết cho middleware role:super_admin|admin
        $adminRole = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::firstOrCreate(
            ['email' => 'superadmin@aventura.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Aventura@2026!'),
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ]
        );

        // Đảm bảo email đã verified
        if (is_null($user->email_verified_at)) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        // Gán cả 2 roles để đảm bảo luôn có quyền truy cập super-admin
        if (! $user->hasRole('admin')) {
            $user->assignRole($adminRole);
        }
        if (! $user->hasRole('super_admin')) {
            $user->assignRole($superAdminRole);
        }

        // Clear permission cache sau khi gán roles
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            //
        }
    }
}
