<?php

namespace App\Http\Controllers;

use App\Events\FraudAlertTriggered;
use App\Events\PurchaseOrderUpdated;
use App\Jobs\ProcessApprovedPurchaseOrderJob;
use App\Models\AccountPayable;
use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPriceHistory;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Services\AnalyticsServiceClient;
use App\Services\InventoryReplenishService;
use App\Services\PriceAnalyticsService;
use App\Services\QuotaService;
use App\Services\SupplierSlaService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        protected PriceAnalyticsService $priceAnalytics,
        protected SupplierSlaService $slaService,
        protected TenantContext $tenantContext,
    ) {}

    // =========================================================================
    // RESTAURANT ADMIN ENDPOINTS (Owners, Managers, Inventory Staff)
    // =========================================================================

    /**
     * Hiển thị danh sách nhà cung cấp & đơn đặt hàng cho nhà hàng.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $user = $request->user();

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'supplier_portal')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'supplier_portal',
                'feature_label' => 'Cổng Nhà Cung Cấp',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Doanh Nghiệp',
            ]);
        }

        $suppliers = Supplier::where('restaurant_id', $user->restaurant_id)
            ->when($this->tenantContext->isBranchScoped(), fn ($q) => $q->where(fn ($scope) => $scope
                ->whereNull('branch_id')->orWhere('branch_id', $this->tenantContext->activeBranchId())))
            ->withCount(['ingredients', 'purchaseOrders'])
            ->get();

        $ingredients = Ingredient::where('restaurant_id', $user->restaurant_id)
            ->when($this->tenantContext->isBranchScoped(), fn ($q) => $q->where(fn ($scope) => $scope
                ->whereNull('branch_id')->orWhere('branch_id', $this->tenantContext->activeBranchId())))
            ->with(['unit'])
            ->get()
            ->map(fn ($ing) => [
                'id' => $ing->id,
                'name' => $ing->name,
                'sku' => $ing->sku,
                'unit_symbol' => $ing->unit?->symbol ?? '—',
                'price' => (float) $ing->average_cost,
                'supplier_id' => $ing->supplier_id,
            ]);

        $purchaseOrders = PurchaseOrder::where('restaurant_id', $user->restaurant_id)
            ->when($this->tenantContext->isBranchScoped(), fn ($q) => $q->where('branch_id', $this->tenantContext->activeBranchId()))
            ->with(['supplier', 'creator', 'reviewer', 'items.ingredient.unit'])
            ->latest()
            ->get()
            ->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'supplier_name' => $po->supplier->name,
                'status' => $po->status,
                'total_amount' => (float) $po->total_amount,
                'invoice_total_amount' => (float) $po->invoice_total_amount,
                'invoice_file_url' => $po->invoice_file_url,
                'is_frozen' => (bool) $po->is_frozen,
                'is_discrepant' => (bool) $po->is_discrepant,
                'discrepancy_details' => $po->discrepancy_details,
                'created_by_name' => $po->creator?->name ?? 'Hệ thống',
                'payment_status' => $po->payment_status,
                'payment_method' => $po->payment_method,
                'discount_percent' => (float) $po->discount_percent,
                'shipping_method' => $po->shipping_method,
                'mismatch_reason' => $po->mismatch_reason,
                'resolution_action' => $po->resolution_action,
                'escrow_transaction_id' => $po->escrow_transaction_id,
                'created_at' => $po->created_at->format('d/m/Y H:i'),
                'items' => $po->items->map(fn ($item) => [
                    'id' => $item->id,
                    'ingredient_name' => $item->ingredient?->name ?? '—',
                    'unit_symbol' => $item->ingredient?->unit?->symbol ?? '—',
                    'quantity_ordered' => (float) $item->quantity_ordered,
                    'quantity_received' => (float) $item->quantity_received,
                    'price_per_unit' => (float) $item->price_per_unit,
                    'invoice_price_per_unit' => (float) $item->invoice_price_per_unit,
                    'total_cost' => (float) $item->total_cost,
                ]),
            ]);

        $units = Unit::where('restaurant_id', $user->restaurant_id)
            ->orWhereNull('restaurant_id')
            ->get(['id', 'name', 'symbol']);

        return Inertia::render('suppliers/Index', [
            'suppliers' => $suppliers,
            'ingredients' => $ingredients,
            'purchaseOrders' => $purchaseOrders,
            'units' => $units,
        ]);
    }

    /**
     * Thêm nhà cung cấp mới.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tax_code' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'in:cod,net7,net15,net30,prepaid'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        Supplier::create(array_merge($data, [
            'restaurant_id' => $user->restaurant_id,
            'status' => 'active',
        ]));

        return back()->with('success', 'Đã thêm nhà cung cấp mới thành công.');
    }

    /**
     * Cập nhật nhà cung cấp.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
            'tax_code' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'in:cod,net7,net15,net30,prepaid'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $supplier->update($data);

        return back()->with('success', 'Đã cập nhật thông tin nhà cung cấp.');
    }

    /**
     * Xóa nhà cung cấp.
     */
    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $supplier->delete();

        return back()->with('success', 'Đã xóa nhà cung cấp.');
    }

    /**
     * Đặt hàng nguyên liệu hàng ngày (PO).
     */
    public function placeOrder(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', \App\Support\TenantRule::exists('ingredients')],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
            'delivery_due_date' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'in:COD,NET_15,NET_30,NET_60'],
            'payment_method' => ['nullable', 'string', 'in:banking,cod,credit'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shipping_method' => ['nullable', 'string', 'in:supplier_delivery,self_pickup,express'],
        ]);

        $user = $request->user();
        $branchId = $this->resolveOperationalBranch($user);
        abort_if($supplier->restaurant_id !== $user->restaurant_id, 403);

        DB::transaction(function () use ($request, $supplier, $user, $branchId) {
            $totalAmount = 0;
            $itemsData = [];

            foreach ($request->input('items') as $item) {
                $ingredient = Ingredient::where('restaurant_id', $user->restaurant_id)
                    ->whereKey($item['ingredient_id'])
                    ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
                    ->firstOrFail();
                $qty = (float) $item['quantity'];
                $price = (float) $ingredient->average_cost;
                $cost = $qty * $price;

                $totalAmount += $cost;

                $itemsData[] = [
                    'ingredient_id' => $ingredient->id,
                    'quantity_ordered' => $qty,
                    'price_per_unit' => $price,
                    'total_cost' => $cost,
                ];
            }

            $discountPercent = (float) $request->input('discount_percent', 0);
            $finalTotalAmount = $totalAmount * (1 - ($discountPercent / 100));

            // Create PO
            $isOwner = $user->hasRole('owner');
            $status = $isOwner ? 'approved' : 'pending_approval';

            $paymentTerms = $request->input('payment_terms', 'COD');
            $dueDate = match ($paymentTerms) {
                'NET_15' => now()->addDays(15),
                'NET_30' => now()->addDays(30),
                'NET_60' => now()->addDays(60),
                default => now(), // COD
            };

            $po = PurchaseOrder::create([
                'restaurant_id' => $user->restaurant_id,
                'branch_id' => $branchId,
                'supplier_id' => $supplier->id,
                'po_number' => 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'status' => $status,
                'total_amount' => $finalTotalAmount,
                'payment_method' => $request->input('payment_method', 'banking'),
                'discount_percent' => $discountPercent,
                'shipping_method' => $request->input('shipping_method', 'supplier_delivery'),
                'created_by' => $user->id,
                'approved_by' => $isOwner ? $user->id : null,
                'notes' => $request->input('notes'),
                'delivery_due_date' => $request->input('delivery_due_date'),
                'payment_terms' => $paymentTerms,
                'due_date' => $dueDate->toDateString(),
            ]);

            foreach ($itemsData as $item) {
                $po->items()->create($item);
            }

            if ($status === 'approved') {
                $this->lockEscrow($po);
                dispatch(new ProcessApprovedPurchaseOrderJob($po->id));
            }
        });

        $msg = $request->user()->hasRole('owner')
            ? 'Đã đặt đơn hàng PO thành công và gửi tín hiệu realtime cho nhà cung cấp.'
            : 'Đã gửi yêu cầu đặt hàng PO chờ chủ nhà hàng duyệt.';

        return back()->with('success', $msg);
    }

    /**
     * Phê duyệt đơn hàng PO (Dành cho Owner).
     */
    public function approveOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->hasRole('owner'), 403);
        $this->authorizePurchaseOrderBranch($request->user(), $purchaseOrder);

        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        $this->lockEscrow($purchaseOrder);

        dispatch(new ProcessApprovedPurchaseOrderJob($purchaseOrder->id));

        return back()->with('success', 'Đã phê duyệt đơn hàng PO. Ký quỹ tiền thầu đã khóa tự động và tín hiệu đặt hàng đã đẩy sang nhà cung cấp.');
    }

    /**
     * Lấy phân tích biến động giá nguyên liệu (AI Price Analytics).
     */
    public function priceAnalytics(Supplier $supplier, Ingredient $ingredient)
    {
        $analysis = $this->priceAnalytics->analyzePriceHistory($supplier->id, $ingredient->id);

        return response()->json($analysis);
    }

    /**
     * Xác thực đối soát 2 lần (Dual-Verification) khi hàng cập bến kho.
     */
    public function verifyOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);
        $this->authorizePurchaseOrderBranch($request->user(), $purchaseOrder);

        $maxSize = SystemSetting::get('upload_invoice_image_max', 4096);
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.ingredient_id' => ['required', \App\Support\TenantRule::exists('ingredients')],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0'],
            'items.*.invoice_price' => ['required', 'numeric', 'min:0'],
            'invoice_file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,pdf', 'max:'.$maxSize],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_notes' => ['nullable', 'string', 'max:500'],
            'mismatch_reason' => ['nullable', 'string', 'max:255'],
            'resolution_action' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        // 1. Upload invoice
        $invoiceUrl = $purchaseOrder->invoice_file_url;
        if ($request->hasFile('invoice_file')) {
            $path = $request->file('invoice_file')->store('invoices', 'public');
            $invoiceUrl = '/storage/'.$path;
        }

        $itemsInput = collect($request->input('items'))->keyBy('ingredient_id');
        $hasDiscrepancy = false;
        $discrepancies = [];

        DB::transaction(function () use ($request, $purchaseOrder, $itemsInput, $invoiceUrl, $user, &$hasDiscrepancy, &$discrepancies) {
            $invoiceTotalAmount = 0;

            foreach ($purchaseOrder->items as $item) {
                $input = $itemsInput->get($item->ingredient_id);
                if (! $input) {
                    continue;
                }

                $receivedQty = (float) $input['quantity_received'];
                $invoicePrice = (float) $input['invoice_price'];
                $itemCost = $receivedQty * $invoicePrice;

                $invoiceTotalAmount += $itemCost;

                // Check mismatch
                $qtyMismatch = abs($item->quantity_ordered - $receivedQty) > 0.001;
                $priceMismatch = abs($item->price_per_unit - $invoicePrice) > 0.01;

                if ($qtyMismatch || $priceMismatch) {
                    $hasDiscrepancy = true;
                    $variancePercent = $item->price_per_unit > 0 ? (abs($invoicePrice - $item->price_per_unit) / $item->price_per_unit) * 100 : 0;
                    $discrepancies[] = [
                        'ingredient_id' => $item->ingredient_id,
                        'ingredient_name' => $item->ingredient?->name ?? 'Unknown',
                        'ordered_qty' => (float) $item->quantity_ordered,
                        'received_qty' => $receivedQty,
                        'listed_price' => (float) $item->price_per_unit,
                        'invoice_price' => $invoicePrice,
                        'variance_percent' => round($variancePercent, 2),
                        'requires_owner' => $variancePercent > 10,
                    ];
                }

                $item->update([
                    'quantity_received' => $receivedQty,
                    'invoice_price_per_unit' => $invoicePrice,
                ]);
            }

            $purchaseOrder->update([
                'invoice_total_amount' => $invoiceTotalAmount,
                'invoice_file_url' => $invoiceUrl,
                'rating' => $request->input('rating'),
                'rating_notes' => $request->input('rating_notes'),
                'mismatch_reason' => $request->input('mismatch_reason'),
                'resolution_action' => $request->input('resolution_action'),
                'delivered_at' => now(),
            ]);

            if ($hasDiscrepancy) {
                // Freeze transaction
                $purchaseOrder->update([
                    'status' => 'frozen',
                    'is_frozen' => true,
                    'is_discrepant' => true,
                    'discrepancy_details' => $discrepancies,
                ]);

                // Log JSON to audit_logs
                $oldValues = [
                    'total_amount' => (float) $purchaseOrder->total_amount,
                    'items' => $purchaseOrder->items->map(fn ($it) => [
                        'ingredient_id' => $it->ingredient_id,
                        'qty' => (float) $it->quantity_ordered,
                        'price' => (float) $it->price_per_unit,
                        'total_cost' => (float) $it->total_cost,
                    ])->toArray(),
                ];

                $newValues = [
                    'po_number' => $purchaseOrder->po_number,
                    'supplier_name' => $purchaseOrder->supplier->name,
                    'invoice_total_amount' => $invoiceTotalAmount,
                    'discrepancies' => $discrepancies,
                ];

                $log = AuditLog::log(
                    'po_discrepancy',
                    'updated',
                    $purchaseOrder,
                    $oldValues,
                    $newValues
                );

                // Clear fraud alerts cache to refresh immediately
                Cache::forget("fraud_alerts:{$purchaseOrder->restaurant_id}:".today()->startOfMonth()->toDateString().':'.today()->toDateString());

                // Broadcast fraud alarm to owner
                $alertData = [
                    'id' => 'po-discrepancy-'.$log->id,
                    'po_number' => $purchaseOrder->po_number,
                    'supplier_name' => $purchaseOrder->supplier->name,
                    'violation_type' => 'Đối soát mua hàng thất bại',
                    'severity' => 'critical',
                    'description' => "Đơn hàng PO #{$purchaseOrder->po_number} bị ĐÓNG BĂNG do chênh lệch đối soát chéo 3 bên.",
                    'occurred_at' => now()->toIso8601String(),
                ];
                event(new FraudAlertTriggered($purchaseOrder->restaurant_id, $alertData));

            } else {
                // Success: update status and add to inventory
                $isCod = $purchaseOrder->payment_terms === 'COD' || empty($purchaseOrder->payment_terms);
                $paymentStatus = $isCod ? 'paid' : 'unpaid';

                $purchaseOrder->update([
                    'status' => 'delivered',
                    'is_frozen' => false,
                    'is_discrepant' => false,
                    'payment_status' => $paymentStatus,
                ]);

                if (! $isCod) {
                    AccountPayable::create([
                        'restaurant_id' => $purchaseOrder->restaurant_id,
                        'purchase_order_id' => $purchaseOrder->id,
                        'supplier_id' => $purchaseOrder->supplier_id,
                        'amount' => $invoiceTotalAmount,
                        'paid_amount' => 0,
                        'due_date' => $purchaseOrder->due_date ?? now()->toDateString(),
                        'status' => 'unpaid',
                    ]);
                }
            }

            // Always update physical inventory and log transactions for what was received
            foreach ($purchaseOrder->items as $item) {
                $inventory = Inventory::firstOrCreate(
                    [
                        'restaurant_id' => $purchaseOrder->restaurant_id,
                        'branch_id' => $purchaseOrder->branch_id,
                        'ingredient_id' => $item->ingredient_id,
                    ],
                    [
                        'quantity_on_hand' => 0,
                        'theoretical_quantity' => 0,
                        'last_cost' => 0,
                    ]
                );

                $oldQty = (float) $inventory->quantity_on_hand;
                $addedQty = (float) $item->quantity_received;

                $inventory->update([
                    'quantity_on_hand' => $oldQty + $addedQty,
                    'theoretical_quantity' => $inventory->theoretical_quantity + $addedQty,
                    'last_cost' => $item->invoice_price_per_unit,
                ]);

                // Create purchase transaction
                InventoryTransaction::create([
                    'restaurant_id' => $purchaseOrder->restaurant_id,
                    'branch_id' => $purchaseOrder->branch_id,
                    'ingredient_id' => $item->ingredient_id,
                    'inventory_id' => $inventory->id,
                    'performed_by' => $user->id,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'type' => 'purchase',
                    'direction' => 'in',
                    'quantity' => $addedQty,
                    'unit_cost' => $item->invoice_price_per_unit,
                    'total_cost' => $addedQty * $item->invoice_price_per_unit,
                    'invoice_file_url' => $invoiceUrl,
                    'notes' => "Nhập kho tự động hoàn tất từ PO #{$purchaseOrder->po_number}".($hasDiscrepancy ? ' (Đóng băng tài chính do chênh lệch)' : ''),
                    'occurred_at' => now(),
                ]);
            }

            // Broadcast update
            event(new PurchaseOrderUpdated($purchaseOrder));
        });

        if ($hasDiscrepancy) {
            return back()->with('warning', 'Đối soát chéo thất bại! Phát hiện chênh lệch giữa giá niêm yết, hóa đơn và thực tế. Đơn hàng đã bị ĐÓNG BĂNG và phát báo động đỏ.');
        }

        return back()->with('success', 'Đối soát chéo thành công. Hàng hóa đã được cộng kho vật lý tự động.');
    }

    /**
     * Lấy báo cáo SLA cho toàn bộ nhà cung cấp của nhà hàng.
     */
    public function getSlaDashboard(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        return response()->json([
            'suppliers' => $this->slaService->calculateForRestaurant(
                $request->user()->restaurant_id,
                $this->tenantContext->activeBranchId(),
            ),
        ]);
    }

    /**
     * Lấy danh sách nguyên vật liệu dưới ngưỡng tồn tối thiểu cùng nhà cung cấp tối ưu nhất.
     */
    public function getReplenishCockpit(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $restaurantId = $request->user()->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();

        // Fetch active ingredients of the restaurant
        $ingredients = Ingredient::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                ->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->with(['unit', 'supplier'])
            ->get();

        // Get total stock levels grouped by ingredient across all branches
        $inventories = Inventory::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->select('ingredient_id', DB::raw('SUM(quantity_on_hand) as quantity_on_hand'))
            ->groupBy('ingredient_id')
            ->get()
            ->keyBy('ingredient_id');

        $suppliers = Supplier::where('restaurant_id', $restaurantId)->get()->keyBy('id');
        $supplierIds = $suppliers->keys()->toArray();

        $understockedIngredients = $ingredients->filter(function ($ing) use ($inventories) {
            $inv = $inventories->get($ing->id);
            $currentStock = $inv ? (float) $inv->quantity_on_hand : 0.0;
            $minStock = (float) ($ing->min_stock_level ?? 0.0);

            return $currentStock < $minStock;
        });

        $understockedIngredientIds = $understockedIngredients->pluck('id')->toArray();

        // Preload all price histories for understocked ingredients in a single query
        $allPriceHistories = SupplierPriceHistory::whereIn('ingredient_id', $understockedIngredientIds)
            ->whereIn('supplier_id', $supplierIds)
            ->orderBy('effective_date', 'desc')
            ->get()
            ->groupBy('ingredient_id');

        $cockpitData = [];

        foreach ($understockedIngredients as $ing) {
            $inv = $inventories->get($ing->id);
            $currentStock = $inv ? (float) $inv->quantity_on_hand : 0.0;
            $minStock = (float) ($ing->min_stock_level ?? 0.0);

            $deficit = $minStock - $currentStock;
            $safetyMarginFactor = 1.2;
            $suggestedQty = round($deficit * $safetyMarginFactor, 3);

            // Find optimal supplier based on lowest price in history from preloaded collection
            $priceHistories = isset($allPriceHistories[$ing->id])
                ? $allPriceHistories[$ing->id]->unique('supplier_id')
                : collect();

            $cheapestRecord = $priceHistories->sortBy('price')->first();

            $optimalSupplier = null;
            $optimalPrice = (float) $ing->average_cost;
            $isCheapestFromHistory = false;

            if ($cheapestRecord) {
                $optimalSupplier = $suppliers->get($cheapestRecord->supplier_id);
                $optimalPrice = (float) $cheapestRecord->price;
                $isCheapestFromHistory = true;
            } elseif ($ing->supplier_id) {
                $optimalSupplier = $suppliers->get($ing->supplier_id);
            }

            $cockpitData[] = [
                'ingredient_id' => $ing->id,
                'ingredient_name' => $ing->name,
                'sku' => $ing->sku,
                'unit_symbol' => $ing->unit?->symbol ?? '—',
                'current_stock' => $currentStock,
                'min_stock_level' => $minStock,
                'suggested_quantity' => $suggestedQty,
                'optimal_supplier' => $optimalSupplier ? [
                    'id' => $optimalSupplier->id,
                    'name' => $optimalSupplier->name,
                ] : null,
                'optimal_price' => $optimalPrice,
                'is_cheapest_from_history' => $isCheapestFromHistory,
                'default_supplier' => $ing->supplier ? [
                    'id' => $ing->supplier->id,
                    'name' => $ing->supplier->name,
                ] : null,
            ];
        }

        return response()->json([
            'recommendations' => $cockpitData,
        ]);
    }

    /**
     * Soạn thảo đơn đặt hàng nháp PO gửi các nhà cung cấp tối ưu hàng loạt.
     */
    public function draftPoBulk(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', \App\Support\TenantRule::exists('ingredients')],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.supplier_id' => ['required', \App\Support\TenantRule::exists('suppliers')],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $branchId = $this->resolveOperationalBranch($user);
        $items = $request->input('items');

        // Group by supplier
        $grouped = collect($items)->groupBy('supplier_id');

        $createdCount = 0;

        DB::transaction(function () use ($grouped, $user, $branchId, &$createdCount) {
            foreach ($grouped as $supplierId => $poItems) {
                $totalAmount = 0;
                $itemsData = [];

                foreach ($poItems as $item) {
                    $qty = (float) $item['quantity'];
                    $price = (float) $item['price'];
                    $cost = $qty * $price;
                    $totalAmount += $cost;

                    $itemsData[] = [
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity_ordered' => $qty,
                        'price_per_unit' => $price,
                        'total_cost' => $cost,
                    ];
                }

                // Create draft PO (pending_approval)
                $po = PurchaseOrder::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $branchId,
                    'supplier_id' => $supplierId,
                    'po_number' => 'PO-'.now()->format('Ymd').'-DRAFT-'.Str::upper(Str::random(4)),
                    'status' => 'pending_approval',
                    'total_amount' => $totalAmount,
                    'created_by' => $user->id,
                    'notes' => 'Đơn hàng tự động nháp được đề xuất bởi Cockpit Tự Động Hóa Chuỗi Cung Ứng.',
                    'delivery_due_date' => now()->addDays(2), // default lead time
                    'payment_terms' => 'COD',
                    'due_date' => now()->toDateString(),
                ]);

                $po->items()->createMany($itemsData);

                $createdCount++;
            }
        });

        return back()->with('success', "Đã tự động soạn thảo thành công {$createdCount} đơn hàng PO nháp gửi đến các nhà cung cấp tối ưu nhất.");
    }

    /**
     * Chạy thủ công AI dự báo tồn kho và tự động đề xuất đơn hàng PO nháp.
     */
    public function triggerAutoReplenish(Request $request, InventoryReplenishService $replenishService): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();
        $branchId = $this->resolveOperationalBranch($user);
        $forecasts = $replenishService->getForecastAndReplenish($user->restaurant_id, $branchId);
        $pos = $replenishService->generateReplenishmentOrders($user->restaurant_id, $forecasts, $user->id, $branchId);
        if (empty($pos)) {
            return back()->with('info', 'Tồn kho hiện tại vẫn ở mức an toàn. Không có nguyên liệu nào chạm ngưỡng cần bổ sung.');
        }

        return back()->with('success', 'AI đã phân tích và tự động tạo thành công '.count($pos).' đơn hàng PO nháp chờ bạn phê duyệt.');
    }

    /**
     * Nhận tệp hóa đơn tải lên và gửi tới FastAPI OCR để trích xuất dữ liệu đối soát.
     */
    public function ocrInvoice(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $request->validate([
            'invoice_file' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,pdf', 'max:4096'],
            'po_items' => ['nullable', 'string'],
        ]);

        $file = $request->file('invoice_file');
        $poContext = $request->input('po_items');

        $baseUrl = config('services.analytics.url');
        $url = "{$baseUrl}/api/analytics/ocr-invoice";

        try {
            // Forward file to Python FastAPI via HTTP client attach
            $response = Http::timeout(10)
                ->withHeaders(app(AnalyticsServiceClient::class)->authHeaders())
                ->attach('file', file_get_contents($file->getPathname()), $file->getClientOriginalName())
                ->post($url, [
                    'po_context' => $poContext,
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            Log::warning('ocrInvoice: Python OCR service returned code '.$response->status());
        } catch (\Throwable $e) {
            Log::error('ocrInvoice: Failed to connect to Python OCR service: '.$e->getMessage());
        }

        // Fallback: If FastAPI is offline, parse po_context directly
        if ($poContext) {
            $items = json_decode($poContext, true);
            $parsed = array_map(fn ($it) => [
                'ingredient_id' => $it['ingredient_id'],
                'ingredient_name' => $it['ingredient_name'] ?? 'Vật tư',
                'quantity' => (float) $it['quantity_ordered'],
                'unit_price' => (float) $it['price_per_unit'],
            ], $items);

            return response()->json([
                'invoice_number' => 'INV-FALLBACK-'.rand(1000, 9999),
                'items' => $parsed,
                'confidence' => 0.85,
                'message' => 'Chế độ dự phòng PHP hoạt động.',
            ]);
        }

        return response()->json(['error' => 'OCR Service offline and no PO context provided.'], 500);
    }

    /**
     * Khóa tiền ký quỹ (Escrow Lock).
     */
    private function lockEscrow(PurchaseOrder $po): void
    {
        $po->update([
            'payment_status' => 'escrow_locked',
            'escrow_transaction_id' => 'ESC-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
        ]);
    }

    /**
     * Thủ công giải ngân tiền ký quỹ cho nhà cung cấp (Escrow Release).
     */
    public function releaseEscrow(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']) && $purchaseOrder->restaurant_id === $request->user()->restaurant_id, 403);
        $this->authorizePurchaseOrderBranch($request->user(), $purchaseOrder);
        abort_unless($purchaseOrder->payment_status === 'escrow_locked', 400);

        // Price variance owner approval enforcement (> 10%)
        if ($purchaseOrder->is_discrepant && is_array($purchaseOrder->discrepancy_details)) {
            foreach ($purchaseOrder->discrepancy_details as $disc) {
                if (! empty($disc['requires_owner']) && ! $request->user()->hasRole('owner')) {
                    return back()->withErrors(['error' => 'Đơn hàng có biến động giá vượt quá 10% so với giá thỏa thuận, yêu cầu Chủ nhà hàng (Owner) phê duyệt giải ngân.']);
                }
            }
        }

        $purchaseOrder->update([
            'payment_status' => 'paid',
            'status' => 'delivered',
            'is_frozen' => false,
        ]);

        return back()->with('success', 'Đã thủ công giải ngân tiền ký quỹ (Escrow Released) cho nhà cung cấp thành công.');
    }

    /**
     * Hoàn trả tiền ký quỹ về tài khoản nhà hàng (Escrow Refund).
     */
    public function refundEscrow(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']) && $purchaseOrder->restaurant_id === $request->user()->restaurant_id, 403);
        $this->authorizePurchaseOrderBranch($request->user(), $purchaseOrder);
        abort_unless($purchaseOrder->payment_status === 'escrow_locked', 400);

        $purchaseOrder->update([
            'payment_status' => 'refunded',
            'status' => 'cancelled',
            'is_frozen' => false,
        ]);

        return back()->with('success', 'Đã hoàn trả tiền ký quỹ (Escrow Refunded) về tài khoản nhà hàng thành công.');
    }
    private function resolveOperationalBranch($user): int
    {
        $branchId = $this->tenantContext->activeBranchId()
            ?? ($user->isOwner() ? ($user->assignedBranchId() ?? $user->getRawOriginal('branch_id')) : null);
        abort_if($branchId === null, 422, 'Hãy chọn chi nhánh hiện tại trước khi ghi nhận nghiệp vụ kho.');
        abort_unless($user->canAccessBranch($branchId), 403);

        return $branchId;
    }

    private function authorizePurchaseOrderBranch($user, PurchaseOrder $purchaseOrder): void
    {
        abort_unless($purchaseOrder->restaurant_id === $user->restaurant_id, 403);
        abort_if($purchaseOrder->branch_id === null, 422, 'Đơn mua hàng cũ chưa được gắn chi nhánh. Hãy chạy backfill dữ liệu trước khi xử lý.');
        abort_unless($user->canAccessBranch((int) $purchaseOrder->branch_id), 403);

        if ($this->tenantContext->isBranchScoped()) {
            abort_unless((int) $purchaseOrder->branch_id === $this->tenantContext->activeBranchId(), 403);
        }
    }
}
