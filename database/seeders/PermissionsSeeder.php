<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Danh sách các quyền thực tế trong hệ thống Aventura
        $permissions = [
            'manage_feedback',
            'view_audit_log',
            'manage_salary',
            'view_report',
            'manage_violations',
        ];

        // Tạo các permissions nếu chưa tồn tại
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // 2. Lấy hoặc tạo role owner (Chủ nhà hàng)
        $ownerRole = Role::firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
        ]);

        // Gán toàn bộ quyền cho role owner
        $ownerRole->syncPermissions($permissions);

        // 3. Lấy hoặc tạo role manager (Quản lý)
        $managerRole = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ]);

        // Manager được cấp quyền xem/quản lý phản hồi và xem/quản lý tố cáo sai phạm
        $managerRole->syncPermissions([
            'manage_feedback',
            'manage_violations',
        ]);

        // Xóa cache permission để đảm bảo cập nhật ngay lập tức
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Bỏ qua nếu có lỗi cache
        }
    }
}
