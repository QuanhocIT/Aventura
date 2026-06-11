<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Danh sách các quyền thực tế trong hệ thống Aventura
        $permissions = [
            'create_orders',
            'manage_orders',
            'process_payments',
            'override_split_penalty',
            'manage_kitchen',
            'manage_employees',
            'manage_salary',
            'view_report',
            'view_fraud_detection',
            'manage_customers',
            'export_customers',
            'view_audit_log',
            'approve_requests',
            'manage_feedback',
            'manage_violations',
            'view_violations',
            'report_violations',
            'manage_restaurant_settings',
            'manage_inventory',
            'manage_supplier_catalog',
            'view_supplier_price_history',
        ];

        // Tạo các permissions nếu chưa tồn tại
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // 2. Lấy hoặc tạo role owner (Chủ nhà hàng) - Có đầy đủ quyền
        $ownerRole = Role::firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
        ]);
        $ownerRole->syncPermissions($permissions);

        // 3. Lấy hoặc tạo role manager (Quản lý)
        $managerRole = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ]);
        $managerRole->syncPermissions([
            'create_orders',
            'manage_orders',
            'process_payments',
            'manage_kitchen',
            'manage_employees',
            'manage_customers',
            'manage_feedback',
            'view_violations',
            'report_violations',
            'view_report',
        ]);

        // 4. Lấy hoặc tạo role cashier (Thu ngân)
        $cashierRole = Role::firstOrCreate([
            'name' => 'cashier',
            'guard_name' => 'web',
        ]);
        $cashierRole->syncPermissions([
            'create_orders',
            'process_payments',
            'manage_customers',
            'report_violations',
        ]);

        // 5. Lấy hoặc tạo role kitchen (Bếp)
        $kitchenRole = Role::firstOrCreate([
            'name' => 'kitchen',
            'guard_name' => 'web',
        ]);
        $kitchenRole->syncPermissions([
            'manage_kitchen',
            'report_violations',
        ]);

        // 6. Lấy hoặc tạo role inventory_staff (Nhân viên kho)
        $inventoryRole = Role::firstOrCreate([
            'name' => 'inventory_staff',
            'guard_name' => 'web',
        ]);
        $inventoryRole->syncPermissions([
            'manage_inventory',
            'view_violations',
            'report_violations',
        ]);

        // 7. Role supplier_admin (Đại diện nhà cung cấp)
        $supplierAdminRole = Role::firstOrCreate([
            'name' => 'supplier_admin',
            'guard_name' => 'web',
        ]);
        $supplierAdminRole->syncPermissions([
            'manage_supplier_catalog',
            'view_supplier_price_history',
        ]);

        // Xóa cache permission để đảm bảo cập nhật ngay lập tức
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable) {
            // Bỏ qua nếu có lỗi cache
        }
    }
}
