<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\IngredientPriceHistory;
use App\Services\ApprovalService;
use App\Services\CentralWarehouseService;
use App\Services\SupplyRequestAnalyticsService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CentralWarehousePriceController extends Controller
{
    public function __construct(
        protected CentralWarehouseService $warehouseService,
        protected SupplyRequestAnalyticsService $analyticsService,
        protected ApprovalService $approvalService,
    ) {}

    /**
     * Workspace riêng cho bảng giá nguyên liệu Kho Tổng.
     */
    public function centralWarehousePricesPage(Request $request): Response
    {
        $user = $request->user();
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $ingredients = $this->analyticsService->centralIngredientQuery($user->restaurant_id, $centralBranch?->id)
            ->with('unit')
            ->orderBy('name')
            ->get();
        $ingredientIds = $ingredients->pluck('id');
        $ingredientNames = $ingredients->pluck('name', 'id');
        $priceHistory = IngredientPriceHistory::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->whereIn('ingredient_id', $ingredientIds)
            ->with(['ingredient:id,name,sku', 'changedBy:id,name', 'approvedBy:id,name'])
            ->latest('created_at')
            ->limit(100)
            ->get();
        $latestHistoryByIngredient = $priceHistory->unique('ingredient_id')->keyBy('ingredient_id');
        $pendingPriceUpdates = ApprovalRequest::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('operation_type', 'warehouse_price_update')
            ->whereIn('status', [ApprovalRequest::STATUS_PENDING, ApprovalRequest::STATUS_ESCALATED])
            ->when(
                ! $user->isOwner() && ! $user->isSuperAdmin(),
                fn ($query) => $query->where('requester_id', $user->id),
            )
            ->with('requester:id,name')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(function (ApprovalRequest $approval) use ($ingredientNames): array {
                $prices = collect($approval->operation_data['prices'] ?? []);

                return [
                    'id' => $approval->id,
                    'status' => $approval->status,
                    'requester_name' => $approval->requester?->name ?? '—',
                    'reason' => $approval->operation_data['reason'] ?? null,
                    'created_at' => $approval->created_at?->format('H:i d/m/Y'),
                    'items' => $prices->map(fn (array $price): array => [
                        'ingredient_id' => (int) ($price['ingredient_id'] ?? 0),
                        'ingredient_name' => $ingredientNames->get((int) ($price['ingredient_id'] ?? 0), 'Nguyên liệu không còn trong danh mục'),
                        'proposed_price' => (float) ($price['average_cost'] ?? 0),
                    ])->values()->all(),
                ];
            })
            ->values();
        $staleCutoff = now()->subDays(30);
        $staleCount = $ingredientIds->filter(function ($ingredientId) use ($latestHistoryByIngredient, $staleCutoff): bool {
            $history = $latestHistoryByIngredient->get($ingredientId);

            return ! $history || $history->created_at?->lt($staleCutoff);
        })->count();
        $largeChangeCount = $priceHistory
            ->where('status', 'approved')
            ->filter(fn (IngredientPriceHistory $history): bool => abs((float) $history->change_percent) >= 10)
            ->unique('ingredient_id')
            ->count();

        return Inertia::render('inventory/CentralWarehousePrices', [
            'ingredients' => $ingredients,
            'canManageWarehouse' => $user->isOwner() || $user->isSuperAdmin(),
            'canProposePrices' => $user->isOwner()
                || $user->isSuperAdmin()
                || $user->hasRole('warehouse_manager')
                || $user->can('warehouse.manage'),
            'pendingPriceUpdates' => $pendingPriceUpdates,
            'priceHistory' => $priceHistory->map(fn (IngredientPriceHistory $history): array => [
                'id' => $history->id,
                'ingredient_id' => $history->ingredient_id,
                'ingredient_name' => $history->ingredient?->name ?? '—',
                'ingredient_sku' => $history->ingredient?->sku,
                'old_price' => (float) ($history->old_price ?? 0),
                'new_price' => (float) ($history->new_price ?? 0),
                'change_percent' => (float) $history->change_percent,
                'status' => $history->status,
                'reason' => $history->change_reason,
                'changed_by' => $history->changedBy?->name ?? '—',
                'approved_by' => $history->approvedBy?->name,
                'created_at' => $history->created_at?->format('H:i d/m/Y'),
                'approved_at' => $history->approved_at?->format('H:i d/m/Y'),
            ])->values(),
            'priceGovernance' => [
                'last_updated_at' => $priceHistory->first()?->created_at?->format('H:i d/m/Y'),
                'stale_count' => $staleCount,
                'large_change_count' => $largeChangeCount,
                'pending_count' => $pendingPriceUpdates->count(),
            ],
        ]);
    }

    /**
     * Kho Tổng cập nhật đơn giá nguyên liệu dùng chung cho chuỗi (Owner/Super Admin).
     */
    public function updateIngredientPrices(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin(),
            403,
            'Chỉ Chủ doanh nghiệp mới được thiết lập trực tiếp giá vốn nguyên liệu toàn chuỗi.'
        );

        $data = $request->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.ingredient_id' => ['required', 'distinct', TenantRule::exists('ingredients')],
            'prices.*.average_cost' => 'required|numeric|min:0',
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $centralIngredientQuery = fn () => $this->analyticsService->centralIngredientQuery($user->restaurant_id, $centralBranch?->id);

        foreach ($data['prices'] as $index => $priceRow) {
            if (! $centralIngredientQuery()->whereKey((int) $priceRow['ingredient_id'])->exists()) {
                abort(403, 'Chỉ được cập nhật giá nguyên liệu thuộc Kho Tổng hoặc catalog toàn chuỗi.');
            }
        }
        $reason = trim((string) ($data['reason'] ?? 'Cập nhật trực tiếp trên bảng giá Kho Tổng.'));
        $updatedIds = DB::transaction(function () use ($data, $centralIngredientQuery, $reason, $user): array {
            $updatedIds = [];

            foreach ($data['prices'] as $priceRow) {
                $ingredient = $centralIngredientQuery()
                    ->whereKey((int) $priceRow['ingredient_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $oldPrice = (float) $ingredient->average_cost;
                $newPrice = round((float) $priceRow['average_cost'], 2);

                if (abs($newPrice - $oldPrice) < 0.005) {
                    continue;
                }

                $ingredient->update(['average_cost' => $newPrice]);
                IngredientPriceHistory::create([
                    'restaurant_id' => $user->restaurant_id,
                    'ingredient_id' => $ingredient->id,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'change_percent' => $oldPrice > 0 ? (($newPrice - $oldPrice) / $oldPrice) * 100 : ($newPrice > 0 ? 100 : 0),
                    'changed_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'change_reason' => $reason,
                    'requires_owner_approval' => false,
                    'status' => 'approved',
                ]);
                $updatedIds[] = $ingredient->id;
            }

            return $updatedIds;
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật đơn giá nguyên liệu đồng bộ toàn chuỗi.',
            'data' => $centralIngredientQuery()
                ->whereIn('id', $updatedIds)
                ->with('unit')
                ->get(),
        ]);
    }

    /**
     * Trưởng kho đề xuất thay đổi đơn giá nguyên liệu (gửi Chủ duyệt).
     */
    public function proposeIngredientPrices(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage'),
            403,
            'Bạn không có quyền đề xuất giá nguyên liệu.'
        );

        $data = $request->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.ingredient_id' => ['required', 'distinct', TenantRule::exists('ingredients')],
            'prices.*.average_cost' => 'required|numeric|min:0',
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $centralIngredientQuery = fn () => $this->analyticsService->centralIngredientQuery($user->restaurant_id, $centralBranch?->id);

        foreach ($data['prices'] as $index => $priceRow) {
            if (! $centralIngredientQuery()->whereKey((int) $priceRow['ingredient_id'])->exists()) {
                abort(403, 'Chỉ được đề xuất giá nguyên liệu thuộc Kho Tổng hoặc catalog toàn chuỗi.');
            }
        }

        $this->approvalService->submitRequest('warehouse_price_update', $data, $user);

        return response()->json([
            'success' => true,
            'message' => 'Yêu cầu cập nhật đơn giá đã gửi Chủ nhà hàng phê duyệt.',
        ]);
    }
}
