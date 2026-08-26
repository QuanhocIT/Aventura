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
        order_confirmed: 'Xác nhận đơn hàng',
        order_paid: 'Thanh toán đơn hàng',
        order_cancelled: 'Hủy đơn hàng',
        order_item_cancelled: 'Hủy món trong đơn',
        kitchen_item_cancelled: 'Hủy món trong bếp',
        temporary_order_confirmed: 'Xác nhận đơn tạm',
        temporary_order_revision_requested: 'Yêu cầu sửa đơn tạm',
        temporary_order_cancelled_by_guest: 'Khách hủy đơn tạm',
        order_split: 'Tách đơn hàng',
        order_split_override: 'Xác nhận duyệt tách đơn',
        price_modified: 'Thay đổi đơn giá món',
        discount_applied: 'Áp dụng mã giảm giá',
        kitchen_menu_unavailable: 'Tạm ngưng phục vụ món bếp',
        kitchen_product_paused: 'Tạm ngưng phục vụ món',
        kitchen_product_resumed: 'Mở lại phục vụ món',
        kitchen_product_branch_paused: 'Tạm ngưng món tại chi nhánh',
        kitchen_reopen_requested: 'Yêu cầu mở lại món',
        kitchen_reopen_approved: 'Duyệt mở lại món',

        // Reservations, cash and warehouse workflows
        reservation_created: 'Tạo lượt đặt bàn',
        reservation_confirmed: 'Xác nhận đặt bàn',
        reservation_cancelled: 'Hủy đặt bàn',
        cash_register_opened: 'Mở ca thu ngân',
        cash_transaction_posted: 'Ghi nhận giao dịch tiền',
        cash_transaction_reversed: 'Đảo giao dịch tiền',
        warehouse_staff_status_updated: 'Cập nhật trạng thái nhân viên kho',
        warehouse_task_assigned: 'Phân công công việc kho',
        warehouse_task_reassigned: 'Đổi người phụ trách công việc kho',

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
        'App\\Models\\TemporaryOrder': 'Đơn hàng tạm',
        TemporaryOrder: 'Đơn hàng tạm',
        'App\\Models\\OrderItem': 'Món trong đơn',
        OrderItem: 'Món trong đơn',
        'App\\Models\\Product': 'Món ăn / sản phẩm',
        Product: 'Món ăn / sản phẩm',
        'App\\Models\\TableReservation': 'Lượt đặt bàn',
        TableReservation: 'Lượt đặt bàn',
        'App\\Models\\Incident': 'Sự cố vận hành',
        Incident: 'Sự cố vận hành',
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

    const EXTENDED_FIELD_MAP: Record<string, string> = {
        id: 'Mã bản ghi',
        code: 'Mã',
        type: 'Loại',
        task_type: 'Loại công việc',
        task_id: 'Mã công việc kho',
        order_id: 'Mã đơn hàng',
        order_number: 'Số đơn hàng',
        temporary_order_id: 'Mã đơn tạm',
        reservation_id: 'Mã đặt bàn',
        order_item_id: 'Mã món trong đơn',
        product_id: 'Mã món / sản phẩm',
        ingredient_id: 'Mã nguyên liệu',
        employee_id: 'Mã nhân viên',
        user_id: 'Mã người dùng',
        restaurant_id: 'Mã nhà hàng',
        branch_id: 'Mã chi nhánh',
        table_id: 'Mã bàn',
        supplier_id: 'Mã nhà cung cấp',
        report_id: 'Mã báo cáo',
        report_code: 'Mã báo cáo',
        incident_id: 'Mã sự cố',
        violation_id: 'Mã vi phạm',
        approval_id: 'Mã phê duyệt',
        approval_request_id: 'Mã yêu cầu phê duyệt',
        shift_id: 'Mã ca làm',
        shift_closing_id: 'Mã phiếu chốt ca',
        voucher_code: 'Mã phiếu',
        voucher_id: 'Mã phiếu',
        purchase_order_id: 'Mã đơn mua hàng',
        supply_request_id: 'Mã yêu cầu cấp hàng',
        receiving_voucher_id: 'Mã phiếu nhận hàng',
        stock_transfer_id: 'Mã phiếu điều chuyển',
        fixed_asset_id: 'Mã tài sản',
        asset_id: 'Mã tài sản',
        ip_address: 'Địa chỉ IP',
        user_agent: 'Trình duyệt và thiết bị',
        performed_at: 'Thời điểm thực hiện',
        performed_by: 'Người thực hiện',
        result_notes: 'Ghi chú kết quả',
        evidence_count: 'Số lượng bằng chứng',
        evidence_path: 'Tài liệu bằng chứng',
        evidence_paths: 'Tài liệu bằng chứng',
        created_by: 'Người tạo',
        updated_by: 'Người cập nhật',
        deleted_by: 'Người xóa',
        started_by: 'Người bắt đầu',
        completed_by: 'Người hoàn thành',
        submitted_by: 'Người nộp',
        approved_by: 'Người phê duyệt',
        rejected_by: 'Người từ chối',
        resolved_by: 'Người xử lý',
        cancelled_by: 'Người hủy',
        reviewed_by: 'Người xem xét',
        verified_by: 'Người xác minh',
        assigned_to: 'Người được phân công',
        assigned_at: 'Thời điểm phân công',
        accepted_by: 'Người tiếp nhận',
        requested_by: 'Người yêu cầu',
        requested_by_user_id: 'Mã người yêu cầu',
        target_user_id: 'Mã người dùng đích',
        to_user_id: 'Mã người nhận',
        from_branch_id: 'Mã chi nhánh gửi',
        to_branch_id: 'Mã chi nhánh nhận',
        branch_name: 'Tên chi nhánh',
        branch_code: 'Mã chi nhánh',
        restaurant_name: 'Tên nhà hàng',
        restaurant_code: 'Mã nhà hàng',
        employee_name: 'Tên nhân viên',
        product_name: 'Tên món / sản phẩm',
        supplier_name: 'Tên nhà cung cấp',
        table_name: 'Tên bàn',
        title: 'Tiêu đề',
        description: 'Mô tả',
        details: 'Chi tiết',
        message: 'Thông báo',
        reason: 'Lý do',
        note: 'Ghi chú',
        notes: 'Ghi chú',
        internal_notes: 'Ghi chú nội bộ',
        result: 'Kết quả',
        source: 'Nguồn phát sinh',
        method: 'Phương thức',
        channel: 'Kênh',
        scope: 'Phạm vi áp dụng',
        level: 'Mức độ',
        severity: 'Mức độ nghiêm trọng',
        priority: 'Mức độ ưu tiên',
        workflow_status: 'Trạng thái quy trình',
        assignment_status: 'Trạng thái phân công',
        payment_status: 'Trạng thái thanh toán',
        approval_status: 'Trạng thái phê duyệt',
        verification_status: 'Trạng thái xác minh',
        delivery_status: 'Trạng thái giao hàng',
        payment_method: 'Phương thức thanh toán',
        task_status: 'Trạng thái công việc',
        role: 'Vai trò / phân quyền',
        roles: 'Vai trò / phân quyền',
        old_roles: 'Vai trò trước khi đổi',
        new_roles: 'Vai trò sau khi đổi',
        user_role: 'Vai trò người thực hiện',
        quantity: 'Số lượng',
        qty: 'Số lượng',
        requested_quantity: 'Số lượng yêu cầu',
        received_quantity: 'Số lượng đã nhận',
        dispatched_quantity: 'Số lượng đã xuất',
        quantity_on_hand: 'Số lượng tồn kho',
        cancelled_quantity: 'Số lượng đã hủy',
        reserved_quantity: 'Số lượng đã giữ',
        physical_quantity: 'Số lượng thực tế',
        theoretical_quantity: 'Số lượng theo sổ',
        count: 'Số lượng',
        total: 'Tổng số',
        subtotal: 'Tạm tính',
        amount: 'Số tiền',
        amount_in: 'Tiền vào',
        amount_out: 'Tiền ra',
        unit_price: 'Đơn giá',
        unit_cost: 'Giá vốn đơn vị',
        cost: 'Chi phí',
        total_cost: 'Tổng chi phí',
        discount_percent: 'Tỷ lệ giảm giá',
        tax_amount: 'Tiền thuế',
        tax_rate: 'Thuế suất',
        service_charge: 'Phí dịch vụ',
        refund_amount: 'Số tiền hoàn',
        balance: 'Số dư',
        variance: 'Chênh lệch',
        variance_amount: 'Giá trị chênh lệch',
        start_date: 'Ngày bắt đầu',
        end_date: 'Ngày kết thúc',
        started_at: 'Thời điểm bắt đầu',
        completed_at: 'Thời điểm hoàn thành',
        submitted_at: 'Thời điểm nộp',
        approved_at: 'Thời điểm phê duyệt',
        rejected_at: 'Thời điểm từ chối',
        resolved_at: 'Thời điểm xử lý',
        cancelled_at: 'Thời điểm hủy',
        deleted_at: 'Thời điểm xóa',
        due_at: 'Hạn xử lý',
        due_date: 'Ngày đến hạn',
        duration: 'Thời lượng',
        duration_minutes: 'Thời lượng (phút)',
        duration_hours: 'Thời lượng (giờ)',
        reason_code: 'Mã lý do',
        rejection_reason: 'Lý do từ chối',
        cancellation_reason: 'Lý do hủy',
        resolution_notes: 'Ghi chú xử lý',
        verification_notes: 'Ghi chú xác minh',
        received_note: 'Ghi chú nhận hàng',
        features: 'Tính năng',
        settings: 'Thiết lập',
        changed_policies: 'Quy định đã thay đổi',
        before: 'Giá trị trước',
        after: 'Giá trị sau',
        old_values: 'Dữ liệu trước thay đổi',
        new_values: 'Dữ liệu sau thay đổi',
        seeder: 'Nguồn tạo dữ liệu',
    };

    const VALUE_MAP: Record<string, string> = {
        waiting_verification: 'Chờ nhân viên xác minh',
        pending: 'Đang chờ xử lý',
        confirmed: 'Đã xác nhận',
        preparing: 'Đang chế biến',
        ready: 'Sẵn sàng phục vụ',
        completed: 'Đã hoàn thành',
        cancelled: 'Đã hủy',
        no_show: 'Không đến',
        escalated: 'Đã chuyển cấp xử lý',
        draft: 'Bản nháp',
        submitted: 'Đã nộp',
        approved: 'Đã phê duyệt',
        rejected: 'Đã từ chối',
        disputed: 'Đang tranh chấp',
        resolved: 'Đã xử lý',
        active: 'Đang hoạt động',
        inactive: 'Ngừng hoạt động',
        enabled: 'Đã bật',
        disabled: 'Đã tắt',
        in_progress: 'Đang thực hiện',
        planned: 'Đã lên kế hoạch',
        assigned: 'Đã phân công',
        accepted: 'Đã tiếp nhận',
        received: 'Đã nhận',
        dispatched: 'Đã xuất kho',
        delivered: 'Đã giao',
        paid: 'Đã thanh toán',
        unpaid: 'Chưa thanh toán',
        refunded: 'Đã hoàn tiền',
        processing: 'Đang xử lý',
        failed: 'Thất bại',
        success: 'Thành công',
        closed: 'Đã đóng',
        opened: 'Đã mở',
        packing: 'Đóng gói',
        putaway: 'Đưa hàng vào vị trí',
        receiving: 'Tiếp nhận hàng',
        dispatching: 'Xuất hàng',
        stocktaking: 'Kiểm kê kho',
        transfer: 'Điều chuyển kho',
        cash: 'Tiền mặt',
        card: 'Thẻ',
        bank_transfer: 'Chuyển khoản',
        ewallet: 'Ví điện tử',
        cod: 'Thu hộ khi giao hàng',
        owner: 'Chủ nhà hàng',
        manager: 'Quản lý chi nhánh',
        cashier: 'Nhân viên thu ngân',
        kitchen: 'Nhân viên bếp',
        inventory_staff: 'Nhân viên kho',
        warehouse_manager: 'Quản lý kho tổng',
        warehouse_staff: 'Nhân viên kho tổng',
    };

    const FIELD_VALUE_MAP: Record<string, Record<string, string>> = {
        task_type: {
            packing: 'Đóng gói',
            putaway: 'Đưa hàng vào vị trí',
            receiving: 'Tiếp nhận hàng',
            dispatching: 'Xuất hàng',
            stocktaking: 'Kiểm kê kho',
            transfer: 'Điều chuyển kho',
        },
        payment_status: {
            unpaid: 'Chưa thanh toán',
            paid: 'Đã thanh toán',
            partially_paid: 'Đã thanh toán một phần',
            refunded: 'Đã hoàn tiền',
        },
        payment_method: {
            cash: 'Tiền mặt',
            card: 'Thẻ',
            bank_transfer: 'Chuyển khoản',
            ewallet: 'Ví điện tử',
            cod: 'Thu hộ khi giao hàng',
        },
    };

    const ENUM_TOKEN_MAP: Record<string, string> = {
        waiting: 'đang chờ',
        verification: 'xác minh',
        progress: 'tiến độ',
        requested: 'đã yêu cầu',
        customer: 'khách hàng',
        staff: 'nhân viên',
        branch: 'chi nhánh',
        central: 'kho tổng',
    };

    const SENSITIVE_FIELD_PATTERN =
        /(password|secret|token|api_key|private_key|credential)/i;

    const ACTION_TOKEN_MAP: Record<string, string> = {
        create: 'tạo',
        created: 'tạo',
        update: 'cập nhật',
        updated: 'cập nhật',
        delete: 'xóa',
        deleted: 'xóa',
        cancel: 'hủy',
        cancelled: 'hủy',
        confirm: 'xác nhận',
        confirmed: 'xác nhận',
        approve: 'phê duyệt',
        approved: 'phê duyệt',
        reject: 'từ chối',
        rejected: 'từ chối',
        start: 'bắt đầu',
        started: 'bắt đầu',
        complete: 'hoàn thành',
        completed: 'hoàn thành',
        assign: 'phân công',
        assigned: 'phân công',
        receive: 'tiếp nhận',
        received: 'đã nhận',
        dispatch: 'xuất kho',
        dispatched: 'đã xuất kho',
        resolve: 'xử lý',
        resolved: 'xử lý',
        request: 'yêu cầu',
        requested: 'yêu cầu',
        report: 'báo cáo',
        reported: 'báo cáo',
        order: 'đơn hàng',
        temporary: 'đơn tạm',
        item: 'món',
        kitchen: 'bếp',
        warehouse: 'kho',
        task: 'công việc',
        reservation: 'đặt bàn',
        transfer: 'điều chuyển',
        stock: 'tồn kho',
        payment: 'thanh toán',
        refund: 'hoàn tiền',
        policy: 'quy định',
        violation: 'vi phạm',
        incident: 'sự cố',
        audit: 'kiểm toán',
        branch: 'chi nhánh',
        user: 'người dùng',
        account: 'tài khoản',
    };

    function normalizeValue(value: unknown): string {
        return String(value).trim().toLowerCase();
    }

    function formatEnumValue(value: string, key: string): string {
        const normalized = normalizeValue(value);
        const fieldValues = FIELD_VALUE_MAP[key];

        if (fieldValues?.[normalized]) {
            return fieldValues[normalized];
        }

        if (VALUE_MAP[normalized]) {
            return VALUE_MAP[normalized];
        }

        if (/^[a-z0-9]+(?:[_-][a-z0-9]+)+$/i.test(value)) {
            const words = normalized
                .split(/[_-]/)
                .map((word) => ENUM_TOKEN_MAP[word] ?? word);

            return words.join(' ').replace(/^\S/, (char) => char.toUpperCase());
        }

        return value;
    }

    function formatUserAgent(userAgent: string): string {
        const edge = userAgent.match(/Edg\/([\d.]+)/i);
        const opera = userAgent.match(/OPR\/([\d.]+)/i);
        const chrome = userAgent.match(/Chrome\/([\d.]+)/i);
        const firefox = userAgent.match(/Firefox\/([\d.]+)/i);
        const safari = userAgent.match(/Version\/([\d.]+).*Safari/i);
        const browser = edge
            ? `Microsoft Edge ${edge[1].split('.')[0]}`
            : opera
              ? `Opera ${opera[1].split('.')[0]}`
              : chrome
                ? `Google Chrome ${chrome[1].split('.')[0]}`
                : firefox
                  ? `Firefox ${firefox[1].split('.')[0]}`
                  : safari
                    ? `Safari ${safari[1].split('.')[0]}`
                    : null;
        const platform = /Windows NT/i.test(userAgent)
            ? 'Windows 10/11'
            : /Android/i.test(userAgent)
              ? 'Android'
              : /iPhone|iPad/i.test(userAgent)
                ? 'iPhone/iPad'
                : /Mac OS X/i.test(userAgent)
                  ? 'macOS'
                  : /Linux/i.test(userAgent)
                    ? 'Linux'
                    : null;

        return [browser, platform].filter(Boolean).join(' · ') || userAgent;
    }

    function formatNumber(value: number): string {
        return new Intl.NumberFormat('vi-VN', {
            maximumFractionDigits: 2,
        }).format(value);
    }

    function isMoneyField(key: string): boolean {
        return (
            /(?:amount|price|salary|cost|penalty|bonus|revenue|fee|balance|budget|wage|deposit|commission|tax)/i.test(
                key,
            ) && !/(percent|rate|count|id|quantity)/i.test(key)
        );
    }

    function formatObjectValue(value: Record<string, any>): string {
        return Object.entries(value)
            .map(
                ([nestedKey, nestedValue]) =>
                    `${formatFieldLabel(nestedKey)}: ${formatFieldValue(nestedValue, nestedKey)}`,
            )
            .join('; ');
    }

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
        const translatedTokens = action
            .split(/[._]/)
            .map((token) => ACTION_TOKEN_MAP[token.toLowerCase()] ?? token);

        return translatedTokens
            .join(' ')
            .replace(/^\S/, (char) => char.toUpperCase());
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

        if (EXTENDED_FIELD_MAP[key]) {
            return EXTENDED_FIELD_MAP[key];
        }

        return key
            .replace(/([a-z])([A-Z])/g, '$1 $2')
            .replace(/_/g, ' ')
            .replace(/\bip\b/gi, 'IP')
            .replace(/\bqr\b/gi, 'QR')
            .replace(/\bgps\b/gi, 'GPS')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    /**
     * Formats property values (booleans, nulls, currency, dates) into clean Vietnamese strings.
     */
    function formatFieldValue(val: any, key: string): string {
        if (val === null || val === undefined || val === '') {
            return '—';
        }

        if (SENSITIVE_FIELD_PATTERN.test(key)) {
            return 'Đã cập nhật (đã ẩn thông tin bảo mật)';
        }

        if (typeof val === 'boolean') {
            return val ? 'Có' : 'Không';
        }

        if (typeof val === 'number') {
            if (isMoneyField(key)) {
                return `${formatNumber(val)} đ`;
            }

            if (key === 'evidence_count') {
                return `${formatNumber(val)} tệp`;
            }

            if (/(?:^|_)count$/i.test(key)) {
                return `${formatNumber(val)} mục`;
            }

            if (/(percent|percentage|rate)/i.test(key)) {
                return `${formatNumber(val)}%`;
            }

            if (/(minutes?|mins?)/i.test(key)) {
                return `${formatNumber(val)} phút`;
            }

            if (/(hours?|hrs?)/i.test(key)) {
                return `${formatNumber(val)} giờ`;
            }

            if (/(temperature|_temp|temp_)/i.test(key)) {
                return `${formatNumber(val)} °C`;
            }

            if (/(^id$|_id$)/i.test(key)) {
                return `#${String(val)}`;
            }

            return formatNumber(val);
        }

        if (typeof val === 'object') {
            if (Array.isArray(val)) {
                if (val.length === 0) {
                    return 'Không có';
                }

                return val
                    .map((item) =>
                        typeof item === 'object'
                            ? formatObjectValue(item)
                            : formatFieldValue(item, key),
                    )
                    .join(', ');
            }

            return formatObjectValue(val);
        }

        const strVal = String(val);

        if (key === 'user_agent') {
            return formatUserAgent(strVal);
        }

        if (/(^id$|_id$)/i.test(key) && /^\d+$/.test(strVal)) {
            return `#${strVal}`;
        }

        if (isMoneyField(key) && /^-?\d+(?:\.\d+)?$/.test(strVal)) {
            return `${formatNumber(Number(strVal))} đ`;
        }

        if (/^\d{4}-\d{2}-\d{2}(T|\s)?/.test(strVal)) {
            const date = new Date(strVal);

            if (!Number.isNaN(date.getTime())) {
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

        return formatEnumValue(strVal, key);
    }

    return {
        formatAction,
        formatSubjectType,
        formatFieldLabel,
        formatFieldValue,
    };
}
