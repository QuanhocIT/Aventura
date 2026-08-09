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
            'create_orders',
            'manage_orders',
            'split_orders',
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
            'adjust_inventory',
            'company_policies.view',
            'company_policies.manage',
            'operational_audit.view',
            'operational_audit.report',
            'operational_audit.approve',
            'warehouse.view',
            'warehouse.manage',
            'warehouse_governance.view',
            'warehouse_governance.manage',
            'supplier.portal.view',
            'supply_requests.view',
            'supply_requests.create',
            'supply_requests.approve',
            'supply_requests.dispatch',
            'supply_requests.receive',
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
            'split_orders',
            'process_payments',
            'manage_kitchen',
            'manage_employees',
            'manage_salary',
            'manage_customers',
            'manage_feedback',
            'approve_requests',
            'view_violations',
            'report_violations',
            'view_report',
            'company_policies.view',
            'operational_audit.view',
            'supply_requests.view',
            'supply_requests.create',
            'supply_requests.receive',
        ]);

        // 4. Lấy hoặc tạo role cashier (Thu ngân)
        $cashierRole = Role::firstOrCreate([
            'name' => 'cashier',
            'guard_name' => 'web',
        ]);
        $cashierRole->syncPermissions([
            'create_orders',
            'split_orders',
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
            'view_violations',
            'report_violations',
            'adjust_inventory',
            'company_policies.view',
            'supply_requests.view',
            'supply_requests.create',
            'supply_requests.receive',
        ]);

        // 6b. Role warehouse_manager (Trưởng Kho Tổng)
        $warehouseManagerRole = Role::firstOrCreate([
            'name' => 'warehouse_manager',
            'guard_name' => 'web',
        ]);
        $warehouseManagerRole->syncPermissions([
            'adjust_inventory',
            'company_policies.view',
            'warehouse.view',
            'warehouse.manage',
            'warehouse_governance.view',
            'warehouse_governance.manage',
            'supply_requests.view',
            'supply_requests.approve',
            'supply_requests.dispatch',
        ]);

        // 6c. Role warehouse_staff (Nhân viên Kho Tổng)
        $warehouseStaffRole = Role::firstOrCreate([
            'name' => 'warehouse_staff',
            'guard_name' => 'web',
        ]);
        $warehouseStaffRole->syncPermissions([
            'adjust_inventory',
            'company_policies.view',
            'warehouse.view',
            'supply_requests.view',
            'supply_requests.dispatch',
        ]);

        // 6d. Role operations_inspector (Giám sát viên Vận hành / Auditor)
        $inspectorRole = Role::firstOrCreate([
            'name' => 'operations_inspector',
            'guard_name' => 'web',
        ]);
        $inspectorRole->syncPermissions([
            'view_violations',
            'report_violations',
            'company_policies.view',
            'operational_audit.view',
            'operational_audit.report',
        ]);

        // 6e. Role supplier (NhÃ  cung cáº¥p gá»‘c)
        $supplierRole = Role::firstOrCreate([
            'name' => 'supplier',
            'guard_name' => 'web',
        ]);
        $supplierRole->syncPermissions([
            'supplier.portal.view',
        ]);

        // 7. Lấy hoặc tạo role waiter (Nhân viên order)
        $waiterRole = Role::firstOrCreate([
            'name' => 'waiter',
            'guard_name' => 'web',
        ]);
        $waiterRole->syncPermissions([
            'create_orders',
            'manage_customers',
            'report_violations',
        ]);

        // Xóa cache permission để đảm bảo cập nhật ngay lập tức
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Bỏ qua nếu có lỗi cache
        }
    }
}
