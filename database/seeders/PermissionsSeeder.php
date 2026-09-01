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
            // ── Đặt hàng & Thanh toán ─────────────────────────────────────────
            'create_orders',
            'manage_orders',
            'split_orders',
            'process_payments',
            'override_split_penalty',

            // ── Bếp & Nhà hàng ────────────────────────────────────────────────
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

            // ── Tồn kho & Chi nhánh ───────────────────────────────────────────
            'adjust_inventory',
            'inventory.count',            // Thực hiện kiểm kê tồn kho
            'inventory.adjust.approve',   // Duyệt điều chỉnh tồn kho (khác người thực hiện)

            // ── Kho Tổng: Trưởng Kho ─────────────────────────────────────────
            'warehouse.view',
            'warehouse.manage',
            'warehouse.dashboard',        // Xem dashboard tổng hợp Kho Tổng
            'warehouse.report',           // Xuất báo cáo tồn kho, giao hàng, sai lệch
            'warehouse.audit',            // Xem nhật ký kiểm toán kho
            'warehouse.receive',          // Nhập hàng vào Kho Tổng từ nhà cung cấp

            // ── Kho Tổng: Quyền nhỏ thực thi & Duyệt ─────────────────────────
            'warehouse.receive.submit',   // Nhận hàng thực tế / PO
            'warehouse.putaway',          // Cất hàng vào vị trí
            'warehouse.pick',             // Soạn hàng & quét mã lô/nguyên liệu
            'warehouse.pack',             // Soạn hàng, quét mã lô, đóng gói
            'warehouse.handover',         // Bàn giao hàng cho đơn vị vận chuyển
            'inventory.count.execute',    // Thực hiện đếm kiểm kê thực tế
            'inventory.adjust.request',   // Đề xuất điều chỉnh tồn kho
            'warehouse.dispute.view',     // Xem hồ sơ tranh chấp thiếu/hỏng
            'supply_requests.dispatch_approve', // Duyệt xuất kho (Trưởng kho/Owner)

            // ── Kho Tổng: Quyền nhân viên vận hành mới ───────────────────────
            'warehouse.scan',             // Quét mã QR/barcode nguyên liệu, lô, vị trí
            'warehouse.incident.report',  // Báo sự cố, hỏng hóc, thiếu hụt
            'warehouse.shift.handover',   // Bàn giao ca cuối làm việc
            'warehouse.own_history.view', // Xem lịch sử thao tác của chính mình
            'warehouse.receiving.create', // Tạo phiếu nhận hàng GRN
            'warehouse.receiving.confirm', // Xác nhận phiếu nhận hàng

            // ── Kho Tổng: Quyền Trưởng Kho bổ sung ──────────────────────────
            'warehouse.task.assign',      // Phân công công việc cho nhân viên
            'warehouse.kpi.view',         // Xem KPI nhân viên kho
            'warehouse.location.manage',  // Quản lý vị trí kho và quy tắc vận hành
            'warehouse.limit.configure',  // Cấu hình hạn mức chênh lệch theo doanh nghiệp
            'warehouse.receiving.verify', // Xác minh phiếu nhận hàng (manager)

            // ── Kho Tổng: Quyền Quản lý Đội ngũ Kho Tổng ─────────────────────
            'warehouse.staff.view',
            'warehouse.staff.assignment.manage',
            'warehouse.staff.schedule.manage',
            'warehouse.staff.attendance.view',
            'warehouse.staff.attendance.manage',
            'warehouse.staff.leave.approve',
            'warehouse.staff.kpi.view',
            'warehouse.staff.incident.manage',

            // ── Quy tắc Quản trị Kho ─────────────────────────────────────────
            'warehouse_governance.view',
            'warehouse_governance.manage',

            // ── Yêu cầu cấp phát ─────────────────────────────────────────────
            'supply_requests.view',
            'supply_requests.create',
            'supply_requests.approve',    // Duyệt đơn (phải khác người tạo)
            'supply_requests.dispatch',   // Duyệt xuất kho (phải khác người soạn)
            'supply_requests.receive',    // Xác nhận nhận hàng (phải khác người xuất)
            'supply_requests.cancel',     // Hủy đơn chưa xuất
            'supply_requests.partial',    // Cấp phát một phần / hỗ trợ backorder

            // ── Quản lý đơn giá ──────────────────────────────────────────────
            'price_management.view',
            'price_management.manage',    // Thay đổi đơn giá nguyên liệu
            'price_management.approve',   // Duyệt thay đổi giá vượt biên động (chỉ owner)

            // ── Chính sách & Kiểm toán ───────────────────────────────────────
            'company_policies.view',
            'company_policies.manage',
            'operational_audit.view',
            'operational_audit.report',
            'operational_audit.approve',
             'operational_audit.manage',
             'operational_audit.reinspect',
             'operational_audit.view_all',
             'operational_audit.assign',
             'operational_audit.accept_assignment',
             'operational_audit.verify',
             'operational_audit.capa.manage',
             'operational_audit.capa.verify',
             'operational_audit.evidence.upload',
             'operational_audit.branch_acknowledge',
             'operational_inspection.view',
             'operational_inspection.create',
             'operational_inspection.execute',
             'operational_inspection.manage',

            // ── Nhà cung cấp ─────────────────────────────────────────────────
            'supplier.portal.view',

            // ── Đào tạo & Thiết bị ─────────────────────────────────────────
            'training.manage',
            'equipment.manage',
            'equipment.report',

            // ── Quyền Quản trị Tài chính, Analytics, Goal, Flow & Inspection ──
            'finance.view',
            'finance.manage',
            'fixed_assets.view',
            'fixed_assets.view_all',
            'fixed_assets.manage',
            'fixed_assets.inspect',
            'analytics.view',
            'goals.manage',
            'cashflow.view',
            'cashflow.manage',
            'audit.read',
            'audit.manage',
            'branch.manage',
            'warehouse.approve',
            'warehouse.dispatch',
            'inspection.close',
        ];

        // Tạo các permissions nếu chưa tồn tại
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name'       => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // ─────────────────────────────────────────────────────────────────────
        // 2. Chủ nhà hàng (owner) — toàn quyền
        // ─────────────────────────────────────────────────────────────────────
        $ownerRole = Role::firstOrCreate([
            'name'       => 'owner',
            'guard_name' => 'web',
        ]);
        $ownerRole->syncPermissions($permissions);

        // ─────────────────────────────────────────────────────────────────────
        // 3. Quản lý chi nhánh (manager) — điều hành chi nhánh, tạo và nhận hàng
        // ─────────────────────────────────────────────────────────────────────
        $managerRole = Role::firstOrCreate([
            'name'       => 'manager',
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
            'manage_violations',
            'view_violations',
            'report_violations',
            'view_report',
            'view_fraud_detection',
            'adjust_inventory',
            'inventory.count',
            'company_policies.view',
             'operational_audit.view',
             'operational_audit.branch_acknowledge',
            'supply_requests.view',
            'supply_requests.create',
            'supply_requests.receive',    // Chi nhánh xác nhận nhận hàng
            'supply_requests.cancel',     // Hủy đơn do mình tạo (còn pending)
            'price_management.view',
            'training.manage',
            'equipment.manage',
            'equipment.report',
            'finance.view',
            'fixed_assets.view',
            'cashflow.view',
            'cashflow.manage',
            'branch.manage',
            'goals.manage',
            'analytics.view',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // 4. Thu ngân (cashier)
        // ─────────────────────────────────────────────────────────────────────
        $cashierRole = Role::firstOrCreate([
            'name'       => 'cashier',
            'guard_name' => 'web',
        ]);
        $cashierRole->syncPermissions([
            'create_orders',
            'split_orders',
            'process_payments',
            'manage_customers',
            'report_violations',
            'equipment.report',
            'cashflow.view',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // 5. Bếp (kitchen)
        // ─────────────────────────────────────────────────────────────────────
        $kitchenRole = Role::firstOrCreate([
            'name'       => 'kitchen',
            'guard_name' => 'web',
        ]);
        $kitchenRole->syncPermissions([
            'manage_kitchen',
            'report_violations',
            'equipment.report',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // 6. Nhân viên kho chi nhánh (inventory_staff)
        // ─────────────────────────────────────────────────────────────────────
        $inventoryRole = Role::firstOrCreate([
            'name'       => 'inventory_staff',
            'guard_name' => 'web',
        ]);
        $inventoryRole->syncPermissions([
            'view_violations',
            'report_violations',
            'adjust_inventory',
            'inventory.count',
            'company_policies.view',
            'supply_requests.view',
            'supply_requests.create',
            'supply_requests.receive',
            'equipment.report',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // 6b. Trưởng Kho Tổng (warehouse_manager)
        //     Quyền chính: xem toàn chuỗi, duyệt cấp phát, xử lý sai lệch,
        //     duyệt kiểm kê, quản lý giá, xuất báo cáo
        //     Bị chặn: tự tạo đơn rồi tự duyệt (enforcement ở middleware)
        // ─────────────────────────────────────────────────────────────────────
        $warehouseManagerRole = Role::firstOrCreate([
            'name'       => 'warehouse_manager',
            'guard_name' => 'web',
        ]);
        $warehouseManagerRole->syncPermissions([
            'adjust_inventory',
            'inventory.count',
            'inventory.count.execute',
            'inventory.adjust.request',
            'inventory.adjust.approve',
            'company_policies.view',
            'warehouse.view',
            'warehouse.manage',
            'warehouse.dashboard',
            'warehouse.report',
            'warehouse.audit',
            'warehouse.receive',
            'warehouse.receive.submit',
            'warehouse.putaway',
            'warehouse.pick',
            'warehouse.pack',
            'warehouse.handover',
            'warehouse.dispute.view',
            'warehouse_governance.view',
            'warehouse_governance.manage',
            'supply_requests.view',
            'supply_requests.approve',
            'supply_requests.dispatch',
            'supply_requests.dispatch_approve',
            'supply_requests.cancel',
            'supply_requests.partial',
            'price_management.view',
            'operational_audit.view',
            'view_violations',
            'report_violations',
            'manage_violations',
            'warehouse.approve',
            'warehouse.dispatch',
            // Quyền mới bổ sung
            'warehouse.scan',
            'warehouse.incident.report',
            'warehouse.shift.handover',
            'warehouse.own_history.view',
            'warehouse.receiving.create',
            'warehouse.receiving.verify',
            'warehouse.task.assign',
            'warehouse.kpi.view',
            'warehouse.location.manage',
            'warehouse.limit.configure',
            'equipment.report',
            'warehouse.staff.view',
            'warehouse.staff.assignment.manage',
            'warehouse.staff.schedule.manage',
            'warehouse.staff.attendance.view',
            'warehouse.staff.attendance.manage',
            'warehouse.staff.leave.approve',
            'warehouse.staff.kpi.view',
            'warehouse.staff.incident.manage',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // 6c. Nhân viên Kho Tổng (warehouse_staff)
        //     Quyền chính: nhập hàng thực tế, soạn hàng, quét mã, đóng gói, bàn giao, đếm kiểm kê
        //     Không có: duyệt đơn, duyệt xuất, điều chỉnh tồn độc lập, sửa đơn giá
        // ─────────────────────────────────────────────────────────────────────
        $warehouseStaffRole = Role::firstOrCreate([
            'name'       => 'warehouse_staff',
            'guard_name' => 'web',
        ]);
        $warehouseStaffRole->syncPermissions([
            // Xem & điều hướng
            'warehouse.view',
            'supply_requests.view',
            'company_policies.view',
            'warehouse.own_history.view',
            'view_violations',
            'report_violations',
            // Nhận hàng
            'warehouse.receive.submit',
            'warehouse.receiving.create',
            // Quét mã
            'warehouse.scan',
            // Vận hành kho
            'warehouse.putaway',
            'warehouse.pick',
            'warehouse.pack',
            'warehouse.handover',
            // Kiểm kê
            'inventory.count.execute',
            // Đề xuất & báo cáo
            'inventory.adjust.request',
            'warehouse.incident.report',
            // Bàn giao ca
            'warehouse.shift.handover',
            // Xem tranh chấp (chỉ xem, không tự đóng)
            'warehouse.dispute.view',
            'equipment.report',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // 6d. Giám sát vận hành (operations_inspector)
        // ─────────────────────────────────────────────────────────────────────
        $inspectorRole = Role::firstOrCreate([
            'name'       => 'operations_inspector',
            'guard_name' => 'web',
        ]);
        $inspectorRole->syncPermissions([
            'view_violations',
            'report_violations',
            'company_policies.view',
            'operational_audit.view',
            'operational_audit.report',
             'operational_audit.manage',
             'operational_audit.reinspect',
             'operational_audit.view_all',
             'operational_audit.evidence.upload',
             'operational_inspection.view',
             'operational_inspection.create',
             'operational_inspection.execute',
            'fixed_assets.view',
            'fixed_assets.view_all',
            'fixed_assets.inspect',
            'warehouse.report',
            'warehouse.audit',
            'supply_requests.view',
            'price_management.view',
            'analytics.view',
            'audit.read',
            'audit.manage',
            'view_audit_log',
            'inspection.close',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // 6e. Thanh tra độc lập (compliance_auditor)
        // ─────────────────────────────────────────────────────────────────────
        $auditorRole = Role::firstOrCreate([
            'name'       => 'compliance_auditor',
            'guard_name' => 'web',
        ]);
        $auditorRole->syncPermissions([
            'view_violations',
            'report_violations',
            'company_policies.view',
            'operational_audit.view',
            'operational_audit.report',
             'operational_audit.reinspect',
             'operational_audit.view_all',
             'operational_audit.accept_assignment',
             'operational_audit.verify',
             'operational_audit.capa.verify',
             'operational_audit.evidence.upload',
             'operational_inspection.view',
             'operational_inspection.execute',
            'fixed_assets.view',
            'fixed_assets.view_all',
            'fixed_assets.inspect',
            'audit.read',
            'audit.manage',
            'view_audit_log',
            'inspection.close',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // 6f. Kế toán / Tài chính tập đoàn (accountant)
        // ─────────────────────────────────────────────────────────────────────
        $accountantRole = Role::firstOrCreate([
            'name'       => 'accountant',
            'guard_name' => 'web',
        ]);
        $accountantRole->syncPermissions([
            'finance.view',
            'finance.manage',
            'fixed_assets.view',
            'fixed_assets.view_all',
            'fixed_assets.manage',
            'cashflow.view',
            'cashflow.manage',
            'price_management.view',
            'view_report',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // 6g. Nhà cung cấp (supplier)
        // ─────────────────────────────────────────────────────────────────────
        $supplierRole = Role::firstOrCreate([
            'name'       => 'supplier',
            'guard_name' => 'web',
        ]);
        $supplierRole->syncPermissions([
            'supplier.portal.view',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // 7. Nhân viên order (waiter)
        // ─────────────────────────────────────────────────────────────────────
        $waiterRole = Role::firstOrCreate([
            'name'       => 'waiter',
            'guard_name' => 'web',
        ]);
        $waiterRole->syncPermissions([
            'create_orders',
            'manage_customers',
            'report_violations',
            'equipment.report',
        ]);

        // Xóa cache permission để đảm bảo cập nhật ngay lập tức
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Bỏ qua nếu có lỗi cache
        }
    }
}
