<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'superadmin@aventura.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Avenrura@2026!'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles(['super_admin']);
    }
}
