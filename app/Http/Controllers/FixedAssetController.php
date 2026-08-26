<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Models\FixedAssetHandover;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\FinancialPostingService;
use App\Services\FixedAssetCustodyService;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetController extends Controller
{
    public function __construct(
        private FinancialPostingService $financialPostingService,
        private FixedAssetCustodyService $custodyService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeView($request);

        $canViewAll = $this->canViewAll($user);
        $tenantContext = app(TenantContext::class);
        $assetQuery = FixedAsset::query()
            ->with([
                'branch:id,name',
                'custodian:id,name,email',
                'latestHandover.branch:id,name',
                'latestHandover.handedOverBy:id,name',
                'latestHandover.toUser:id,name,email',
                'latestInspection.branch:id,name',
                'latestInspection.inspector:id,name',
            ])
            ->latest('id');

        if ($tenantContext->isBranchScoped()) {
            $branchId = $tenantContext->activeBranchId();
            $assetQuery->where(function ($query) use ($branchId, $user): void {
                $query->where('branch_id', $branchId)
                    ->orWhereHas('handovers', fn ($handover) => $handover
                        ->where('branch_id', $branchId)
                        ->where('to_user_id', $user->id)
                        ->where('status', FixedAssetHandover::STATUS_PENDING));
            });
        } elseif (! $canViewAll) {
            $branchId = $user->assignedBranchId();
            $assetQuery->where(function ($query) use ($branchId, $user): void {
                $query->where('branch_id', $branchId ?: -1)
                    ->orWhereHas('handovers', fn ($handover) => $handover
                        ->where('to_user_id', $user->id)
                        ->where('status', FixedAssetHandover::STATUS_PENDING));
            });
        }

        $assets = $assetQuery
            ->paginate(30)
            ->withQueryString()
            ->through(fn (FixedAsset $asset): array => $this->serializeAsset($asset, $user));

        $statsQuery = clone $assetQuery;
        $statsRows = $statsQuery->withoutEagerLoads()->reorder()->get(['id', 'custody_status', 'condition_status']);
        $stats = [
            'total' => $statsRows->count(),
            'pending_handover' => $statsRows->where('custody_status', 'pending_handover')->count(),
            'assigned' => $statsRows->where('custody_status', 'assigned')->count(),
            'attention' => $statsRows->where('custody_status', 'attention')->count(),
            'unassessed' => $statsRows->where('condition_status', 'unassessed')->count(),
        ];

        $branches = RestaurantBranch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->with('manager:id,name,email')
            ->when(
                $tenantContext->isBranchScoped(),
                fn ($query) => $query->whereKey($tenantContext->activeBranchId()),
            )
            ->when(
                ! $canViewAll && ! $tenantContext->isBranchScoped(),
                fn ($query) => $query->whereKey($user->assignedBranchId() ?: -1),
            )
            ->orderBy('name')
            ->get()
            ->map(fn (RestaurantBranch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'manager' => $branch->manager ? [
                    'id' => $branch->manager->id,
                    'name' => $branch->manager->name,
                    'email' => $branch->manager->email,
                ] : null,
            ])
            ->values();

        $managers = User::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->with('roles:id,name')
            ->get()
            ->filter(fn (User $manager): bool => $manager->isBranchManager())
            ->map(fn (User $manager): array => [
                'id' => $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
                'branch_id' => $manager->assignedBranchId(),
            ])
            ->filter(fn (array $manager): bool => $manager['branch_id'] !== null)
            ->values();

        return Inertia::render('fixed-assets/Index', [
            'assets' => $assets,
            'branches' => $branches,
            'managers' => $managers,
            'stats' => $stats,
            'currentUserId' => $user->id,
            'permissions' => [
                'canManageAssets' => $this->canManage($user),
                'canHandover' => $this->canHandover($user),
                'canAcceptHandover' => $user->isBranchManager(),
                'canInspect' => $this->canInspect($user),
                'canViewAll' => $canViewAll,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManage($request);

        $data = $request->validate([
            'asset_code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('fixed_assets', 'asset_code')->where('restaurant_id', $user->restaurant_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:150'],
            'model' => ['nullable', 'string', 'max:150'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'unit' => ['nullable', 'string', 'max:30'],
            'serial_number' => ['nullable', 'string', 'max:150'],
            'branch_id' => [
                'required',
                TenantRule::exists('restaurant_branches'),
            ],
            'purchase_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['nullable', 'numeric', 'min:0.01'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'warranty_until' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'payment_method' => ['nullable', 'string', 'in:bank_transfer,cash,credit'],
            'specifications' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        app(TenantContext::class)->assertWriteBranch((int) $data['branch_id']);
        $data['quantity'] = (int) ($data['quantity'] ?? 1);
        $data['unit'] = $data['unit'] ?: 'cái';
        $data['unit_cost'] = $data['unit_cost'] ?? round((float) $data['cost'] / $data['quantity'], 2);
        $data['useful_life_months'] = (int) ($data['useful_life_months'] ?? 36);
        $creditAccount = match ($data['payment_method'] ?? 'credit') {
            'cash' => '1111',
            'bank_transfer' => '1121',
            default => '3311',
        };

        $asset = DB::transaction(function () use ($data, $user, $creditAccount): FixedAsset {
            $asset = FixedAsset::create([
                'restaurant_id' => $user->restaurant_id,
                'branch_id' => $data['branch_id'],
                'asset_code' => $data['asset_code'],
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'quantity' => $data['quantity'],
                'unit' => $data['unit'],
                'serial_number' => $data['serial_number'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'in_service_date' => $data['purchase_date'],
                'cost' => $data['cost'],
                'unit_cost' => $data['unit_cost'],
                'supplier' => $data['supplier'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'warranty_until' => $data['warranty_until'] ?? null,
                'specifications' => $data['specifications'] ?? null,
                'residual_value' => 0,
                'useful_life_months' => $data['useful_life_months'],
                'notes' => $data['notes'] ?? null,
                'custody_status' => 'unassigned',
                'condition_status' => 'unassessed',
                'created_by' => $user->id,
            ]);

            $this->financialPostingService->post([
                'restaurant_id' => $asset->restaurant_id,
                'branch_id' => $asset->branch_id,
                'entry_date' => $asset->purchase_date,
                'source_type' => FixedAsset::class,
                'source_id' => $asset->id,
                'idempotency_key' => 'fixed-asset:acquisition:'.$asset->id,
                'description' => 'Ghi nhận tài sản '.$asset->asset_code,
                'created_by' => $user->id,
                'posted_by' => $user->id,
                'lines' => [
                    ['account' => '2111', 'debit' => $asset->cost, 'credit' => 0],
                    ['account' => $creditAccount, 'debit' => 0, 'credit' => $asset->cost],
                ],
            ]);

            return $asset;
        });

        return back()->with('success', "Đã tạo hồ sơ tài sản {$asset->asset_code}. Hãy lập biên bản bàn giao cho quản lý chi nhánh.");
    }

    public function update(Request $request, FixedAsset $asset): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManage($request);

        abort_if($asset->restaurant_id !== $user->restaurant_id, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:150'],
            'model' => ['nullable', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit' => ['required', 'string', 'max:30'],
            'serial_number' => ['nullable', 'string', 'max:150'],
            'unit_cost' => ['nullable', 'numeric', 'min:0.01'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'warranty_until' => ['nullable', 'date', 'after_or_equal:'.$asset->purchase_date?->format('Y-m-d')],
            'specifications' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['unit'] = trim($data['unit']) ?: 'cái';
        $data['unit_cost'] = $data['unit_cost'] ?? round((float) $asset->cost / (int) $data['quantity'], 2);

        $asset->update($data);

        return back()->with('success', "Đã cập nhật thông tin tài sản {$asset->asset_code}.");
    }

    public function storeHandover(Request $request, FixedAsset $asset): RedirectResponse
    {
        $this->authorizeHandover($request);
        $user = $request->user();

        abort_if($asset->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'branch_id' => [
                'required',
                TenantRule::exists('restaurant_branches'),
            ],
            'to_user_id' => ['required', TenantRule::exists('users')],
            'handover_date' => ['required', 'date'],
            'condition_at_handover' => ['required', 'in:good,minor_issue,major_issue'],
            'custody_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'image', 'max:5120'],
        ]);
        app(TenantContext::class)->assertWriteBranch((int) $data['branch_id']);

        $data['evidence_path'] = $request->file('evidence')?->store("fixed-assets/handovers/{$user->restaurant_id}", 'public');
        $this->custodyService->createHandover($asset, $user, $data);

        return back()->with('success', 'Đã lập biên bản bàn giao. Chờ quản lý chi nhánh xác nhận.');
    }

    public function acceptHandover(Request $request, FixedAssetHandover $handover): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeAccept($request);
        app(TenantContext::class)->assertWriteBranch((int) $handover->branch_id);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $this->custodyService->acceptHandover($handover, $user, $data['notes'] ?? null);

        return back()->with('success', 'Đã xác nhận nhận tài sản và trở thành người chịu trách nhiệm tại chi nhánh.');
    }

    public function rejectHandover(Request $request, FixedAssetHandover $handover): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeAccept($request);
        app(TenantContext::class)->assertWriteBranch((int) $handover->branch_id);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);

        $this->custodyService->rejectHandover($handover, $user, $data['reason']);

        return back()->with('success', 'Đã từ chối biên bản bàn giao và ghi nhận lý do.');
    }

    public function inspect(Request $request, FixedAsset $asset): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeInspect($request);

        abort_if($asset->restaurant_id !== $user->restaurant_id, 403);
        app(TenantContext::class)->assertWriteBranch((int) $asset->branch_id);

        $data = $request->validate([
            'fixed_asset_handover_id' => ['nullable', TenantRule::exists('fixed_asset_handovers')],
            'inspection_type' => ['required', 'in:handover,routine,surprise,incident'],
            'inspected_at' => ['required', 'date'],
            'condition_status' => ['required', 'in:good,minor_issue,major_issue,missing'],
            'result' => ['required', 'in:pass,needs_action,fail'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'findings' => ['required', 'string', 'min:5', 'max:5000'],
            'action_required' => ['nullable', 'required_if:result,needs_action,fail', 'string', 'max:3000'],
            'evidence' => ['nullable', 'image', 'max:5120'],
        ]);

        if (! empty($data['fixed_asset_handover_id'])) {
            $handoverBelongsToAsset = FixedAssetHandover::where('fixed_asset_id', $asset->id)
                ->whereKey($data['fixed_asset_handover_id'])
                ->where('status', FixedAssetHandover::STATUS_ACCEPTED)
                ->exists();

            if (! $handoverBelongsToAsset) {
                abort(422, 'Biên bản bàn giao không thuộc tài sản hoặc chưa được xác nhận.');
            }
        }

        $data['evidence_path'] = $request->file('evidence')?->store("fixed-assets/inspections/{$user->restaurant_id}", 'public');
        $this->custodyService->inspect($asset, $user, $data);

        return back()->with('success', 'Đã lưu biên bản kiểm tra và đánh giá tài sản.');
    }

    private function serializeAsset(FixedAsset $asset, User $viewer): array
    {
        $latestHandover = $asset->latestHandover;
        $latestInspection = $asset->latestInspection;

        return [
            'id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'name' => $asset->name,
            'category' => $asset->category,
            'brand' => $asset->brand,
            'model' => $asset->model,
            'quantity' => (int) ($asset->quantity ?: 1),
            'unit' => $asset->unit ?: 'cái',
            'serial_number' => $asset->serial_number,
            'branch_name' => $asset->branch?->name,
            'branch_id' => $asset->branch_id,
            'purchase_date' => $asset->purchase_date?->format('Y-m-d'),
            'cost' => (float) $asset->cost,
            'unit_cost' => (float) ($asset->unit_cost ?: ((float) $asset->cost / max(1, (int) ($asset->quantity ?: 1)))),
            'supplier' => $asset->supplier,
            'invoice_number' => $asset->invoice_number,
            'warranty_until' => $asset->warranty_until?->format('Y-m-d'),
            'specifications' => $asset->specifications,
            'notes' => $asset->notes,
            'status' => $asset->status,
            'custody_status' => $asset->custody_status ?? 'unassigned',
            'condition_status' => $asset->condition_status ?? 'unassessed',
            'custody_location' => $asset->custody_location,
            'last_inspected_at' => $asset->last_inspected_at?->format('Y-m-d H:i'),
            'custodian' => $asset->custodian ? [
                'id' => $asset->custodian->id,
                'name' => $asset->custodian->name,
                'email' => $asset->custodian->email,
            ] : null,
            'latest_handover' => $latestHandover ? [
                'id' => $latestHandover->id,
                'handover_code' => $latestHandover->handover_code,
                'status' => $latestHandover->status,
                'handover_date' => $latestHandover->handover_date?->format('Y-m-d'),
                'branch_name' => $latestHandover->branch?->name,
                'handed_over_by' => $latestHandover->handedOverBy ? [
                    'id' => $latestHandover->handedOverBy->id,
                    'name' => $latestHandover->handedOverBy->name,
                ] : null,
                'to_user' => $latestHandover->toUser ? [
                    'id' => $latestHandover->toUser->id,
                    'name' => $latestHandover->toUser->name,
                    'email' => $latestHandover->toUser->email,
                ] : null,
                'condition_at_handover' => $latestHandover->condition_at_handover,
                'custody_location' => $latestHandover->custody_location,
                'notes' => $latestHandover->notes,
                'rejection_reason' => $latestHandover->rejection_reason,
                'accepted_at' => $latestHandover->accepted_at?->format('d/m/Y H:i'),
            ] : null,
            'latest_inspection' => $latestInspection ? [
                'id' => $latestInspection->id,
                'inspection_code' => $latestInspection->inspection_code,
                'inspection_type' => $latestInspection->inspection_type,
                'inspected_at' => $latestInspection->inspected_at?->format('Y-m-d'),
                'condition_status' => $latestInspection->condition_status,
                'result' => $latestInspection->result,
                'score' => $latestInspection->score,
                'findings' => $latestInspection->findings,
                'action_required' => $latestInspection->action_required,
                'inspector_name' => $latestInspection->inspector?->name,
                'evidence_url' => $this->publicUrl($latestInspection->evidence_path),
            ] : null,
            'can_accept_handover' => $latestHandover?->status === FixedAssetHandover::STATUS_PENDING
                && (int) $latestHandover->to_user_id === (int) $viewer->id,
            'handover_evidence_url' => $this->publicUrl($latestHandover?->evidence_path),
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function authorizeView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner()
            || $user->isSuperAdmin()
            || $user->isBranchManager()
            || $user->hasAnyRole(['accountant', 'operations_inspector', 'compliance_auditor'])
            || $user->hasPermissionTo('fixed_assets.view'),
            403,
        );
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->canManage($request->user()), 403, 'Bạn không có quyền lập hồ sơ tài sản.');
    }

    private function authorizeHandover(Request $request): void
    {
        abort_unless($this->canHandover($request->user()), 403, 'Bạn không có quyền lập biên bản bàn giao tài sản.');
    }

    private function authorizeAccept(Request $request): void
    {
        abort_unless($request->user()->isBranchManager(), 403, 'Chỉ Quản lý chi nhánh mới được xác nhận tài sản được giao.');
    }

    private function authorizeInspect(Request $request): void
    {
        $user = $request->user();
        abort_unless($this->canInspect($user), 403, 'Tài khoản này không có quyền kiểm tra tài sản.');
    }

    private function canManage(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->hasRole('accountant')
            || $user->hasPermissionTo('fixed_assets.manage');
    }

    private function canHandover(User $user): bool
    {
        return $this->canManage($user);
    }

    private function canInspect(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->hasAnyRole(['operations_inspector', 'compliance_auditor'])
            || $user->hasPermissionTo('fixed_assets.inspect')
            || $user->hasPermissionTo('operational_audit.report');
    }

    private function canViewAll(User $user): bool
    {
        return $user->canViewAllBranches()
            || $user->hasPermissionTo('fixed_assets.view_all');
    }
}
