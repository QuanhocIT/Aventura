<?php

namespace App\Http\Controllers;

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
    public function __construct(private PolicyEnforcementService $policy) {}

    public function index(Request $request): Response
    {
        $restaurantId = $request->user()->restaurant_id;
        $policy = $this->policy->getPolicy($restaurantId);

        $recentAudit = AuditLog::where('restaurant_id', $restaurantId)
            ->whereIn('action', ['policy_check', 'discount_applied', 'order_cancelled', 'discount_applied_bypass', 'system_settings_update'])
            ->with('user:id,name')
            ->latest()
            ->take(30)
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
        abort_unless($request->user()->hasRole('owner'), 403);

        $data = $request->validate([
            'max_discount_percent_staff' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_discount_percent_manager' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_cancel_amount_staff' => ['required', 'numeric', 'min:0'],
            'max_cancel_amount_manager' => ['required', 'numeric', 'min:0'],
            'staff_view_revenue' => ['required', 'boolean'],
            'staff_view_salary' => ['required', 'boolean'],
            'staff_view_cost_price' => ['required', 'boolean'],
            'manager_view_other_salary' => ['required', 'boolean'],
            'restrict_to_shift_hours' => ['required', 'boolean'],
            'audit_all_changes' => ['required', 'boolean'],
        ]);

        $restaurantId = $request->user()->restaurant_id;

        $old = OperationPolicy::withoutGlobalScopes()->where('restaurant_id', $restaurantId)->first();
        $oldValues = $old ? $old->toArray() : [];

        OperationPolicy::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => $restaurantId],
            $data
        );

        Cache::forget("operation_policy:{$restaurantId}");

        $this->policy->logSensitiveAction($request->user(), 'policy_updated', null, $oldValues, $data);

        return back()->with('success', 'Đã cập nhật chính sách phân quyền.');
    }

    public function checkPermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:discount,cancel,view_revenue,view_salary,view_cost_price'],
            'value' => ['nullable', 'numeric'],
        ]);

        $user = $request->user();

        $result = match ($data['action']) {
            'discount' => $this->policy->canApplyDiscount($user, (float) ($data['value'] ?? 0)),
            'cancel' => $this->policy->canCancelOrder($user, (float) ($data['value'] ?? 0)),
            'view_revenue' => ['allowed' => $this->policy->canViewData($user, 'revenue')],
            'view_salary' => ['allowed' => $this->policy->canViewData($user, 'salary')],
            'view_cost_price' => ['allowed' => $this->policy->canViewData($user, 'cost_price')],
        };

        return response()->json($result);
    }
}
