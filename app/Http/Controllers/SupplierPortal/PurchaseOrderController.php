<?php

namespace App\Http\Controllers\SupplierPortal;

use App\Events\Supplier\PurchaseOrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatusLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $user     = $request->user();
        $supplier = $user->supplier;

        $status = $request->string('status');
        $search = $request->string('search');

        $orders = PurchaseOrder::with(['items.catalogItem', 'items.unit', 'branch'])
            ->where('supplier_id', $supplier->id)
            ->when($status->isNotEmpty(), fn ($q) => $q->where('status', $status))
            ->when($search->isNotEmpty(), fn ($q) => $q->where('po_number', 'like', "%{$search}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $statusCounts = PurchaseOrder::where('supplier_id', $supplier->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('supplier-portal/PurchaseOrders', [
            'orders'       => $orders,
            'statusCounts' => $statusCounts,
            'statusLabels' => PurchaseOrder::$statusLabels,
            'transitions'  => PurchaseOrder::$transitions,
            'filters'      => [
                'status' => (string) $status,
                'search' => (string) $search,
            ],
        ]);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $user = $request->user();
        abort_if($purchaseOrder->supplier_id !== $user->supplier_id, 403);

        $purchaseOrder->load([
            'items.catalogItem',
            'items.ingredient',
            'items.unit',
            'branch',
            'creator',
            'statusLogs.changedBy',
        ]);

        return Inertia::render('supplier-portal/PurchaseOrderDetail', [
            'order'        => $purchaseOrder,
            'statusLabels' => PurchaseOrder::$statusLabels,
            'transitions'  => PurchaseOrder::$transitions,
        ]);
    }

    public function advance(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = $request->user();
        abort_if($purchaseOrder->supplier_id !== $user->supplier_id, 403);
        abort_unless($purchaseOrder->canAdvance(), 422, 'Không thể chuyển trạng thái này.');

        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $fromStatus = $purchaseOrder->status;
        $toStatus   = $purchaseOrder->nextStatus();
        $now        = now();

        DB::transaction(function () use ($purchaseOrder, $fromStatus, $toStatus, $now, $user, $request): void {
            $timestamps = [
                'received'   => 'received_at',
                'preparing'  => 'preparing_at',
                'in_transit' => 'in_transit_at',
                'delivered'  => 'delivered_at',
            ];

            $updateData = ['status' => $toStatus];
            if (isset($timestamps[$toStatus])) {
                $updateData[$timestamps[$toStatus]] = $now;
            }
            if ($request->filled('note')) {
                $updateData['supplier_note'] = $request->input('note');
            }

            $purchaseOrder->update($updateData);

            PurchaseOrderStatusLog::create([
                'restaurant_id'     => $purchaseOrder->restaurant_id,
                'purchase_order_id' => $purchaseOrder->id,
                'from_status'       => $fromStatus,
                'to_status'         => $toStatus,
                'changed_by'        => $user->id,
                'note'              => $request->input('note'),
                'occurred_at'       => $now,
            ]);

            event(new PurchaseOrderStatusUpdated($purchaseOrder->fresh(), $fromStatus, $toStatus));
        });

        $label = PurchaseOrder::$statusLabels[$toStatus];

        return back()->with('success', "Đã chuyển trạng thái sang: {$label}");
    }
}
