<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixSuperAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Gán role super_admin (id=1) cho user id=8
        $exists = DB::table('model_has_roles')
            ->where('role_id', 1)
            ->where('model_type', 'App\\Models\\User')
            ->where('model_id', 8)
            ->exists();
        if (! $exists) {
            DB::table('model_has_roles')->insert([
                'role_id' => 1,
                'model_type' => 'App\\Models\\User',
                'model_id' => 8,
            ]);
        }
    }
}
