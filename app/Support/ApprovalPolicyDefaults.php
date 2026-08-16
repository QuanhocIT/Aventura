<?php

namespace App\Support;

use App\Models\ApprovalPolicy;

/**
 * Bộ chính sách khởi điểm cho một nhà hàng.
 *
 * Chủ doanh nghiệp chỉnh lại hạn mức sau trong màn hình Thẩm quyền phê duyệt.
 * Nguyên tắc đặt mặc định: mở những việc Quản lý phải xử lý ngay tại chi nhánh,
 * kèm hạn mức tiền đủ nhỏ để một quyết định sai không gây thiệt hại lớn.
 *
 * Loại nào không có trong danh sách này thì mặc định chỉ Chủ được duyệt —
 * thiếu cấu hình luôn nghiêng về phía chặt hơn.
 */
final class ApprovalPolicyDefaults
{
    /**
     * Hạn mức tính bằng VND. null = không giới hạn theo giá trị.
     */
    public const DEFAULTS = [
        // ── POS: hai việc thu ngân cần Quản lý xử lý ngay ────────────────────
        'order_refund' => [
            'manager_can_approve' => true,
            'manager_limit_amount' => 500_000,
            'manager_daily_limit' => 2_000_000,
            'manager_monthly_limit' => 20_000_000,
        ],
        'order_item_cancel' => [
            'manager_can_approve' => true,
            'manager_limit_amount' => 300_000,
            'manager_daily_limit' => 1_500_000,
            // Điều kiện của Chủ: bếp chưa bấm bắt đầu chế biến thì Quản lý mới
            // được duyệt. Đã bắt đầu thì nguyên liệu đã tiêu hao → Chủ quyết.
            'conditions' => ['kitchen_not_started' => true],
        ],

        // ── Kho tại chi nhánh ────────────────────────────────────────────────
        'inventory_waste' => [
            'manager_can_approve' => true,
            'manager_limit_amount' => 500_000,
            'manager_daily_limit' => 2_000_000,
            'manager_monthly_limit' => 20_000_000,
        ],
        'inventory_purchase' => [
            'manager_can_approve' => true,
            'manager_limit_amount' => 2_000_000,
            'manager_daily_limit' => 10_000_000,
        ],
        'inventory_adjustment' => [
            'manager_can_approve' => true,
            'manager_limit_amount' => 1_000_000,
            'manager_daily_limit' => 3_000_000,
        ],
        'inventory_stocktake' => ['manager_can_approve' => true],
        'inventory_create' => ['manager_can_approve' => true],
        'inventory_update' => ['manager_can_approve' => true],
        'inventory_recipe_save' => ['manager_can_approve' => false],
        'supply_request' => ['manager_can_approve' => true],

        // ── Chấm công trong ca ───────────────────────────────────────────────
        'shift_checkin' => ['manager_can_approve' => true],
        'shift_checkout' => ['manager_can_approve' => true],

        // ── Cố ý để Chủ quyết ────────────────────────────────────────────────
        // Xóa nguyên liệu và xóa công thức là thao tác mất dữ liệu; điều chỉnh
        // lương là tiền của người khác. Chủ có thể bật lên nếu muốn.
        'inventory_delete' => ['manager_can_approve' => false],
        'inventory_recipe_delete' => ['manager_can_approve' => false],
        'salary_adjustment' => ['manager_can_approve' => false],
    ];

    /**
     * Nạp chính sách mặc định ở phạm vi toàn chuỗi cho một nhà hàng.
     *
     * Dùng updateOrCreate nên chạy lại nhiều lần vẫn an toàn, nhưng sẽ ghi đè
     * hạn mức Chủ đã tự chỉnh — vì vậy mặc định chỉ tạo dòng còn thiếu.
     */
    public static function applyTo(int $restaurantId, bool $overwriteExisting = false): int
    {
        $written = 0;

        foreach (self::DEFAULTS as $operationType => $settings) {
            $existing = ApprovalPolicy::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('operation_type', $operationType)
                ->whereNull('branch_id')
                ->first();

            if ($existing && ! $overwriteExisting) {
                continue;
            }

            $attributes = [
                'manager_can_approve' => $settings['manager_can_approve'],
                'manager_limit_amount' => $settings['manager_limit_amount'] ?? null,
                'manager_daily_limit' => $settings['manager_daily_limit'] ?? null,
                'manager_monthly_limit' => $settings['manager_monthly_limit'] ?? null,
                'requires_owner_countersign' => $settings['requires_owner_countersign'] ?? false,
                'auto_escalate_after_minutes' => $settings['auto_escalate_after_minutes'] ?? null,
                'conditions' => $settings['conditions'] ?? null,
                'is_active' => true,
            ];

            if ($existing) {
                $existing->update($attributes);
            } else {
                ApprovalPolicy::withoutGlobalScopes()->create($attributes + [
                    'restaurant_id' => $restaurantId,
                    'branch_id' => null,
                    'operation_type' => $operationType,
                ]);
            }

            $written++;
        }

        return $written;
    }
}
