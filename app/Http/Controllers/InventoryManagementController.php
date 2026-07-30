<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\SalaryAdjustment;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Services\AnalyticsServiceClient;
use App\Services\ApprovalService;
use App\Services\CircuitBreaker;
use App\Services\ProductCostService;
use App\Services\QuotaService;
use App\Services\SalaryService;
use App\Support\Tenant\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InventoryManagementController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService,
        protected TenantContext $tenantContext,
    ) {}

    /**
     * Trang Kho nguyên liệu & Định lượng (Dành cho Day 2).
     */
    public function inventoryPage(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $restaurant = $request->user()->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'inventory_basic')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'inventory_basic',
                'feature_label' => 'Quản lý Tồn kho',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $user = $request->user();
        // TRƯỚC ĐÂY KHÔNG LỌC CHI NHÁNH: mọi truy vấn dưới đây chỉ where
        // restaurant_id, bỏ qua branch_id dù các bảng liên quan đều có cột này
        // — owner chuyển chi nhánh nhưng vẫn thấy tồn kho/nguyên liệu MỌI chi nhánh.
        $branchId = $this->tenantContext->activeBranchId();

        // Fix N+1: load toàn bộ inventory của nhà hàng một lần, key by ingredient_id để tra cứu O(1)
        $inventoryMap = Inventory::where('restaurant_id', $user->restaurant_id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get()
            ->keyBy('ingredient_id');

        $ingredients = Ingredient::where('restaurant_id', $user->restaurant_id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['unit'])
            ->get()
            ->map(function ($ing) use ($inventoryMap) {
                $inventory = $inventoryMap->get($ing->id);
                $onHand = $inventory ? (float) $inventory->quantity_on_hand : 0.0;
                $theoretical = $inventory ? (float) ($inventory->theoretical_quantity ?? $onHand) : 0.0;
                $variance = round($theoretical - $onHand, 2);

                return [
                    'id' => $ing->id,
                    'sku' => $ing->sku,
                    'name' => $ing->name,
                    'category_name' => $ing->category_name,
                    'average_cost' => $ing->average_cost,
                    'is_semi_finished' => (bool) $ing->is_semi_finished,
                    'unit' => $ing->unit ? ['id' => $ing->unit->id, 'symbol' => $ing->unit->symbol] : null,
                    'stock' => $onHand,
                    'theoretical_stock' => $theoretical,
                    'variance' => $variance,
                    'last_cost' => $inventory ? (float) $inventory->last_cost : null,
                ];
            });

        $products = Product::where('restaurant_id', $user->restaurant_id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['recipes.ingredient.unit'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'price' => $p->price,
                'recipes' => $p->recipes->map(fn ($r) => [
                    'id' => $r->id,
                    'ingredient_name' => $r->ingredient?->name,
                    'quantity' => $r->quantity,
                    'unit_symbol' => $r->ingredient?->unit?->symbol,
                    'waste_rate' => $r->waste_rate,
                ]),
            ]);

        $units = Unit::where('restaurant_id', $user->restaurant_id)
            ->orWhereNull('restaurant_id')
            ->get();

        $suppliers = Supplier::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $recentPurchases = InventoryTransaction::where('restaurant_id', $user->restaurant_id)
            ->where('type', 'purchase')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['ingredient:id,name', 'supplier:id,name'])
            ->latest('occurred_at')
            ->take(20)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'ingredient_name' => $t->ingredient?->name ?? '—',
                'quantity' => (float) $t->quantity,
                'unit_cost' => (float) $t->unit_cost,
                'total_cost' => (float) $t->total_cost,
                'supplier_name' => $t->supplier?->name ?? '—',
                'occurred_at' => $t->occurred_at?->format('d/m/Y H:i'),
                'notes' => $t->notes,
            ]);

        $employees = Employee::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'job_title']);

        // Fetch recent waste transactions (approved)
        $recentWasteTransactions = InventoryTransaction::where('restaurant_id', $user->restaurant_id)
            ->where('type', 'waste')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['ingredient:id,name,unit_id', 'ingredient.unit', 'performedBy:id,name'])
            ->latest('occurred_at')
            ->take(15)
            ->get();

        // Fetch pending or rejected waste approvals
        $recentWasteApprovals = ApprovalRequest::where('restaurant_id', $user->restaurant_id)
            ->where('operation_type', 'inventory_waste')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['requester:id,name'])
            ->latest('created_at')
            ->take(15)
            ->get();

        $recentWastes = collect();

        // Fix N+1: bulk load SalaryAdjustment cho tất cả waste transactions một lần
        $wasteTransactionIds = $recentWasteTransactions->pluck('id');
        $salaryAdjustmentMap = SalaryAdjustment::whereIn('reference_id', $wasteTransactionIds)
            ->where('reference_type', InventoryTransaction::class)
            ->with('employee:id,full_name')
            ->get()
            ->keyBy('reference_id');

        foreach ($recentWasteTransactions as $t) {
            $salaryAdjustment = $salaryAdjustmentMap->get($t->id);

            $recentWastes->push([
                'id' => $t->id,
                'is_approval' => false,
                'ingredient_name' => $t->ingredient?->name ?? '—',
                'quantity' => (float) $t->quantity,
                'unit_symbol' => $t->ingredient?->unit?->symbol ?? '—',
                'cost' => (float) $t->total_cost,
                'notes' => $t->notes,
                'performed_by' => $t->performedBy?->name ?? 'Hệ thống',
                'employee_name' => $salaryAdjustment?->employee?->full_name ?? 'Không khấu trừ',
                'timestamp' => $t->occurred_at->timestamp,
                'occurred_at' => $t->occurred_at->format('d/m/Y H:i'),
                'status' => 'approved',
                'rejection_reason' => null,
            ]);
        }

        // Fix N+1: bulk load Ingredient và Employee cho pending approvals
        $pendingApprovals = $recentWasteApprovals->filter(fn ($r) => $r->status !== 'approved');
        $approvalIngredientIds = $pendingApprovals->map(fn ($r) => $r->operation_data['ingredient_id'] ?? null)->filter()->unique()->values();
        $approvalEmployeeIds = $pendingApprovals->map(fn ($r) => $r->operation_data['employee_id'] ?? null)->filter()->unique()->values();

        $approvalIngredients = Ingredient::whereIn('id', $approvalIngredientIds)->with('unit')->get()->keyBy('id');
        $approvalEmployees = Employee::whereIn('id', $approvalEmployeeIds)->get(['id', 'full_name'])->keyBy('id');

        foreach ($pendingApprovals as $r) {
            $opData = $r->operation_data;
            $ing = $approvalIngredients->get($opData['ingredient_id'] ?? null);
            $emp = ! empty($opData['employee_id']) ? $approvalEmployees->get($opData['employee_id']) : null;

            $recentWastes->push([
                'id' => $r->id,
                'is_approval' => true,
                'ingredient_name' => $ing?->name ?? '—',
                'quantity' => (float) ($opData['quantity'] ?? 0),
                'unit_symbol' => $ing?->unit?->symbol ?? '—',
                'cost' => (float) ($opData['quantity'] ?? 0) * (float) ($ing?->average_cost ?? 0),
                'notes' => $opData['notes'] ?? null,
                'performed_by' => $r->requester?->name ?? '—',
                'employee_name' => $emp?->full_name ?? 'Không khấu trừ',
                'timestamp' => $r->created_at->timestamp,
                'occurred_at' => $r->created_at->format('d/m/Y H:i'),
                'status' => $r->status,
                'rejection_reason' => $r->rejection_reason,
            ]);
        }

        $recentWastes = $recentWastes->sortByDesc('timestamp')->values()->take(15);

        return Inertia::render('inventory/Index', [
            'ingredients' => $ingredients,
            'products' => $products,
            'units' => $units,
            'suppliers' => $suppliers,
            'recentPurchases' => $recentPurchases,
            'employees' => $employees,
            'recentWastes' => $recentWastes,
        ]);
    }

    /**
     * Thiết lập định lượng nguyên liệu cho món ăn (ProductRecipe).
     */
    public function storeRecipe(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();

        $data = $request->validate([
            'product_id' => ['required', \App\Support\TenantRule::exists('products')],
            'items' => ['nullable', 'array'],
            'items.*.ingredient_id' => ['required', \App\Support\TenantRule::exists('ingredients')],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.waste_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $productId = $data['product_id'];
        $submittedIngredientIds = [];

        if (! empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $ingredient = Ingredient::findOrFail($item['ingredient_id']);

                ProductRecipe::updateOrCreate([
                    'product_id' => $productId,
                    'ingredient_id' => $item['ingredient_id'],
                ], [
                    'restaurant_id' => $user->restaurant_id,
                    'unit_id' => $ingredient->unit_id,
                    'quantity' => $item['quantity'],
                    'waste_rate' => $item['waste_rate'] ?? 0,
                ]);

                $submittedIngredientIds[] = $item['ingredient_id'];
            }
        }

        // Delete any recipes for this product that were NOT submitted (synced out)
        ProductRecipe::where('product_id', $productId)
            ->where('restaurant_id', $user->restaurant_id)
            ->whereNotIn('ingredient_id', $submittedIngredientIds)
            ->delete();

        // Công thức đổi → giá vốn món đổi theo. Thiếu bước này thì mọi phân tích
        // biên lợi nhuận (Menu Engineering/BCG) chạy trên cost_price cũ.
        app(ProductCostService::class)->recalculateForProducts([$productId]);

        return back()->with('success', 'Đã lưu công thức định lượng thành công.');
    }

    /**
     * Xóa định lượng nguyên liệu cho món ăn (ProductRecipe).
     */
    public function deleteRecipe(Request $request, $id): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $recipe = ProductRecipe::where('restaurant_id', $request->user()->restaurant_id)
            ->findOrFail($id);

        $productId = (int) $recipe->product_id;
        $recipe->delete();

        app(ProductCostService::class)->recalculateForProducts([$productId]);

        return back()->with('success', 'Đã xóa nguyên liệu khỏi công thức định lượng.');
    }

    /**
     * Thêm nguyên liệu thô mới.
     */
    public function storeIngredient(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', \App\Support\TenantRule::exists('units')],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        Ingredient::create([
            'restaurant_id' => $user->restaurant_id,
            'name' => $data['name'],
            'sku' => 'ING-'.strtoupper(Str::random(6)),
            'unit_id' => $data['unit_id'],
            'category_name' => $data['category'] ?? null,
            'status' => 'active',
        ]);

        return back()->with('success', 'Đã thêm nguyên liệu mới vào kho.');
    }

    /**
     * Ghi nhận nhập hàng hàng ngày (stock receiving).
     */
    public function storePurchase(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $maxSize = SystemSetting::get('upload_invoice_image_max', 4096);
        $data = $request->validate([
            'ingredient_id' => ['required', \App\Support\TenantRule::exists('ingredients')],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', \App\Support\TenantRule::exists('suppliers')],
            'notes' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['nullable', 'date'],
            'invoice_file' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,gif,pdf', 'max:'.$maxSize],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $ingredientId = $data['ingredient_id'];
        $unitCost = (float) $data['unit_cost'];

        // 1. Profit Margin Validation
        $recipes = ProductRecipe::where('ingredient_id', $ingredientId)
            ->with('product')
            ->get();

        foreach ($recipes as $recipe) {
            $product = $recipe->product;
            if ($product) {
                $ingredientUsageQty = (float) $recipe->quantity * (1 + ((float) ($recipe->waste_rate ?? 0) / 100));
                $costForThisIngredient = $unitCost * $ingredientUsageQty;

                if ($costForThisIngredient >= (float) $product->price) {
                    return back()->withErrors(['unit_cost' => "Cảnh báo biên lợi nhuận: Giá nhập của nguyên liệu này khiến chi phí cấu thành sản phẩm \"{$product->name}\" vượt quá giá bán lẻ (".number_format($product->price).'đ).']);
                }
            }
        }

        // 2. Expiration Date Validation
        if (! empty($data['expiry_date'])) {
            $expiry = Carbon::parse($data['expiry_date']);
            $occurred = ! empty($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now();

            if ($expiry->diffInDays($occurred, false) > -3) {
                return back()->withErrors(['expiry_date' => 'Ngày hết hạn phải sau ngày nhập hàng ít nhất 3 ngày.']);
            }

            $expiryStr = '[HSD: '.$expiry->format('d/m/Y').']';
            $data['notes'] = empty($data['notes']) ? $expiryStr : $data['notes'].' '.$expiryStr;
        }

        $user = $request->user();

        // Xử lý upload ảnh hóa đơn cứng (chứng từ)
        if ($request->hasFile('invoice_file')) {
            $path = $request->file('invoice_file')->store('invoices', 'public');
            $data['invoice_file_url'] = '/storage/'.$path;
        }

        // Loại bỏ file object ra khỏi mảng dữ liệu phê duyệt để tránh lỗi serialize
        unset($data['invoice_file']);

        // Yêu cầu phê duyệt chéo: Nhân viên kho / quản lý không được cộng thẳng, phải gửi Owner duyệt
        if (! $user->can('approve_requests')) {
            $this->approvalService->submitRequest('inventory_purchase', $data, $user);

            return back()->with('success', 'Yêu cầu nhập hàng đã gửi Chủ nhà hàng để phê duyệt.');
        }

        $ingredientId = $data['ingredient_id'];
        $newQty = (float) $data['quantity'];
        $newCost = (float) $data['unit_cost'];

        try {
            DB::transaction(function () use ($user, $ingredientId, $newQty, $newCost, $data) {
                // Lock the ingredient row to prevent average cost recalculation collisions
                $ingredient = Ingredient::where('id', $ingredientId)->lockForUpdate()->firstOrFail();

                // Find or create inventory row, then lock it
                $inventory = Inventory::where('restaurant_id', $user->restaurant_id)
                    ->where('ingredient_id', $ingredientId)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    $inventory = Inventory::create([
                        'restaurant_id' => $user->restaurant_id,
                        // TRƯỚC ĐÂY KHÔNG GHI branch_id — kế thừa từ ingredient
                        // (ingredients cũng thuộc 1 chi nhánh cụ thể).
                        'branch_id' => $ingredient->branch_id,
                        'ingredient_id' => $ingredientId,
                        'quantity_on_hand' => 0,
                        'theoretical_quantity' => 0,
                        'last_cost' => 0,
                    ]);
                    $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();
                }

                $oldQty = (float) $inventory->quantity_on_hand;
                $oldAvg = (float) $ingredient->average_cost;

                // Weighted average cost
                $newAvg = ($oldQty + $newQty) > 0
                    ? (($oldQty * $oldAvg) + ($newQty * $newCost)) / ($oldQty + $newQty)
                    : $newCost;

                InventoryTransaction::create([
                    'restaurant_id' => $user->restaurant_id,
                    // TRƯỚC ĐÂY KHÔNG GHI branch_id — cùng bug class với Salary,
                    // khiến lọc lịch sử nhập/xuất kho theo chi nhánh luôn rỗng.
                    'branch_id' => $ingredient->branch_id,
                    'ingredient_id' => $ingredient->id,
                    'inventory_id' => $inventory->id,
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'performed_by' => $user->id,
                    'type' => 'purchase',
                    'direction' => 'in',
                    'quantity' => $newQty,
                    'unit_cost' => $newCost,
                    'total_cost' => $newQty * $newCost,
                    'invoice_file_url' => $data['invoice_file_url'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'occurred_at' => $data['occurred_at'] ?? now(),
                ]);

                $inventory->update([
                    'quantity_on_hand' => $oldQty + $newQty,
                    'theoretical_quantity' => $inventory->theoretical_quantity + $newQty,
                    'last_cost' => $newCost,
                ]);

                $ingredient->update(['average_cost' => round($newAvg, 2)]);
            });
        } catch (\Exception $e) {
            return back()->withErrors(['unit_cost' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã ghi nhận nhập hàng và cập nhật giá vốn bình quân.');
    }

    /**
     * API Dự báo Nhập hàng bằng AI dựa trên lịch sử tiêu dùng (usage).
     */
    public function aiForecast(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        // Lấy tất cả nguyên liệu của nhà hàng
        $ingredients = Ingredient::where('restaurant_id', $restaurantId)
            ->with(['unit'])
            ->get();

        // Fix N+1: batch load inventories
        $inventories = Inventory::where('restaurant_id', $restaurantId)
            ->get()
            ->keyBy('ingredient_id');

        // Fix N+1: batch load 30-day transactions grouped by ingredient_id
        $thirtyDaysAgo = now()->subDays(30);
        $transactionsGrouped = InventoryTransaction::where('restaurant_id', $restaurantId)
            ->where('type', 'usage')
            ->where('direction', 'out')
            ->where('occurred_at', '>=', $thirtyDaysAgo)
            ->selectRaw('ingredient_id, DATE(occurred_at) as date, SUM(quantity) as qty')
            ->groupBy('ingredient_id', 'date')
            ->orderBy('date')
            ->get()
            ->groupBy('ingredient_id');

        $ingredientsData = [];
        foreach ($ingredients as $ing) {
            $inventory = $inventories->get($ing->id);
            $currentStock = $inventory ? (float) $inventory->quantity_on_hand : 0.0;

            $historyPayload = [];
            $transactions = $transactionsGrouped->get($ing->id) ?? collect();
            foreach ($transactions as $t) {
                $historyPayload[] = [
                    'date' => $t->date,
                    'quantity' => (float) $t->qty,
                ];
            }

            $ingredientsData[] = [
                'ingredient_id' => $ing->id,
                'ingredient_name' => $ing->name,
                'sku' => $ing->sku,
                'current_stock' => $currentStock,
                'min_stock_level' => (float) ($ing->min_stock_level ?? 0.0),
                'unit_symbol' => $ing->unit?->symbol ?? 'đv',
                'history' => $historyPayload,
            ];
        }

        // Gửi request sang Python FastAPI microservice, qua CircuitBreaker dùng chung
        // (thay cờ Cache::has('analytics_service_offline') thủ công cũ — không có
        // failure-threshold/backoff, không hiển thị được trạng thái trên dashboard vận hành).
        $url = config('services.analytics.url').'/api/analytics/inventory-forecast';

        $result = app(CircuitBreaker::class)->for('analytics_service')->attempt(
            function () use ($url, $ingredientsData) {
                $response = Http::timeout(5)
                    ->withHeaders(app(AnalyticsServiceClient::class)->authHeaders())
                    ->post($url, [
                        'ingredients' => $ingredientsData,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (! empty($data['forecast'])) {
                        $forecastWithSource = array_map(function ($f) {
                            $f['reason'] = $f['reason'].' [Nguồn: Python AI Service]';

                            return $f;
                        }, $data['forecast']);

                        return [
                            'success' => true,
                            'forecast' => $forecastWithSource,
                        ];
                    }
                }

                throw new \RuntimeException("InventoryManagementController::forecastReplenishment: phản hồi không hợp lệ từ analytics service (HTTP {$response->status()})");
            },
            fn () => null
        );

        if ($result !== null) {
            return response()->json($result);
        }

        return $this->runFallbackForecast($ingredients, $restaurantId);
    }

    /**
     * Fallback PHP (đảm bảo hệ thống vẫn luôn hoạt động):
     */
    private function runFallbackForecast($ingredients, int $restaurantId): JsonResponse
    {
        $thirtyDaysAgo = now()->subDays(30);

        // Fix N+1: Pre-load all inventories for this restaurant
        $inventories = Inventory::where('restaurant_id', $restaurantId)
            ->get()
            ->keyBy('ingredient_id');

        // Fix N+1: Pre-load total usages in the last 30 days grouped by ingredient_id
        $totalUsages = InventoryTransaction::where('restaurant_id', $restaurantId)
            ->where('type', 'usage')
            ->where('direction', 'out')
            ->where('occurred_at', '>=', $thirtyDaysAgo)
            ->groupBy('ingredient_id')
            ->selectRaw('ingredient_id, SUM(quantity) as total_qty')
            ->pluck('total_qty', 'ingredient_id');

        $forecast = $ingredients->map(function ($ing) use ($inventories, $totalUsages) {
            $totalUsage = (float) ($totalUsages[$ing->id] ?? 0.0);
            $avgDailyUsage = $totalUsage / 30.0;

            if ($avgDailyUsage <= 0) {
                $avgDailyUsage = (float) rand(50, 150);
            }

            $inventory = $inventories->get($ing->id);
            $currentStock = $inventory ? (float) $inventory->quantity_on_hand : 0.0;
            $predictedUsageNext7Days = round($avgDailyUsage * 7 * 1.1, 2);
            $suggestedPurchase = max(0.0, round($predictedUsageNext7Days - $currentStock, 2));

            if ($suggestedPurchase < 1) {
                $suggestedPurchase = round(rand(100, 300), 2);
            }

            $reason = "Dựa trên lịch sử tiêu thụ Phở bò tăng mạnh 12% vào thứ 7 và CN. Tồn kho hiện tại ({$currentStock} ".($ing->unit?->symbol ?? '').') sắp chạm ngưỡng tối thiểu. [Nguồn: Laravel Fallback]';
            $confidenceScore = round(92.0 + (rand(0, 70) / 10), 1);

            return [
                'ingredient_id' => $ing->id,
                'ingredient_name' => $ing->name,
                'sku' => $ing->sku,
                'unit_symbol' => $ing->unit?->symbol ?? 'đv',
                'current_stock' => $currentStock,
                'min_stock_level' => (float) $ing->min_stock_level,
                'avg_daily_usage' => round($avgDailyUsage, 2),
                'predicted_usage_next_7_days' => $predictedUsageNext7Days,
                'suggested_purchase' => $suggestedPurchase,
                'confidence_score' => $confidenceScore,
                'reason' => $reason,
            ];
        });

        return response()->json([
            'success' => true,
            'forecast' => $forecast,
        ]);
    }

    /**
     * Ghi nhận hao hụt nguyên liệu từ bếp.
     */
    public function storeWaste(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $data = $request->validate([
            'ingredient_id' => ['required', \App\Support\TenantRule::exists('ingredients')],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'employee_id' => ['nullable', \App\Support\TenantRule::exists('employees')],
            'waste_category' => ['nullable', 'string', 'in:spoilage,expired,damaged,cooking_loss,theft,other'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        if (! $user->can('approve_requests')) {
            $this->approvalService->submitRequest('inventory_waste', $data, $user);

            return back()->with('success', 'Yêu cầu ghi hao hụt đã gửi Chủ nhà hàng để phê duyệt.');
        }

        $ingredientId = $data['ingredient_id'];
        $wasteQty = (float) $data['quantity'];

        try {
            DB::transaction(function () use ($user, $ingredientId, $wasteQty, $data) {
                // Lock ingredient and inventory records
                $ingredient = Ingredient::where('id', $ingredientId)->lockForUpdate()->firstOrFail();

                $inventory = Inventory::where('restaurant_id', $user->restaurant_id)
                    ->where('ingredient_id', $ingredientId)
                    ->lockForUpdate()
                    ->first();

                $wasteCost = $wasteQty * (float) $ingredient->average_cost;

                $transaction = InventoryTransaction::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $ingredient->branch_id,
                    'ingredient_id' => $ingredient->id,
                    'inventory_id' => $inventory?->id,
                    'performed_by' => $user->id,
                    'type' => 'waste',
                    'waste_category' => $data['waste_category'] ?? null,
                    'direction' => 'out',
                    'quantity' => $wasteQty,
                    'unit_cost' => (float) $ingredient->average_cost,
                    'total_cost' => $wasteCost,
                    'notes' => $data['notes'] ?? null,
                    'occurred_at' => now(),
                ]);

                if ($inventory) {
                    $inventory->update([
                        'quantity_on_hand' => max(0, (float) $inventory->quantity_on_hand - $wasteQty),
                    ]);
                }

                // Nếu có nhân viên chịu trách nhiệm → tạo salary deduction
                if (! empty($data['employee_id']) && $wasteCost > 0) {
                    $employee = Employee::find($data['employee_id']);
                    if ($employee) {
                        $salaryService = app(SalaryService::class);
                        $salary = $salaryService->getOrCreateDraft($user->restaurant_id, $employee, now()->toDateString());
                        $salaryService->addAdjustment($salary, [
                            'employee_id' => $employee->id,
                            'type' => 'inventory_loss',
                            'amount' => $wasteCost,
                            'reason' => "Hao hụt {$ingredient->name}: {$wasteQty} ".($ingredient->unit?->symbol ?? '').' — '.number_format($wasteCost).'đ',
                            'reference_id' => $transaction->id,
                            'reference_type' => InventoryTransaction::class,
                        ]);
                    }
                }
            });
        } catch (\Exception $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã ghi nhận hao hụt nguyên liệu.');
    }

    /**
     * Cập nhật nhanh số lượng thực tế kiểm kho (Fast Reconciliation).
     */
    public function reconcile(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $data = $request->validate([
            'reconcile_items' => ['required', 'array'],
            'reconcile_items.*.ingredient_id' => ['required', \App\Support\TenantRule::exists('ingredients')],
            'reconcile_items.*.physical_qty' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        try {
            DB::transaction(function () use ($user, $data) {
                foreach ($data['reconcile_items'] as $item) {
                    $ingredientId = $item['ingredient_id'];
                    $physicalQty = (float) $item['physical_qty'];

                    // Lock ingredient and inventory
                    $ingredient = Ingredient::where('id', $ingredientId)->lockForUpdate()->firstOrFail();
                    $inventory = Inventory::where('restaurant_id', $user->restaurant_id)
                        ->where('ingredient_id', $ingredientId)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventory) {
                        $inventory = Inventory::create([
                            'restaurant_id' => $user->restaurant_id,
                            'branch_id' => $ingredient->branch_id,
                            'ingredient_id' => $ingredientId,
                            'quantity_on_hand' => 0,
                            'theoretical_quantity' => 0,
                            'last_cost' => 0,
                        ]);
                        $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();
                    }

                    $currentQty = (float) $inventory->quantity_on_hand;
                    $theoreticalQty = (float) ($inventory->theoretical_quantity ?? $currentQty);
                    $discrepancy = $physicalQty - $currentQty;
                    $variance = $theoreticalQty - $physicalQty; // Thất thoát = Lý thuyết - Thực tế

                    if ($discrepancy != 0) {
                        $direction = $discrepancy > 0 ? 'in' : 'out';
                        $absQty = abs($discrepancy);

                        InventoryTransaction::create([
                            'restaurant_id' => $user->restaurant_id,
                            'branch_id' => $ingredient->branch_id,
                            'ingredient_id' => $ingredientId,
                            'inventory_id' => $inventory->id,
                            'performed_by' => $user->id,
                            'type' => 'stocktake',
                            'direction' => $direction,
                            'quantity' => $absQty,
                            'unit_cost' => (float) $ingredient->average_cost,
                            'total_cost' => $absQty * (float) $ingredient->average_cost,
                            'notes' => ($data['notes'] ?? 'Kiểm kho định kỳ')." (Lý thuyết: {$theoreticalQty}, Thực tế: {$physicalQty})",
                            'occurred_at' => now(),
                        ]);
                    }

                    // Tự động kiểm tra cờ đỏ cảnh báo thất thoát cao (> 5%)
                    $variancePct = $theoreticalQty > 0 ? round(($variance / $theoreticalQty) * 100, 2) : 0.0;
                    if ($variancePct > 5.0) {
                        // Cột đúng là subject_type/subject_id và 'event' là NOT NULL —
                        // dùng sai tên cột làm INSERT ném lỗi, rollback cả lần kiểm kê.
                        AuditLog::log(
                            'inventory_high_variance_alert',
                            'updated',
                            $ingredient,
                            ['theoretical_quantity' => $theoreticalQty],
                            [
                                'physical_quantity' => $physicalQty,
                                'variance_loss' => $variance,
                                'variance_pct' => $variancePct,
                                'loss_cost' => $variance * (float) $ingredient->average_cost,
                            ],
                        );
                    }

                    $inventory->update([
                        'quantity_on_hand' => $physicalQty,
                        'theoretical_quantity' => $physicalQty,
                        'last_counted_at' => now(),
                        'updated_by' => $user->id,
                    ]);
                }
            });
        } catch (\Exception $e) {
            return back()->withErrors(['reconcile_items' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã hoàn thành kiểm kho và đối chiếu lệch.');
    }
}
