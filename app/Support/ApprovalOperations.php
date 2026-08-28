<?php

namespace App\Support;

/**
 * Danh mục tập trung các loại thao tác cần phê duyệt.
 *
 * Trước đây nhãn hiển thị nằm rải trong model và danh sách hợp lệ nằm trong
 * ENUM của database. Gom về một chỗ để thêm loại mới không phải sửa schema.
 */
final class ApprovalOperations
{
    /** Nhãn tiếng Việt hiển thị cho người dùng. */
    public const LABELS = [
        'cash_manual_transaction' => 'Giao dịch thu/chi tiền mặt thủ công',
        'inventory_purchase_batch' => 'Nhập nhiều nguyên liệu kho',
        // Kho & nguyên liệu
        'inventory_create' => 'Thêm nguyên liệu mới',
        'inventory_update' => 'Cập nhật thông tin nguyên liệu',
        'inventory_delete' => 'Xóa nguyên liệu khỏi kho',
        'inventory_adjustment' => 'Điều chỉnh tồn kho',
        'inventory_purchase' => 'Nhập nguyên liệu kho',
        'inventory_waste' => 'Ghi nhận hủy hàng / hao hụt',
        'inventory_stocktake' => 'Kiểm kê và điều chỉnh tồn kho',
        'inventory_recipe_save' => 'Cập nhật công thức định lượng',
        'inventory_recipe_delete' => 'Xóa công thức định lượng',

        // Kho Tổng
        'warehouse_set_central' => 'Thiết lập Kho Tổng',
        'warehouse_price_update' => 'Cập nhật đơn giá nguyên liệu toàn chuỗi',
        'warehouse_supply_approve' => 'Duyệt đơn cấp phát từ chi nhánh',
        'warehouse_supply_dispatch' => 'Xuất Kho Tổng giao chi nhánh',
        'warehouse_supply_reject' => 'Từ chối đơn cấp phát',
        'supply_request' => 'Đơn cấp phát nguyên liệu',

        // Nhân sự
        'salary_adjustment' => 'Điều chỉnh lương nhân sự',
        'employee_bonus' => 'Đề xuất thưởng nhân viên',
        'shift_checkin' => 'Xác nhận vào ca',
        'shift_checkout' => 'Xác nhận hết ca',
        'employee_create' => 'Tạo nhân viên mới',

        // POS
        'order_refund' => 'Hoàn tiền đơn hàng POS',
        'order_item_cancel' => 'Hủy món / ghi nhận tổn thất',
    ];

    /**
     * Thao tác Quản lý chi nhánh KHÔNG BAO GIỜ được duyệt.
     *
     * Danh sách này cố tình hard-code, không cấu hình được qua giao diện — kể cả
     * Chủ doanh nghiệp cũng không bật lên được. Đây là ranh giới cứng của hệ thống.
     *
     * Các khóa chưa có tính năng tương ứng vẫn được liệt kê sẵn, để khi tính năng
     * ra đời thì đã bị chặn từ trước thay vì phải nhớ bổ sung.
     */
    public const MANAGER_FORBIDDEN = [
        // Chính sách & giá toàn chuỗi
        'warehouse_set_central',
        'warehouse_price_update',
        'product_base_price_update',
        'inventory_recipe_save',
        'promotion_chain_wide',
        'coupon_chain_wide',

        // Tài khoản & phân quyền
        'employee_create',
        'user_role_grant',
        'manager_account_create',

        // Tiền lớn & cấu hình tài chính
        'withdrawal_request',
        'cash_manual_transaction',
        'bank_account_update',
        'tax_config_update',
        'payment_gateway_update',

        // Dữ liệu không được phép biến mất
        'order_delete',
        'transaction_delete',
        'audit_log_delete',
        'incident_delete',
    ];

    /**
     * Khóa trong operation_data chứa số tiền liên quan, xét theo thứ tự ưu tiên.
     * Dùng để đối chiếu hạn mức của Quản lý.
     */
    private const AMOUNT_KEYS = [
        'cash_manual_transaction' => ['amount'],
        'inventory_purchase_batch' => ['total_cost', 'estimated_cost'],
        'order_refund' => ['refund_amount'],
        'order_item_cancel' => ['refund_amount', 'line_total'],
        'salary_adjustment' => ['amount'],
        'inventory_purchase' => ['total_cost', 'estimated_cost'],
        'inventory_waste' => ['estimated_cost', 'total_cost'],
        'inventory_adjustment' => ['total_cost', 'estimated_cost'],
        'supply_request' => ['total_amount'],
        'warehouse_supply_approve' => ['total_amount'],
        'warehouse_supply_dispatch' => ['total_amount'],
    ];

    public static function label(string $operationType): string
    {
        return self::LABELS[$operationType] ?? $operationType;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function isForbiddenForManager(string $operationType): bool
    {
        return in_array($operationType, self::MANAGER_FORBIDDEN, true);
    }

    /**
     * Rút số tiền liên quan từ dữ liệu thao tác.
     *
     * Trả về null khi thao tác không mang giá trị tiền hoặc dữ liệu không đủ —
     * khi đó hạn mức theo số tiền không áp dụng, các chặn khác vẫn còn hiệu lực.
     */
    public static function amountFor(string $operationType, array $data): ?float
    {
        if ($operationType === 'inventory_purchase_batch' && ! empty($data['items'])) {
            $total = 0.0;
            foreach ($data['items'] as $item) {
                if (is_numeric($item['quantity'] ?? null) && is_numeric($item['unit_cost'] ?? null)) {
                    $total += (float) $item['quantity'] * (float) $item['unit_cost'];
                }
            }
            return round(abs($total), 2);
        }

        foreach (self::AMOUNT_KEYS[$operationType] ?? [] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return round(abs((float) $data[$key]), 2);
            }
        }

        // Nhiều luồng kho chỉ gửi số lượng và đơn giá, chưa nhân sẵn.
        if (isset($data['quantity'], $data['unit_cost'])
            && is_numeric($data['quantity']) && is_numeric($data['unit_cost'])) {
            return round(abs((float) $data['quantity'] * (float) $data['unit_cost']), 2);
        }

        return null;
    }

    /**
     * Nhân viên chịu tác động trực tiếp của thao tác, nếu có.
     *
     * Đây là chốt chặn tự duyệt gián tiếp: một Quản lý không được duyệt yêu cầu
     * ảnh hưởng tới chính mình, kể cả khi người bấm nút tạo là người khác.
     */
    public static function subjectEmployeeIdFor(string $operationType, array $data): ?int
    {
        $value = $data['employee_id'] ?? $data['subject_employee_id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
