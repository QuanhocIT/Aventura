<?php

namespace App\Http\Controllers\Warehouse;

use App\Events\Supplier\PurchaseOrderCreated;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderStatusLog;
use App\Models\Supplier;
use App\Models\SupplierCatalogItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $status     = $request->string('status');
        $supplierId = $request->string('supplier_id');
        $search     = $request->string('search');

        $orders = PurchaseOrder::with(['supplier', 'branch', 'creator'])
            ->when($status->isNotEmpty(), fn ($q) => $q->where('status', $status))
            ->when($supplierId->isNotEmpty(), fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($search->isNotEmpty(), fn ($q) => $q->where('po_number', 'like', "%{$search}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $suppliers = Supplier::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Warehouse/PurchaseOrders/Index', [
            'orders'       => $orders,
            'suppliers'    => $suppliers,
            'statusLabels' => PurchaseOrder::$statusLabels,
            'filters'      => [
                'status'      => (string) $status,
                'supplier_id' => (string) $supplierId,
                'search'      => (string) $search,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $supplierId = $request->input('supplier_id');

        $suppliers = Supplier::select('id', 'name')->orderBy('name')->get();

        $catalogItems = $supplierId
            ? SupplierCatalogItem::with('ingredient', 'unit')
                ->where('supplier_id', $supplierId)
                ->where('status', 'active')
                ->get()
            : collect();

        return Inertia::render('Warehouse/PurchaseOrders/Create', [
            'suppliers'    => $suppliers,
            'catalogItems' => $catalogItems,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id'            => 'required|integer|exists:suppliers,id',
            'expected_delivery_date' => 'nullable|date|after_or_equal:today',
            'note'                   => 'nullable|string|max:1000',
            'items'                  => 'required|array|min:1',
            'items.*.supplier_catalog_item_id' => 'required|integer|exists:supplier_catalog_items,id',
            'items.*.ingredient_id'  => 'nullable|integer|exists:ingredients,id',
            'items.*.unit_id'        => 'required|integer|exists:units,id',
            'items.*.quantity'       => 'required|numeric|min:0.001',
            'items.*.unit_price'     => 'required|numeric|min:0',
        ]);

        $po = DB::transaction(function () use ($data, $request): PurchaseOrder {
            $restaurantId = $request->user()->restaurant_id;

            $totalAmount = collect($data['items'])->sum(
                fn ($i) => $i['quantity'] * $i['unit_price']
            );

            $po = PurchaseOrder::create([
                'restaurant_id'          => $restaurantId,
                'supplier_id'            => $data['supplier_id'],
                'po_number'              => PurchaseOrder::generatePoNumber(),
                'status'                 => 'pending',
                'total_amount'           => $totalAmount,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'note'                   => $data['note'] ?? null,
                'created_by'             => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                PurchaseOrderItem::create([
                    'restaurant_id'            => $restaurantId,
                    'purchase_order_id'        => $po->id,
                    'supplier_catalog_item_id' => $item['supplier_catalog_item_id'],
                    'ingredient_id'            => $item['ingredient_id'] ?? null,
                    'unit_id'                  => $item['unit_id'],
                    'quantity'                 => $item['quantity'],
                    'unit_price'               => $item['unit_price'],
                    'total_price'              => $item['quantity'] * $item['unit_price'],
                ]);
            }

            PurchaseOrderStatusLog::create([
                'restaurant_id'     => $restaurantId,
                'purchase_order_id' => $po->id,
                'from_status'       => null,
                'to_status'         => 'pending',
                'changed_by'        => $request->user()->id,
                'note'              => 'Đơn hàng được tạo',
                'occurred_at'       => now(),
            ]);

            event(new PurchaseOrderCreated($po->load('supplier', 'items')));

            return $po;
        });

        return redirect()->route('warehouse.purchase-orders.show', $po)
            ->with('success', "Đã tạo đơn hàng {$po->po_number} và gửi đến nhà cung cấp.");
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load([
            'supplier',
            'branch',
            'creator',
            'items.catalogItem',
            'items.ingredient',
            'items.unit',
            'statusLogs.changedBy',
        ]);

        return Inertia::render('Warehouse/PurchaseOrders/Show', [
            'order'        => $purchaseOrder,
            'statusLabels' => PurchaseOrder::$statusLabels,
        ]);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_if(
            !in_array($purchaseOrder->status, ['pending', 'received']),
            422,
            'Chỉ có thể hủy đơn hàng ở trạng thái Chờ tiếp nhận hoặc Đã tiếp nhận.'
        );

        $request->validate(['note' => 'nullable|string|max:500']);

        DB::transaction(function () use ($purchaseOrder, $request): void {
            $fromStatus = $purchaseOrder->status;

            $purchaseOrder->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);

            PurchaseOrderStatusLog::create([
                'restaurant_id'     => $purchaseOrder->restaurant_id,
                'purchase_order_id' => $purchaseOrder->id,
                'from_status'       => $fromStatus,
                'to_status'         => 'cancelled',
                'changed_by'        => $request->user()->id,
                'note'              => $request->input('note', 'Nhà hàng hủy đơn'),
                'occurred_at'       => now(),
            ]);
        });

        return back()->with('success', 'Đã hủy đơn hàng.');
    }

    /** AJAX: trả về catalog items của supplier được chọn */
    public function catalogItems(Supplier $supplier): \Illuminate\Http\JsonResponse
    {
        $items = SupplierCatalogItem::with('ingredient', 'unit')
            ->where('supplier_id', $supplier->id)
            ->where('status', 'active')
            ->get(['id', 'name', 'packaging_spec', 'current_price', 'unit_id', 'ingredient_id', 'sku']);

        return response()->json($items);
    }
}
