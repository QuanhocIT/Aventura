<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\IngredientPriceHistory;
use App\Services\ApprovalService;
use App\Services\CentralWarehouseAiService;
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
     * Workspace theo dõi giá nguyên liệu từng đợt nhập Kho Tổng (Batch-level Price Ledger).
     * Tuyệt đối không tính cào bằng trung bình và không cho sửa giá bằng tay.
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

        $allBatches = \App\Models\InventoryBatch::where('restaurant_id', $user->restaurant_id)
            ->whereIn('ingredient_id', $ingredientIds)
            ->with(['supplier:id,name', 'location:id,location_code,zone'])
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('ingredient_id');

        $totalBatchesCount = 0;
        $priceUpCount = 0;
        $priceDownCount = 0;
        $largeChangeCount = 0;
        $latestPurchasedAt = null;
        $staleCutoff = now()->subDays(30);
        $staleCount = 0;

        $mappedIngredients = $ingredients->map(function ($ing) use (
            $allBatches,
            $staleCutoff,
            &$totalBatchesCount,
            &$priceUpCount,
            &$priceDownCount,
            &$largeChangeCount,
            &$staleCount,
            &$latestPurchasedAt
        ): array {
            $batches = $allBatches->get($ing->id, collect())->values();
            $totalBatchesCount += $batches->count();

            $latestBatch = $batches->first();
            $previousBatch = $batches->get(1);

            $latestCost = $latestBatch ? (float) $latestBatch->unit_cost : (float) ($ing->average_cost ?? 0);
            $previousCost = $previousBatch ? (float) $previousBatch->unit_cost : null;

            $priceTrend = null;
            if ($latestBatch && $previousBatch && $previousCost !== null && $previousCost > 0) {
                $diff = $latestCost - $previousCost;
                $pct = round(($diff / $previousCost) * 100, 1);
                if (abs($pct) >= 10) {
                    $largeChangeCount++;
                }
                if ($pct > 0) {
                    $priceUpCount++;
                    $trendType = 'up';
                } elseif ($pct < 0) {
                    $priceDownCount++;
                    $trendType = 'down';
                } else {
                    $trendType = 'stable';
                }
                $priceTrend = [
                    'diff' => $diff,
                    'percent' => $pct,
                    'trend' => $trendType,
                ];
            } elseif ($latestBatch && ! $previousBatch) {
                $priceTrend = [
                    'diff' => 0,
                    'percent' => 0,
                    'trend' => 'initial',
                ];
            }

            $isStale = ! $latestBatch || ! $latestBatch->purchased_at || $latestBatch->purchased_at->lt($staleCutoff);
            if ($isStale) {
                $staleCount++;
            }

            if ($latestBatch && $latestBatch->purchased_at) {
                if (! $latestPurchasedAt || $latestBatch->purchased_at->gt($latestPurchasedAt)) {
                    $latestPurchasedAt = $latestBatch->purchased_at;
                }
            }

            $totalStock = (float) $batches->where('quantity_remaining', '>', 0)->sum('quantity_remaining');
            $activeBatchesCount = $batches->where('quantity_remaining', '>', 0)->count();

            $formattedBatches = $batches->map(function ($b): array {
                return [
                    'id' => $b->id,
                    'batch_number' => $b->batch_number ?? ('LÔ-'.$b->id),
                    'supplier_name' => $b->supplier?->name ?? 'Nhà cung cấp nội bộ / Chưa gắn',
                    'unit_cost' => (float) $b->unit_cost,
                    'quantity_remaining' => (float) $b->quantity_remaining,
                    'purchased_at' => $b->purchased_at?->format('d/m/Y') ?? '—',
                    'raw_purchased_at' => $b->purchased_at?->toDateString(),
                    'expiry_date' => $b->expiry_date?->format('d/m/Y'),
                    'status' => $b->status ?? 'active',
                    'is_expired' => $b->isExpired(),
                    'location_name' => $b->location?->location_code ?: ($b->location?->zone ?: null),
                ];
            })->all();

            return [
                'id' => $ing->id,
                'name' => $ing->name,
                'sku' => $ing->sku,
                'unit' => $ing->unit ? [
                    'id' => $ing->unit->id,
                    'name' => $ing->unit->name,
                    'symbol' => $ing->unit->symbol,
                ] : null,
                'latest_cost' => $latestCost,
                'previous_cost' => $previousCost,
                'price_trend' => $priceTrend,
                'latest_purchased_at' => $latestBatch?->purchased_at?->format('d/m/Y') ?? null,
                'latest_batch_number' => $latestBatch?->batch_number,
                'latest_supplier_name' => $latestBatch?->supplier?->name,
                'is_stale' => $isStale,
                'total_stock' => $totalStock,
                'active_batches_count' => $activeBatchesCount,
                'batches_count' => count($formattedBatches),
                'batches' => $formattedBatches,
            ];
        })->values();

        $priceGovernance = [
            'last_updated_at' => $latestPurchasedAt?->format('H:i d/m/Y') ?? ($latestPurchasedAt?->format('d/m/Y') ?? null),
            'stale_count' => $staleCount,
            'large_change_count' => $largeChangeCount,
            'pending_count' => 0,
            'total_batches' => $totalBatchesCount,
            'price_up_count' => $priceUpCount,
            'price_down_count' => $priceDownCount,
        ];

        return Inertia::render('inventory/CentralWarehousePrices', [
            'ingredients' => $mappedIngredients,
            'canManageWarehouse' => $user->isOwner() || $user->isSuperAdmin(),
            'priceGovernance' => $priceGovernance,
            'centralWarehouseAi' => app(CentralWarehouseAiService::class)->analyze([
                'priceGovernance' => $priceGovernance,
            ]),
        ]);
    }

    /**
     * Chặn cập nhật đơn giá thủ công để tuân thủ tính bất biến sổ cái (Ledger Immutability).
     */
    public function updateIngredientPrices(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Hệ thống đã khóa điều chỉnh giá thủ công để đảm bảo tính minh bạch sổ cái. Đơn giá được xác định tự động từ các hóa đơn và phiếu nhập hàng thực tế.',
        ], 403);
    }

    /**
     * Chặn đề xuất giá thủ công.
     */
    public function proposeIngredientPrices(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Hệ thống quản lý giá theo từng đợt nhập thực tế (Batch-level Cost Ledger). Không áp dụng cơ chế đề xuất giá thủ công.',
        ], 403);
    }
}
