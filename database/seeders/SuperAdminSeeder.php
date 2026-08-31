<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // The break-glass role is intentionally separate from platform-admin
        // sub-roles. Never seed a shared/default credential or a fake login.
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $email = (string) env('SUPERADMIN_EMAIL', 'superadmin@aventura.local');
        $bootstrapPassword = env('SUPERADMIN_BOOTSTRAP_PASSWORD');
        if (app()->environment('production') && blank($bootstrapPassword)) {
            throw new \RuntimeException('SUPERADMIN_BOOTSTRAP_PASSWORD must be configured before running the production seed.');
        }

        $bootstrapPassword ??= Str::password(32);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($bootstrapPassword),
                'email_verified_at' => null,
                'last_login_at' => null,
                'must_change_password' => true,
                'activation_token' => Str::random(40),
                'activation_expires_at' => now()->addDays(7),
                'status' => 'active',
            ]
        );

        // Do not reset an existing administrator's password on every deploy.
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
