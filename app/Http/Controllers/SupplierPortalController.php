<?php

namespace App\Http\Controllers;

use App\Events\PurchaseOrderUpdated;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPriceHistory;
use App\Models\SystemSetting;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPortalController extends Controller
{
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

        $pos = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', ['delivered', 'frozen'])
            ->get();

        $totalPos = $pos->count();
        $onTimeCount = 0;
        $accurateCount = 0;

        foreach ($pos as $po) {
            if (! $po->is_discrepant) {
                $accurateCount++;
            }

            if ($po->delivery_due_date && $po->delivered_at) {
                $dueDate = Carbon::parse($po->delivery_due_date);
                $deliveredDate = Carbon::parse($po->delivered_at);
                if ($deliveredDate->lte($dueDate->addMinutes(30))) {
                    $onTimeCount++;
                }
            } else {
                $onTimeCount++;
            }
        }

        $onTimeRate = $totalPos > 0 ? ($onTimeCount / $totalPos) * 100 : 100;
        $accuracyRate = $totalPos > 0 ? ($accurateCount / $totalPos) * 100 : 100;
        $averageRating = $pos->whereNotNull('rating')->avg('rating') ?? 5.0;

        return Inertia::render('supplier/Dashboard', [
            'supplier' => $supplier,
            'stats' => [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'pending_orders' => $pendingOrders,
                'total_revenue' => (float) $totalRevenue,
            ],
            'recentOrders' => $recentOrders,
            'sla' => [
                'on_time_rate' => round($onTimeRate, 1),
                'accuracy_rate' => round($accuracyRate, 1),
                'average_rating' => round($averageRating, 1),
            ],
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
            'supplier' => $supplier,
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
            'unit_id' => ['required', 'integer', \App\Support\TenantRule::exists('units', restaurantId: (int) $supplier->restaurant_id)],
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

        $maxSize = SystemSetting::get('upload_invoice_image_max', 4096);
        $request->validate([
            'status' => ['required', 'in:preparing,shipping,delivered'],
            'invoice_file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,pdf', 'max:'.$maxSize],
        ]);

        $status = $request->input('status');

        $invoiceUrl = $purchaseOrder->invoice_file_url;
        if ($request->hasFile('invoice_file')) {
            $path = $request->file('invoice_file')->store('invoices', 'public');
            $invoiceUrl = '/storage/'.$path;
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
    public function getSlaMetrics(Request $request, Supplier $supplier)
    {
        $user = $request->user();
        $isAllowed = $user->hasAnyRole(['owner', 'manager', 'inventory_staff']) ||
            ($user->hasRole('supplier') && (int) $user->supplier_id === (int) $supplier->id);

        abort_unless($isAllowed, 403);

        $pos = PurchaseOrder::where('supplier_id', $supplier->id)
            ->whereIn('status', ['delivered', 'frozen'])
            ->get();

        $totalPos = $pos->count();
        $onTimeCount = 0;
        $accurateCount = 0;

        foreach ($pos as $po) {
            // Check accuracy
            if (! $po->is_discrepant) {
                $accurateCount++;
            }

            // Check on-time
            if ($po->delivery_due_date && $po->delivered_at) {
                $dueDate = Carbon::parse($po->delivery_due_date);
                $deliveredDate = Carbon::parse($po->delivered_at);
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
        $ingredients = Ingredient::where('supplier_id', $supplier->id)->get();
        $ingredientIds = $ingredients->pluck('id')->toArray();
        $histories = SupplierPriceHistory::where('supplier_id', $supplier->id)
            ->whereIn('ingredient_id', $ingredientIds)
            ->orderBy('effective_date')
            ->get()
            ->groupBy('ingredient_id');

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
        }

        // Get recent ratings
        $recentRatings = $pos->whereNotNull('rating')->map(fn ($po) => [
            'po_number' => $po->po_number,
            'rating' => $po->rating,
            'rating_notes' => $po->rating_notes,
            'delivered_at' => $po->delivered_at->format('d/m/Y H:i'),
        ])->values()->all();

        $averageRating = $pos->whereNotNull('rating')->avg('rating') ?? 5.0;

        return response()->json([
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'total_orders_analyzed' => $totalPos,
            'on_time_rate' => round($onTimeRate, 1),
            'accuracy_rate' => round($accuracyRate, 1),
            'average_rating' => round($averageRating, 1),
            'price_volatility' => $priceVolatility,
            'recent_ratings' => $recentRatings,
        ]);
    }
}
