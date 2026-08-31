<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class FixSuperAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SUPERADMIN_EMAIL', 'superadmin@aventura.local');
        $user = User::where('email', $email)->first();
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        if ($user && ! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }
}
