<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\OperationPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PolicyEnforcementService
{
    public function getPolicy(int $restaurantId): OperationPolicy
    {
        return Cache::remember("operation_policy:{$restaurantId}", 600, function () use ($restaurantId) {
            return OperationPolicy::withoutGlobalScopes()
                ->firstOrCreate(
                    ['restaurant_id' => $restaurantId],
                    []
                );
        });
    }

    public function canApplyDiscount(User $user, float $discountPercent): array
    {
        $policy = $this->getPolicy($user->restaurant_id);
        $isOwner = $user->hasRole('owner');
        $isManager = $user->hasAnyRole(['owner', 'manager']);

        if ($isOwner) {
            return ['allowed' => true];
        }

        $maxPercent = $isManager
            ? (float) $policy->max_discount_percent_manager
            : (float) $policy->max_discount_percent_staff;

        if ($discountPercent > $maxPercent) {
            return [
                'allowed' => false,
                'requires_approval' => true,
                'message' => "Giảm giá {$discountPercent}% vượt giới hạn {$maxPercent}% cho vai trò của bạn. Cần phê duyệt từ cấp trên.",
                'max_allowed' => $maxPercent,
            ];
        }

        return ['allowed' => true];
    }

    public function canCancelOrder(User $user, float $orderAmount): array
    {
        $policy = $this->getPolicy($user->restaurant_id);
        $isOwner = $user->hasRole('owner');
        $isManager = $user->hasAnyRole(['owner', 'manager']);

        if ($isOwner) {
            return ['allowed' => true];
        }

        $maxAmount = $isManager
            ? (float) $policy->max_cancel_amount_manager
            : (float) $policy->max_cancel_amount_staff;

        if ($maxAmount > 0 && $orderAmount > $maxAmount) {
            return [
                'allowed' => false,
                'requires_approval' => true,
                'message' => "Đơn hàng " . number_format($orderAmount) . "đ vượt giới hạn hủy " . number_format($maxAmount) . "đ. Chỉ Manager+ mới được hủy.",
            ];
        }

        if ($maxAmount === 0.0 && ! $isManager) {
            return [
                'allowed' => false,
                'message' => 'Nhân viên không có quyền hủy đơn hàng.',
            ];
        }

        return ['allowed' => true];
    }

    public function canViewData(User $user, string $dataType): bool
    {
        $policy = $this->getPolicy($user->restaurant_id);

        if ($user->hasRole('owner')) {
            return true;
        }

        $isManager = $user->hasRole('manager');

        return match ($dataType) {
            'revenue' => $isManager || $policy->staff_view_revenue,
            'salary' => $isManager ? $policy->manager_view_other_salary : $policy->staff_view_salary,
            'cost_price' => $isManager || $policy->staff_view_cost_price,
            default => true,
        };
    }

    public function isWithinShiftHours(User $user): bool
    {
        $policy = $this->getPolicy($user->restaurant_id);

        if (! $policy->restrict_to_shift_hours || $user->hasRole('owner')) {
            return true;
        }

        $now = now();
        $hasActiveShift = \App\Models\ScheduleAssignment::where('employee_id', function ($q) use ($user) {
            $q->select('id')->from('employees')->where('user_id', $user->id)->limit(1);
        })
            ->where('scheduled_date', $now->toDateString())
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->exists();

        return $hasActiveShift;
    }

    public function logSensitiveAction(User $user, string $action, $subject = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        AuditLog::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => null,
            'user_id' => $user->id,
            'user_role' => $user->roles->first()?->name ?? 'unknown',
            'event' => 'policy_check',
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id ?? null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
