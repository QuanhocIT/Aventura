/**
 * Composable providing human-friendly Vietnamese labels, translations,
 * subject formatting, and field value diff rendering for Audit Logs.
 */

export function useAuditLogFormatter() {
    const ACTION_MAP: Record<string, string> = {
        // Overtime & HR Policy
        overtime_policy_updated: 'Cập nhật chính sách tăng ca',
        overtime_policy_created: 'Tạo chính sách tăng ca',
        overtime_request_created: 'Tạo yêu cầu tăng ca',
        overtime_request_approved: 'Phê duyệt yêu cầu tăng ca',
        overtime_request_rejected: 'Từ chối yêu cầu tăng ca',
        bonus_created: 'Tạo khoản thưởng nhân viên',
        bonus_updated: 'Cập nhật khoản thưởng nhân viên',
        salary_calculated: 'Tính bảng lương nhân sự',
        salary_approved: 'Phê duyệt bảng lương',

        // Warehouse & Inventory Tasks
        'warehouse_task.start': 'Bắt đầu công việc kho',
        'warehouse_task.complete': 'Hoàn thành công việc kho',
        'warehouse_task.cancel': 'Hủy công việc kho',
        'warehouse_task.assign': 'Phân công công việc kho',
        'warehouse_task.create': 'Tạo mới công việc kho',
        material_closing: 'Chốt kiểm kê nguyên vật liệu',

        // Employee Management
        'employee.created': 'Tạo mới hồ sơ nhân sự',
        'employee.updated': 'Cập nhật hồ sơ nhân sự',
        'employee.deleted': 'Xóa hồ sơ nhân sự',
        employee_created: 'Tạo mới hồ sơ nhân sự',
        employee_updated: 'Cập nhật hồ sơ nhân sự',
        employee_deleted: 'Xóa hồ sơ nhân sự',

        // Orders & POS
        order_created: 'Tạo đơn hàng mới',
        order_updated: 'Cập nhật đơn hàng',
        order_cancelled: 'Hủy đơn hàng',
        order_split: 'Tách đơn hàng',
        order_split_override: 'Xác nhận duyệt tách đơn',
        price_modified: 'Thay đổi đơn giá món',
        discount_applied: 'Áp dụng mã giảm giá',
        kitchen_menu_unavailable: 'Tạm ngưng phục vụ món bếp',

        // Inspections, Violations & Policies
        violation_reported: 'Lập báo cáo sai phạm nội bộ',
        violation_resolved: 'Xử lý báo cáo sai phạm nội bộ',
        company_policy_created: 'Tạo mới Bộ Quy định & Tiêu chuẩn',
        company_policy_updated: 'Cập nhật Bộ Quy định & Tiêu chuẩn',
        'operational_audit.reported': 'Lập biên bản vi phạm vận hành',
        'operational_audit.resolved': 'Xử lý biên bản vi phạm vận hành',

        // Fixed Assets
        fixed_asset_created: 'Tạo mới tài sản cố định',
        fixed_asset_updated: 'Cập nhật tài sản cố định',
        fixed_asset_transferred: 'Điều chuyển tài sản',
        fixed_asset_maintenance: 'Gửi bảo trì tài sản',

        // Shifts & Cash
        shift_handover_created: 'Mở phiên bàn giao ca',
        shift_handover_submitted: 'Nộp biên bản bàn giao ca',
        shift_handover_accepted: 'Xác nhận nhận bàn giao ca',
        shift_handover_disputed: 'Báo cáo tranh chấp ca',

        // Admin & Security
        reset_password: 'Đặt lại mật khẩu',
        disable_2fa: 'Tắt xác thực 2 bước (2FA)',
        toggle_account_status: 'Thay đổi trạng thái tài khoản',
        impersonate_start: 'Bắt đầu mạo danh đăng nhập',
        create_admin_account: 'Tạo tài khoản quản trị viên',
        update_admin_role: 'Cập nhật vai trò quản trị',
        'update_admin_role.before': 'Kiểm tra trước khi đổi vai trò',
        'update_admin_role.after': 'Đã hoàn tất đổi vai trò',
        export_audit_logs: 'Xuất dữ liệu nhật ký kiểm toán',
        audit_retention_update: 'Cập nhật thời hạn lưu trữ nhật ký',
        audit_retention_prune: 'Xóa nhật ký quá hạn lưu trữ',
        test_data_seeded: 'Seed dữ liệu thử nghiệm',
        seed_demo_order: 'Tạo đơn hàng mẫu demo',
    };

    const SUBJECT_MAP: Record<string, string> = {
        'App\\Models\\OvertimePolicy': 'Chính sách tăng ca',
        OvertimePolicy: 'Chính sách tăng ca',
        'App\\Models\\WarehouseTaskAssignment': 'Phân công công việc kho',
        WarehouseTaskAssignment: 'Phân công công việc kho',
        'App\\Models\\Employee': 'Hồ sơ nhân viên',
        Employee: 'Hồ sơ nhân viên',
        'App\\Models\\EmployeeBonus': 'Khoản thưởng nhân viên',
        EmployeeBonus: 'Khoản thưởng nhân viên',
        'App\\Models\\OvertimeRequest': 'Yêu cầu tăng ca',
        OvertimeRequest: 'Yêu cầu tăng ca',
        'App\\Models\\Order': 'Đơn hàng',
        Order: 'Đơn hàng',
        'App\\Models\\ShiftHandover': 'Biên bản bàn giao ca',
        ShiftHandover: 'Biên bản bàn giao ca',
        'App\\Models\\CompanyPolicy': 'Bộ Quy định & Tiêu chuẩn',
        CompanyPolicy: 'Bộ Quy định & Tiêu chuẩn',
        'App\\Models\\OperationalInfringementReport': 'Biên bản sai phạm',
        OperationalInfringementReport: 'Biên bản sai phạm',
        'App\\Models\\OperationalInspectionPlan': 'Kế hoạch thanh tra',
        OperationalInspectionPlan: 'Kế hoạch thanh tra',
        'App\\Models\\OperationalInspection': 'Phiên kiểm tra hiện trường',
        OperationalInspection: 'Phiên kiểm tra hiện trường',
        'App\\Models\\FixedAsset': 'Tài sản cố định',
        FixedAsset: 'Tài sản cố định',
        'App\\Models\\Salary': 'Bảng lương',
        Salary: 'Bảng lương',
        'App\\Models\\User': 'Tài khoản người dùng',
        User: 'Tài khoản người dùng',
        'App\\Models\\ChecklistTemplate': 'Mẫu quy trình checklist',
        ChecklistTemplate: 'Mẫu quy trình checklist',
        'App\\Models\\ChecklistCompletion': 'Checklist vận hành',
        ChecklistCompletion: 'Checklist vận hành',
        'App\\Models\\RestaurantBranch': 'Chi nhánh',
        RestaurantBranch: 'Chi nhánh',
        'App\\Models\\Restaurant': 'Nhà hàng',
        Restaurant: 'Nhà hàng',
    };

    const FIELD_MAP: Record<string, string> = {
        require_qr: 'Yêu cầu quét mã QR',
        require_gps: 'Yêu cầu vị trí GPS',
        require_photo: 'Yêu cầu chụp ảnh xác nhận',
        effective_from: 'Ngày áp dụng',
        max_daily_hours: 'Giờ tăng ca tối đa / ngày',
        max_weekly_hours: 'Giờ tăng ca tối đa / tuần',
        max_monthly_hours: 'Giờ tăng ca tối đa / tháng',
        normal_multiplier: 'Hệ số lương ca ngày thường',
        night_multiplier: 'Hệ số lương ca đêm',
        holiday_multiplier: 'Hệ số lương ngày lễ/tết',
        job_title: 'Chức danh / Vị trí',
        base_salary: 'Mức lương cơ bản',
        assigned_branch_id: 'Chi nhánh làm việc',
        full_name: 'Họ và tên',
        name: 'Tên hiển thị',
        phone: 'Số điện thoại',
        email: 'Địa chỉ Email',
        identity_card_number: 'Số CCCD / CMND',
        address: 'Địa chỉ liên hệ',
        birth_date: 'Ngày sinh',
        hire_date: 'Ngày vào làm',
        bank_name: 'Tên ngân hàng',
        bank_account_number: 'Số tài khoản ngân hàng',
        status: 'Trạng thái',
        is_active: 'Trạng thái hoạt động',
        role: 'Vai trò / Phân quyền',
        total_amount: 'Tổng tiền',
        subtotal_amount: 'Tạm tính tiền hàng',
        discount_amount: 'Số tiền giảm giá',
        price: 'Đơn giá món',
        quantity: 'Số lượng',
        notes: 'Ghi chú bổ sung',
        reason: 'Lý do thực hiện',
        title: 'Tiêu đề',
        description: 'Mô tả chi tiết',
        penalty_amount: 'Số tiền phạt',
        bonus_amount: 'Số tiền thưởng',
        net_salary: 'Lương thực nhận',
        start_time: 'Giờ bắt đầu',
        end_time: 'Giờ kết thúc',
        overtime_hours: 'Số giờ làm tăng ca',
        approved_by: 'Người phê duyệt',
        resolved_by: 'Người xử lý',
        branch_id: 'Chi nhánh áp dụng',
        created_at: 'Thời gian tạo',
        updated_at: 'Thời gian cập nhật',
        out_of_stock_until: 'Tạm ngưng phục vụ đến',
        out_of_stock_reason: 'Lý do tạm ngưng món',
        split_from_order_id: 'Tách từ đơn hàng #',
    };

    /**
     * Translates action strings to readable Vietnamese.
     */
    function formatAction(action: string): string {
        if (!action) {
            return 'Hành động không xác định';
        }

        if (ACTION_MAP[action]) {
            return ACTION_MAP[action];
        }

        // Format raw snake_case or dotted string
        return action
            .replace(/[._]/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    /**
     * Formats subject_type class names into friendly Vietnamese model names.
     */
    function formatSubjectType(type: string | null): string {
        if (!type) {
            return '';
        }

        const cleanType = type.replace(/^App\\Models\\/, '');

        return SUBJECT_MAP[type] ?? SUBJECT_MAP[cleanType] ?? cleanType;
    }

    /**
     * Formats field names into readable Vietnamese property titles.
     */
    function formatFieldLabel(key: string): string {
        if (FIELD_MAP[key]) {
            return FIELD_MAP[key];
        }

        return key
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    /**
     * Formats property values (booleans, nulls, currency, dates) into clean Vietnamese strings.
     */
    function formatFieldValue(val: any, key: string): string {
        if (val === null || val === undefined || val === '') {
            return '—';
        }

        if (typeof val === 'boolean') {
            return val ? 'Có (Đã bật)' : 'Không (Tắt)';
        }

        if (typeof val === 'number') {
            // Check if key implies currency
            if (/(amount|salary|price|penalty|bonus)/i.test(key) && val > 100) {
                return new Intl.NumberFormat('vi-VN').format(val) + ' đ';
            }

            return String(val);
        }

        if (typeof val === 'object') {
            return JSON.stringify(val);
        }

        const strVal = String(val);

        // Format ISO date format YYYY-MM-DD or YYYY-MM-DDTHH:mm:ss
        if (/^\d{4}-\d{2}-\d{2}(T|\s)?/.test(strVal)) {
            const date = new Date(strVal);

            if (!isNaN(date.getTime())) {
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');

                if (strVal.includes('T') || strVal.includes(':')) {
                    return `${hours}:${minutes} ${day}/${month}/${year}`;
                }

                return `${day}/${month}/${year}`;
            }
        }

        return strVal;
    }

    return {
        formatAction,
        formatSubjectType,
        formatFieldLabel,
        formatFieldValue,
    };
}
