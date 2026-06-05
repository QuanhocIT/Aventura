<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\RequestForProposal;
use App\Models\RfpBid;
use App\Models\RfpBidItem;
use App\Models\RfpItem;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class RfpController extends Controller
{
    // =========================================================================
    // RESTAURANT ADMIN ENDPOINTS (Owners, Managers)
    // =========================================================================

    /**
     * Danh sách các yêu cầu chào thầu (RFP) và hồ sơ thầu.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();

        $rfps = RequestForProposal::where('restaurant_id', $user->restaurant_id)
            ->with(['items', 'bids.supplier', 'bids.items.rfpItem'])
            ->latest()
            ->get();

        return Inertia::render('suppliers/RfpManagement', [
            'rfps' => $rfps,
        ]);
    }

    /**
     * Tạo yêu cầu chào thầu (RFP) mới.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['required', 'date', 'after:now'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_name' => ['required', 'string', 'max:255'],
            'items.*.quantity_required' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_symbol' => ['required', 'string', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $rfp = RequestForProposal::create([
                'restaurant_id' => $user->restaurant_id,
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'due_date' => Carbon::parse($request->input('due_date')),
                'status' => 'open',
            ]);

            foreach ($request->input('items') as $item) {
                $rfp->items()->create([
                    'ingredient_name' => $item['ingredient_name'],
                    'quantity_required' => $item['quantity_required'],
                    'unit_symbol' => $item['unit_symbol'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Đã đăng tải yêu cầu đấu thầu báo giá (RFP) thành công tới các nhà cung cấp.');
    }

    /**
     * Đóng thầu thủ công.
     */
    public function close(Request $request, RequestForProposal $rfp): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']) && $rfp->restaurant_id === $request->user()->restaurant_id, 403);

        $rfp->update(['status' => 'closed']);

        return back()->with('success', 'Đã đóng thầu thành công. Các nhà cung cấp không thể nộp thêm hồ sơ.');
    }

    /**
     * Chấp nhận hồ sơ thầu thắng cuộc -> Auto tạo đơn PO đã duyệt.
     */
    public function acceptBid(Request $request, RfpBid $bid): RedirectResponse
    {
        $rfp = $bid->rfp;
        abort_unless($request->user()->hasRole('owner') && $rfp->restaurant_id === $request->user()->restaurant_id, 403);
        abort_unless($rfp->status !== 'completed', 400);

        DB::transaction(function () use ($bid, $rfp, $request) {
            // 1. Accept winning bid and reject others
            $bid->update(['status' => 'accepted']);
            $rfp->bids()->where('id', '!=', $bid->id)->update(['status' => 'rejected']);
            $rfp->update(['status' => 'completed']);

            // 2. Generate approved Purchase Order automatically
            $po = PurchaseOrder::create([
                'restaurant_id' => $rfp->restaurant_id,
                'supplier_id' => $bid->supplier_id,
                'po_number' => 'PO-' . now()->format('Ymd') . '-RFP-' . $rfp->id,
                'status' => 'approved',
                'total_amount' => $bid->total_amount,
                'created_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'notes' => "Đơn hàng tự động tạo từ hồ sơ thầu thắng cuộc cho yêu cầu RFP #{$rfp->id}: {$rfp->title}.",
                'delivery_due_date' => $bid->proposed_delivery_date,
                'payment_status' => 'escrow_locked',
                'escrow_transaction_id' => 'ESC-' . now()->format('Ymd') . '-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8)),
            ]);

            // 3. Map bid items to PO items
            foreach ($bid->items as $bidItem) {
                // Try to map to an existing ingredient in restaurant inventory by name match
                $ingredient = Ingredient::where('restaurant_id', $rfp->restaurant_id)
                    ->where('name', $bidItem->rfpItem->ingredient_name)
                    ->first();

                // If not found, map to the first ingredient associated with this supplier as fallback
                if (!$ingredient) {
                    $ingredient = Ingredient::where('restaurant_id', $rfp->restaurant_id)
                        ->where('supplier_id', $bid->supplier_id)
                        ->first();
                }

                $po->items()->create([
                    'ingredient_id' => $ingredient ? $ingredient->id : 1, // safety fallback ID
                    'quantity_ordered' => $bidItem->rfpItem->quantity_required,
                    'price_per_unit' => $bidItem->proposed_price_per_unit,
                    'total_cost' => $bidItem->rfpItem->quantity_required * $bidItem->proposed_price_per_unit,
                ]);
            }

            // 4. Dispatch PO processing job (triggers realtime broadcast to supplier)
            dispatch(new \App\Jobs\ProcessApprovedPurchaseOrderJob($po->id));
        });

        return back()->with('success', 'Đã chấp nhận hồ sơ thầu thắng cuộc. Đơn hàng PO đã được tạo và tự động gửi tới nhà cung cấp.');
    }

    // =========================================================================
    // B2B SUPPLIER PORTAL ENDPOINTS
    // =========================================================================

    /**
     * Trang xem các gói thầu RFP công khai dành cho nhà cung cấp.
     */
    public function supplierIndex(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasRole('supplier') && $user->supplier_id, 403);

        $supplier = Supplier::findOrFail($user->supplier_id);

        // Fetch open/closed RFPs for this restaurant tenant
        $rfps = RequestForProposal::where('restaurant_id', $supplier->restaurant_id)
            ->with(['items', 'bids' => function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id)->with('items');
            }])
            ->latest()
            ->get();

        return Inertia::render('supplier/RfpPortal', [
            'rfps' => $rfps,
            'supplier' => $supplier,
        ]);
    }

    /**
     * Nộp báo giá chào thầu (Submit Bid).
     */
    public function supplierSubmitBid(Request $request, RequestForProposal $rfp): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('supplier') && $user->supplier_id, 403);
        abort_unless($rfp->status === 'open' && Carbon::now()->lt($rfp->due_date), 400);

        $request->validate([
            'proposed_delivery_date' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array'],
            'items.*.rfp_item_id' => ['required', 'exists:rfp_items,id'],
            'items.*.proposed_price' => ['required', 'numeric', 'min:0'],
        ]);

        $supplierId = $user->supplier_id;

        DB::transaction(function () use ($request, $rfp, $supplierId) {
            // Calculate total amount from proposed prices
            $totalAmount = 0;
            $itemsData = [];

            foreach ($request->input('items') as $item) {
                $rfpItem = RfpItem::findOrFail($item['rfp_item_id']);
                $price = (float) $item['proposed_price'];
                $cost = $rfpItem->quantity_required * $price;
                $totalAmount += $cost;

                $itemsData[] = [
                    'rfp_item_id' => $rfpItem->id,
                    'proposed_price_per_unit' => $price,
                ];
            }

            // Create or update Bid
            $bid = RfpBid::updateOrCreate(
                ['rfp_id' => $rfp->id, 'supplier_id' => $supplierId],
                [
                    'proposed_delivery_date' => Carbon::parse($request->input('proposed_delivery_date')),
                    'total_amount' => $totalAmount,
                    'notes' => $request->input('notes'),
                    'status' => 'submitted',
                ]
            );

            // Save items
            $bid->items()->delete();
            foreach ($itemsData as $item) {
                $bid->items()->create($item);
            }
        });

        return back()->with('success', 'Đã nộp hồ sơ báo giá thầu thành công. Chủ nhà hàng sẽ đánh giá và phản hồi bạn.');
    }
}
