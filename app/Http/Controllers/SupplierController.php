<?php

namespace App\Http\Controllers;

use App\Events\FraudAlertTriggered;
use App\Events\PurchaseOrderUpdated;
use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierPriceHistory;
use App\Models\Unit;
use App\Models\User;
use App\Services\PriceAnalyticsService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        protected PriceAnalyticsService $priceAnalytics
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
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'supplier_portal')) {
            return Inertia::render('FeatureGate', [
                'feature'       => 'supplier_portal',
                'feature_label' => 'Cổng Nhà Cung Cấp',
                'plan_name'     => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Doanh Nghiệp',
            ]);
        }

        $suppliers = Supplier::where('restaurant_id', $user->restaurant_id)
            ->withCount(['ingredients', 'purchaseOrders'])
            ->get();

        $ingredients = Ingredient::where('restaurant_id', $user->restaurant_id)
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
            'items.*.ingredient_id' => ['required', 'exists:ingredients,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
            'delivery_due_date' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'in:COD,NET_15,NET_30,NET_60'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($request, $supplier, $user) {
            $totalAmount = 0;
            $itemsData = [];

            foreach ($request->input('items') as $item) {
                $ingredient = Ingredient::findOrFail($item['ingredient_id']);
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
                'supplier_id' => $supplier->id,
                'po_number' => 'PO-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
                'status' => $status,
                'total_amount' => $totalAmount,
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
                dispatch(new \App\Jobs\ProcessApprovedPurchaseOrderJob($po->id));
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

        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        $this->lockEscrow($purchaseOrder);

        dispatch(new \App\Jobs\ProcessApprovedPurchaseOrderJob($purchaseOrder->id));

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

        $maxSize = \App\Models\SystemSetting::get('upload_invoice_image_max', 4096);
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.ingredient_id' => ['required', 'exists:ingredients,id'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0'],
            'items.*.invoice_price' => ['required', 'numeric', 'min:0'],
            'invoice_file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,pdf', 'max:' . $maxSize],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        // 1. Upload invoice
        $invoiceUrl = $purchaseOrder->invoice_file_url;
        if ($request->hasFile('invoice_file')) {
            $path = $request->file('invoice_file')->store('invoices', 'public');
            $invoiceUrl = '/storage/' . $path;
        }

        $itemsInput = collect($request->input('items'))->keyBy('ingredient_id');
        $hasDiscrepancy = false;
        $discrepancies = [];

        DB::transaction(function () use ($request, $purchaseOrder, $itemsInput, $invoiceUrl, $user, &$hasDiscrepancy, &$discrepancies) {
            $invoiceTotalAmount = 0;

            foreach ($purchaseOrder->items as $item) {
                $input = $itemsInput->get($item->ingredient_id);
                if (!$input) continue;

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
                    'items' => $purchaseOrder->items->map(fn($it) => [
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
                \Illuminate\Support\Facades\Cache::forget("fraud_alerts:{$purchaseOrder->restaurant_id}:" . today()->startOfMonth()->toDateString() . ":" . today()->toDateString());

                // Broadcast fraud alarm to owner
                $alertData = [
                    'id' => 'po-discrepancy-' . $log->id,
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

                if (!$isCod) {
                    \App\Models\AccountPayable::create([
                        'restaurant_id' => $purchaseOrder->restaurant_id,
                        'purchase_order_id' => $purchaseOrder->id,
                        'supplier_id' => $purchaseOrder->supplier_id,
                        'amount' => $invoiceTotalAmount,
                        'paid_amount' => 0,
                        'due_date' => $purchaseOrder->due_date ?? now()->toDateString(),
                        'status' => 'unpaid',
                    ]);
                }

                foreach ($purchaseOrder->items as $item) {
                    $inventory = Inventory::firstOrCreate(
                        [
                            'restaurant_id' => $purchaseOrder->restaurant_id,
                            'branch_id' => $purchaseOrder->branch_id,
                            'ingredient_id' => $item->ingredient_id
                        ],
                        [
                            'quantity_on_hand' => 0,
                            'theoretical_quantity' => 0,
                            'last_cost' => 0
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
                        'notes' => "Nhập kho tự động hoàn tất từ PO #{$purchaseOrder->po_number}",
                        'occurred_at' => now(),
                    ]);
                }

                // Broadcast update
                event(new PurchaseOrderUpdated($purchaseOrder));
            }
        });

        if ($hasDiscrepancy) {
            return back()->with('warning', 'Đối soát chéo thất bại! Phát hiện chênh lệch giữa giá niêm yết, hóa đơn và thực tế. Đơn hàng đã bị ĐÓNG BĂNG và phát báo động đỏ.');
        }

        return back()->with('success', 'Đối soát chéo thành công. Hàng hóa đã được cộng kho vật lý tự động.');
    }

    // =========================================================================
    // SUPPLIER PORTAL ENDPOINTS (Supplier reps)
    // =========================================================================

    /**
     * Dashboard dành cho Nhà cung cấp.
     */
    public function supplierDashboard(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasRole('supplier') && $user->supplier_id, 403);

        $supplier = Supplier::findOrFail($user->supplier_id);

        $totalOrders = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->count();

        $completedOrders = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->where('status', 'delivered')
            ->count();

        $pendingOrders = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', ['approved', 'preparing', 'shipping'])
            ->count();

        $totalRevenue = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->where('status', 'delivered')
            ->sum('invoice_total_amount');

        $recentOrders = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'status' => $po->status,
                'total_amount' => (float) $po->total_amount,
                'created_at' => $po->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('supplier/Dashboard', [
            'supplier' => $supplier,
            'stats' => [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'pending_orders' => $pendingOrders,
                'total_revenue' => (float) $totalRevenue,
            ],
            'recentOrders' => $recentOrders,
        ]);
    }

    /**
     * Danh mục & Bảng giá của Nhà cung cấp.
     */
    public function supplierCatalog(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasRole('supplier') && $user->supplier_id, 403);

        $supplier = Supplier::findOrFail($user->supplier_id);

        $ingredients = Ingredient::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->with(['unit'])
            ->get()
            ->map(fn ($ing) => [
                'id' => $ing->id,
                'name' => $ing->name,
                'sku' => $ing->sku,
                'category_name' => $ing->category_name,
                'description' => $ing->description,
                'price' => (float) $ing->average_cost,
                'unit_symbol' => $ing->unit?->symbol ?? '—',
                'status' => $ing->status,
            ]);

        $units = Unit::withoutGlobalScopes()->get(['id', 'name', 'symbol']);

        return Inertia::render('supplier/Catalog', [
            'ingredients' => $ingredients,
            'units' => $units,
        ]);
    }

    /**
     * Cập nhật / Thêm mới nguyên vật liệu niêm yết bảng giá.
     */
    public function storeCatalogItem(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('supplier') && $user->supplier_id, 403);

        $supplier = Supplier::findOrFail($user->supplier_id);

        $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'category_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $price = (float) $request->input('price');
        $id = $request->input('id');

        DB::transaction(function () use ($request, $supplier, $user, $price, $id) {
            if ($id) {
                // Update
                $ingredient = Ingredient::withoutGlobalScopes()->findOrFail($id);
                abort_unless((int) $ingredient->supplier_id === (int) $supplier->id, 403);

                $oldPrice = (float) $ingredient->average_cost;
                $ingredient->update([
                    'name' => $request->input('name'),
                    'sku' => $request->input('sku'),
                    'average_cost' => $price,
                    'unit_id' => $request->input('unit_id'),
                    'category_name' => $request->input('category_name'),
                    'description' => $request->input('description'),
                    'status' => $request->input('status'),
                ]);

                // If price changed, write to histories
                if (abs($oldPrice - $price) > 0.01) {
                    SupplierPriceHistory::create([
                        'supplier_id' => $supplier->id,
                        'ingredient_id' => $ingredient->id,
                        'price' => $price,
                        'effective_date' => now(),
                        'created_by' => $user->id,
                    ]);
                }
            } else {
                // Create
                $ingredient = Ingredient::create([
                    'restaurant_id' => $supplier->restaurant_id, // multi-tenancy reference
                    'supplier_id' => $supplier->id,
                    'name' => $request->input('name'),
                    'sku' => $request->input('sku'),
                    'average_cost' => $price,
                    'unit_id' => $request->input('unit_id'),
                    'category_name' => $request->input('category_name'),
                    'description' => $request->input('description'),
                    'status' => $request->input('status'),
                ]);

                SupplierPriceHistory::create([
                    'supplier_id' => $supplier->id,
                    'ingredient_id' => $ingredient->id,
                    'price' => $price,
                    'effective_date' => now(),
                    'created_by' => $user->id,
                ]);
            }
        });

        return back()->with('success', 'Đã lưu niêm yết nguyên vật liệu thành công.');
    }

    /**
     * Xem và xử lý đơn hàng PO của Nhà cung cấp.
     */
    public function supplierOrders(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasRole('supplier') && $user->supplier_id, 403);

        $supplier = Supplier::findOrFail($user->supplier_id);

        $orders = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->with(['items.ingredient.unit'])
            ->latest()
            ->get()
            ->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'status' => $po->status,
                'total_amount' => (float) $po->total_amount,
                'invoice_total_amount' => (float) $po->invoice_total_amount,
                'invoice_file_url' => $po->invoice_file_url,
                'notes' => $po->notes,
                'created_at' => $po->created_at->format('d/m/Y H:i'),
                'payment_status' => $po->payment_status,
                'escrow_transaction_id' => $po->escrow_transaction_id,
                'items' => $po->items->map(fn ($item) => [
                    'id' => $item->id,
                    'ingredient_id' => $item->ingredient_id,
                    'ingredient_name' => $item->ingredient?->name ?? 'Unknown',
                    'unit_symbol' => $item->ingredient?->unit?->symbol ?? '—',
                    'quantity_ordered' => (float) $item->quantity_ordered,
                    'quantity_received' => (float) $item->quantity_received,
                    'price_per_unit' => (float) $item->price_per_unit,
                ]),
            ]);

        return Inertia::render('supplier/Orders', [
            'orders' => $orders,
        ]);
    }

    /**
     * Cập nhật trạng thái vận đơn của Nhà cung cấp.
     */
    public function updateOrderStatus(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('supplier') && $user->supplier_id === $purchaseOrder->supplier_id, 403);

        $maxSize = \App\Models\SystemSetting::get('upload_invoice_image_max', 4096);
        $request->validate([
            'status' => ['required', 'in:preparing,shipping,delivered'],
            'invoice_file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,pdf', 'max:' . $maxSize],
        ]);

        $status = $request->input('status');

        $invoiceUrl = $purchaseOrder->invoice_file_url;
        if ($request->hasFile('invoice_file')) {
            $path = $request->file('invoice_file')->store('invoices', 'public');
            $invoiceUrl = '/storage/' . $path;
        }

        $purchaseOrder->update([
            'status' => $status,
            'invoice_file_url' => $invoiceUrl,
        ]);

        // Realtime notify restaurant
        event(new PurchaseOrderUpdated($purchaseOrder));

        return back()->with('success', 'Đã chuyển đổi trạng thái vận đơn thành công.');
    }

    /**
     * Lấy chỉ số SLA đối soát và giao nhận của Nhà cung cấp.
     */
    private function calculateSlaForSupplier(Supplier $supplier, ?array $context = null): array
    {
        $pos = isset($context['pos'])
            ? ($context['pos']->get($supplier->id) ?? collect())
            : PurchaseOrder::where('supplier_id', $supplier->id)
                ->whereIn('status', ['delivered', 'frozen'])
                ->get();

        $totalPos = $pos->count();
        $onTimeCount = 0;
        $accurateCount = 0;

        foreach ($pos as $po) {
            // Check accuracy
            if (!$po->is_discrepant) {
                $accurateCount++;
            }

            // Check on-time
            if ($po->delivery_due_date && $po->delivered_at) {
                $dueDate = \Carbon\Carbon::parse($po->delivery_due_date);
                $deliveredDate = \Carbon\Carbon::parse($po->delivered_at);
                // 30 mins grace period
                if ($deliveredDate->lte($dueDate->addMinutes(30))) {
                    $onTimeCount++;
                }
            } else {
                // If no due date, count as on-time for safety or ignore
                $onTimeCount++;
            }
        }

        $onTimeRate = $totalPos > 0 ? ($onTimeCount / $totalPos) * 100 : 100;
        $accuracyRate = $totalPos > 0 ? ($accurateCount / $totalPos) * 100 : 100;

        // Price Volatility per ingredient
        $priceVolatility = [];
        $ingredients = isset($context['ingredients'])
            ? ($context['ingredients']->get($supplier->id) ?? collect())
            : Ingredient::where('supplier_id', $supplier->id)->get();

        $ingredientIds = $ingredients->pluck('id')->toArray();
        $histories = isset($context['histories'])
            ? ($context['histories']->get($supplier->id) ?? collect())->groupBy('ingredient_id')
            : SupplierPriceHistory::where('supplier_id', $supplier->id)
                ->whereIn('ingredient_id', $ingredientIds)
                ->orderBy('effective_date')
                ->get()
                ->groupBy('ingredient_id');

        $totalVolatility = 0;
        $volatilityCount = 0;

        foreach ($ingredients as $ing) {
            $prices = isset($histories[$ing->id])
                ? $histories[$ing->id]->pluck('price')->toArray()
                : [];

            $count = count($prices);
            if ($count > 1) {
                $mean = array_sum($prices) / $count;
                $variance = 0.0;
                foreach ($prices as $p) {
                    $variance += pow($p - $mean, 2);
                }
                $stdDev = sqrt($variance / ($count - 1));
                $volatility = ($mean > 0) ? ($stdDev / $mean) * 100 : 0;
            } else {
                $volatility = 0;
            }

            $priceVolatility[] = [
                'ingredient_name' => $ing->name,
                'sku' => $ing->sku,
                'current_price' => (float) $ing->average_cost,
                'price_history_count' => $count,
                'volatility_percent' => round($volatility, 2),
            ];

            if ($count > 1) {
                $totalVolatility += $volatility;
                $volatilityCount++;
            }
        }

        $avgVolatility = $volatilityCount > 0 ? ($totalVolatility / $volatilityCount) : 0;

        // Get recent ratings
        $recentRatings = $pos->whereNotNull('rating')->map(fn($po) => [
            'po_number' => $po->po_number,
            'rating' => $po->rating,
            'rating_notes' => $po->rating_notes,
            'delivered_at' => $po->delivered_at->format('d/m/Y H:i'),
        ])->values()->all();

        $averageRating = $pos->whereNotNull('rating')->avg('rating') ?? 5.0;

        return [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'total_orders_analyzed' => $totalPos,
            'on_time_rate' => round($onTimeRate, 1),
            'accuracy_rate' => round($accuracyRate, 1),
            'average_rating' => round($averageRating, 1),
            'average_volatility' => round($avgVolatility, 1),
            'price_volatility' => $priceVolatility,
            'recent_ratings' => $recentRatings,
        ];
    }

    /**
     * Lấy chỉ số SLA đối soát và giao nhận của Nhà cung cấp.
     */
    public function getSlaMetrics(Request $request, Supplier $supplier)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $metrics = $this->calculateSlaForSupplier($supplier);

        return response()->json($metrics);
    }

    /**
     * Lấy báo cáo SLA cho toàn bộ nhà cung cấp của nhà hàng.
     */
    public function getSlaDashboard(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $user = $request->user();
        $suppliers = Supplier::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->get();

        $supplierIds = $suppliers->pluck('id')->toArray();

        // Preload context values to optimize queries
        $context = [];

        $context['pos'] = PurchaseOrder::whereIn('supplier_id', $supplierIds)
            ->whereIn('status', ['delivered', 'frozen'])
            ->get()
            ->groupBy('supplier_id');

        $context['ingredients'] = Ingredient::whereIn('supplier_id', $supplierIds)
            ->get()
            ->groupBy('supplier_id');

        $allIngredientIds = Ingredient::whereIn('supplier_id', $supplierIds)->pluck('id')->toArray();
        $context['histories'] = SupplierPriceHistory::whereIn('supplier_id', $supplierIds)
            ->whereIn('ingredient_id', $allIngredientIds)
            ->orderBy('effective_date')
            ->get()
            ->groupBy('supplier_id');

        $dashboard = [];
        foreach ($suppliers as $supplier) {
            $dashboard[] = $this->calculateSlaForSupplier($supplier, $context);
        }

        return response()->json([
            'suppliers' => $dashboard
        ]);
    }

    /**
     * Lấy danh sách nguyên vật liệu dưới ngưỡng tồn tối thiểu cùng nhà cung cấp tối ưu nhất.
     */
    public function getReplenishCockpit(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $restaurantId = $request->user()->restaurant_id;

        // Fetch active ingredients of the restaurant
        $ingredients = Ingredient::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->with(['unit', 'supplier'])
            ->get();

        // Get total stock levels grouped by ingredient across all branches
        $inventories = Inventory::where('restaurant_id', $restaurantId)
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
            'recommendations' => $cockpitData
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
            'items.*.ingredient_id' => ['required', 'exists:ingredients,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.supplier_id' => ['required', 'exists:suppliers,id'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $items = $request->input('items');

        // Group by supplier
        $grouped = collect($items)->groupBy('supplier_id');

        $createdCount = 0;

        DB::transaction(function () use ($grouped, $user, &$createdCount) {
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
                    'supplier_id' => $supplierId,
                    'po_number' => 'PO-' . now()->format('Ymd') . '-DRAFT-' . Str::upper(Str::random(4)),
                    'status' => 'pending_approval',
                    'total_amount' => $totalAmount,
                    'created_by' => $user->id,
                    'notes' => 'Đơn hàng tự động nháp được đề xuất bởi Cockpit Tự Động Hóa Chuỗi Cung Ứng.',
                    'delivery_due_date' => now()->addDays(2), // default lead time
                    'payment_terms' => 'COD',
                    'due_date' => now()->toDateString(),
                ]);

                foreach ($itemsData as $itData) {
                    $po->items()->create($itData);
                }

                $createdCount++;
            }
        });

        return back()->with('success', "Đã tự động soạn thảo thành công {$createdCount} đơn hàng PO nháp gửi đến các nhà cung cấp tối ưu nhất.");
    }

    /**
     * Chạy thủ công AI dự báo tồn kho và tự động đề xuất đơn hàng PO nháp.
     */
    public function triggerAutoReplenish(Request $request, \App\Services\InventoryReplenishService $replenishService): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();
        $forecasts = $replenishService->getForecastAndReplenish($user->restaurant_id);
        $pos = $replenishService->generateReplenishmentOrders($user->restaurant_id, $forecasts, $user->id);

        if (empty($pos)) {
            return back()->with('info', 'Tồn kho hiện tại vẫn ở mức an toàn. Không có nguyên liệu nào chạm ngưỡng cần bổ sung.');
        }

        return back()->with('success', 'AI đã phân tích và tự động tạo thành công ' . count($pos) . ' đơn hàng PO nháp chờ bạn phê duyệt.');
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
                ->attach('file', file_get_contents($file->getPathname()), $file->getClientOriginalName())
                ->post($url, [
                    'po_context' => $poContext,
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            Log::warning("ocrInvoice: Python OCR service returned code " . $response->status());
        } catch (\Throwable $e) {
            Log::error("ocrInvoice: Failed to connect to Python OCR service: " . $e->getMessage());
        }

        // Fallback: If FastAPI is offline, parse po_context directly
        if ($poContext) {
            $items = json_decode($poContext, true);
            $parsed = array_map(fn($it) => [
                'ingredient_id' => $it['ingredient_id'],
                'ingredient_name' => $it['ingredient_name'] ?? 'Vật tư',
                'quantity' => (float) $it['quantity_ordered'],
                'unit_price' => (float) $it['price_per_unit'],
            ], $items);

            return response()->json([
                'invoice_number' => 'INV-FALLBACK-' . rand(1000, 9999),
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
            'escrow_transaction_id' => 'ESC-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8)),
        ]);
    }

    /**
     * Thủ công giải ngân tiền ký quỹ cho nhà cung cấp (Escrow Release).
     */
    public function releaseEscrow(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']) && $purchaseOrder->restaurant_id === $request->user()->restaurant_id, 403);
        abort_unless($purchaseOrder->payment_status === 'escrow_locked', 400);

        // Price variance owner approval enforcement (> 10%)
        if ($purchaseOrder->is_discrepant && is_array($purchaseOrder->discrepancy_details)) {
            foreach ($purchaseOrder->discrepancy_details as $disc) {
                if (!empty($disc['requires_owner']) && !$request->user()->hasRole('owner')) {
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
        abort_unless($purchaseOrder->payment_status === 'escrow_locked', 400);

        $purchaseOrder->update([
            'payment_status' => 'refunded',
            'status' => 'cancelled',
            'is_frozen' => false,
        ]);

        return back()->with('success', 'Đã hoàn trả tiền ký quỹ (Escrow Refunded) về tài khoản nhà hàng thành công.');
    }

    /**
     * Lấy danh sách các đề xuất chuyển kho liên chi nhánh (AI & PHP fallback).
     */
    public function transferRecommendations(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();

        // 1. Fetch branches
        $branches = \App\Models\RestaurantBranch::where('restaurant_id', $user->restaurant_id)->get();
        if ($branches->count() <= 1) {
            return response()->json(['recommendations' => [], 'message' => 'Bạn cần tối thiểu 2 chi nhánh để thực hiện luân chuyển kho liên chi nhánh.']);
        }

        // 2. Fetch active ingredients
        $ingredients = \App\Models\Ingredient::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->with('unit')
            ->get();

        // 3. Fetch inventories
        $inventories = \App\Models\Inventory::where('restaurant_id', $user->restaurant_id)->get();

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
                $inv = $inventories->first(fn($i) => $i->branch_id === $branch->id && $i->ingredient_id === $ing->id);
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

        // 6. Call Python FastAPI
        $baseUrl = config('services.analytics.url');
        $recommendations = null;

        try {
            $response = Http::timeout(10)->post("{$baseUrl}/api/analytics/transfer-recommendations", [
                'inventories' => $payload
            ]);

            if ($response->successful()) {
                $recommendations = $response->json()['recommendations'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::warning("transferRecommendations: Failed to contact Python service: " . $e->getMessage());
        }

        // 7. PHP Fallback if python is offline
        if ($recommendations === null) {
            $recommendations = [];
            $groupedByIng = collect($payload)->groupBy('ingredient_id');
            foreach ($groupedByIng as $ingId => $branchStockList) {
                $deficits = $branchStockList->filter(fn($item) => $item['current_stock'] < $item['min_stock_level']);
                $candidates = $branchStockList->filter(fn($item) => $item['current_stock'] > $item['min_stock_level']);

                foreach ($deficits as $def) {
                    $deficitQty = $def['min_stock_level'] - $def['current_stock'];
                    $ingName = $def['ingredient_name'];
                    $unit = $def['unit_symbol'];

                    $validCandidates = [];
                    foreach ($candidates as $cand) {
                        if ($cand['branch_id'] === $def['branch_id']) continue;

                        $excess = $cand['current_stock'] - $cand['min_stock_level'];
                        $avgDaily = $cand['average_daily_usage'];
                        $coverageDays = $avgDaily > 0 ? $cand['current_stock'] / $avgDaily : 999.0;

                        if ($coverageDays >= 14.0 || $avgDaily <= 0.01) {
                            $validCandidates[] = array_merge($cand, [
                                'excess' => $excess,
                                'coverage_days' => $coverageDays
                            ]);
                        }
                    }

                    if (empty($validCandidates)) continue;

                    // Sort by excess desc
                    usort($validCandidates, fn($a, $b) => $b['excess'] <=> $a['excess']);
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
                        'reason' => $reason
                    ];
                }
            }
        }

        return response()->json([
            'recommendations' => $recommendations,
            'branches' => $branches
        ]);
    }

    /**
     * Thực thi lệnh luân chuyển kho nội bộ.
     */
    public function storeInternalTransfer(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();

        $request->validate([
            'from_branch_id' => ['required', 'exists:restaurant_branches,id'],
            'to_branch_id' => ['required', 'exists:restaurant_branches,id', 'different:from_branch_id'],
            'ingredient_id' => ['required', 'exists:ingredients,id'],
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
                $invFrom = \App\Models\Inventory::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $fromBranchId)
                    ->where('ingredient_id', $ingId)
                    ->lockForUpdate()
                    ->first();

                if (!$invFrom || (float) $invFrom->quantity_on_hand < $quantity) {
                    throw new \Exception("Chi nhánh xuất không đủ tồn kho thực tế để chuyển.");
                }

                // 2. Pessimistic lock / create on to_branch inventory
                $invTo = \App\Models\Inventory::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $toBranchId)
                    ->where('ingredient_id', $ingId)
                    ->lockForUpdate()
                    ->first();

                if (!$invTo) {
                    $invTo = \App\Models\Inventory::create([
                        'restaurant_id' => $user->restaurant_id,
                        'branch_id' => $toBranchId,
                        'ingredient_id' => $ingId,
                        'quantity_on_hand' => 0.0,
                        'theoretical_quantity' => 0.0,
                        'last_cost' => $invFrom->last_cost
                    ]);
                }

                // 3. Update stock levels
                $invFrom->decrement('quantity_on_hand', $quantity);
                $invFrom->decrement('theoretical_quantity', $quantity);

                $invTo->increment('quantity_on_hand', $quantity);
                $invTo->increment('theoretical_quantity', $quantity);

                // 4. Create out transaction for from_branch
                \App\Models\InventoryTransaction::create([
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
                    'notes' => "Điều phối kho nội bộ: Xuất chuyển sang chi nhánh #" . $toBranchId,
                    'occurred_at' => now(),
                ]);

                // 5. Create in transaction for to_branch
                \App\Models\InventoryTransaction::create([
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
                    'notes' => "Điều phối kho nội bộ: Nhận hàng luân chuyển từ chi nhánh #" . $fromBranchId,
                    'occurred_at' => now(),
                ]);

                // 6. Create internal transfer log
                \App\Models\InternalTransfer::create([
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
    public function listInternalTransfers(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $transfers = \App\Models\InternalTransfer::where('restaurant_id', $request->user()->restaurant_id)
            ->with(['fromBranch', 'toBranch', 'ingredient.unit', 'creator'])
            ->latest()
            ->get();

        return response()->json(['transfers' => $transfers]);
    }
}
