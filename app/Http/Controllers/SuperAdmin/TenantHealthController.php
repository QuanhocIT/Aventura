<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\QuotaService;
use App\Services\SuperAdminAuditStream;
use App\Support\TenantFeatureFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantHealthController extends Controller
{
    public function __construct(private readonly QuotaService $quota) {}

    public function index(): Response
    {
        $restaurants = Restaurant::with(['plan', 'owner', 'featureFlags'])
            ->withCount('branches')
            ->latest()
            ->get();

        $branches = RestaurantBranch::with(['restaurant:id,name', 'manager:id,name,email'])
            ->orderBy('restaurant_id')
            ->orderBy('id')
            ->get();

        $users = User::with(['roles', 'employee'])
            ->whereNotNull('restaurant_id')
            ->get();

        $branchesById = $branches->keyBy('id');
        $issues = collect();
        $addIssue = function (array $issue) use ($issues): void {
            $issues->push($issue);
        };

        foreach ($users->filter(fn (User $user) => $user->status === 'active' && $user->isBranchManager()) as $manager) {
            if ($manager->assignedBranchId() === null) {
                $addIssue([
                    'id' => "manager_without_branch:user:{$manager->id}",
                    'type' => 'manager_without_branch',
                    'severity' => 'critical',
                    'title' => 'Manager active chưa có branch',
                    'message' => "{$manager->name} ({$manager->email}) đang active nhưng chưa được gán chi nhánh.",
                    'tenant_id' => $manager->restaurant_id,
                    'tenant_name' => $manager->restaurant?->name,
                    'user_id' => $manager->id,
                    'user_name' => $manager->name,
                    'user_email' => $manager->email,
                    'branch_id' => null,
                    'branch_name' => null,
                    'repair_actions' => ['assign_manager'],
                ]);
            }
        }

        foreach ($branches->filter(fn (RestaurantBranch $branch) => $branch->status === 'active') as $branch) {
            $manager = $branch->manager;
            $managerIsValid = $manager
                && $manager->status === 'active'
                && $manager->restaurant_id === $branch->restaurant_id
                && $manager->isBranchManager()
                && $manager->assignedBranchId() === $branch->id;

            if (! $managerIsValid) {
                $addIssue([
                    'id' => "branch_without_manager:branch:{$branch->id}",
                    'type' => 'branch_without_manager',
                    'severity' => 'warning',
                    'title' => 'Branch chưa có manager hợp lệ',
                    'message' => "Chi nhánh {$branch->name} chưa có manager active được gán đúng.",
                    'tenant_id' => $branch->restaurant_id,
                    'tenant_name' => $branch->restaurant?->name,
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'user_id' => null,
                    'user_name' => null,
                    'user_email' => null,
                    'repair_actions' => ['assign_manager'],
                ]);
            }
        }

        foreach ($restaurants as $restaurant) {
            $owner = $restaurant->owner;
            if (! $owner || $owner->status !== 'active' || ! $owner->isOwner()) {
                $addIssue([
                    'id' => "tenant_without_owner:tenant:{$restaurant->id}",
                    'type' => 'tenant_without_owner',
                    'severity' => 'critical',
                    'title' => 'Tenant chưa có owner hợp lệ',
                    'message' => "Tenant {$restaurant->name} chưa có tài khoản owner active.",
                    'tenant_id' => $restaurant->id,
                    'tenant_name' => $restaurant->name,
                    'branch_id' => null,
                    'branch_name' => null,
                    'user_id' => $owner?->id,
                    'user_name' => $owner?->name,
                    'user_email' => $owner?->email,
                    'repair_actions' => ['assign_owner'],
                ]);
            }
        }

        foreach ($users as $user) {
            $assignments = array_filter([
                ['source' => 'user', 'branch_id' => $user->getRawOriginal('branch_id')],
                ['source' => 'employee', 'branch_id' => $user->employee?->getRawOriginal('branch_id')],
            ], fn (array $assignment) => $assignment['branch_id'] !== null);

            $assignmentIds = collect($assignments)->pluck('branch_id')->unique()->values();
            if ($assignmentIds->count() > 1) {
                $addIssue([
                    'id' => "wrong_assignment:mismatch:{$user->id}",
                    'type' => 'wrong_assignment',
                    'severity' => 'critical',
                    'title' => 'User có branch assignment không đồng bộ',
                    'message' => "User {$user->name} có user.branch_id và employee.branch_id khác nhau.",
                    'tenant_id' => $user->restaurant_id,
                    'tenant_name' => $user->restaurant?->name,
                    'branch_id' => null,
                    'branch_name' => null,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'repair_actions' => ['reassign_branch', 'clear_branch'],
                ]);
            }

            foreach ($assignments as $assignment) {
                $branch = $branchesById->get((int) $assignment['branch_id']);
                if (! $branch || (int) $branch->restaurant_id !== (int) $user->restaurant_id) {
                    $addIssue([
                        'id' => "wrong_assignment:{$assignment['source']}:{$user->id}:{$assignment['branch_id']}",
                        'type' => 'wrong_assignment',
                        'severity' => 'critical',
                        'title' => 'User bị gán sai tenant/branch',
                        'message' => "User {$user->name} đang trỏ tới branch không tồn tại hoặc thuộc tenant khác.",
                        'tenant_id' => $user->restaurant_id,
                        'tenant_name' => $user->restaurant?->name,
                        'branch_id' => $branch?->id,
                        'branch_name' => $branch?->name,
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                        'repair_actions' => ['reassign_branch', 'clear_branch'],
                    ]);
                }
            }
        }

        foreach ($branches->filter(fn (RestaurantBranch $branch) => $branch->status === 'inactive') as $branch) {
            $activeUsers = $users->filter(function (User $user) use ($branch): bool {
                if ($user->status !== 'active') {
                    return false;
                }

                return (int) $user->getRawOriginal('branch_id') === $branch->id
                    || (int) $user->employee?->getRawOriginal('branch_id') === $branch->id;
            });

            foreach ($activeUsers as $user) {
                $addIssue([
                    'id' => "inactive_branch_active_user:{$branch->id}:{$user->id}",
                    'type' => 'inactive_branch_active_user',
                    'severity' => 'warning',
                    'title' => 'Branch inactive còn tài khoản active',
                    'message' => "{$user->name} vẫn active nhưng đang gán vào branch inactive {$branch->name}.",
                    'tenant_id' => $branch->restaurant_id,
                    'tenant_name' => $branch->restaurant?->name,
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'repair_actions' => ['reassign_branch', 'clear_branch'],
                ]);
            }
        }

        $managerCandidates = $users
            ->filter(fn (User $user) => $user->status === 'active' && $user->isBranchManager())
            ->map(fn (User $user) => [
                'id' => $user->id,
                'tenant_id' => $user->restaurant_id,
                'name' => $user->name,
                'email' => $user->email,
                'branch_id' => $user->assignedBranchId(),
            ])
            ->values();

        $ownerCandidates = $users
            ->filter(fn (User $user) => $user->status === 'active' && ! $user->isSuperAdmin())
            ->map(fn (User $user) => [
                'id' => $user->id,
                'tenant_id' => $user->restaurant_id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->values()->all(),
            ])
            ->values();

        return Inertia::render('super-admin/tenant-health/Index', [
            'summary' => [
                'tenants' => $restaurants->count(),
                'branches' => $branches->count(),
                'issues' => $issues->count(),
                'critical' => $issues->where('severity', 'critical')->count(),
                'by_type' => $issues->groupBy('type')->map->count()->all(),
            ],
            'issues' => $issues->values()->all(),
            'tenants' => $restaurants->map(function (Restaurant $restaurant): array {
                $overrides = $restaurant->featureFlags->keyBy('feature');
                $effective = collect(TenantFeatureFlags::keys())->mapWithKeys(function (string $feature) use ($restaurant, $overrides): array {
                    $override = $overrides->get($feature);

                    return [$feature => $override ? (bool) $override->enabled : $this->quota->hasFeature($restaurant, $feature)];
                })->all();

                return [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'code' => $restaurant->code,
                    'status' => $restaurant->status,
                    'lifecycle_status' => $restaurant->lifecycleStatus(),
                    'plan' => $restaurant->plan?->name ?? 'Miễn phí',
                    'plan_code' => $restaurant->plan?->code ?? 'free',
                    'owner' => $restaurant->owner?->name,
                    'branches_count' => $restaurant->branches_count,
                    'features' => $effective,
                    'feature_overrides' => $overrides->mapWithKeys(fn ($flag) => [$flag->feature => (bool) $flag->enabled])->all(),
                ];
            })->values()->all(),
            'branches' => $branches->map(fn (RestaurantBranch $branch) => [
                'id' => $branch->id,
                'tenant_id' => $branch->restaurant_id,
                'name' => $branch->name,
                'status' => $branch->status,
            ])->values()->all(),
            'manager_candidates' => $managerCandidates->all(),
            'owner_candidates' => $ownerCandidates->all(),
            'feature_flags' => collect(TenantFeatureFlags::keys())->map(fn (string $feature) => [
                'key' => $feature,
                'label' => TenantFeatureFlags::label($feature),
            ])->values()->all(),
            'lifecycle_statuses' => ['active', 'suspended', 'archived', 'sandbox'],
        ]);
    }

    public function repair(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in([
                'assign_manager', 'assign_owner', 'reassign_branch', 'clear_branch',
                'set_lifecycle', 'set_feature_flag',
            ])],
            'tenant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'branch_id' => ['nullable', 'integer', 'exists:restaurant_branches,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'lifecycle_status' => ['nullable', Rule::in(['active', 'suspended', 'archived', 'sandbox'])],
            'feature' => ['nullable', Rule::in(TenantFeatureFlags::keys())],
            'enabled' => ['nullable', 'boolean'],
            'mode' => ['nullable', Rule::in(['override', 'inherit'])],
        ]);

        return DB::transaction(function () use ($request, $data): RedirectResponse {
            return match ($data['action']) {
                'assign_manager' => $this->assignManager($request, $data),
                'assign_owner' => $this->assignOwner($request, $data),
                'reassign_branch' => $this->reassignBranch($request, $data),
                'clear_branch' => $this->clearBranch($request, $data),
                'set_lifecycle' => $this->setLifecycle($request, $data),
                'set_feature_flag' => $this->setFeatureFlag($request, $data),
            };
        });
    }

    private function assignManager(Request $request, array $data): RedirectResponse
    {
        $tenant = $this->tenant($data);
        $branch = RestaurantBranch::where('restaurant_id', $tenant->id)->findOrFail($this->required($data, 'branch_id'));
        $user = User::where('restaurant_id', $tenant->id)->findOrFail($this->required($data, 'user_id'));

        if ($branch->status !== 'active' || $user->status !== 'active' || ! $user->isBranchManager()) {
            throw ValidationException::withMessages(['user_id' => 'Manager và branch phải active, cùng tenant và đúng role manager.']);
        }

        $alreadyAssigned = RestaurantBranch::where('restaurant_id', $tenant->id)
            ->where('manager_user_id', $user->id)
            ->where('status', 'active')
            ->where('id', '!=', $branch->id)
            ->exists();

        if ($alreadyAssigned) {
            throw ValidationException::withMessages(['user_id' => 'Manager này đã được gán cho branch active khác.']);
        }

        $previousManagerId = $branch->getRawOriginal('manager_user_id');
        $before = [
            'branch_manager_user_id' => $previousManagerId,
            'user_branch_id' => $user->getRawOriginal('branch_id'),
            'employee_branch_id' => $user->employee?->getRawOriginal('branch_id'),
        ];

        if ($previousManagerId && (int) $previousManagerId !== $user->id) {
            $previous = User::whereKey($previousManagerId)->first();
            if ($previous) {
                if ((int) $previous->getRawOriginal('branch_id') === $branch->id) {
                    $previous->forceFill(['branch_id' => null])->saveQuietly();
                }
                if ($previous->employee && (int) $previous->employee->getRawOriginal('branch_id') === $branch->id) {
                    $previous->employee->forceFill(['branch_id' => null])->saveQuietly();
                }
            }
        }

        $user->forceFill(['branch_id' => $branch->id])->saveQuietly();
        if ($user->employee) {
            $user->employee->forceFill(['branch_id' => $branch->id])->saveQuietly();
        }
        $branch->forceFill(['manager_user_id' => $user->id])->saveQuietly();

        $after = [
            'branch_manager_user_id' => $user->id,
            'user_branch_id' => $user->getRawOriginal('branch_id'),
            'employee_branch_id' => $user->employee?->getRawOriginal('branch_id'),
        ];
        $this->audit($request, 'tenant_health_assign_manager', $tenant, $branch, $branch, $before, $after);

        return back()->with('success', "Đã gán {$user->name} làm manager cho {$branch->name}.");
    }

    private function assignOwner(Request $request, array $data): RedirectResponse
    {
        $tenant = $this->tenant($data);
        $user = User::where('restaurant_id', $tenant->id)->findOrFail($this->required($data, 'user_id'));
        if ($user->status !== 'active' || $user->isSuperAdmin()) {
            throw ValidationException::withMessages(['user_id' => 'Owner phải là tài khoản tenant active, không phải platform-admin.']);
        }

        $before = [
            'owner_user_id' => $tenant->owner_user_id,
            'user_roles' => $user->getRoleNames()->values()->all(),
        ];
        $user->syncRoles(['owner']);
        $tenant->forceFill(['owner_user_id' => $user->id])->saveQuietly();
        $after = [
            'owner_user_id' => $tenant->owner_user_id,
            'user_roles' => $user->fresh()->getRoleNames()->values()->all(),
        ];
        $this->audit($request, 'tenant_health_assign_owner', $tenant, null, $tenant, $before, $after);

        return back()->with('success', "Đã gán {$user->name} làm owner cho tenant {$tenant->name}.");
    }

    private function reassignBranch(Request $request, array $data): RedirectResponse
    {
        $tenant = $this->tenant($data);
        $branch = RestaurantBranch::where('restaurant_id', $tenant->id)->where('status', 'active')->findOrFail($this->required($data, 'branch_id'));
        $user = User::where('restaurant_id', $tenant->id)->findOrFail($this->required($data, 'user_id'));

        if ($user->isSuperAdmin() || $user->status !== 'active') {
            throw ValidationException::withMessages(['user_id' => 'Chỉ tài khoản tenant active mới được gán branch.']);
        }
        if ($user->isBranchManager() && $branch->manager_user_id && (int) $branch->manager_user_id !== $user->id) {
            throw ValidationException::withMessages(['branch_id' => 'Branch đã có manager khác; hãy dùng thao tác gán manager.']);
        }

        $before = [
            'user_branch_id' => $user->getRawOriginal('branch_id'),
            'employee_branch_id' => $user->employee?->getRawOriginal('branch_id'),
        ];
        $user->forceFill(['branch_id' => $branch->id])->saveQuietly();
        if ($user->employee) {
            $user->employee->forceFill(['branch_id' => $branch->id])->saveQuietly();
        }
        if ($user->isBranchManager()) {
            $branch->forceFill(['manager_user_id' => $user->id])->saveQuietly();
        }
        $after = [
            'user_branch_id' => $user->getRawOriginal('branch_id'),
            'employee_branch_id' => $user->employee?->getRawOriginal('branch_id'),
        ];
        $this->audit($request, 'tenant_health_reassign_branch', $tenant, $branch, $user, $before, $after);

        return back()->with('success', "Đã gán {$user->name} vào {$branch->name}.");
    }

    private function clearBranch(Request $request, array $data): RedirectResponse
    {
        $user = User::findOrFail($this->required($data, 'user_id'));
        $tenant = $user->restaurant;
        abort_unless($tenant, 422, 'User chưa thuộc tenant.');

        $before = [
            'user_branch_id' => $user->getRawOriginal('branch_id'),
            'employee_branch_id' => $user->employee?->getRawOriginal('branch_id'),
        ];
        RestaurantBranch::where('manager_user_id', $user->id)->update(['manager_user_id' => null]);
        $user->forceFill(['branch_id' => null])->saveQuietly();
        if ($user->employee) {
            $user->employee->forceFill(['branch_id' => null])->saveQuietly();
        }
        $this->audit($request, 'tenant_health_clear_branch', $tenant, null, $user, $before, [
            'user_branch_id' => null,
            'employee_branch_id' => null,
        ]);

        return back()->with('success', "Đã xóa gán branch của {$user->name}.");
    }

    private function setLifecycle(Request $request, array $data): RedirectResponse
    {
        $tenant = $this->tenant($data);
        $status = $data['lifecycle_status'] ?? null;
        if (! $status) {
            throw ValidationException::withMessages(['lifecycle_status' => 'Chưa chọn lifecycle status.']);
        }

        $before = ['lifecycle_status' => $tenant->lifecycleStatus()];
        $tenant->forceFill(['lifecycle_status' => $status])->saveQuietly();
        $this->audit($request, 'tenant_lifecycle_update', $tenant, null, $tenant, $before, ['lifecycle_status' => $status]);
        Cache::forget("quota_summary:{$tenant->id}");

        return back()->with('success', "Đã chuyển tenant {$tenant->name} sang trạng thái {$status}.");
    }

    private function setFeatureFlag(Request $request, array $data): RedirectResponse
    {
        $tenant = $this->tenant($data);
        $feature = $data['feature'] ?? null;
        if (! $feature) {
            throw ValidationException::withMessages(['feature' => 'Chưa chọn feature flag.']);
        }

        $current = $tenant->featureFlags()->where('feature', $feature)->first();
        $before = [
            'feature' => $feature,
            'override' => $current?->enabled,
            'effective' => $this->quota->hasFeature($tenant, $feature),
        ];

        if (($data['mode'] ?? 'override') === 'inherit') {
            $current?->delete();
            $afterOverride = null;
        } else {
            $enabled = (bool) ($data['enabled'] ?? false);
            $tenant->featureFlags()->updateOrCreate(
                ['feature' => $feature],
                ['enabled' => $enabled],
            );
            $afterOverride = $enabled;
        }

        Cache::forget("quota_summary:{$tenant->id}");
        $this->audit($request, 'tenant_feature_flag_update', $tenant, null, $tenant, $before, [
            'feature' => $feature,
            'override' => $afterOverride,
            'effective' => $this->quota->hasFeature($tenant->fresh(), $feature),
        ]);

        return back()->with('success', "Đã cập nhật feature {$feature} cho {$tenant->name}.");
    }

    private function tenant(array $data): Restaurant
    {
        return Restaurant::findOrFail($this->required($data, 'tenant_id'));
    }

    private function required(array $data, string $key): int
    {
        if (empty($data[$key])) {
            throw ValidationException::withMessages([$key => 'Trường này là bắt buộc.']);
        }

        return (int) $data[$key];
    }

    private function audit(
        Request $request,
        string $action,
        Restaurant $tenant,
        ?RestaurantBranch $branch,
        ?Model $subject,
        array $before,
        array $after,
    ): void {
        AuditLog::create([
            'restaurant_id' => $tenant->id,
            'branch_id' => $branch?->id,
            'user_id' => $request->user()->id,
            'user_role' => $request->user()->getRoleNames()->first() ?? 'superadmin',
            'event' => 'updated',
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : Restaurant::class,
            'subject_id' => $subject?->getKey() ?? $tenant->id,
            'old_values' => $before,
            'new_values' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        SuperAdminAuditStream::record($action, [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch?->id,
            'before' => $before,
            'after' => $after,
        ], $subject ? get_class($subject) : Restaurant::class, $subject?->getKey() ?? $tenant->id, 'warning');
    }
}
