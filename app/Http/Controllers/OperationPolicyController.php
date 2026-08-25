<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesRestaurantSettings;
use App\Models\AuditLog;
use App\Models\OperationPolicy;
use App\Services\PolicyEnforcementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class OperationPolicyController extends Controller
{
    private const AUDIT_ACTIONS = [
        'policy_updated',
        'policy_check',
        'discount_applied',
        'discount_applied_bypass',
        'price_discount_bypass',
        'order_cancelled_bypass',
        'order_item_cancel_requested',
        'order_item_lock_bypass',
        'refund_requested',
        'order_refund_processed',
    ];

    use AuthorizesRestaurantSettings;

    public function __construct(private PolicyEnforcementService $policy) {}

    public function index(Request $request): Response
    {
        $this->authorizeRestaurantSettings($request);
        $restaurantId = $request->user()->restaurant_id;
        $policy = $this->policy->getPolicy($restaurantId);

        $recentAudit = AuditLog::where('restaurant_id', $restaurantId)
            ->whereIn('action', self::AUDIT_ACTIONS)
            ->with('user:id,name')
            ->latest()
            ->take(50)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user_name' => $log->user?->name ?? 'System',
                'user_role' => $log->user_role,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('operation-policies/Index', [
            'policy' => $policy,
            'recentAudit' => $recentAudit,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeRestaurantSettings($request);

        $data = $request->validate([
            'max_discount_percent_staff' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_discount_percent_manager' => ['required', 'numeric', 'min:0', 'max:100', 'gte:max_discount_percent_staff'],
            'max_cancel_amount_staff' => ['required', 'numeric', 'min:0'],
            'max_cancel_amount_manager' => ['required', 'numeric', 'min:0', 'gte:max_cancel_amount_staff'],
            'staff_view_revenue' => ['required', 'boolean'],
            'staff_view_salary' => ['required', 'boolean'],
            'staff_view_cost_price' => ['required', 'boolean'],
            'manager_view_other_salary' => ['required', 'boolean'],
            'restrict_to_shift_hours' => ['required', 'boolean'],
            'audit_all_changes' => ['required', 'boolean'],
        ]);

        foreach ([
            'max_discount_percent_staff',
            'max_discount_percent_manager',
            'max_cancel_amount_staff',
            'max_cancel_amount_manager',
        ] as $field) {
            $data[$field] = (float) $data[$field];
        }

        foreach ([
            'staff_view_revenue',
            'staff_view_salary',
            'staff_view_cost_price',
            'manager_view_other_salary',
            'restrict_to_shift_hours',
            'audit_all_changes',
        ] as $field) {
            $data[$field] = (bool) $data[$field];
        }

        $restaurantId = $request->user()->restaurant_id;

        $fields = array_keys($data);
        $old = OperationPolicy::withoutGlobalScopes()->where('restaurant_id', $restaurantId)->first();
        $oldValues = $old
            ? collect($fields)->mapWithKeys(fn (string $field) => [$field => $old->{$field}])->all()
            : [];

        foreach (array_keys($oldValues) as $field) {
            $oldValues[$field] = in_array($field, [
                'max_discount_percent_staff',
                'max_discount_percent_manager',
                'max_cancel_amount_staff',
                'max_cancel_amount_manager',
            ], true)
                ? (float) $oldValues[$field]
                : (bool) $oldValues[$field];
        }

        OperationPolicy::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => $restaurantId],
            $data
        );

        Cache::forget("operation_policy:{$restaurantId}");

        if ($oldValues !== $data) {
            $this->policy->logSensitiveAction($request->user(), 'policy_updated', null, $oldValues, $data);
        }

        return back()->with('success', $oldValues === $data
            ? 'Chính sách không có thay đổi mới.'
            : 'Đã cập nhật chính sách phân quyền.');
    }

    public function checkPermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:discount,cancel,view_revenue,view_salary,view_cost_price'],
            'value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();

        $result = match ($data['action']) {
            'discount' => array_merge(
                $this->policy->canApplyDiscount($user, (float) ($data['value'] ?? 0)),
                ['within_shift_hours' => $this->policy->isWithinShiftHours($user)],
            ),
            'cancel' => array_merge(
                $this->policy->canCancelOrder($user, (float) ($data['value'] ?? 0)),
                ['within_shift_hours' => $this->policy->isWithinShiftHours($user)],
            ),
            'view_revenue' => ['allowed' => $this->policy->canViewData($user, 'revenue')],
            'view_salary' => ['allowed' => $this->policy->canViewData($user, 'salary')],
            'view_cost_price' => ['allowed' => $this->policy->canViewData($user, 'cost_price')],
        };

        return response()->json($result);
    }
}
