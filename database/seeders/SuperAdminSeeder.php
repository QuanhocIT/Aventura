<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Tạo role admin nếu chưa có
        $role = Role::firstOrCreate(['name' => 'admin']);

        // Tìm hoặc tạo user superadmin
        $user = User::firstOrCreate(
            ['email' => 'superadmin@aventura.local'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('Avenrura@2026!'),
            ]
        );

        // Gán role admin cho user
        if (!$user->hasRole('admin')) {
            $user->assignRole('admin');
        }
    }
}
