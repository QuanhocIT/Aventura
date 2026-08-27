<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\InternalTransfer;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryBatchAllocation;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\StockTransferRequest;
use App\Models\User;
use App\Notifications\StockTransferStageNotification;
use App\Services\AnalyticsServiceClient;
use App\Services\CircuitBreaker;
use App\Services\WarehouseReverseLogisticsService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InternalTransferController extends Controller
{
    /**
     * Lấy danh sách các đề xuất chuyển kho liên chi nhánh (AI & PHP fallback).
     */
    public function transferRecommendations(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage'), 403);

        // 1. Fetch branches
        $branches = RestaurantBranch::where('restaurant_id', $user->restaurant_id)->get();
        if ($branches->count() <= 1) {
            return response()->json(['recommendations' => [], 'message' => 'Bạn cần tối thiểu 2 chi nhánh để thực hiện luân chuyển kho liên chi nhánh.']);
        }

        // 2. Fetch active ingredients
        $ingredients = Ingredient::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->with('unit')
            ->get();

        // 3. Fetch inventories
        $inventories = Inventory::where('restaurant_id', $user->restaurant_id)->get();

        // 4. Fetch daily consumption per branch & ingredient over the last 30 days
        $endDate = now();
        $startDate = now()->subDays(30);

        $orderItems = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('product_recipes', 'products.id', '=', 'product_recipes.product_id')
            ->join('ingredients', 'ingredients.id', '=', 'product_recipes.ingredient_id')
            ->join('units as recipe_units', 'recipe_units.id', '=', 'product_recipes.unit_id')
            ->join('units as ingredient_units', 'ingredient_units.id', '=', 'ingredients.unit_id')
            ->where('orders.restaurant_id', $user->restaurant_id)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.completed_at', [$startDate, $endDate])
            ->select(
                'orders.branch_id',
                'product_recipes.ingredient_id',
                DB::raw('SUM(order_items.quantity * product_recipes.quantity * (COALESCE(recipe_units.conversion_factor_to_base, 1) / COALESCE(ingredient_units.conversion_factor_to_base, 1)) * (1 + (product_recipes.waste_rate / 100))) as total_used')
            )
            ->groupBy('orders.branch_id', 'product_recipes.ingredient_id')
            ->get();

        $dailyUsageMap = [];
        foreach ($orderItems as $item) {
            $dailyUsageMap[$item->branch_id][$item->ingredient_id] = (float) $item->total_used / 30.0;
        }

        // 5. Compile payload
        $payload = [];
        foreach ($ingredients as $ing) {
            foreach ($branches as $branch) {
                $inv = $inventories->first(fn ($i) => $i->branch_id === $branch->id && $i->ingredient_id === $ing->id);
                $currentStock = $inv ? (float) $inv->quantity_on_hand : 0.0;
                $avgDaily = $dailyUsageMap[$branch->id][$ing->id] ?? 0.0;

                $payload[] = [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'ingredient_id' => $ing->id,
                    'ingredient_name' => $ing->name,
                    'sku' => $ing->sku,
                    'current_stock' => $currentStock,
                    'min_stock_level' => (float) $ing->min_stock_level,
                    'unit_symbol' => $ing->unit?->symbol ?? 'kg',
                    'average_daily_usage' => $avgDaily,
                ];
            }
        }

        // 6. Call Python FastAPI, qua CircuitBreaker dùng chung (trước đây bare try/catch,
        // không có failure-threshold/backoff, và THIẾU header X-Internal-API-Key mà
        // analytics_service bắt buộc — xem AnalyticsServiceClient).
        $baseUrl = config('services.analytics.url');

        $recommendations = app(CircuitBreaker::class)->for('analytics_service')->attempt(
            function () use ($baseUrl, $payload) {
                $response = Http::timeout(10)
                    ->withHeaders(app(AnalyticsServiceClient::class)->authHeaders())
                    ->post("{$baseUrl}/api/analytics/transfer-recommendations", [
                        'inventories' => $payload,
                    ]);

                if (! $response->successful()) {
                    throw new \RuntimeException("transferRecommendations: analytics service trả lỗi HTTP {$response->status()}");
                }

                return $response->json()['recommendations'] ?? null;
            },
            function (): ?array {
                Log::warning('transferRecommendations: Python service không khả dụng, dùng Fallback PHP.');

                return null;
            }
        );

        // 7. PHP Fallback if python is offline
        if ($recommendations === null) {
            $recommendations = [];
            $groupedByIng = collect($payload)->groupBy('ingredient_id');
            foreach ($groupedByIng as $ingId => $branchStockList) {
                $deficits = $branchStockList->filter(fn ($item) => $item['current_stock'] < $item['min_stock_level']);
                $candidates = $branchStockList->filter(fn ($item) => $item['current_stock'] > $item['min_stock_level']);

                foreach ($deficits as $def) {
                    $deficitQty = $def['min_stock_level'] - $def['current_stock'];
                    $ingName = $def['ingredient_name'];
                    $unit = $def['unit_symbol'];

                    $validCandidates = [];
                    foreach ($candidates as $cand) {
                        if ($cand['branch_id'] === $def['branch_id']) {
                            continue;
                        }

                        $excess = $cand['current_stock'] - $cand['min_stock_level'];
                        $avgDaily = $cand['average_daily_usage'];
                        $coverageDays = $avgDaily > 0 ? $cand['current_stock'] / $avgDaily : 999.0;

                        if ($coverageDays >= 14.0 || $avgDaily <= 0.01) {
                            $validCandidates[] = array_merge($cand, [
                                'excess' => $excess,
                                'coverage_days' => $coverageDays,
                            ]);
                        }
                    }

                    if (empty($validCandidates)) {
                        continue;
                    }

                    // Sort by excess desc
                    usort($validCandidates, fn ($a, $b) => $b['excess'] <=> $a['excess']);
                    $best = $validCandidates[0];

                    $suggested = min($deficitQty, $best['excess']);
                    $reason = sprintf(
                        "Chi nhánh '%s' đang có lượng tồn dư thừa %.2f %s (đủ dùng %.1f ngày với tốc độ tiêu thụ %.2f/ngày).",
                        $best['branch_name'],
                        $best['excess'],
                        $unit,
                        $best['coverage_days'],
                        $best['average_daily_usage']
                    );

                    $recommendations[] = [
                        'ingredient_id' => (int) $ingId,
                        'ingredient_name' => $ingName,
                        'unit_symbol' => $unit,
                        'from_branch_id' => (int) $best['branch_id'],
                        'from_branch_name' => $best['branch_name'],
                        'to_branch_id' => (int) $def['branch_id'],
                        'to_branch_name' => $def['branch_name'],
                        'suggested_quantity' => round($suggested, 3),
                        'reason' => $reason,
                    ];
                }
            }
        }

        return response()->json([
            'recommendations' => $recommendations,
            'branches' => $branches,
            'inventories' => $inventories,
        ]);
    }

    /**
     * Thực thi lệnh luân chuyển kho nội bộ.
     */
    public function storeInternalTransfer(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage'), 403);

        $request->validate([
            'from_branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'to_branch_id' => ['required', TenantRule::exists('restaurant_branches'), 'different:from_branch_id'],
            'ingredient_id' => ['required', TenantRule::exists('ingredients')],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        $fromBranchId = (int) $request->input('from_branch_id');
        $toBranchId = (int) $request->input('to_branch_id');
        $ingId = (int) $request->input('ingredient_id');
        $quantity = (float) $request->input('quantity');
        $idempotencyKey = trim((string) ($request->input('idempotency_key') ?: $request->header('Idempotency-Key', '')));

        // The former endpoint performed a one-shot stock move and could not
        // record receiving discrepancies or return damaged goods. Keep the
        // URL for compatibility, but create the canonical controlled request
        // instead; stock changes now happen only in route/dispatch/receive.
        $openRequest = StockTransferRequest::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('from_branch_id', $fromBranchId)
            ->where('to_branch_id', $toBranchId)
            ->where('ingredient_id', $ingId)
            ->where('quantity_requested', $quantity)
            ->whereIn('status', ['requested', 'routed', 'dispatched', 'discrepancy', 'quarantined', 'return_requested'])
            ->first();
        if ($openRequest) {
            return back()->with('success', 'Chi nhÃ¡nh nháº­n Ä‘Ã£ cÃ³ yÃªu cáº§u Ä‘iá»u chuyá»ƒn Ä‘ang xá»­ lÃ½.');
        }
        if ($idempotencyKey !== '') {
            $existingRequest = StockTransferRequest::query()
                ->where('restaurant_id', $user->restaurant_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existingRequest) {
                return back()->with('success', 'YÃªu cáº§u Ä‘iá»u chuyá»ƒn Ä‘Ã£ Ä‘Æ°á»£c ghi nháº­n trÆ°á»›c Ä‘Ã³.');
            }
        }

        $sourceBranch = RestaurantBranch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->findOrFail($fromBranchId);
        $destinationBranch = RestaurantBranch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->findOrFail($toBranchId);
        $reason = $request->input('notes') ?: 'YÃªu cáº§u luÃ¢n chuyá»ƒn kho ná»™i bá»™.';
        $controlledRequest = StockTransferRequest::create([
            'restaurant_id' => $user->restaurant_id,
            'from_branch_id' => $sourceBranch->id,
            'to_branch_id' => $destinationBranch->id,
            'ingredient_id' => $ingId,
            'quantity_requested' => $quantity,
            'priority' => 'normal',
            'reason' => $reason,
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            'status' => 'requested',
            'requested_by' => $user->id,
        ]);
        User::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('id', '!=', $user->id)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['owner', 'warehouse_manager']))
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(new StockTransferStageNotification($controlledRequest, 'requested', $user->name)));
        AuditLog::log('stock_transfer_requested_from_legacy_endpoint', 'created', $controlledRequest, null, [
            'source' => 'inventory.internal-transfers',
            'by' => $user->name,
        ]);

        return back()->with('success', 'ÄÃ£ chuyá»ƒn yÃªu cáº§u sang quy trÃ¬nh Ä‘iá»u chuyá»ƒn cÃ³ kiá»ƒm Ä‘áº¿m. Kho nguá»“n cáº§n Ä‘á»‹nh tuyáº¿n vÃ  xuáº¥t hÃ ng.');

        if ($idempotencyKey !== '' && InventoryTransaction::where('restaurant_id', $user->restaurant_id)
            ->where('idempotency_key', $idempotencyKey.':out')->exists()) {
            return back()->with('success', 'Lá»‡nh luÃ¢n chuyá»ƒn Ä‘Ã£ Ä‘Æ°á»£c ghi nháº­n trÆ°á»›c Ä‘Ã³.');
        }

        $recentDuplicate = InternalTransfer::where('restaurant_id', $user->restaurant_id)
            ->where('from_branch_id', $fromBranchId)
            ->where('to_branch_id', $toBranchId)
            ->where('ingredient_id', $ingId)
            ->where('quantity', $quantity)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->first();
        if ($recentDuplicate) {
            return back()->with('success', 'Lá»‡nh luÃ¢n chuyá»ƒn vá»«a Ä‘Æ°á»£c ghi nháº­n, khÃ´ng táº¡o trÃ¹ng.');
        }

        try {
            DB::transaction(function () use ($user, $fromBranchId, $toBranchId, $ingId, $quantity, $request, $idempotencyKey) {
                // 1. Pessimistic lock on from_branch inventory
                $invFrom = Inventory::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $fromBranchId)
                    ->where('ingredient_id', $ingId)
                    ->lockForUpdate()
                    ->first();

                if (! $invFrom || (float) $invFrom->quantity_available + 0.0005 < $quantity) {
                    throw new \Exception('Chi nhánh xuất không đủ tồn kho thực tế để chuyển.');
                }

                $sourceBefore = (float) $invFrom->quantity_on_hand;
                $sourceAfter = round($sourceBefore - $quantity, 3);
                $sourceTheoreticalBefore = $invFrom->effectiveTheoreticalQuantity();

                $sourceBatches = InventoryBatch::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $fromBranchId)
                    ->where('ingredient_id', $ingId)
                    ->where('status', 'active')
                    ->where(function ($query): void {
                        $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today());
                    })
                    ->where('quantity_remaining', '>', 0)
                    ->orderByRaw('expiry_date IS NULL')
                    ->orderBy('expiry_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $activeBatchQuantity = (float) $sourceBatches->sum('quantity_remaining');
                $unallocatedOpeningQuantity = max(0, $sourceBefore - $activeBatchQuantity);
                if ($unallocatedOpeningQuantity > 0.0005) {
                    $sourceBatches->push(InventoryBatch::create([
                        'restaurant_id' => $user->restaurant_id,
                        'branch_id' => $fromBranchId,
                        'ingredient_id' => $ingId,
                        'batch_number' => 'LEGACY-TRANSFER-'.$fromBranchId.'-'.$ingId.'-'.now()->format('YmdHis'),
                        'quantity_remaining' => $unallocatedOpeningQuantity,
                        'unit_cost' => (float) $invFrom->last_cost,
                        'purchased_at' => now()->toDateString(),
                        'status' => 'active',
                    ]));
                    $activeBatchQuantity += $unallocatedOpeningQuantity;
                }
                if ($activeBatchQuantity + 0.0005 < $quantity) {
                    throw new \Exception('Tồn theo lô không đủ để chuyển. Hãy kiểm tra lại số dư và batch trước khi thực hiện.');
                }

                $remainingToAllocate = $quantity;
                $sourceAllocations = [];
                foreach ($sourceBatches as $sourceBatch) {
                    if ($remainingToAllocate <= 0.0005) {
                        break;
                    }
                    $allocated = min($remainingToAllocate, (float) $sourceBatch->quantity_remaining);
                    if ($allocated <= 0) {
                        continue;
                    }
                    $sourceAllocations[] = [
                        'batch' => $sourceBatch,
                        'quantity' => round($allocated, 3),
                        'unit_cost' => (float) $sourceBatch->unit_cost,
                    ];
                    $remainingToAllocate = round($remainingToAllocate - $allocated, 3);
                }
                if ($remainingToAllocate > 0.0005) {
                    throw new \Exception('Không thể phân bổ đủ batch cho lệnh chuyển.');
                }

                $sourceTotalCost = array_sum(array_map(
                    fn (array $allocation): float => $allocation['quantity'] * $allocation['unit_cost'],
                    $sourceAllocations,
                ));
                $transferUnitCost = $quantity > 0 ? $sourceTotalCost / $quantity : (float) $invFrom->last_cost;

                // 2. Pessimistic lock / create on to_branch inventory
                $invTo = Inventory::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $toBranchId)
                    ->where('ingredient_id', $ingId)
                    ->lockForUpdate()
                    ->first();

                if (! $invTo) {
                    $invTo = Inventory::create([
                        'restaurant_id' => $user->restaurant_id,
                        'branch_id' => $toBranchId,
                        'ingredient_id' => $ingId,
                        'quantity_on_hand' => 0.0,
                        'theoretical_quantity' => 0.0,
                        'last_cost' => $invFrom->last_cost,
                    ]);
                }

                $destinationBefore = (float) $invTo->quantity_on_hand;
                $destinationAfter = round($destinationBefore + $quantity, 3);
                $destinationTheoreticalBefore = $invTo->effectiveTheoreticalQuantity();

                // Create the transfer document first so both ledger entries
                // can point to an immutable business source. This prevents
                // finance reports from mistaking an internal move for a
                // branch purchase.
                $internalTransfer = InternalTransfer::create([
                    'restaurant_id' => $user->restaurant_id,
                    'from_branch_id' => $fromBranchId,
                    'to_branch_id' => $toBranchId,
                    'ingredient_id' => $ingId,
                    'quantity' => $quantity,
                    'status' => 'completed',
                    'created_by' => $user->id,
                    'completed_by' => $user->id,
                    'completed_at' => now(),
                    'notes' => $request->input('notes') ?? 'Đề xuất luân chuyển kho nội bộ từ AI.',
                ]);

                // 4. Create out transaction for from_branch
                $outTransaction = InventoryTransaction::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $fromBranchId,
                    'ingredient_id' => $ingId,
                    'inventory_id' => $invFrom->id,
                    'performed_by' => $user->id,
                    'type' => 'adjustment',
                    'direction' => 'out',
                    'quantity' => $quantity,
                    'unit_cost' => $transferUnitCost,
                    'total_cost' => $sourceTotalCost,
                    'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey.':out' : null,
                    'source_type' => 'internal_transfer',
                    'source_id' => $internalTransfer->id,
                    'notes' => 'Điều phối kho nội bộ: Xuất chuyển sang chi nhánh #'.$toBranchId,
                    'quantity_before' => $sourceBefore,
                    'quantity_after' => $sourceAfter,
                    'occurred_at' => now(),
                ]);

                // 5. Create in transaction for to_branch
                $inTransaction = InventoryTransaction::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $toBranchId,
                    'ingredient_id' => $ingId,
                    'inventory_id' => $invTo->id,
                    'performed_by' => $user->id,
                    'type' => 'adjustment',
                    'direction' => 'in',
                    'quantity' => $quantity,
                    'unit_cost' => $transferUnitCost,
                    'total_cost' => $sourceTotalCost,
                    'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey.':in' : null,
                    'source_type' => 'internal_transfer',
                    'source_id' => $internalTransfer->id,
                    'notes' => 'Điều phối kho nội bộ: Nhận hàng luân chuyển từ chi nhánh #'.$fromBranchId,
                    'quantity_before' => $destinationBefore,
                    'quantity_after' => $destinationAfter,
                    'occurred_at' => now(),
                ]);

                $invFrom->update([
                    'quantity_on_hand' => $sourceAfter,
                    'theoretical_quantity' => $sourceTheoreticalBefore - $quantity,
                    'updated_by' => $user->id,
                ]);
                $invTo->update([
                    'quantity_on_hand' => $destinationAfter,
                    'theoretical_quantity' => $destinationTheoreticalBefore + $quantity,
                    'last_cost' => $transferUnitCost,
                    'updated_by' => $user->id,
                ]);

                $batchService = app(WarehouseReverseLogisticsService::class);
                foreach ($sourceAllocations as $allocation) {
                    /** @var InventoryBatch $sourceBatch */
                    $sourceBatch = $allocation['batch'];
                    $allocated = $allocation['quantity'];
                    $sourceBatchAfter = round((float) $sourceBatch->quantity_remaining - $allocated, 3);
                    $sourceBatch->update([
                        'quantity_remaining' => $sourceBatchAfter,
                        'status' => $sourceBatchAfter <= 0.0005 ? 'depleted' : 'active',
                    ]);
                    InventoryBatchAllocation::create([
                        'restaurant_id' => $user->restaurant_id,
                        'branch_id' => $fromBranchId,
                        'inventory_batch_id' => $sourceBatch->id,
                        'inventory_transaction_id' => $outTransaction->id,
                        'direction' => 'out',
                        'quantity' => $allocated,
                        'unit_cost' => $allocation['unit_cost'],
                    ]);

                    $destinationBatch = $batchService->createDestinationBatch(
                        (int) $user->restaurant_id,
                        $toBranchId,
                        $ingId,
                        $allocated,
                        $allocation['unit_cost'],
                        $user,
                        $sourceBatch,
                    );
                    InventoryBatchAllocation::create([
                        'restaurant_id' => $user->restaurant_id,
                        'branch_id' => $toBranchId,
                        'inventory_batch_id' => $destinationBatch->id,
                        'inventory_transaction_id' => $inTransaction->id,
                        'direction' => 'in',
                        'quantity' => $allocated,
                        'unit_cost' => $allocation['unit_cost'],
                    ]);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã thực hiện lệnh luân chuyển kho nội bộ liên chi nhánh thành công.');
    }

    /**
     * Lấy nhật ký các lệnh luân chuyển kho nội bộ.
     */
    public function listInternalTransfers(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage'), 403);

        $transfers = InternalTransfer::where('restaurant_id', $request->user()->restaurant_id)
            ->with(['fromBranch', 'toBranch', 'ingredient.unit', 'creator'])
            ->latest()
            ->get();

        $controlledTransfers = StockTransferRequest::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->with(['fromBranch', 'toBranch', 'ingredient.unit', 'requestedBy'])
            ->latest()
            ->get()
            ->map(fn (StockTransferRequest $transfer): array => [
                'id' => 'request-'.$transfer->id,
                'ingredient' => [
                    'name' => $transfer->ingredient?->name,
                    'unit' => ['symbol' => $transfer->ingredient?->unit?->symbol],
                ],
                'quantity' => (float) $transfer->quantity_requested,
                'from_branch' => ['name' => $transfer->fromBranch?->name],
                'to_branch' => ['name' => $transfer->toBranch?->name],
                'creator' => ['name' => $transfer->requestedBy?->name],
                'status' => $transfer->status,
                'notes' => $transfer->reason,
                'completed_at' => $transfer->received_at,
                'created_at' => $transfer->created_at,
                'transfer_kind' => 'controlled',
            ]);

        return response()->json([
            'transfers' => $transfers
                ->concat($controlledTransfers)
                ->sortByDesc('created_at')
                ->values(),
        ]);
    }
}
