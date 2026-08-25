<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ áp dụng mã khuyến mãi vào đơn hàng: chống gian lận thời gian thực
 * (tần suất áp mã bất thường, yêu cầu mã bypass quản lý), kiểm tra hiệu lực
 * (thời hạn, giá trị đơn tối thiểu, ngân sách), tính số tiền giảm — tách khỏi
 * PromotionController vì đây là logic tài chính phức tạp nhất trong controller
 * (khoá bi quan + transaction), theo đúng khuôn "chia để trị" đã áp dụng cho
 * các controller lớn khác.
 */
class PromotionApplicationService
{
    public function __construct(
        private PromotionStackingService $promotionStacking,
    ) {}

    /**
     * Áp mã khuyến mãi vào 1 đơn hàng — bọc trong transaction có lockForUpdate
     * để tránh race condition khi 2 request áp mã cùng lúc.
     *
     * @return array{success: bool, status: string, message: string, data?: array}
     */
    public function applyToOrder(int $restaurantId, User $actingUser, int $orderId, string $code, ?string $bypassCode): array
    {
        // Tìm kiếm voucher hoạt động và đã được duyệt
        $promotion = Promotion::where('restaurant_id', $restaurantId)
            ->where('code', strtoupper($code))
            ->where('is_active', true)
            ->where('is_approved', true)
            ->first();

        if (! $promotion) {
            return ['success' => false, 'status' => 'error', 'message' => 'Mã khuyến mãi không tồn tại hoặc đã bị vô hiệu hóa.'];
        }

        // Bọc toàn bộ check budget, cập nhật order và voucher trong transaction với lockForUpdate
        return DB::transaction(function () use ($promotion, $orderId, $bypassCode, $restaurantId, $actingUser) {
            // Lock order và promotion
            $order = Order::where('restaurant_id', $restaurantId)
                ->where('id', $orderId)
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();
            $promotion = Promotion::where('id', $promotion->id)->lockForUpdate()->firstOrFail();

            if (
                $promotion->branch_id !== null
                && (int) $promotion->branch_id !== (int) $order->branch_id
            ) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Mã khuyến mãi không áp dụng cho chi nhánh của đơn hàng này.',
                ];
            }

            // Real-Time Fraud Prevention Checks
            $cashierFastKey = "voucher_applied_fast_check:{$restaurantId}:{$actingUser->id}";
            $cashierFastCount = (int) Cache::get($cashierFastKey, 0);

            $customerId = $order->customer_id;
            $customerFastCount = 0;
            if ($customerId) {
                $customerFastKey = "voucher_applied_fast_check_cust:{$restaurantId}:{$customerId}";
                $customerFastCount = (int) Cache::get($customerFastKey, 0);
            }

            $cashierAppliedCount = max($cashierFastCount, AuditLog::where('restaurant_id', $restaurantId)
                ->where('user_id', $actingUser->id)
                ->where('action', 'discount_applied')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->count());

            $customerAppliedCount = 0;
            if ($customerId) {
                $customerAppliedCount = max($customerFastCount, Order::where('restaurant_id', $restaurantId)
                    ->where('customer_id', $customerId)
                    ->where('id', '!=', $order->id)
                    ->whereNotNull('completed_at')
                    ->where('discount_amount', '>', 0)
                    ->where('completed_at', '>=', now()->subMinutes(10))
                    ->count());
            }

            $suspicious = ($cashierAppliedCount >= 3) || ($customerAppliedCount > 0);
            $usedBypass = false;

            if ($suspicious) {
                $approvingUser = User::validateManagerBypass($bypassCode ?? '', $restaurantId);

                if (! $approvingUser || (! $approvingUser->isOwner() && ! $approvingUser->isSuperAdmin())) {
                    return [
                        'success' => false,
                        'status' => 'requires_bypass',
                        'message' => 'Cảnh báo gian lận AI: Phát hiện tần suất áp dụng voucher bất thường. Yêu cầu nhập mã phê duyệt của quản lý để tiếp tục.',
                    ];
                }

                $usedBypass = true;

                // Log special bypass action
                AuditLog::log('discount_applied_bypass', 'updated', $order, null, [
                    'cashier_applied_last_5_min' => $cashierAppliedCount,
                    'customer_applied_last_10_min' => $customerAppliedCount,
                    'bypass_code_used' => true,
                    'approved_by_user_id' => $approvingUser->id,
                    'approved_by_user_name' => $approvingUser->name,
                ]);
            }

            // 2. Kiểm tra thời hạn áp dụng
            $now = now();
            if (! $promotion->hasStarted($now)) {
                return ['success' => false, 'status' => 'error', 'message' => 'Chương trình khuyến mãi chưa đến thời gian áp dụng.'];
            }
            if ($promotion->isExpired($now)) {
                return ['success' => false, 'status' => 'error', 'message' => 'Mã khuyến mãi này đã hết hạn sử dụng.'];
            }

            // 3. Kiểm tra giá trị đơn hàng tối thiểu
            $subtotal = (float) $order->subtotal;
            if ($subtotal < (float) $promotion->min_order_amount) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã giảm giá này. Giá trị tối thiểu cần đạt: '.number_format($promotion->min_order_amount).'đ',
                ];
            }

            if (! $this->promotionStacking->validateConditions($promotion, $order)) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Đơn hàng hiện tại chưa đáp ứng điều kiện của mã khuyến mãi này.',
                ];
            }

            // 3b. Giới hạn lượt sử dụng (tổng và theo từng khách hàng)
            if ($promotion->usage_limit !== null) {
                $totalUsed = PromotionUsage::where('promotion_id', $promotion->id)->count();
                if ($totalUsed >= (int) $promotion->usage_limit) {
                    return [
                        'success' => false,
                        'status' => 'error',
                        'message' => 'Mã khuyến mãi này đã hết lượt sử dụng.',
                    ];
                }
            }

            if ($promotion->usage_limit_per_customer !== null && $customerId) {
                $customerUsed = PromotionUsage::where('promotion_id', $promotion->id)
                    ->where('customer_id', $customerId)
                    ->count();

                if ($customerUsed >= (int) $promotion->usage_limit_per_customer) {
                    return [
                        'success' => false,
                        'status' => 'error',
                        'message' => 'Khách hàng này đã dùng hết lượt cho phép của mã khuyến mãi.',
                    ];
                }
            }

            // 4. Tính toán số tiền được giảm
            $discountAmount = 0.0;
            if ($promotion->type === 'fixed_amount') {
                $discountAmount = (float) $promotion->value;
            } else {
                // Giảm theo phần trăm
                $calculated = $subtotal * ((float) $promotion->value / 100);

                // Giới hạn giảm giá tối đa
                $maxDiscount = (float) $promotion->max_discount_amount;
                if ($maxDiscount > 0 && $calculated > $maxDiscount) {
                    $discountAmount = $maxDiscount;
                } else {
                    $discountAmount = $calculated;
                }
            }

            // Đảm bảo số tiền giảm giá không lớn hơn subtotal
            $discountAmount = min($discountAmount, $subtotal);

            // Budget cap check
            if ($promotion->budget_cap !== null) {
                if ($promotion->isBudgetExhausted()) {
                    return ['success' => false, 'status' => 'error', 'message' => 'Chương trình khuyến mãi đã hết ngân sách.'];
                }
                $remaining = $promotion->remainingBudget();
                $discountAmount = min($discountAmount, $remaining);
            }

            // Chống áp trùng: đọc bảng promotion_usages thay vì dò chuỗi trong
            // orders.note — cách cũ bị vô hiệu chỉ bằng việc sửa ghi chú đơn.
            $alreadyApplied = PromotionUsage::where('promotion_id', $promotion->id)
                ->where('order_id', $order->id)
                ->exists();

            if ($alreadyApplied) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Mã khuyến mãi này đã được áp dụng cho đơn hàng.',
                ];
            }

            // 5. Cộng dồn với giảm giá hợp lệ đã có trên đơn (ưu đãi hạng hội
            // viên, quy đổi điểm tích lũy, giảm giá tay của quản lý).
            $existingDiscount = (float) ($order->discount_amount ?? 0);

            // Trần tổng giảm giá: trước đây tổng chỉ bị chặn ở mức bằng subtotal,
            // nghĩa là voucher chồng lên ưu đãi hội viên có thể đưa hóa đơn về 0đ
            // mà không ai được cảnh báo và không có chỗ nào cấu hình được.
            $maxPercent = (float) config('promotions.max_total_discount_percent', 100);
            $discountCeiling = $subtotal * (min(100.0, max(0.0, $maxPercent)) / 100);

            if ($existingDiscount >= $discountCeiling) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Đơn hàng đã đạt mức giảm giá tối đa cho phép ('
                        .rtrim(rtrim(number_format($maxPercent, 1, ',', '.'), '0'), ',').'%). Không thể áp thêm mã.',
                ];
            }

            $discountAmount = min($discountAmount, $discountCeiling - $existingDiscount);
            $newTotalDiscount = min($discountCeiling, $existingDiscount + $discountAmount);

            $totalDiscountPercent = $subtotal > 0 ? ($newTotalDiscount / $subtotal) * 100 : 0.0;
            $warnPercent = (float) config('promotions.warn_total_discount_percent', 50);
            $discountWarning = $totalDiscountPercent > $warnPercent
                ? 'Cảnh báo: tổng giảm giá của hóa đơn này đã lên tới '
                    .round($totalDiscountPercent, 1).'% (gồm cả ưu đãi hội viên/điểm tích lũy đã áp trước đó).'
                : null;

            $order->update([
                'discount_amount' => $newTotalDiscount,
                'total_amount' => max(0.0, $subtotal - $newTotalDiscount),
                'note' => ($order->note ? $order->note.' ' : '').'[Đã áp mã voucher: '.$promotion->code.': -'.number_format($discountAmount).'đ]',
            ]);

            // Lưu vết lượt áp mã — nguồn dữ liệu cho báo cáo hiệu quả từng
            // chương trình và cho việc chống áp trùng ở lần gọi sau.
            PromotionUsage::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $order->branch_id,
                'promotion_id' => $promotion->id,
                'order_id' => $order->id,
                'customer_id' => $customerId,
                'applied_by' => $actingUser->id,
                'discount_amount' => $discountAmount,
                'order_subtotal' => $subtotal,
                'used_bypass' => $usedBypass,
            ]);

            // Cập nhật bộ đếm Cache thời gian thực (chống Race Condition)
            $cashierFastKey = "voucher_applied_fast_check:{$restaurantId}:{$actingUser->id}";
            $cnt = (int) Cache::get($cashierFastKey, 0);
            Cache::put($cashierFastKey, $cnt + 1, now()->addMinutes(5));

            if ($customerId) {
                $customerFastKey = "voucher_applied_fast_check_cust:{$restaurantId}:{$customerId}";
                $cntCust = (int) Cache::get($customerFastKey, 0);
                Cache::put($customerFastKey, $cntCust + 1, now()->addMinutes(10));
            }

            // Track budget spent
            if ($promotion->budget_cap !== null) {
                $promotion->increment('budget_spent', $discountAmount);
                if ($promotion->auto_deactivate_on_budget && $promotion->fresh()->isBudgetExhausted()) {
                    $promotion->update(['is_active' => false]);
                }
            }

            // Hết lượt thì tự tắt luôn, tránh cashier tiếp tục thử mã đã cạn.
            if (
                $promotion->usage_limit !== null
                && PromotionUsage::where('promotion_id', $promotion->id)->count() >= (int) $promotion->usage_limit
            ) {
                $promotion->update(['is_active' => false]);
            }

            if ($discountWarning !== null) {
                AuditLog::log('discount_stacked_high', 'updated', $order, [
                    'existing_discount' => $existingDiscount,
                ], [
                    'promotion_code' => $promotion->code,
                    'voucher_discount' => $discountAmount,
                    'total_discount' => $newTotalDiscount,
                    'total_discount_percent' => round($totalDiscountPercent, 2),
                ]);
            }

            return [
                'success' => true,
                'status' => 'ok',
                'message' => 'Áp dụng mã giảm giá thành công!',
                'data' => [
                    'discount_amount' => $discountAmount,
                    'total_amount' => $order->total_amount,
                    'promotion_name' => $promotion->name,
                    'total_discount_percent' => round($totalDiscountPercent, 1),
                    'warning' => $discountWarning,
                ],
            ];
        });
    }

    /**
     * Kiểm tra nhanh hiệu lực mã khuyến mãi (không áp dụng, không khoá) — dùng
     * cho preview trước khi cashier thực sự áp mã.
     *
     * @return array{valid: bool, message?: string, promotion?: array}
     */
    public function validateForOrder(int $restaurantId, string $code): array
    {
        $promotion = Promotion::where('restaurant_id', $restaurantId)
            ->where('code', strtoupper($code))
            ->where('is_active', true)
            ->where('is_approved', true)
            ->first();

        if (! $promotion) {
            return ['valid' => false, 'message' => 'Mã không tồn tại hoặc đã vô hiệu hóa.'];
        }

        $now = now();
        if (! $promotion->hasStarted($now)) {
            return ['valid' => false, 'message' => 'Chưa đến thời gian áp dụng.'];
        }
        if ($promotion->isExpired($now)) {
            return ['valid' => false, 'message' => 'Mã đã hết hạn.'];
        }
        if ($promotion->isBudgetExhausted()) {
            return ['valid' => false, 'message' => 'Đã hết ngân sách khuyến mãi.'];
        }

        $usageCount = PromotionUsage::where('promotion_id', $promotion->id)->count();
        if ($promotion->usage_limit !== null && $usageCount >= (int) $promotion->usage_limit) {
            return ['valid' => false, 'message' => 'Mã đã hết lượt sử dụng.'];
        }

        return [
            'valid' => true,
            'promotion' => [
                'name' => $promotion->name,
                'type' => $promotion->type,
                'value' => $promotion->value,
                'remaining_budget' => $promotion->remainingBudget(),
                'usage_count' => $usageCount,
                'usage_limit' => $promotion->usage_limit,
            ],
        ];
    }
}
