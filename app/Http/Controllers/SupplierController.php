<?php

namespace App\Http\Controllers;

use App\Events\PurchaseOrderCreated;
use App\Events\PurchaseOrderStatusUpdated;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    /**
     * Display suppliers page with suppliers list, ingredients, and POs.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $suppliers = Supplier::orderBy('name')->get();

        $ingredients = Ingredient::with('unit')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $purchaseOrders = PurchaseOrder::with(['supplier', 'creator', 'items.ingredient.unit'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'status' => $po->status,
                'total_amount' => (float) $po->total_amount,
                'notes' => $po->notes,
                'invoice_file_url' => $po->invoice_file_url,
                'created_at' => $po->created_at->format('Y-m-d H:i:s'),
                'creator_name' => $po->creator?->name ?? 'Hệ thống',
                'supplier_name' => $po->supplier?->name ?? 'Không có',
                'items_count' => $po->items->count(),
                'items' => $po->items->map(fn ($item) => [
                    'id' => $item->id,
                    'ingredient_name' => $item->ingredient?->name ?? 'Nguyên liệu',
                    'quantity' => (float) $item->quantity,
                    'unit_symbol' => $item->ingredient?->unit?->symbol ?? '',
                    'unit_cost' => (float) $item->unit_cost,
                ]),
            ]);

        return Inertia::render('suppliers/Index', [
            'suppliers' => $suppliers,
            'ingredients' => $ingredients,
            'purchaseOrders' => $purchaseOrders,
        ]);
    }

    /**
     * Store a newly created supplier.
     */
    public function store(Request $request): RedirectResponse
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

        Supplier::create($data);

        return back()->with('success', 'Đã thêm nhà cung cấp mới thành công.');
    }

    /**
     * Update the specified supplier.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($supplier->restaurant_id !== $request->user()->restaurant_id, 404);

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

        return back()->with('success', 'Đã cập nhật nhà cung cấp thành công.');
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($supplier->restaurant_id !== $request->user()->restaurant_id, 404);

        $supplier->delete();

        return back()->with('success', 'Đã xóa nhà cung cấp thành công.');
    }

    /**
     * Create a new PO (Purchase Order) request.
     */
    public function storePO(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'exists:ingredients,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $user, &$po) {
            $today = date('Ymd');
            $count = PurchaseOrder::whereDate('created_at', today())->count();
            $poNumber = 'PO-'.$today.'-'.str_pad($count + 1, 3, '0', STR_PAD_LEFT);

            $po = PurchaseOrder::create([
                'restaurant_id' => $user->restaurant_id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'po_number' => $poNumber,
                'status' => 'received',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            foreach ($data['items'] as $item) {
                $qty = (float) $item['quantity'];
                $cost = (float) $item['unit_cost'];
                $totalAmount += ($qty * $cost);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                ]);
            }

            $po->update(['total_amount' => $totalAmount]);
        });

        // Trigger Laravel Reverb Real-time event
        event(new PurchaseOrderCreated($po));

        return back()->with('success', 'Yêu cầu đặt hàng PO đã được khởi tạo thành công.');
    }

    /**
     * Update the status of a PO.
     */
    public function updatePOStatus(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);
        abort_if($purchaseOrder->restaurant_id !== $user->restaurant_id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:received,preparing,shipping,delivered'],
            'invoice_file' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:2048'],
        ]);

        $newStatus = $data['status'];
        $currentStatus = $purchaseOrder->status;

        // Skip if same status
        if ($newStatus === $currentStatus) {
            return back();
        }

        // Strict workflow sequencing check
        $validTransitions = [
            'received' => ['preparing'],
            'preparing' => ['shipping'],
            'shipping' => ['delivered'],
            'delivered' => [],
        ];

        if (! in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            return back()->withErrors(['status' => 'Quy trình chuyển đổi trạng thái không hợp lệ: phải đi theo thứ tự Đã tiếp nhận -> Đang chuẩn bị hàng -> Đang vận chuyển -> Đã hạ hàng tại kho.']);
        }

        $invoiceFileUrl = null;
        if ($newStatus === 'delivered' && $request->hasFile('invoice_file')) {
            $file = $request->file('invoice_file');
            $path = Storage::disk('s3')->putFile('po-invoices', $file);
            $invoiceFileUrl = Storage::disk('s3')->url($path);
        }

        DB::transaction(function () use ($purchaseOrder, $newStatus, $user, $invoiceFileUrl) {
            $updateData = ['status' => $newStatus];
            if ($invoiceFileUrl !== null) {
                $updateData['invoice_file_url'] = $invoiceFileUrl;
            }
            $purchaseOrder->update($updateData);

            // If delivered, automatically perform stock inbound and transaction
            if ($newStatus === 'delivered') {
                $purchaseOrder->load('items.ingredient');

                foreach ($purchaseOrder->items as $item) {
                    $ingredient = $item->ingredient;
                    if (! $ingredient) {
                        continue;
                    }

                    // Get or create inventory record
                    $inventory = Inventory::firstOrCreate(
                        ['restaurant_id' => $user->restaurant_id, 'ingredient_id' => $ingredient->id],
                        ['quantity_on_hand' => 0, 'theoretical_quantity' => 0, 'last_cost' => 0]
                    );

                    $newQty = (float) $item->quantity;
                    $newCost = (float) $item->unit_cost;
                    $oldQty = (float) $inventory->quantity_on_hand;
                    $oldAvg = (float) $ingredient->average_cost;

                    // Weighted average cost calculation
                    $newAvg = ($oldQty + $newQty) > 0
                        ? (($oldQty * $oldAvg) + ($newQty * $newCost)) / ($oldQty + $newQty)
                        : $newCost;

                    // Create inventory transaction
                    InventoryTransaction::create([
                        'restaurant_id' => $user->restaurant_id,
                        'ingredient_id' => $ingredient->id,
                        'inventory_id' => $inventory->id,
                        'supplier_id' => $purchaseOrder->supplier_id,
                        'performed_by' => $user->id,
                        'type' => 'purchase',
                        'direction' => 'in',
                        'quantity' => $newQty,
                        'unit_cost' => $newCost,
                        'total_cost' => $newQty * $newCost,
                        'occurred_at' => now(),
                        'notes' => 'Tự động nhập hàng hoàn thành từ PO #'.$purchaseOrder->po_number,
                        'invoice_file_url' => $purchaseOrder->invoice_file_url,
                    ]);

                    // Update stock and cost values
                    $inventory->increment('quantity_on_hand', $newQty);
                    $inventory->increment('theoretical_quantity', $newQty);
                    $inventory->update(['last_cost' => $newCost]);
                    $ingredient->update(['average_cost' => $newAvg]);
                }
            }
        });

        // Trigger Laravel Reverb Real-time event
        event(new PurchaseOrderStatusUpdated($purchaseOrder));

        $statusVn = [
            'preparing' => 'Đang chuẩn bị hàng',
            'shipping' => 'Đang vận chuyển',
            'delivered' => 'Đã hạ hàng tại kho (Đã cộng kho)',
        ];

        return back()->with('success', 'Đơn hàng '.$purchaseOrder->po_number.' đã chuyển sang trạng thái: '.($statusVn[$newStatus] ?? $newStatus));
    }
}
