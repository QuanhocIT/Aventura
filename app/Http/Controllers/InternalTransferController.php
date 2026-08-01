<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\InternalTransfer;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Services\AnalyticsServiceClient;
use App\Services\CircuitBreaker;
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
        abort_unless($request->user()->isOwner(), 403);

        $user = $request->user();

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
            ->where('orders.restaurant_id', $user->restaurant_id)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.completed_at', [$startDate, $endDate])
            ->select(
                'orders.branch_id',
                'product_recipes.ingredient_id',
                DB::raw('SUM(order_items.quantity * product_recipes.quantity * (1 + (product_recipes.waste_rate / 100))) as total_used')
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
        abort_unless($request->user()->isOwner(), 403);

        $user = $request->user();

        $request->validate([
            'from_branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'to_branch_id' => ['required', TenantRule::exists('restaurant_branches'), 'different:from_branch_id'],
            'ingredient_id' => ['required', TenantRule::exists('ingredients')],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $fromBranchId = (int) $request->input('from_branch_id');
        $toBranchId = (int) $request->input('to_branch_id');
        $ingId = (int) $request->input('ingredient_id');
        $quantity = (float) $request->input('quantity');

        try {
            DB::transaction(function () use ($user, $fromBranchId, $toBranchId, $ingId, $quantity, $request) {
                // 1. Pessimistic lock on from_branch inventory
                $invFrom = Inventory::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $fromBranchId)
                    ->where('ingredient_id', $ingId)
                    ->lockForUpdate()
                    ->first();

                if (! $invFrom || (float) $invFrom->quantity_on_hand < $quantity) {
                    throw new \Exception('Chi nhánh xuất không đủ tồn kho thực tế để chuyển.');
                }

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

                // 3. Update stock levels
                $invFrom->decrement('quantity_on_hand', $quantity);
                $invFrom->decrement('theoretical_quantity', $quantity);

                $invTo->increment('quantity_on_hand', $quantity);
                $invTo->increment('theoretical_quantity', $quantity);

                // 4. Create out transaction for from_branch
                InventoryTransaction::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $fromBranchId,
                    'ingredient_id' => $ingId,
                    'inventory_id' => $invFrom->id,
                    'performed_by' => $user->id,
                    'type' => 'adjustment',
                    'direction' => 'out',
                    'quantity' => $quantity,
                    'unit_cost' => $invFrom->last_cost,
                    'total_cost' => $quantity * $invFrom->last_cost,
                    'notes' => 'Điều phối kho nội bộ: Xuất chuyển sang chi nhánh #'.$toBranchId,
                    'occurred_at' => now(),
                ]);

                // 5. Create in transaction for to_branch
                InventoryTransaction::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $toBranchId,
                    'ingredient_id' => $ingId,
                    'inventory_id' => $invTo->id,
                    'performed_by' => $user->id,
                    'type' => 'adjustment',
                    'direction' => 'in',
                    'quantity' => $quantity,
                    'unit_cost' => $invFrom->last_cost,
                    'total_cost' => $quantity * $invFrom->last_cost,
                    'notes' => 'Điều phối kho nội bộ: Nhận hàng luân chuyển từ chi nhánh #'.$fromBranchId,
                    'occurred_at' => now(),
                ]);

                // 6. Create internal transfer log
                InternalTransfer::create([
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
        abort_unless($request->user()->isOwner(), 403);

        $transfers = InternalTransfer::where('restaurant_id', $request->user()->restaurant_id)
            ->with(['fromBranch', 'toBranch', 'ingredient.unit', 'creator'])
            ->latest()
            ->get();

        return response()->json(['transfers' => $transfers]);
    }
}
